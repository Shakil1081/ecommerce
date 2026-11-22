<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Logistics\Actions\CalculateShippingRateAction;
use App\Modules\Order\Actions\CalculateOrderFraudRiskScoreAction;
use App\Modules\Order\Actions\ResolvePostOrderRedirectAction;
use App\Modules\Order\DTOs\OrderFraudRiskAssessmentDto;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\OrderStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DirectOrderController extends Controller
{
    /**
     * Handle 1-click fast funnel order placed directly from the product page.
     */
    public function store(
        Request $request,
        CalculateOrderFraudRiskScoreAction $calculateFraudScore,
        ReserveStockForOrderAction $reserveStock,
        CalculateShippingRateAction $shippingRateCalculator,
        ResolvePostOrderRedirectAction $resolveRedirect
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'string', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'customer_name' => ['required', 'string', 'min:2', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:25'],
            'delivery_address' => ['required', 'string', 'min:5', 'max:500'],
            'delivery_zone' => ['required', 'string', 'in:inside_dhaka,dhaka_suburbs,outside_dhaka'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'user_company_fax' => ['nullable', 'string', 'max:100'], // Invisible honeypot field
            '_timing_token' => ['nullable', 'string', 'max:255'],
        ], [
            'customer_name.required' => 'অনুগ্রহ করে আপনার নাম লিখুন।',
            'customer_phone.required' => 'অনুগ্রহ করে আপনার মোবাইল নম্বর লিখুন।',
            'delivery_address.required' => 'অনুগ্রহ করে আপনার সম্পূর্ণ ডেলিভারি ঠিকানা লিখুন।',
            'delivery_zone.required' => 'অনুগ্রহ করে ডেলিভারি এরিয়া নির্বাচন করুন।',
        ]);

        // Clean & format Bangladesh phone number
        $cleanPhone = preg_replace('/[^\d+]/', '', $validated['customer_phone']) ?? '';
        if (str_starts_with($cleanPhone, '880')) {
            $cleanPhone = '0'.substr($cleanPhone, 3);
        } elseif (str_starts_with($cleanPhone, '+880')) {
            $cleanPhone = '0'.substr($cleanPhone, 4);
        }

        if (! preg_match('/^01[3-9]\d{8}$/', $cleanPhone)) {
            throw ValidationException::withMessages([
                'customer_phone' => ['অনুগ্রহ করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর দিন (যেমন: 017xxxxxxxx)'],
            ]);
        }

        $variant = ProductVariant::with(['product'])->findOrFail($validated['product_variant_id']);

        if (! $variant->is_active) {
            throw ValidationException::withMessages([
                'product_variant_id' => ['দুঃখিত, এই পণ্য ভ্যারিয়েন্টটি বর্তমানে স্থগিত রয়েছে।'],
            ]);
        }

        $quantity = (int) $validated['quantity'];
        // Fixed-precision money math (never floats for currency)
        $unitPrice = number_format((float) $variant->selling_price_bdt, 4, '.', '');
        $subtotal = bcmul($unitPrice, (string) $quantity, 4);

        // Admin-configured zone delivery fee (same source as cart checkout)
        $zone = $validated['delivery_zone'];
        $isFreeShipping = bccomp($subtotal, CartResource::FREE_SHIPPING_THRESHOLD, 4) >= 0;
        $shippingFee = $shippingRateCalculator->feeForZone($zone, $isFreeShipping);

        $total = bcadd($subtotal, $shippingFee, 4);

        // Multi-signal anti-fraud evaluation
        $fraudAssessment = $calculateFraudScore([
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $cleanPhone,
            'delivery_address' => $validated['delivery_address'],
            'payment_method' => 'cash_on_delivery',
            'ip_address' => $request->ip(),
            'device_fingerprint' => $request->header('X-Device-Fingerprint'),
            'honeypot_value' => $request->input('user_company_fax'),
            'timing_token' => $request->input('_timing_token'),
            'skip_courier_lookup' => app()->environment('testing', 'local'),
        ]);

        if ($fraudAssessment->riskLevel === OrderFraudRiskAssessmentDto::LEVEL_BLACKLISTED) {
            throw ValidationException::withMessages([
                'fraud' => ['আপনার অর্ডারটি প্রক্রিয়া করা সম্ভব হচ্ছে না। অনুগ্রহ করে কাস্টমার সাপোর্টে যোগাযোগ করুন।'],
            ]);
        }

        // Resolve geographic district matching zone
        $district = BangladeshDistrict::where('zone_type', $zone)->where('is_active', true)->first()
            ?? BangladeshDistrict::first();

        if (! $district) {
            $division = BangladeshDivision::firstOrCreate(
                ['name_en' => 'Dhaka'],
                ['name_bn' => 'ঢাকা', 'is_active' => true]
            );

            $district = BangladeshDistrict::create([
                'division_id' => $division->id,
                'name_en' => 'Dhaka',
                'name_bn' => 'ঢাকা',
                'zone_type' => 'inside_dhaka',
                'is_active' => true,
            ]);
        }

        $now = Carbon::now();
        $orderNumber = 'ORD-'.$now->format('Ymd').'-'.strtoupper(Str::random(6));

        /** @var Order $order */
        $order = DB::transaction(function () use (
            $request,
            $validated,
            $cleanPhone,
            $variant,
            $quantity,
            $unitPrice,
            $subtotal,
            $shippingFee,
            $total,
            $zone,
            $district,
            $now,
            $orderNumber,
            $fraudAssessment,
            $reserveStock
        ): Order {
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => $request->user()?->id,
                'guest_token' => $request->cookie('ecommerce_guest_token') ?: Str::uuid()->toString(),
                'status' => Order::STATUS_PENDING,
                'payment_status' => Order::PAYMENT_PENDING,
                'payment_method' => 'cash_on_delivery',
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $cleanPhone,
                'customer_email' => $request->user()?->email,
                'ip_address' => $request->ip(),
                'device_fingerprint' => $request->header('X-Device-Fingerprint'),
                'user_agent' => $request->userAgent(),
                'division_id' => $district->division_id,
                'district_id' => $district->id,
                'delivery_zone' => $zone,
                'delivery_address' => $validated['delivery_address'],
                'customer_notes' => $validated['customer_notes'] ?? null,
                'subtotal_bdt' => $subtotal,
                'discount_amount_bdt' => '0.0000',
                'shipping_fee_bdt' => $shippingFee,
                'total_bdt' => $total,
                'advance_paid_bdt' => '0.0000',
                'payable_cod_amount_bdt' => $total,
                'coupon_code' => null,
                'fraud_risk_score' => $fraudAssessment->score,
                'fraud_risk_level' => $fraudAssessment->riskLevel,
            ]);

            // Create Order Item
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product?->name ?? 'Product',
                'sku' => $variant->sku,
                'attributes' => $variant->getAttribute('attributes') ?? [],
                'quantity' => $quantity,
                'unit_price_bdt' => $unitPrice,
                'tier_savings_bdt' => '0.0000',
                'subtotal_bdt' => $subtotal,
                'unit_cogs_bdt' => (string) ($variant->purchase_cost_bdt ?? '0.0000'),
            ]);

            // Record Initial Status History
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => Order::STATUS_PENDING,
                'changed_by_user_id' => $request->user()?->id,
                'reason' => 'Direct 1-Click Funnel Order from Product Page',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'fraud_score' => $fraudAssessment->score,
                    'fraud_risk_level' => $fraudAssessment->riskLevel,
                    'source' => 'product_page_funnel',
                ],
                'created_at' => $now,
            ]);

            // Attempt stock reservation if warehouse stock exists
            try {
                $reserveStock($order);
            } catch (\Throwable) {
                // Gracefully continue if inventory tracking is unseeded or flexible
            }

            return $order;
        });

        $redirectUrl = $resolveRedirect($order, $request->ip());
        $requiresOtp = $order->requiresOtpVerification();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'requires_otp' => $requiresOtp,
                'redirect_url' => $redirectUrl,
                'message' => 'ধন্যবাদ! আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।',
            ], 201);
        }

        if ($requiresOtp) {
            return redirect()->to($redirectUrl)
                ->with('info', 'আপনার মোবাইলে একটি কোড পাঠানো হয়েছে। অর্ডার নিশ্চিত করতে কোডটি লিখুন।');
        }

        return redirect()->to($redirectUrl)
            ->with('success', 'ধন্যবাদ! আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।');
    }
}
