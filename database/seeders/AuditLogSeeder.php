<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Admin\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Order\Models\Order;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'like', '%admin%')->first()
            ?? User::first();

        $adminId = $admin?->id;
        $adminName = $admin?->name ?? 'System Administrator';
        $adminEmail = $admin?->email ?? 'admin@ecommerce.test';

        // 1. Seed audit logs for existing orders
        $orders = Order::query()->with('items')->latest('id')->get();

        foreach ($orders as $index => $order) {
            $baseTime = Carbon::parse($order->created_at ?? now()->subDays(3));

            // Order Placed entry
            AuditLog::create([
                'ulid' => (string) Str::ulid(),
                'event' => AuditLog::EVENT_ORDER_STATUS_CHANGED,
                'auditable_type' => Order::class,
                'auditable_id' => (string) $order->id,
                'description' => "Order [{$order->order_number}] placed by {$order->customer_name} ({$order->customer_phone}) for ৳".number_format((float) $order->total_bdt, 2)." via {$order->payment_method}.",
                'old_values' => null,
                'new_values' => [
                    'order_number' => $order->order_number,
                    'status' => 'pending',
                    'total_bdt' => $order->total_bdt,
                    'payment_method' => $order->payment_method,
                    'delivery_zone' => $order->delivery_zone,
                ],
                'user_id' => null,
                'user_name' => $order->customer_name,
                'user_email' => $order->customer_email,
                'ip_address' => $order->ip_address ?? '103.230.104.12',
                'user_agent' => $order->user_agent ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'created_at' => $baseTime,
                'updated_at' => $baseTime,
            ]);

            // Confirmed entry (if confirmed or beyond)
            if ($order->status !== 'pending') {
                $confirmTime = $baseTime->copy()->addMinutes(15);
                AuditLog::create([
                    'ulid' => (string) Str::ulid(),
                    'event' => AuditLog::EVENT_ORDER_STATUS_CHANGED,
                    'auditable_type' => Order::class,
                    'auditable_id' => (string) $order->id,
                    'description' => "Order [{$order->order_number}] confirmed and inventory stock reserved by {$adminName}.",
                    'old_values' => ['status' => 'pending'],
                    'new_values' => ['status' => 'confirmed'],
                    'user_id' => $adminId,
                    'user_name' => $adminName,
                    'user_email' => $adminEmail,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                    'created_at' => $confirmTime,
                    'updated_at' => $confirmTime,
                ]);
            }

            // Courier booking entry (if booked)
            if (! empty($order->courier_tracking_code) || ! empty($order->courier_consignment_id)) {
                $bookTime = $baseTime->copy()->addHours(1);
                $courierName = ucfirst($order->courier_code ?? 'Courier');
                $tracking = $order->courier_tracking_code ?? $order->courier_consignment_id;

                AuditLog::create([
                    'ulid' => (string) Str::ulid(),
                    'event' => AuditLog::EVENT_ORDER_STATUS_CHANGED,
                    'auditable_type' => Order::class,
                    'auditable_id' => (string) $order->id,
                    'description' => "Order [{$order->order_number}] consignment booked with {$courierName}. Tracking Code: {$tracking}.",
                    'old_values' => [
                        'status' => 'confirmed',
                        'courier_code' => null,
                        'courier_tracking_code' => null,
                    ],
                    'new_values' => [
                        'status' => 'processing',
                        'courier_code' => $order->courier_code,
                        'courier_tracking_code' => $tracking,
                    ],
                    'user_id' => $adminId,
                    'user_name' => $adminName,
                    'user_email' => $adminEmail,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                    'created_at' => $bookTime,
                    'updated_at' => $bookTime,
                ]);
            }

            // Returned entry
            if ($order->status === 'returned') {
                $returnTime = $baseTime->copy()->addDays(2);
                AuditLog::create([
                    'ulid' => (string) Str::ulid(),
                    'event' => AuditLog::EVENT_STOCK_ADJUSTED,
                    'auditable_type' => Order::class,
                    'auditable_id' => (string) $order->id,
                    'description' => "Courier returned parcel inward scanned and items restocked for order [{$order->order_number}]. Reason: Customer refused delivery / unreachable.",
                    'old_values' => ['status' => 'handed_to_courier'],
                    'new_values' => ['status' => 'returned', 'restocked_units' => $order->items->sum('quantity') ?: 1],
                    'user_id' => $adminId,
                    'user_name' => $adminName,
                    'user_email' => $adminEmail,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                    'created_at' => $returnTime,
                    'updated_at' => $returnTime,
                ]);
            }
        }

        // 2. Seed Product Price Update Audit Entries
        $sampleProducts = Product::query()->take(4)->get();
        foreach ($sampleProducts as $i => $product) {
            $oldPrice = (float) $product->price_bdt;
            $newPrice = $oldPrice + 150.00;
            $time = Carbon::now()->subDays(4 - $i)->subHours(3);

            AuditLog::create([
                'ulid' => (string) Str::ulid(),
                'event' => AuditLog::EVENT_PRICE_UPDATED,
                'auditable_type' => Product::class,
                'auditable_id' => (string) $product->id,
                'description' => "Updated retail selling price for '{$product->name}' from ৳".number_format($oldPrice, 2).' to ৳'.number_format($newPrice, 2).' following customs duty reconciliation.',
                'old_values' => ['price_bdt' => $oldPrice],
                'new_values' => ['price_bdt' => $newPrice, 'currency' => 'BDT'],
                'user_id' => $adminId,
                'user_name' => $adminName,
                'user_email' => $adminEmail,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                'created_at' => $time,
                'updated_at' => $time,
            ]);
        }

        // 3. Seed Stock Movements & GRN Receipts
        AuditLog::create([
            'ulid' => (string) Str::ulid(),
            'event' => AuditLog::EVENT_STOCK_ADJUSTED,
            'auditable_type' => 'App\Modules\Procurement\Models\GoodsReceivedNote',
            'auditable_id' => 'GRN-2026-001',
            'description' => 'Inward GRN [GRN-2026-001] verified and received: 120 units allocated to Dhaka Central Hub (FIFO Inventory Valuation).',
            'old_values' => ['received_status' => 'pending_inspection'],
            'new_values' => ['received_status' => 'completed', 'total_units_received' => 120],
            'user_id' => $adminId,
            'user_name' => $adminName,
            'user_email' => $adminEmail,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subDays(5),
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // 4. Seed Statutory NBR Tax Invoices (Mushak 6.3)
        AuditLog::create([
            'ulid' => (string) Str::ulid(),
            'event' => AuditLog::EVENT_MUSHAK_ISSUED,
            'auditable_type' => 'App\Modules\TaxCompliance\Models\Mushak63Invoice',
            'auditable_id' => 'M63-2026-001',
            'description' => 'Statutory NBR Mushak 6.3 Tax Invoice [M63-2026-001] issued with sequential lock: Mushak VAT 15% applied.',
            'old_values' => null,
            'new_values' => [
                'mushak_number' => 'M63-2026-001',
                'vat_rate_percent' => 15.00,
                'net_vat_bdt' => 20700.00,
            ],
            'user_id' => $adminId,
            'user_name' => $adminName,
            'user_email' => $adminEmail,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subDays(2),
            'updated_at' => Carbon::now()->subDays(2),
        ]);

        // 5. Seed Anti-Fraud Blacklist & Security Events
        AuditLog::create([
            'ulid' => (string) Str::ulid(),
            'event' => AuditLog::EVENT_FRAUD_LIST_UPDATED,
            'auditable_type' => 'App\Modules\Customer\Models\CustomerFraudList',
            'auditable_id' => '1',
            'description' => 'Phone [01711000000] added to Merchant COD Blacklist. Reason: Repeated courier return refusals / fake order history.',
            'old_values' => null,
            'new_values' => [
                'type' => 'blacklist',
                'phone_number' => '01711000000',
                'action_taken' => 'cod_blocked',
            ],
            'user_id' => $adminId,
            'user_name' => $adminName,
            'user_email' => $adminEmail,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subDays(1),
            'updated_at' => Carbon::now()->subDays(1),
        ]);

        AuditLog::create([
            'ulid' => (string) Str::ulid(),
            'event' => AuditLog::EVENT_SECURITY_SETTING_CHANGED,
            'auditable_type' => 'App\Modules\Admin\Models\BotSecuritySetting',
            'auditable_id' => '1',
            'description' => 'Updated bot defense configuration: Cloudflare Turnstile enabled on Checkout & Registration endpoints.',
            'old_values' => ['turnstile_enabled' => false],
            'new_values' => ['turnstile_enabled' => true, 'action' => 'managed_challenge'],
            'user_id' => $adminId,
            'user_name' => $adminName,
            'user_email' => $adminEmail,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
            'created_at' => Carbon::now()->subHours(12),
            'updated_at' => Carbon::now()->subHours(12),
        ]);
    }
}
