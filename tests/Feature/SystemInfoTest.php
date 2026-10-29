<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Admin\Services\ChangelogParserService;
use Tests\TestCase;

class SystemInfoTest extends TestCase
{
    public function test_changelog_parser_extracts_structured_releases(): void
    {
        $parser = new ChangelogParserService;
        $releases = $parser->parse(base_path('CHANGELOG.md'));

        $this->assertIsArray($releases);
        $this->assertNotEmpty($releases);

        $first = $releases[0];
        $this->assertArrayHasKey('version', $first);
        $this->assertArrayHasKey('is_unreleased', $first);
        $this->assertArrayHasKey('sections', $first);
        $this->assertNotEmpty($first['sections']);
    }

    public function test_admin_can_access_system_updates_and_diagnostics_page(): void
    {
        $response = $this->get(route('admin.settings.system'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/SystemUpdates')
            ->has('systemInfo')
            ->has('systemInfo.appName')
            ->has('systemInfo.phpVersion')
            ->has('systemInfo.laravelVersion')
            ->has('systemInfo.environment')
            ->has('systemInfo.activeStorageDriver')
            ->has('releases')
        );
    }
}
