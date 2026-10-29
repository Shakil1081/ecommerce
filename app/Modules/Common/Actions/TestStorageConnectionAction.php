<?php

declare(strict_types=1);

namespace App\Modules\Common\Actions;

use App\Modules\Common\Models\MediaStorageSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class TestStorageConnectionAction
{
    /**
     * Test the storage connection by uploading, reading, and deleting a test file.
     *
     * @param  MediaStorageSetting|array<string, mixed>  $settingOrData
     * @return array{success: bool, latency_ms: int|null, message: string}
     */
    public function __invoke(MediaStorageSetting|array $settingOrData): array
    {
        $setting = $settingOrData instanceof MediaStorageSetting
            ? $settingOrData
            : new MediaStorageSetting($settingOrData);

        $testDiskName = 'temp_test_storage_'.Str::lower(Str::random(8));
        $diskConfig = $setting->toDiskConfig();

        Config::set("filesystems.disks.{$testDiskName}", $diskConfig);

        $testFileName = 'storage_test_'.Str::ulid().'.txt';
        $testContent = 'Enterprise E-Commerce Storage Handshake - '.now()->toIso8601String();

        try {
            $disk = Storage::disk($testDiskName);

            $startTime = hrtime(true);

            // 1. Write Test File
            $written = $disk->put($testFileName, $testContent);
            if (! $written) {
                return [
                    'success' => false,
                    'latency_ms' => null,
                    'message' => 'Failed to write test file to storage disk.',
                ];
            }

            // 2. Read Test File
            $retrievedContent = $disk->get($testFileName);
            if ($retrievedContent !== $testContent) {
                return [
                    'success' => false,
                    'latency_ms' => null,
                    'message' => 'Integrity mismatch: Retrieved content does not match uploaded content.',
                ];
            }

            // 3. Delete Test File
            $disk->delete($testFileName);

            $endTime = hrtime(true);
            $latencyMs = (int) round(($endTime - $startTime) / 1e6);

            // Update model statistics if persisted
            if ($setting->exists) {
                $setting->update([
                    'latency_ms' => $latencyMs,
                    'last_tested_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'latency_ms' => $latencyMs,
                'message' => "Storage connection verified successfully ({$latencyMs}ms latency).",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'latency_ms' => null,
                'message' => 'Storage handshake failed: '.$e->getMessage(),
            ];
        } finally {
            // Clean up temporary disk configuration
            Config::set("filesystems.disks.{$testDiskName}", null);
        }
    }
}
