<?php

declare(strict_types=1);

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Customer\Actions\RequestOrderReturnAction;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Order\Models\Order;
use App\Modules\Order\Models\OrderItem;
use App\Modules\Order\Models\OrderStatusHistory;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * My Account: overview, order history, order detail (with return requests) and profile settings.
 * Every query is scoped to the logged-in user's own records.
 */
final class AccountController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $user = $this->customer($request);

        $recentOrders = $user->orders()->latest('id')->take(5)->get();

        return Inertia::render('Storefront/Account/Dashboard', [
            'customer' => $this->customerSummary($user),
            'stats' => [
                'orders' => $user->orders()->count(),
                'active_orders' => $user->orders()->whereNotIn('status', [
                    Order::STATUS_DELIVERED, Order::STATUS_CANCELLED, Order::STATUS_RETURNED,
                ])->count(),
                'wishlist' => $user->wishlistItems()->count(),
                'addresses' => $user->addresses()->count(),
            ],
            'recentOrders' => $recentOrders->map(fn (Order $order): array => $this->orderRow($order))->values(),
        ]);
    }

    public function orders(Request $request): Response
    {
        $user = $this->customer($request);

        return Inertia::render('Storefront/Account/Orders', [
            'orders' => $user->orders()
                ->withCount('items')
                ->latest('id')
                ->paginate(10)
                ->through(fn (Order $order): array => $this->orderRow($order)),
        ]);
    }

    public function showOrder(Request $request, string $order): Response
    {
        $user = $this->customer($request);

        /** @var Order $orderModel */
        $orderModel = $user->orders()
            ->where('order_number', $order)
            ->with(['items', 'statusHistories', 'district'])
            ->firstOrFail();

        $returns = ReturnMerchandiseAuthorization::query()
            ->where('order_id', $orderModel->id)
            ->with('items')
            ->latest('id')
            ->get();

        $deliveredAt = RequestOrderReturnAction::deliveredAt($orderModel);
        $returnable = RequestOrderReturnAction::returnableQuantities($orderModel);
        $canRequestReturn = RequestOrderReturnAction::isWithinReturnWindow($orderModel) && array_sum($returnable) > 0;

        return Inertia::render('Storefront/Account/OrderShow', [
            'order' => [
                ...$this->orderRow($orderModel),
                'payment_status' => $orderModel->payment_status,
                'customer_name' => $orderModel->customer_name,
                'customer_phone' => $orderModel->customer_phone,
                'delivery_address' => $orderModel->delivery_address,
                'district' => $orderModel->district?->name_en,
                'subtotal_bdt' => (string) $orderModel->subtotal_bdt,
                'discount_amount_bdt' => (string) $orderModel->discount_amount_bdt,
                'shipping_fee_bdt' => (string) $orderModel->shipping_fee_bdt,
                'advance_paid_bdt' => (string) $orderModel->advance_paid_bdt,
                'courier_tracking_code' => $orderModel->courier_tracking_code,
                'awaiting_otp' => $orderModel->requiresOtpVerification(),
                'items' => $orderModel->items->map(fn (OrderItem $item): array => [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'attributes' => $item->getAttribute('attributes') ?? [],
                    'quantity' => (int) $item->quantity,
                    'unit_price_bdt' => (string) $item->unit_price_bdt,
                    'subtotal_bdt' => (string) $item->subtotal_bdt,
                    'returnable_quantity' => $returnable[(int) $item->id] ?? 0,
                ])->values(),
                'timeline' => $orderModel->statusHistories
                    ->sortBy('id')
                    ->map(fn (OrderStatusHistory $history): array => [
                        'status' => $history->to_status,
                        'note' => $history->from_status === $history->to_status ? $history->reason : null,
                        'at' => $history->created_at?->format('d M Y, h:i A'),
                    ])->values(),
            ],
            'returns' => $returns->map(fn (ReturnMerchandiseAuthorization $rma): array => [
                'rma_number' => $rma->rma_number,
                'status' => $rma->status,
                'reason_category' => $rma->reason_category,
                'refund_status' => $rma->refund_status,
                'refund_amount_bdt' => (string) $rma->refund_amount_bdt,
                'created_at' => $rma->created_at?->format('d M Y'),
                'items' => $rma->items->map(fn ($item): array => [
                    'item_name' => $item->item_name,
                    'quantity' => (int) $item->quantity_requested,
                ])->values(),
            ])->values(),
            'returnPolicy' => [
                'can_request' => $canRequestReturn,
                'window_days' => RequestOrderReturnAction::RETURN_WINDOW_DAYS,
                'window_ends_at' => $deliveredAt?->copy()->addDays(RequestOrderReturnAction::RETURN_WINDOW_DAYS)->format('d M Y'),
                'reasons' => RequestOrderReturnAction::REASONS,
            ],
        ]);
    }

    public function requestReturn(Request $request, string $order, RequestOrderReturnAction $requestReturn): RedirectResponse
    {
        $user = $this->customer($request);

        /** @var Order $orderModel */
        $orderModel = $user->orders()->where('order_number', $order)->firstOrFail();

        $validated = $request->validate([
            'reason' => ['required', 'string', Rule::in(RequestOrderReturnAction::REASONS)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $rma = $requestReturn(
            customer: $user,
            order: $orderModel,
            items: $validated['items'],
            reason: $validated['reason'],
            notes: $validated['notes'] ?? null,
        );

        return back()->with('success', "Return request {$rma->rma_number} submitted. Our team will contact you to arrange pickup.");
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('Storefront/Account/Profile', [
            'customer' => $this->customerSummary($this->customer($request)),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $this->customer($request);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'phone' => BangladeshPhoneNumberRule::toLocalFormat(
                BangladeshPhoneNumberRule::cleanNumber((string) $request->input('phone'))
            ),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
        ]);

        if (! BangladeshPhoneNumberRule::isValidFormat(BangladeshPhoneNumberRule::cleanNumber($validated['phone']))) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid 11-digit Bangladesh mobile number (e.g. 017XXXXXXXX).',
            ]);
        }

        $user->update($validated);

        return back()->with('success', 'Your profile has been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $this->customer($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'Your password has been changed.');
    }

    private function customer(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array{name: string, email: string, phone: ?string, member_since: ?string}
     */
    private function customerSummary(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'member_since' => $user->created_at?->format('M Y'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'total_bdt' => (string) $order->total_bdt,
            'items_count' => $order->items_count ?? null,
            'placed_at' => $order->created_at?->format('d M Y'),
        ];
    }
}
