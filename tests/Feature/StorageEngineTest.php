<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Actions\TestStorageConnectionAction;
use App\Modules\Common\Actions\UpdateMediaStorageSettingsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageEngineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test pre-flight test connection handshake on local storage.
     */
    public function test_can_test_local_storage_connection_handshake(): void
    {
        $action = new TestStorageConnectionAction;

        $result = $action([
            'driver' => 'local',
            'name' => 'Local Server Storage',
        ]);

        $this->assertTrue($result['success']);
        $this->assertIsInt($result['latency_ms']);
        $this->assertStringContainsString('verified successfully', $result['message']);
    }

    /**
     * Test updating and activating storage settings with encryption.
     */
    public function test_can_persist_and_activate_storage_setting_with_encryption(): void
    {
        $updateAction = new UpdateMediaStorageSettingsAction;

        // 1. Create first active setting (Cloudflare R2)
        $r2Setting = $updateAction([
            'driver' => 'r2',
            'name' => 'Cloudflare R2 Primary',
            'is_active' => true,
            'credentials' => [
                'account_id' => 'cf_acc_123456',
                'key' => 'r2_key_abcdef',
                'secret' => 'super_secret_r2_key',
                'bucket' => 'catalog-assets',
            ],
        ]);

        $this->assertTrue($r2Setting->is_active);
        $this->assertEquals('Cloudflare R2 Primary', $r2Setting->name);

        // Verify credentials in DB are encrypted at rest
        $rawDbRecord = DB::table('media_storage_settings')->where('id', $r2Setting->id)->first();
        $this->assertNotNull($rawDbRecord);
        $this->assertStringNotContainsString('super_secret_r2_key', (string) $rawDbRecord->credentials);

        // 2. Create second active setting (AWS S3) and verify previous is deactivated
        $s3Setting = $updateAction([
            'driver' => 's3',
            'name' => 'AWS S3 Backup',
            'is_active' => true,
            'credentials' => [
                'key' => 'aws_key_xyz',
                'secret' => 'aws_secret_key',
                'bucket' => 'backup-bucket',
                'region' => 'ap-southeast-1',
            ],
        ]);

        $this->assertTrue($s3Setting->is_active);
        $this->assertFalse($r2Setting->fresh()->is_active);
    }

    /**
     * Test media migration console command with empty disk.
     */
    public function test_media_migrate_disk_command_handles_empty_source(): void
    {
        Storage::fake('empty_source');
        Storage::fake('empty_target');

        $this->artisan('media:migrate-disk --from=empty_source --to=empty_target')
            ->expectsOutputToContain('No media files found on source disk')
            ->assertExitCode(0);
    }

    /**
     * Test media migration console command transfers files successfully.
     */
    public function test_media_migrate_disk_command_migrates_files_successfully(): void
    {
        Storage::fake('source_disk');
        Storage::fake('target_disk');

        Storage::disk('source_disk')->put('product_banner.webp', 'test_binary_data');

        $this->artisan('media:migrate-disk --from=source_disk --to=target_disk')
            ->expectsOutputToContain('Successfully copied: 1 files')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('target_disk')->exists('product_banner.webp'));
        $this->assertEquals('test_binary_data', Storage::disk('target_disk')->get('product_banner.webp'));
    }

    /**
     * Test admin media storage settings endpoint.
     */
    public function test_admin_can_access_media_storage_settings_page(): void
    {
        $response = $this->get('/admin/settings/media-storage');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/MediaStorageSettings')
            ->has('settings')
            ->has('activeDriver')
        );
    }
}
