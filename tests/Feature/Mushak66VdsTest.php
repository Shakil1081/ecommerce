<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Procurement\Models\LocalPurchaseOrder;
use App\Modules\Procurement\Models\Supplier;
use App\Modules\TaxCompliance\Actions\GenerateMushak66CertificateAction;
use App\Modules\TaxCompliance\Models\Mushak66Certificate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class Mushak66VdsTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $validSupplier;

    private LocalPurchaseOrder $validPo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validSupplier = Supplier::create([
            'code' => 'SUP-PRAN-001',
            'name' => 'PRAN Agro Business Ltd.',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001928374-0101',
            'address' => 'PRAN-RFL Center, Middle Badda, Dhaka-1212',
            'is_active' => true,
        ]);

        $this->validPo = LocalPurchaseOrder::create([
            'po_number' => 'PO-LOC-202610-099',
            'supplier_id' => $this->validSupplier->id,
            'order_date' => '2026-10-20',
            'status' => 'received',
            'mushak_6_3_invoice_number' => 'M6.3-PRAN-88912',
            'mushak_6_3_invoice_date' => '2026-10-20',
            'subtotal_bdt' => '100000.0000',
            'vat_amount_bdt' => '15000.0000',
            'vds_rate_percent' => '5.00',
            'vds_amount_bdt' => '5000.0000',
            'tds_rate_percent' => '3.00',
            'tds_amount_bdt' => '3000.0000',
            'total_payable_to_supplier_bdt' => '107000.0000',
        ]);
    }

    public function test_generates_statutory_mushak_6_6_certificate_with_atomic_sequence(): void
    {
        $action = app(GenerateMushak66CertificateAction::class);
        $issueDate = CarbonImmutable::parse('2026-10-22 14:00:00');

        $result = $action($this->validPo, [
            'issue_date' => $issueDate,
        ]);

        $this->assertFalse($result->isExisting);
        $this->assertSame('M6.6-202610-000001', $result->certificateNumber);
        $this->assertSame('PRAN Agro Business Ltd.', $result->supplierName);
        $this->assertSame('0019283740101', $result->supplierBin);

        $cert = $result->certificate;
        $this->assertInstanceOf(Mushak66Certificate::class, $cert);
        $this->assertSame($this->validPo->id, $cert->local_purchase_order_id);
        $this->assertSame('M6.3-PRAN-88912', $cert->supplier_invoice_number);
        $this->assertEquals(100000.00, (float) $cert->base_taxable_value_bdt);
        $this->assertEquals(15000.00, (float) $cert->vat_amount_bdt);
        $this->assertEquals(5000.00, (float) $cert->vds_deducted_amount_bdt);
        $this->assertEquals(107000.00, (float) $cert->net_paid_to_supplier_bdt);
        $this->assertSame('issued', $cert->status);
        $this->assertNotEmpty($cert->integrity_hash);

        // Parent PO relationship and fields updated
        $this->validPo->refresh();
        $this->assertSame('M6.6-202610-000001', $this->validPo->mushak_6_6_certificate_number);
        $this->assertNotNull($this->validPo->mushak66Certificate);
        $this->assertSame($cert->id, $this->validPo->mushak66Certificate->id);
    }

    public function test_generation_is_idempotent(): void
    {
        $action = app(GenerateMushak66CertificateAction::class);

        $res1 = $action($this->validPo);
        $res2 = $action($this->validPo);

        $this->assertFalse($res1->isExisting);
        $this->assertTrue($res2->isExisting);
        $this->assertSame($res1->certificateNumber, $res2->certificateNumber);
        $this->assertSame(1, Mushak66Certificate::where('local_purchase_order_id', $this->validPo->id)->count());
    }

    public function test_fails_if_supplier_bin_is_invalid(): void
    {
        $invalidSupplier = Supplier::create([
            'code' => 'SUP-BAD-BIN',
            'name' => 'Bogus Vendor',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '12345', // invalid length
            'is_active' => true,
        ]);

        $po = LocalPurchaseOrder::create([
            'po_number' => 'PO-LOC-BAD-01',
            'supplier_id' => $invalidSupplier->id,
            'order_date' => '2026-10-21',
            'status' => 'received',
            'mushak_6_3_invoice_number' => 'M6.3-INV-001',
            'subtotal_bdt' => '10000.0000',
            'vat_amount_bdt' => '1500.0000',
            'vds_rate_percent' => '5.00',
            'vds_amount_bdt' => '500.0000',
            'total_payable_to_supplier_bdt' => '11000.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supplier does not possess a valid 9 or 13-digit NBR BIN');

        app(GenerateMushak66CertificateAction::class)($po);
    }

    public function test_fails_if_supplier_mushak_6_3_invoice_number_is_missing(): void
    {
        $po = LocalPurchaseOrder::create([
            'po_number' => 'PO-LOC-NO-INV',
            'supplier_id' => $this->validSupplier->id,
            'order_date' => '2026-10-21',
            'status' => 'received',
            'mushak_6_3_invoice_number' => null, // missing
            'subtotal_bdt' => '10000.0000',
            'vat_amount_bdt' => '1500.0000',
            'vds_rate_percent' => '5.00',
            'vds_amount_bdt' => '500.0000',
            'total_payable_to_supplier_bdt' => '11000.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Supplier Mushak 6.3 Tax Invoice number is required');

        app(GenerateMushak66CertificateAction::class)($po);
    }

    public function test_fails_if_vds_amount_is_zero(): void
    {
        $po = LocalPurchaseOrder::create([
            'po_number' => 'PO-LOC-ZERO-VDS',
            'supplier_id' => $this->validSupplier->id,
            'order_date' => '2026-10-21',
            'status' => 'received',
            'mushak_6_3_invoice_number' => 'M6.3-INV-999',
            'subtotal_bdt' => '10000.0000',
            'vat_amount_bdt' => '1500.0000',
            'vds_rate_percent' => '0.00',
            'vds_amount_bdt' => '0.0000',
            'total_payable_to_supplier_bdt' => '11500.0000',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('VDS deduction amount must be greater than zero');

        app(GenerateMushak66CertificateAction::class)($po);
    }

    public function test_supports_treasury_deposit_tracking(): void
    {
        $action = app(GenerateMushak66CertificateAction::class);

        $result = $action($this->validPo, [
            'treasury_challan_number' => 'TR-2026-CHAL-8821',
            'treasury_challan_date' => '2026-10-25',
            'treasury_bank_name' => 'Sonali Bank PLC',
            'treasury_branch_name' => 'Dhaka Local Office',
        ]);

        $cert = $result->certificate;
        $this->assertTrue($cert->isDeposited());
        $this->assertSame('deposited', $cert->status);
        $this->assertSame('TR-2026-CHAL-8821', $cert->treasury_challan_number);
    }
}
