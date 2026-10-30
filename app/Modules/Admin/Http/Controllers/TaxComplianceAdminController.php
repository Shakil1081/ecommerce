<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\TaxCompliance\Actions\GenerateMushak61RegisterAction;
use App\Modules\TaxCompliance\Actions\GenerateMushak62RegisterAction;
use App\Modules\TaxCompliance\Actions\GenerateMushak91ReturnAction;
use App\Modules\TaxCompliance\Actions\SubmitMushak91ReturnAction;
use App\Modules\TaxCompliance\Models\Mushak63Invoice;
use App\Modules\TaxCompliance\Models\Mushak65Challan;
use App\Modules\TaxCompliance\Models\Mushak66Certificate;
use App\Modules\TaxCompliance\Models\Mushak67CreditNote;
use App\Modules\TaxCompliance\Models\Mushak68DebitNote;
use App\Modules\TaxCompliance\Models\Mushak91Return;
use App\Modules\TaxCompliance\Models\ReturnMerchandiseAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxComplianceAdminController extends Controller
{
    /**
     * Display Tax Compliance overview dashboard.
     */
    public function dashboard(): Response
    {
        $currentPeriod = CarbonImmutable::now()->format('Y-m');

        $stats = [
            'current_period' => $currentPeriod,
            'mushak_6_3_count' => Mushak63Invoice::where('tax_period', $currentPeriod)->count(),
            'total_sales_vat_bdt' => (string) Mushak63Invoice::where('tax_period', $currentPeriod)->sum('vat_total_bdt'),
            'mushak_9_1_status' => Mushak91Return::where('tax_period', $currentPeriod)->value('status') ?? 'not_compiled',
            'mushak_6_6_vds_count' => Mushak66Certificate::where('tax_period', $currentPeriod)->count(),
            'total_vds_bdt' => (string) Mushak66Certificate::where('tax_period', $currentPeriod)->sum('vds_deducted_amount_bdt'),
            'mushak_6_5_transfers_count' => Mushak65Challan::where('tax_period', $currentPeriod)->count(),
            'rma_returns_count' => ReturnMerchandiseAuthorization::whereYear('created_at', date('Y'))->whereMonth('created_at', date('m'))->count(),
            'mushak_6_7_credit_notes_count' => Mushak67CreditNote::where('tax_period', $currentPeriod)->count(),
            'mushak_6_8_debit_notes_count' => Mushak68DebitNote::where('tax_period', $currentPeriod)->count(),
        ];

        $recentInvoices = Mushak63Invoice::with('order')
            ->orderByDesc('issue_date')
            ->take(5)
            ->get();

        $recentReturns = Mushak91Return::orderByDesc('tax_period')
            ->take(6)
            ->get();

        $recentRmas = ReturnMerchandiseAuthorization::with('order')
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        return Inertia::render('Admin/TaxCompliance/TaxComplianceDashboard', [
            'stats' => $stats,
            'recentInvoices' => $recentInvoices,
            'recentReturns' => $recentReturns,
            'recentRmas' => $recentRmas,
        ]);
    }

    /**
     * List issued Mushak 6.3 Tax Invoices.
     */
    public function mushak63Index(Request $request): Response
    {
        $query = Mushak63Invoice::with('order')->orderByDesc('issue_date');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tax_period')) {
            $query->where('tax_period', $request->input('tax_period'));
        }

        $invoices = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/TaxCompliance/Mushak63Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['search', 'tax_period']),
        ]);
    }

    /**
     * Display printable official Mushak 6.3 Tax Invoice.
     */
    public function mushak63Show(Mushak63Invoice $invoice): Response
    {
        $invoice->load(['items', 'order.items']);

        return Inertia::render('Admin/TaxCompliance/Mushak63Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * List monthly Mushak 9.1 VAT Returns.
     */
    public function mushak91Index(Request $request): Response
    {
        $returns = Mushak91Return::orderByDesc('tax_period')->paginate(12);
        $currentPeriod = CarbonImmutable::now()->format('Y-m');

        return Inertia::render('Admin/TaxCompliance/Mushak91Index', [
            'returns' => $returns,
            'currentPeriod' => $currentPeriod,
        ]);
    }

    /**
     * Compile or recalculate monthly Mushak 9.1 return.
     */
    public function mushak91Compile(
        Request $request,
        GenerateMushak91ReturnAction $compileAction
    ): RedirectResponse {
        $validated = $request->validate([
            'tax_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'force' => ['nullable', 'boolean'],
        ]);

        $compileAction($validated['tax_period'], [
            'force' => (bool) ($validated['force'] ?? false),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()->back()->with('success', "Mushak 9.1 return for {$validated['tax_period']} compiled successfully.");
    }

    /**
     * Officially submit a Mushak 9.1 VAT return.
     */
    public function mushak91Submit(
        Request $request,
        Mushak91Return $return,
        SubmitMushak91ReturnAction $submitAction
    ): RedirectResponse {
        $validated = $request->validate([
            'treasury_challan_number' => ['nullable', 'string', 'max:64'],
            'treasury_challan_date' => ['nullable', 'date'],
            'treasury_deposit_amount_bdt' => ['nullable', 'numeric', 'min:0'],
        ]);

        $submitAction($return, $validated);

        return redirect()->back()->with('success', "Mushak 9.1 return {$return->tax_period} submitted successfully.");
    }

    /**
     * List Mushak 6.6 VDS Certificates.
     */
    public function mushak66Index(Request $request): Response
    {
        $query = Mushak66Certificate::with(['supplier', 'localPurchaseOrder'])->orderByDesc('issue_date');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('supplier_bin', 'like', "%{$search}%");
            });
        }

        $certificates = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/TaxCompliance/Mushak66Index', [
            'certificates' => $certificates,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * List Customer Returns (RMA) and statutory Credit Notes (Mushak 6.7).
     */
    public function rmaIndex(Request $request): Response
    {
        $query = ReturnMerchandiseAuthorization::with(['order', 'items', 'creditNote'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $rmas = $query->paginate(15)->withQueryString();

        return Inertia::render('Admin/TaxCompliance/RmaIndex', [
            'rmas' => $rmas,
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Display NBR Statutory Registers (Mushak 6.1 Purchase & Mushak 6.2 Sales).
     */
    public function registers(
        Request $request,
        GenerateMushak61RegisterAction $register61Action,
        GenerateMushak62RegisterAction $register62Action
    ): Response {
        $taxPeriod = $request->input('tax_period', CarbonImmutable::now()->format('Y-m'));

        $register61 = $register61Action($taxPeriod);
        $register62 = $register62Action($taxPeriod);

        return Inertia::render('Admin/TaxCompliance/MushakRegisters', [
            'taxPeriod' => $taxPeriod,
            'register61' => $register61,
            'register62' => $register62,
        ]);
    }
}
