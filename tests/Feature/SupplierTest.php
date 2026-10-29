<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Models\BangladeshThana;
use App\Modules\Procurement\Actions\VerifySupplierBinAction;
use App\Modules\Procurement\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_international_supplier_with_fx_details(): void
    {
        $supplier = Supplier::create([
            'code' => 'SUP-INT-001',
            'name' => 'Shenzhen Global Electronics Ltd',
            'type' => 'international',
            'country_code' => 'CN',
            'currency' => 'USD',
            'contact_person' => 'Chen Wei',
            'email' => 'sales@shenzhen-global.cn',
            'phone' => '+86 755 12345678',
            'bank_details' => [
                'bank_name' => 'Bank of China',
                'swift_bic' => 'BKCHCNBJ',
                'account_number' => '1029384756',
                'beneficiary' => 'Shenzhen Global Electronics Ltd',
            ],
            'payment_terms' => '30% Advance TT + 70% against BL copy',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'code' => 'SUP-INT-001',
            'type' => 'international',
            'country_code' => 'CN',
            'currency' => 'USD',
        ]);

        $this->assertNotEmpty($supplier->ulid);
        $this->assertEquals('Bank of China', $supplier->bank_details['bank_name']);
        $this->assertEquals(1, Supplier::international()->count());
        $this->assertEquals(0, Supplier::local()->count());
    }

    public function test_can_create_local_supplier_with_bangladesh_geo_and_tax_credentials(): void
    {
        $division = BangladeshDivision::create([
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
        ]);

        $district = BangladeshDistrict::create([
            'division_id' => $division->id,
            'name_en' => 'Dhaka',
            'name_bn' => 'ঢাকা',
            'zone_type' => BangladeshDistrict::ZONE_INSIDE_DHAKA,
        ]);

        $thana = BangladeshThana::create([
            'district_id' => $district->id,
            'name_en' => 'Tejgaon',
            'name_bn' => 'তেজগাঁও',
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-LOC-001',
            'name' => 'Padma Trading Distribution Ltd',
            'company_name_bn' => 'পদ্মা ট্রেডিং ডিস্ট্রিবিউশন লিঃ',
            'type' => 'local',
            'country_code' => 'BD',
            'currency' => 'BDT',
            'bin_number' => '001234567-0101',
            'tin_number' => '123456789012',
            'trade_license_number' => 'TRAD/DNCC/012934/2024',
            'contact_person' => 'Md. Rafiqul Islam',
            'email' => 'rafiq@padmatrading.com.bd',
            'phone' => '+8801711000000',
            'address' => 'Plot 45, Tejgaon I/A, Dhaka-1208',
            'division_id' => $division->id,
            'district_id' => $district->id,
            'thana_id' => $thana->id,
            'bank_details' => [
                'bank_name' => 'Eastern Bank PLC',
                'branch_name' => 'Tejgaon Branch',
                'routing_number' => '095264178',
                'account_number' => '1041060000123',
                'account_name' => 'Padma Trading Distribution Ltd',
            ],
            'payment_terms' => 'Cash on Delivery / Mushak 6.3 within 7 days',
            'is_vds_applicable' => true,
            'is_tds_applicable' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'code' => 'SUP-LOC-001',
            'type' => 'local',
            'bin_number' => '001234567-0101',
            'tin_number' => '123456789012',
            'is_vds_applicable' => true,
        ]);

        $this->assertEquals('Tejgaon', $supplier->thana->name_en);
        $this->assertEquals('Dhaka', $supplier->district->name_en);
        $this->assertEquals(1, Supplier::local()->count());
    }

    public function test_verify_supplier_bin_action_validates_13_digit_and_9_digit_nbr_bin(): void
    {
        $verifier = new VerifySupplierBinAction;

        // 13-digit format with hyphen
        $result1 = $verifier('001234567-0101');
        $this->assertTrue($result1['is_valid']);
        $this->assertEquals('13_digit_online', $result1['format']);
        $this->assertEquals('0012345670101', $result1['normalized_bin']);
        $this->assertNull($result1['error']);

        // 9-digit legacy format
        $result2 = $verifier('123456789');
        $this->assertTrue($result2['is_valid']);
        $this->assertEquals('9_digit_legacy', $result2['format']);
        $this->assertEquals('123456789', $result2['normalized_bin']);
        $this->assertNull($result2['error']);

        // Invalid format (less than 9 digits)
        $result3 = $verifier('12345');
        $this->assertFalse($result3['is_valid']);
        $this->assertEquals('invalid', $result3['format']);
        $this->assertNotNull($result3['error']);

        // Null/empty input
        $result4 = $verifier(null);
        $this->assertFalse($result4['is_valid']);
        $this->assertNull($result4['format']);
    }

    public function test_verify_supplier_tin_validates_12_digit_taxpayer_number(): void
    {
        $verifier = new VerifySupplierBinAction;

        // Valid 12-digit e-TIN
        $tinResult = $verifier->verifyTin('1234-5678-9012');
        $this->assertTrue($tinResult['is_valid']);
        $this->assertEquals('123456789012', $tinResult['normalized_tin']);

        // Invalid length e-TIN
        $invalidTin = $verifier->verifyTin('1234567');
        $this->assertFalse($invalidTin['is_valid']);
        $this->assertNotNull($invalidTin['error']);
    }
}
