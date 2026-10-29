<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Models;

use App\Modules\Common\Models\BangladeshDistrict;
use App\Modules\Common\Models\BangladeshDivision;
use App\Modules\Common\Models\BangladeshThana;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property string $name
 * @property string|null $company_name_bn
 * @property string $type
 * @property string $country_code
 * @property string $currency
 * @property string|null $bin_number
 * @property string|null $tin_number
 * @property string|null $trade_license_number
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property int|null $division_id
 * @property int|null $district_id
 * @property int|null $thana_id
 * @property array|null $bank_details
 * @property string|null $payment_terms
 * @property bool $is_vds_applicable
 * @property bool $is_tds_applicable
 * @property bool $is_active
 * @property string|null $notes
 */
class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'ulid',
        'code',
        'name',
        'company_name_bn',
        'type',
        'country_code',
        'currency',
        'bin_number',
        'tin_number',
        'trade_license_number',
        'contact_person',
        'email',
        'phone',
        'address',
        'division_id',
        'district_id',
        'thana_id',
        'bank_details',
        'payment_terms',
        'is_vds_applicable',
        'is_tds_applicable',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'bank_details' => 'array',
            'is_vds_applicable' => 'boolean',
            'is_tds_applicable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            if (empty($supplier->ulid)) {
                $supplier->ulid = (string) Str::ulid();
            }
        });
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(BangladeshDivision::class, 'division_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(BangladeshDistrict::class, 'district_id');
    }

    public function thana(): BelongsTo
    {
        return $this->belongsTo(BangladeshThana::class, 'thana_id');
    }

    public function scopeInternational(Builder $query): Builder
    {
        return $query->where('type', 'international');
    }

    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('type', 'local');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
