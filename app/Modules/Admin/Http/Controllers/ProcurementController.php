<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Procurement\Actions\IssueMushak66Action;
use App\Modules\Procurement\Actions\ReceiveGoodsNoteAction;
use App\Modules\Procurement\Actions\VerifySupplierBinAction;
use App\Modules\Procurement\Models\GoodsReceivedNote;
use App\Modules\Procurement\Models\ImportPoItem;
use App\Modules\Procurement\Models\ImportPurchaseOrder;
use App\Modules\Procurement\Models\LocalPoItem;
use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ProcurementController extends Controller
{
    /**
     * Display the supplier directory (International & Local).
     */
    public function suppliers(Request $request): Response
    {
        $query = Supplier::query()->with(['division', 'district', 'thana']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('bin_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $suppliers = $query->latest('id')->paginate(15)->withQueryString();

        return Inertia::render('Admin/Procurement/SupplierIndex', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'type']),
        ]);
    }

    /**
     * Store a newly created supplier.
     */
    public function storeSupplier(Request $request, VerifySupplierBinAction $verifyBin): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name_bn' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:suppliers,code'],
            'type' => ['required', 'in:international,local'],
            'country_code' => ['required', 'string', 'max:3'],
            'currency' => ['required', 'string', 'max:3'],
            'bin_number' => ['nullable', 'string', 'max:30'],
            'tin_number' => ['nullable', 'string', 'max:30'],
            'trade_license_number' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'is_vds_applicable' => ['boolean'],
            'is_tds_applicable' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($validated['bin_number'])) {
            $check = $verifyBin($validated['bin_number']);
            if ($check['is_valid'] && $check['normalized_bin']) {
                $validated['bin_number'] = $check['normalized_bin'];
            }
        }

        Supplier::create($validated);

        return redirect()->route('admin.procurement.suppliers')
            ->with('success', 'Supplier created successfully.');
    }

    /**
     * Display global commercial import purchase orders.
     */
    public function importOrders(Request $request): Response
    {
        $orders = ImportPurchaseOrder::query()
            ->with(['supplier', 'items.variant.product'])
            ->latest('id')
            ->paginate(15);

        $suppliers = Supplier::international()->active()->get(['id', 'name', 'code', 'currency']);
        $products = Product::with(['variants'])->where('is_published', true)->get();

        return Inertia::render('Admin/Procurement/ImportOrderIndex', [
            'orders' => $orders,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Store a new global import purchase order with items.
     */
    public function storeImportOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'po_number' => ['required', 'string', 'unique:import_purchase_orders,po_number'],
            'currency' => ['required', 'string', 'max:3'],
            'exchange_rate_to_bdt' => ['required', 'numeric', 'min:0.0001'],
            'payment_method' => ['required', 'in:letter_of_credit,telegraphic_transfer,open_account'],
            'lc_number' => ['nullable', 'string'],
            'lc_issuing_bank' => ['nullable', 'string'],
            'port_of_loading' => ['nullable', 'string'],
            'port_of_discharge' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_foreign' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            $order = ImportPurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'po_number' => $validated['po_number'],
                'currency' => $validated['currency'],
                'exchange_rate_to_bdt' => $validated['exchange_rate_to_bdt'],
                'payment_method' => $validated['payment_method'],
                'lc_number' => $validated['lc_number'] ?? null,
                'lc_issuing_bank' => $validated['lc_issuing_bank'] ?? null,
                'port_of_loading' => $validated['port_of_loading'] ?? null,
                'port_of_discharge' => $validated['port_of_discharge'],
                'status' => 'pi_received',
            ]);

            $rate = (string) $validated['exchange_rate_to_bdt'];

            foreach ($validated['items'] as $itemData) {
                $foreignPrice = (string) $itemData['unit_price_foreign'];
                $qty = (int) $itemData['quantity_ordered'];
                $bdtPrice = bcmul($foreignPrice, $rate, 4);
                $totalForeign = bcmul($foreignPrice, (string) $qty, 4);
                $totalBdt = bcmul($bdtPrice, (string) $qty, 4);

                ImportPoItem::create([
                    'import_purchase_order_id' => $order->id,
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'],
                    'quantity_ordered' => $qty,
                    'unit_price_foreign' => $foreignPrice,
                    'unit_price_bdt' => $bdtPrice,
                    'total_fob_foreign' => $totalForeign,
                    'total_fob_bdt' => $totalBdt,
                ]);
            }

            $order->recalculateTotals();
        });

        return redirect()->route('admin.procurement.import-orders')
            ->with('success', 'Import purchase order created successfully.');
    }

    /**
     * Display domestic Bangladesh local purchase orders.
     */
    public function localPos(Request $request): Response
    {
        $orders = LocalPurchaseOrder::query()
            ->with(['supplier', 'items.variant.product'])
            ->latest('id')
            ->paginate(15);

        $suppliers = Supplier::local()->active()->get(['id', 'name', 'code', 'bin_number']);
        $products = Product::with(['variants'])->where('is_published', true)->get();

        return Inertia::render('Admin/Procurement/LocalPoIndex', [
            'orders' => $orders,
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created local purchase order with items.
     */
    public function storeLocalPo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'po_number' => ['required', 'string', 'unique:local_purchase_orders,po_number'],
            'order_date' => ['required', 'date'],
            'delivery_deadline' => ['nullable', 'date'],
            'mushak_6_3_invoice_number' => ['nullable', 'string'],
            'mushak_6_3_invoice_date' => ['nullable', 'date'],
            'vds_rate_percent' => ['nullable', 'numeric', 'min:0'],
            'tds_rate_percent' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_variant_id' => ['required', 'exists:product_variants,id'],
            'items.*.quantity_ordered' => ['required', 'integer', 'min:1'],
            'items.*.unit_price_bdt' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate_percent' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($validated): void {
            $po = LocalPurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'po_number' => $validated['po_number'],
                'order_date' => $validated['order_date'],
                'delivery_deadline' => $validated['delivery_deadline'] ?? null,
                'mushak_6_3_invoice_number' => $validated['mushak_6_3_invoice_number'] ?? null,
                'mushak_6_3_invoice_date' => $validated['mushak_6_3_invoice_date'] ?? null,
                'vds_rate_percent' => $validated['vds_rate_percent'] ?? 0.00,
                'tds_rate_percent' => $validated['tds_rate_percent'] ?? 0.00,
                'status' => 'issued',
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (int) $itemData['quantity_ordered'];
                $price = (string) $itemData['unit_price_bdt'];
                $vatRate = (string) ($itemData['vat_rate_percent'] ?? 15.00);

                $subtotal = bcmul($price, (string) $qty, 4);
                $vat = bcdiv(bcmul($subtotal, $vatRate, 6), '100', 4);
                $total = bcadd($subtotal, $vat, 4);

                LocalPoItem::create([
                    'local_purchase_order_id' => $po->id,
                    'product_id' => $itemData['product_id'],
                    'product_variant_id' => $itemData['product_variant_id'],
                    'quantity_ordered' => $qty,
                    'unit_price_bdt' => $price,
                    'subtotal_bdt' => $subtotal,
                    'vat_rate_percent' => $vatRate,
                    'vat_amount_bdt' => $vat,
                    'total_item_cost_bdt' => $total,
                ]);
            }

            $po->recalculateTotals();
        });

        return redirect()->route('admin.procurement.local-pos')
            ->with('success', 'Local purchase order issued successfully.');
    }

    /**
     * Issue statutory NBR Mushak 6.6 Certificate for local purchase order.
     */
    public function issueMushak66(LocalPurchaseOrder $po, IssueMushak66Action $action): RedirectResponse
    {
        $cert = $action($po);

        return redirect()->route('admin.procurement.local-pos')
            ->with('success', "Mushak 6.6 Certificate issued: {$cert['certificate_number']}");
    }

    /**
     * Display Goods Received Notes (GRN) and Inward Receiving view.
     */
    public function grnIndex(Request $request): Response
    {
        $grns = GoodsReceivedNote::query()
            ->with(['warehouse', 'receivedBy', 'items.variant.product'])
            ->latest('id')
            ->paginate(15);

        $warehouses = Warehouse::active()->get(['id', 'name', 'code']);
        $importOrders = ImportPurchaseOrder::active()->get(['id', 'po_number', 'status']);
        $localOrders = LocalPurchaseOrder::issued()->get(['id', 'po_number', 'status']);

        return Inertia::render('Admin/Procurement/GrnReceiving', [
            'grns' => $grns,
            'warehouses' => $warehouses,
            'importOrders' => $importOrders,
            'localOrders' => $localOrders,
        ]);
    }

    /**
     * Process and commit GRN inward stock receiving into physical warehouse inventory.
     */
    public function receiveGrn(GoodsReceivedNote $grn, ReceiveGoodsNoteAction $action): RedirectResponse
    {
        $processed = $action($grn);

        return redirect()->route('admin.procurement.grn')
            ->with('success', "GRN {$processed->grn_number} committed to inventory with status: {$processed->status}");
    }
}
