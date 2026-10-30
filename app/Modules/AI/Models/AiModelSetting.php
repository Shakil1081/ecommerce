<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AiModelSetting extends Model
{
    use HasFactory;

    public const PROVIDER_GEMINI = 'gemini';

    public const PROVIDER_OPENAI = 'openai';

    public const PROVIDER_CLAUDE = 'claude';

    public const PROVIDER_OLLAMA = 'ollama';

    public const FEATURE_CATALOG_ENRICHMENT = 'catalog_enrichment';

    public const FEATURE_SMART_RESTOCKING = 'smart_restocking';

    public const FEATURE_CUSTOMER_SUPPORT = 'customer_support';

    public const FEATURE_TAX_ANOMALY = 'tax_anomaly';

    protected $table = 'ai_model_settings';

    protected $fillable = [
        'ulid',
        'provider',
        'name',
        'is_active',
        'credentials',
        'default_model',
        'temperature',
        'max_tokens',
        'assigned_features',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'encrypted:array',
        'assigned_features' => 'array',
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'provider', 'provider');
    }

    /**
     * Determine if this model provider is assigned to handle a specific operational feature.
     */
    public function handlesFeature(string $feature): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $features = $this->assigned_features ?? [];

        return in_array($feature, $features, true) || in_array('*', $features, true);
    }

    /**
     * Get masked credentials for safe display in UI/API responses.
     *
     * @return array<string, mixed>
     */
    public function getMaskedCredentialsAttribute(): array
    {
        $creds = $this->credentials ?? [];
        $masked = [];

        foreach ($creds as $key => $val) {
            if (! is_string($val) || empty($val)) {
                $masked[$key] = $val;

                continue;
            }

            if (str_contains(strtolower($key), 'key') || str_contains(strtolower($key), 'secret') || str_contains(strtolower($key), 'token')) {
                $len = strlen($val);
                if ($len <= 8) {
                    $masked[$key] = '••••••••';
                } else {
                    $masked[$key] = '••••••••'.substr($val, -4);
                }
            } else {
                $masked[$key] = $val;
            }
        }

        return $masked;
    }
}
