<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Models\Media;
use App\Modules\Common\Services\MediaService;
use App\Modules\Common\Traits\HasStandardMediaConversions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class DummyMediaProduct extends Model implements HasMedia
{
    use HasStandardMediaConversions;

    protected $table = 'dummy_media_products';

    protected $guarded = [];
}

class MediaServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'media_test_'.uniqid();
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        // Create dummy test table
        $this->app['db']->connection()->getSchemaBuilder()->create('dummy_media_products', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function createSampleImage(string $path, int $width = 400, int $height = 400): string
    {
        $img = imagecreatetruecolor($width, $height);
        $blue = imagecolorallocate($img, 30, 144, 255);
        imagefill($img, 0, 0, $blue);
        imagejpeg($img, $path, 90);
        imagedestroy($img);

        return $path;
    }

    public function test_can_compute_blurhash_and_lqip(): void
    {
        $service = new MediaService;
        $imagePath = $this->tempDir.DIRECTORY_SEPARATOR.'sample.jpg';
        $this->createSampleImage($imagePath);

        $blurhash = $service->computeBlurhash($imagePath);
        $this->assertNotNull($blurhash);
        $this->assertIsString($blurhash);
        $this->assertGreaterThan(5, strlen($blurhash));

        $lqip = $service->computeLqip($imagePath);
        $this->assertNotNull($lqip);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $lqip);
    }

    public function test_can_attach_media_with_conversions_and_blurhash(): void
    {
        $service = new MediaService;
        $imagePath = $this->tempDir.DIRECTORY_SEPARATOR.'product.jpg';
        $this->createSampleImage($imagePath, 800, 800);

        $product = DummyMediaProduct::create(['name' => 'Imported Smart Watch']);

        /** @var Media $media */
        $media = $product->addMedia($imagePath)->preservingOriginal()->toMediaCollection('images', 'local');

        $this->assertInstanceOf(Media::class, $media);

        // Attach placeholder data
        $service->attachPlaceholderData($media);
        $media->refresh();

        $this->assertNotNull($media->blurhash);
        $this->assertNotNull($media->lqip);

        $payload = $media->toEnterpriseArray();
        $this->assertEquals($media->id, $payload['id']);
        $this->assertEquals($media->blurhash, $payload['blurhash']);
        $this->assertEquals($media->lqip, $payload['lqip']);
        $this->assertArrayHasKey('breakpoints', $payload);
    }

    public function test_can_apply_watermark_to_protect_import_photography(): void
    {
        $service = new MediaService;
        $sourcePath = $this->tempDir.DIRECTORY_SEPARATOR.'product.jpg';
        $watermarkPath = $this->tempDir.DIRECTORY_SEPARATOR.'watermark.png';
        $outputPath = $this->tempDir.DIRECTORY_SEPARATOR.'watermarked.jpg';

        $this->createSampleImage($sourcePath, 600, 600);

        // Create watermark
        $wm = imagecreatetruecolor(100, 50);
        $white = imagecolorallocate($wm, 255, 255, 255);
        imagefill($wm, 0, 0, $white);
        imagepng($wm, $watermarkPath);
        imagedestroy($wm);

        $result = $service->applyWatermark($sourcePath, $watermarkPath, $outputPath);

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
    }
}
