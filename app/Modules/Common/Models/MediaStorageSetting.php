<?php

declare(strict_types=1);

namespace App\Modules\Common\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaStorageSetting extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'driver',
        'name',
        'is_active',
        'credentials',
        'latency_ms',
        'last_tested_at',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'credentials' => 'encrypted:array',
        'latency_ms' => 'integer',
        'last_tested_at' => 'datetime',
    ];

    /**
     * Scope query to only active storage provider.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Relationship to the user who last updated this configuration.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Convert storage settings to Laravel filesystem disk configuration array.
     */
    public function toDiskConfig(): array
    {
        $creds = $this->credentials ?? [];

        return match ($this->driver) {
            's3' => [
                'driver' => 's3',
                'key' => $creds['key'] ?? '',
                'secret' => $creds['secret'] ?? '',
                'region' => $creds['region'] ?? 'us-east-1',
                'bucket' => $creds['bucket'] ?? '',
                'url' => $creds['cdn_url'] ?? null,
                'endpoint' => $creds['endpoint'] ?? null,
                'use_path_style_endpoint' => false,
                'throw' => true,
            ],
            'r2' => [
                'driver' => 's3',
                'key' => $creds['key'] ?? '',
                'secret' => $creds['secret'] ?? '',
                'region' => 'auto',
                'bucket' => $creds['bucket'] ?? '',
                'url' => $creds['cdn_url'] ?? null,
                'endpoint' => sprintf('https://%s.r2.cloudflarestorage.com', $creds['account_id'] ?? ''),
                'use_path_style_endpoint' => false,
                'throw' => true,
            ],
            'minio' => [
                'driver' => 's3',
                'key' => $creds['key'] ?? '',
                'secret' => $creds['secret'] ?? '',
                'region' => $creds['region'] ?? 'us-east-1',
                'bucket' => $creds['bucket'] ?? '',
                'url' => $creds['cdn_url'] ?? null,
                'endpoint' => $creds['endpoint'] ?? 'http://127.0.0.1:9000',
                'use_path_style_endpoint' => true,
                'throw' => true,
            ],
            default => [
                'driver' => 'local',
                'root' => storage_path('app/public/media'),
                'url' => config('app.url').'/storage/media',
                'visibility' => 'public',
                'throw' => false,
            ],
        };
    }
}
