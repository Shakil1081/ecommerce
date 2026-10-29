<?php

declare(strict_types=1);

namespace App\Modules\Common\Actions;

use App\Modules\Common\Models\MediaStorageSetting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class UpdateMediaStorageSettingsAction
{
    /**
     * Update or create storage provider configuration and optionally activate it.
     *
     * @param array{
     *     driver: string,
     *     name: string,
     *     is_active?: bool,
     *     credentials?: array<string, mixed>,
     *     updated_by_user_id?: int|string|null,
     * } $data
     */
    public function __invoke(array $data, ?MediaStorageSetting $setting = null): MediaStorageSetting
    {
        return DB::transaction(function () use ($data, $setting) {
            $isActive = $data['is_active'] ?? false;

            if ($isActive) {
                // Only one storage disk can be the active primary media driver
                MediaStorageSetting::query()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }

            if ($setting) {
                $setting->update($data);
            } else {
                $setting = MediaStorageSetting::create($data);
            }

            // If active, dynamically rebind the primary 'media' filesystem disk
            if ($setting->is_active) {
                Config::set('filesystems.disks.media', $setting->toDiskConfig());
            }

            return $setting->fresh();
        });
    }
}
