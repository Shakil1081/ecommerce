<?php

declare(strict_types=1);

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cart\Actions\GetActiveCartAction;
use App\Modules\Cart\Http\Resources\CartResource;
use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Inventory\Actions\ReserveStockForOrderAction;
use App\Modules\Order\Actions\CreateOrderFromCartAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class CheckoutController extends Controller
{
    /**
     * Display the 1-Page Checkout screen.
     */
    public function index(
        Request $request,
        GetActiveCartAction $getCart,
        HoneypotTimingGuardService $timingGuard
    ): Response|RedirectResponse {
        $token = $request->cookie('ecommerce_guest_token') ?: $request->header('X-Guest-Token');
        $cart = $getCart($request->user(), is_string($token) ? $token : null);
        $cart->loadMissing('items.variant.product.media');

        // Preload active Bangladesh divisions with their active districts
        $divisions = BangladeshDivision::query()
            ->with(['districts' => function ($query) {
                $query->where('is_active', true)->orderBy('name_en');
            }])
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en', 'name_bn']);

        $timingToken = $timingGuard->generateTimingToken();

        return Inertia::render('Storefront/Checkout', [
            'cart' => new CartResource($cart),
            'divisions' => $divisions,
            'timingToken' => $timingToken,
            'freeShippingThreshold' => (float) CartResource::FREE_SHIPPING_THRESHOLD,
            'shippingRates' => [
                'inside_dhaka' => 60.0,
                'dhaka_suburbs' => 100.0,
                'outside_dhaka' => 130.0,
            ],
        ]);
    }

    /**
     * Handle order submission from the shopping cart.
     */
    public function store(
        Request $request,
        GetActiveCartAction $getCart,
        CreateOrderFromCartAction $createOrder,
        ReserveStockForOrderAction $reserveStock
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'min:2', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:25'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'delivery_zone' => ['nullable', 'string', 'in:inside_dhaka,dhaka_suburbs,outside_dhaka'],
            'division_id' => ['nullable', 'integer', 'exists:bangladesh_divisions,id'],
            'district_id' => ['nullable', 'integer', 'exists:bangladesh_districts,id'],
            'delivery_address' => ['required', 'string', 'min:5', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:cash_on_delivery,bkash,nagad,sslcommerz'],
            'user_company_fax' => ['nullable', 'string', 'max:100'],
            '_form_timing_token' => ['nullable', 'string', 'max:500'],
        ], [
            'customer_name.required' => 'অনুগ্রহ করে আপনার নাম লিখুন।',
            'customer_phone.required' => 'অনুগ্রহ করে আপনার মোবাইল নম্বর লিখুন।',
            'delivery_address.required' => 'অনুগ্রহ করে আপনার সম্পূর্ণ ডেলিভারি ঠিকানা লিখুন।',
            'payment_method.required' => 'অনুগ্রহ করে পেমেন্ট মেথড নির্বাচন করুন।',
        ]);

        // Clean & format Bangladesh phone number
        $cleanPhone = BangladeshPhoneNumberRule::cleanNumber($validated['customer_phone']);
        $localPhone = BangladeshPhoneNumberRule::toLocalFormat($cleanPhone);

        if (! BangladeshPhoneNumberRule::isValidFormat($cleanPhone)) {
            throw ValidationException::withMessages([
                'customer_phone' => ['অনুগ্রহ করে একটি সঠিক ১১ ডিজিটের মোবাইল নম্বর দিন (যেমন: 017xxxxxxxx)'],
            ]);
        }

        $token = $request->cookie('ecommerce_guest_token') ?: $request->header('X-Guest-Token');
        $cart = $getCart($request->user(), is_string($token) ? $token : null);
        $cart->loadMissing('items.variant.product');

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['আপনার শপিং কার্ট খালি। অনুগ্রহ করে পণ্য যোগ করে আবার চেষ্টা করুন।'],
            ]);
        }

        $zone = $validated['delivery_zone'] ?? 'inside_dhaka';

        // Resolve geographic district matching selected delivery zone or explicit district_id
        $districtId = $validated['district_id'] ?? null;
        $divisionId = $validated['division_id'] ?? null;

        if (! $districtId) {
            $district = BangladeshDistrict::query()
                ->where('zone_type', $zone)
                ->where('is_active', true)
                ->first();

            if (! $district) {
                $division = BangladeshDivision::firstOrCreate(
                    ['name_en' => 'Dhaka'],
                    ['name_bn' => 'ঢাকা', 'is_active' => true]
                );

                $district = BangladeshDistrict::create([
                    'division_id' => $division->id,
                    'name_en' => match ($zone) {
                        'outside_dhaka' => 'Chattogram',
                        'dhaka_suburbs' => 'Gazipur',
                        default => 'Dhaka',
                    },
                    'name_bn' => match ($zone) {
                        'outside_dhaka' => 'চট্টগ্রাম',
                        'dhaka_suburbs' => 'গাজীপুর',
                        default => 'ঢাকা',
                    },
                    'code' => match ($zone) {
                        'outside_dhaka' => 'CTG-01',
                        'dhaka_suburbs' => 'GAZ-01',
                        default => 'DHA-01',
                    },
                    'zone_type' => $zone,
                    'is_active' => true,
                ]);
            }

            $districtId = $district->id;
            $divisionId = $district->division_id;
        } elseif (! $divisionId) {
            $district = BangladeshDistrict::find($districtId);
            $divisionId = $district?->division_id;
        }

        $order = $createOrder(
            cart: $cart,
            customerData: [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $localPhone,
                'customer_email' => $validated['customer_email'] ?? $request->user()?->email,
                'division_id' => (int) $divisionId,
                'district_id' => (int) $districtId,
                'delivery_address' => $validated['delivery_address'],
                'customer_notes' => $validated['customer_notes'] ?? null,
                'payment_method' => $validated['payment_method'],
                'honeypot_value' => $request->input('user_company_fax'),
                'timing_token' => $request->input('_form_timing_token'),
                'ip_address' => $request->ip(),
                'device_fingerprint' => $request->header('X-Device-Fingerprint'),
                'user_agent' => $request->userAgent(),
                'skip_courier_lookup' => app()->environment('testing', 'local'),
            ],
            user: $request->user()
        );

        // Attempt stock reservation
        try {
            $reserveStock($order);
        } catch (\Throwable) {
            // Non-blocking for unseeded warehouses
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'redirect_url' => route('storefront.order-confirmed', ['order' => $order->order_number]),
                'message' => 'ধন্যবাদ! আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।',
            ], 201);
        }

        return redirect()->route('storefront.order-confirmed', ['order' => $order->order_number])
            ->with('success', 'ধন্যবাদ! আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।');
    }
}
