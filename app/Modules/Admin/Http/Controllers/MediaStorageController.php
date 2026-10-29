<?php

declare(strict_types=1);

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Actions\TestStorageConnectionAction;
use App\Modules\Common\Actions\UpdateMediaStorageSettingsAction;
use App\Modules\Common\Models\MediaStorageSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaStorageController extends Controller
{
    /**
     * Display the dynamic storage settings overview.
     */
    public function index(): Response
    {
        $settings = MediaStorageSetting::query()
            ->latest()
            ->get()
            ->map(function (MediaStorageSetting $setting) {
                $creds = $setting->credentials ?? [];
                // Mask sensitive keys in UI
                if (isset($creds['secret'])) {
                    $creds['secret'] = '••••••••'.substr((string) $creds['secret'], -4);
                }

                return [
                    'id' => $setting->id,
                    'driver' => $setting->driver,
                    'name' => $setting->name,
                    'is_active' => $setting->is_active,
                    'credentials' => $creds,
                    'latency_ms' => $setting->latency_ms,
                    'last_tested_at' => $setting->last_tested_at?->diffForHumans(),
                    'created_at' => $setting->created_at?->format('Y-m-d H:i'),
                ];
            });

        return Inertia::render('Admin/Settings/MediaStorageSettings', [
            'settings' => $settings,
            'activeDriver' => config('filesystems.disks.media.driver', 'local'),
        ]);
    }

    /**
     * Pre-flight handshake test for storage credentials.
     */
    public function test(Request $request, TestStorageConnectionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'driver' => 'required|string|in:local,s3,r2,gcs,minio',
            'credentials' => 'nullable|array',
            'id' => 'nullable|string',
        ]);

        if (! empty($validated['id'])) {
            $setting = MediaStorageSetting::findOrFail($validated['id']);
            $result = $action($setting);
        } else {
            $result = $action([
                'driver' => $validated['driver'],
                'name' => 'Temporary Pre-Flight Test',
                'credentials' => $validated['credentials'] ?? [],
            ]);
        }

        return response()->json($result);
    }

    /**
     * Store or update media storage configuration.
     */
    public function store(Request $request, UpdateMediaStorageSettingsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|string|exists:media_storage_settings,id',
            'driver' => 'required|string|in:local,s3,r2,gcs,minio',
            'name' => 'required|string|max:128',
            'is_active' => 'boolean',
            'credentials' => 'nullable|array',
        ]);

        $setting = ! empty($validated['id'])
            ? MediaStorageSetting::findOrFail($validated['id'])
            : null;

        $action([
            'driver' => $validated['driver'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? false,
            'credentials' => $validated['credentials'] ?? [],
            'updated_by_user_id' => $request->user()?->id,
        ], $setting);

        return back()->with('success', 'Media storage configuration updated successfully.');
    }

    /**
     * Delete an inactive storage configuration.
     */
    public function destroy(MediaStorageSetting $setting): RedirectResponse
    {
        if ($setting->is_active) {
            return back()->with('error', 'Cannot delete the currently active primary storage disk.');
        }

        $setting->delete();

        return back()->with('success', 'Storage configuration removed.');
    }
}
