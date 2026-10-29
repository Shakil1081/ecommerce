<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Models\GoodsReceivedNote;
use App\Modules\Procurement\Models\GrnItem;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProcurementAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Supplier $localSupplier;

    private Supplier $intSupplier;

    private Product $product;

    private ProductVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $category = Category::create([
            'name' => 'Home Appliances',
            'slug' => 'home-appliances',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Vision',
            'slug' => 'vision',
            'country_of_origin' => 'BD',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'name' => 'Microwave Oven 25L',
            'slug' => 'microwave-oven-25l',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'origin_country' => 'Bangladesh',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'MWO-25L-BLK',
            'barcode_ean' => '8949876543210',
            'attributes' => ['color' => 'Black'],
            'purchase_cost_bdt' => '8500.0000',
            'selling_price_bdt' => '12500.0000',
            'is_active' => true,
        ]);

        $this->localSupplier = Supplier::create([
            'code' => 'SUP-LOC-TEST',
            'name' => 'RFL Electronics Ltd',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001234567-0101',
            'is_vds_applicable' => true,
            'is_active' => true,
        ]);

        $this->intSupplier = Supplier::create([
            'code' => 'SUP-INT-TEST',
            'name' => 'Midea Overseas Trade Co.',
            'type' => 'international',
            'country_code' => 'CN',
            'currency' => 'USD',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub Dhaka',
            'code' => 'HUB-DHK-01',
            'address' => 'Tejgaon, Dhaka',
            'is_central' => true,
            'is_active' => true,
        ]);
    }

    public function test_can_view_suppliers_directory_page(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/procurement/suppliers');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Procurement/SupplierIndex')
            ->has('suppliers.data', 2)
        );
    }

    public function test_can_store_new_supplier_via_admin(): void
    {
        $response = $this->actingAs($this->user)->post('/admin/procurement/suppliers', [
            'name' => 'Singer Bangladesh Ltd',
            'code' => 'SUP-SINGER-01',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '009876543-0202',
            'tin_number' => '998877665544',
            'contact_person' => 'Jamil Ahmed',
            'is_vds_applicable' => true,
            'is_tds_applicable' => true,
            'is_active' => true,
        ]);

        $response->assertRedirect('/admin/procurement/suppliers');
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-SINGER-01',
            'bin_number' => '0098765430202', // normalized
        ]);
    }

    public function test_can_view_and_create_import_purchase_order(): void
    {
        $getRes = $this->actingAs($this->user)->get('/admin/procurement/import-orders');
        $getRes->assertStatus(200);
        $getRes->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Procurement/ImportOrderIndex')
            ->has('orders.data')
            ->has('suppliers')
        );

        $postRes = $this->actingAs($this->user)->post('/admin/procurement/import-orders', [
            'supplier_id' => $this->intSupplier->id,
            'po_number' => 'IPO-2026-TEST-001',
            'currency' => 'USD',
            'exchange_rate_to_bdt' => 122.50,
            'payment_method' => 'letter_of_credit',
            'lc_number' => 'LC-SCB-99881',
            'port_of_discharge' => 'Chattogram',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'quantity_ordered' => 20,
                    'unit_price_foreign' => 70.00,
                ],
            ],
        ]);

        $postRes->assertRedirect('/admin/procurement/import-orders');
        $this->assertDatabaseHas('import_purchase_orders', [
            'po_number' => 'IPO-2026-TEST-001',
            'total_fob_foreign' => '1400.0000',
            'total_fob_bdt' => '171500.0000',
        ]);
    }

    public function test_can_view_create_local_po_and_issue_mushak_6_6(): void
    {
        $getRes = $this->actingAs($this->user)->get('/admin/procurement/local-pos');
        $getRes->assertStatus(200);
        $getRes->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Procurement/LocalPoIndex')
            ->has('orders.data')
        );

        $postRes = $this->actingAs($this->user)->post('/admin/procurement/local-pos', [
            'supplier_id' => $this->localSupplier->id,
            'po_number' => 'LPO-2026-TEST-001',
            'order_date' => '2026-10-25',
            'vds_rate_percent' => 5.0,
            'tds_rate_percent' => 2.0,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'product_variant_id' => $this->variant->id,
                    'quantity_ordered' => 10,
                    'unit_price_bdt' => 8000.00,
                    'vat_rate_percent' => 15.0,
                ],
            ],
        ]);

        $postRes->assertRedirect('/admin/procurement/local-pos');
        $po = LocalPurchaseOrder::where('po_number', 'LPO-2026-TEST-001')->firstOrFail();

        $this->assertEquals('80000.0000', (string) $po->subtotal_bdt);
        $this->assertEquals('12000.0000', (string) $po->vat_amount_bdt);
        $this->assertEquals('4000.0000', (string) $po->vds_amount_bdt);

        // Issue Mushak 6.6
        $issueRes = $this->actingAs($this->user)->post("/admin/procurement/local-pos/{$po->id}/issue-mushak66");
        $issueRes->assertRedirect('/admin/procurement/local-pos');
        $this->assertNotNull($po->fresh()->mushak_6_6_certificate_number);
    }

    public function test_can_view_and_process_grn_intake(): void
    {
        $grn = GoodsReceivedNote::create([
            'grn_number' => 'GRN-2026-TEST-001',
            'grn_date' => '2026-10-28',
            'source_type' => 'local',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        GrnItem::create([
            'goods_received_note_id' => $grn->id,
            'product_variant_id' => $this->variant->id,
            'quantity_ordered' => 15,
            'quantity_received' => 15,
            'bin_location' => 'AISLE-1-SHELF-A',
        ]);

        $getRes = $this->actingAs($this->user)->get('/admin/procurement/grn');
        $getRes->assertStatus(200);
        $getRes->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Procurement/GrnReceiving')
            ->has('grns.data', 1)
        );

        $receiveRes = $this->actingAs($this->user)->post("/admin/procurement/grn/{$grn->id}/receive");
        $receiveRes->assertRedirect('/admin/procurement/grn');
        $this->assertEquals('completed', $grn->fresh()->status);
    }
}
