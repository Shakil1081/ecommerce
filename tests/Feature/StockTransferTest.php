<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Actions\AdjustInventoryStockAction;
use App\Modules\Inventory\Actions\ReceiveWarehouseTransferAction;
use App\Modules\Inventory\Actions\TransferWarehouseStockAction;
use App\Modules\Inventory\Models\InventoryStock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Models\WarehouseTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $sourceWarehouse;

    private Warehouse $destinationWarehouse;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceWarehouse = Warehouse::create([
            'name' => 'Dhaka Central Hub',
            'code' => 'CENTRAL-DHAKA',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $this->destinationWarehouse = Warehouse::create([
            'name' => 'Chattogram Regional Depot',
            'code' => 'DEPOT-CTG',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Gadgets', 'is_active' => true]);
        $brand = Brand::create(['name' => 'DJI Global', 'origin_country' => 'China', 'is_active' => true]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'DJI Osmo Pocket 3',
            'hs_code' => '8525.89.00',
            'is_published' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'DJI-POCKET-3-CREATOR',
            'attributes' => ['Edition' => 'Creator Combo'],
            'purchase_cost_bdt' => 62000.00,
            'selling_price_bdt' => 74500.00,
            'is_active' => true,
        ]);

        // Stock 100 units initially at source warehouse
        $adjustAction = new AdjustInventoryStockAction;
        $adjustAction($this->sourceWarehouse, $this->variant, 100, 'initial_grn');
    }

    public function test_can_dispatch_and_receive_mushak_6_5_inter_depot_transfer(): void
    {
        $adjustAction = new AdjustInventoryStockAction;
        $transferAction = new TransferWarehouseStockAction($adjustAction);
        $receiveAction = new ReceiveWarehouseTransferAction($adjustAction);

        // 1. Dispatch 30 units from Dhaka to Chattogram
        $transfer = $transferAction(
            $this->sourceWarehouse,
            $this->destinationWarehouse,
            [
                ['variant' => $this->variant, 'quantity' => 30],
            ],
            vehicleNumber: 'Dhaka Metro Ta 14-8899',
            driverName: 'Rafiqul Islam',
            driverContact: '01711000000',
            notes: 'Transfer to support Chattogram commercial order demand'
        );

        $this->assertEquals(WarehouseTransfer::STATUS_IN_TRANSIT, $transfer->status);
        $this->assertStringStartsWith('TR-', $transfer->transfer_number);
        $this->assertStringStartsWith('M65-', $transfer->mushak_6_5_serial);
        $this->assertNotNull($transfer->dispatched_at);

        // Verify source stock was reduced: 100 - 30 = 70
        $sourceStock = InventoryStock::where('warehouse_id', $this->sourceWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertEquals(70, $sourceStock->physical_quantity);

        // Destination stock should not have arrived yet
        $destStockBefore = InventoryStock::where('warehouse_id', $this->destinationWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertNull($destStockBefore);

        // 2. Receive the transfer at Chattogram Regional Depot
        $receivedTransfer = $receiveAction($transfer);

        $this->assertEquals(WarehouseTransfer::STATUS_RECEIVED, $receivedTransfer->status);
        $this->assertNotNull($receivedTransfer->received_at);

        // Verify destination stock committed: 30 units
        $destStockAfter = InventoryStock::where('warehouse_id', $this->destinationWarehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();
        $this->assertNotNull($destStockAfter);
        $this->assertEquals(30, $destStockAfter->physical_quantity);
    }

    public function test_prevents_transfer_to_same_warehouse(): void
    {
        $adjustAction = new AdjustInventoryStockAction;
        $transferAction = new TransferWarehouseStockAction($adjustAction);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source and destination warehouse cannot be the same');

        $transferAction(
            $this->sourceWarehouse,
            $this->sourceWarehouse,
            [['variant' => $this->variant, 'quantity' => 5]]
        );
    }

    public function test_prevents_transferring_more_than_available_stock(): void
    {
        $adjustAction = new AdjustInventoryStockAction;
        $transferAction = new TransferWarehouseStockAction($adjustAction);

        // Available is 100, try to transfer 150
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient physical stock');

        $transferAction(
            $this->sourceWarehouse,
            $this->destinationWarehouse,
            [['variant' => $this->variant, 'quantity' => 150]]
        );
    }
}

