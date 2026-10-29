<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Actions;

use App\Modules\Inventory\Models\WarehouseTransfer;
use App\Modules\TaxCompliance\DTOs\Mushak65GenerationResultDto;
use App\Modules\TaxCompliance\Models\Mushak65Challan;
use App\Modules\TaxCompliance\Models\Mushak65Item;
use App\Modules\TaxCompliance\Services\MushakSequenceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateMushak65ChallanAction
{
    public function __construct(
        protected MushakSequenceService $sequenceService,
    ) {}

    /**
     * Generate an official statutory NBR Mushak 6.5 Goods Transfer Challan (পণ্য স্থানান্তর চালানপত্র)
     * for inter-depot stock movements.
     *
     * @param  array<string, mixed>  $options
     */
    public function __invoke(WarehouseTransfer $transfer, array $options = []): Mushak65GenerationResultDto
    {
        // 1. Check for existing challan (idempotent)
        /** @var Mushak65Challan|null $existing */
        $existing = Mushak65Challan::where('warehouse_transfer_id', $transfer->id)
            ->with('items')
            ->first();

        if ($existing) {
            return new Mushak65GenerationResultDto(
                challan: $existing,
                challanNumber: $existing->challan_number,
                sourceWarehouseName: $existing->source_warehouse_name,
                destinationWarehouseName: $existing->destination_warehouse_name,
                totalQuantity: $existing->total_quantity,
                totalValuationBdt: $existing->total_valuation_bdt,
                isExisting: true,
            );
        }

        $transfer->loadMissing('sourceWarehouse', 'destinationWarehouse', 'items.variant.product');

        if ($transfer->source_warehouse_id === $transfer->destination_warehouse_id) {
            throw new InvalidArgumentException('Cannot issue Mushak 6.5 Challan: Source and destination warehouses must be different.');
        }

        if ($transfer->items->isEmpty()) {
            throw new InvalidArgumentException('Cannot issue Mushak 6.5 Challan: Transfer contains no items.');
        }

        $now = CarbonImmutable::now();
        $issueDate = isset($options['issue_date']) ? CarbonImmutable::parse($options['issue_date']) : $now;
        $taxPeriod = $options['tax_period'] ?? $issueDate->format('Y-m');

        $sourceWarehouse = $transfer->sourceWarehouse;
        $destWarehouse = $transfer->destinationWarehouse;

        $sellerBin = config('tax.seller_bin', '001234567-0101');
        $sourceBin = $options['source_warehouse_bin'] ?? $sellerBin;
        $destBin = $options['destination_warehouse_bin'] ?? $sellerBin;

        $vehicle = $options['vehicle_type_and_number'] ?? $transfer->vehicle_number ?? 'Covered Van (Dhaka Metro-U-11-2092)';
        $driverName = $options['driver_name'] ?? $transfer->driver_name;
        $driverContact = $options['driver_contact'] ?? $transfer->driver_contact;

        $dispatchTime = $transfer->dispatched_at ?? $now;

        return DB::transaction(function () use (
            $transfer,
            $sourceWarehouse,
            $destWarehouse,
            $issueDate,
            $taxPeriod,
            $sourceBin,
            $destBin,
            $vehicle,
            $driverName,
            $driverContact,
            $dispatchTime,
            $options
        ): Mushak65GenerationResultDto {
            // 2. Generate statutory sequential challan number under atomic lock
            $challanNumber = $this->sequenceService->generateNextSequence('6.5', $issueDate);

            // 3. Compute item lines and valuations
            $itemsData = [];
            $totalQuantity = 0;
            $totalValuation = 0.00;

            foreach ($transfer->items as $transferItem) {
                $qty = (int) $transferItem->quantity;
                $variant = $transferItem->variant;
                $product = $variant?->product;

                $unitCost = (float) ($variant?->purchase_cost_bdt ?? $variant?->selling_price_bdt ?? 0.00);
                $lineValuation = round($unitCost * $qty, 4);

                $totalQuantity += $qty;
                $totalValuation += $lineValuation;

                $itemsData[] = [
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant?->id,
                    'item_name' => $product?->name ?? 'Transferred Product',
                    'sku' => $variant?->sku ?? 'UNKNOWN-SKU',
                    'hs_code' => $product?->hs_code ?? null,
                    'unit_of_measure' => 'PCS',
                    'quantity' => $qty,
                    'unit_cost_bdt' => number_format($unitCost, 4, '.', ''),
                    'total_valuation_bdt' => number_format($lineValuation, 4, '.', ''),
                ];
            }

            // 4. Compute SHA-256 historical integrity fingerprint
            $hashPayload = [
                'challan_number' => $challanNumber,
                'transfer_id' => $transfer->id,
                'source_warehouse_id' => $sourceWarehouse->id,
                'destination_warehouse_id' => $destWarehouse->id,
                'vehicle' => $vehicle,
                'total_quantity' => $totalQuantity,
                'total_valuation_bdt' => number_format($totalValuation, 4, '.', ''),
                'items' => array_map(fn (array $i): array => [
                    'sku' => $i['sku'],
                    'qty' => $i['quantity'],
                    'val' => $i['total_valuation_bdt'],
                ], $itemsData),
            ];

            $integrityHash = hash('sha256', json_encode($hashPayload, JSON_THROW_ON_ERROR));

            // 5. Persist Mushak 6.5 Challan
            /** @var Mushak65Challan $challan */
            $challan = Mushak65Challan::create([
                'challan_number' => $challanNumber,
                'tax_period' => $taxPeriod,
                'issue_date' => $issueDate,
                'warehouse_transfer_id' => $transfer->id,
                'source_warehouse_id' => $sourceWarehouse->id,
                'source_warehouse_name' => $sourceWarehouse->name,
                'source_warehouse_bin' => $sourceBin,
                'source_warehouse_address' => $sourceWarehouse->address ?? 'Central Hub, Dhaka',
                'destination_warehouse_id' => $destWarehouse->id,
                'destination_warehouse_name' => $destWarehouse->name,
                'destination_warehouse_bin' => $destBin,
                'destination_warehouse_address' => $destWarehouse->address ?? 'Regional Depot, Bangladesh',
                'vehicle_type_and_number' => $vehicle,
                'driver_name' => $driverName,
                'driver_contact' => $driverContact,
                'dispatch_time' => $dispatchTime,
                'arrival_time' => $transfer->received_at,
                'total_quantity' => $totalQuantity,
                'total_valuation_bdt' => number_format($totalValuation, 4, '.', ''),
                'status' => $transfer->status === WarehouseTransfer::STATUS_RECEIVED ? 'received' : 'dispatched',
                'integrity_hash' => $integrityHash,
                'notes' => $options['notes'] ?? $transfer->notes,
                'created_by_user_id' => $options['created_by_user_id'] ?? null,
            ]);

            // 6. Persist Line Items
            foreach ($itemsData as $itemData) {
                $itemData['mushak_6_5_challan_id'] = $challan->id;
                Mushak65Item::create($itemData);
            }

            // 7. Update parent Transfer reference
            $transfer->updateQuietly(['mushak_6_5_serial' => $challanNumber]);

            $challan->load('items');

            return new Mushak65GenerationResultDto(
                challan: $challan,
                challanNumber: $challanNumber,
                sourceWarehouseName: $sourceWarehouse->name,
                destinationWarehouseName: $destWarehouse->name,
                totalQuantity: $totalQuantity,
                totalValuationBdt: $challan->total_valuation_bdt,
                isExisting: false,
            );
        });
    }
}
