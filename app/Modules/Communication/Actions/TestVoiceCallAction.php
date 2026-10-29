<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\DTOs\VoiceCallTestResultDto;
use App\Modules\Communication\Services\VoiceCallingManager;

class TestVoiceCallAction
{
    public function __construct(
        private readonly VoiceCallingManager $manager,
    ) {}

    /**
     * Perform pre-flight connection test and optional live verification test call.
     *
     * @param  array<string, mixed>|null  $overrideCredentials
     */
    public function __invoke(
        string $testPhoneNumber,
        ?string $provider = null,
        ?array $overrideCredentials = null,
    ): VoiceCallTestResultDto {
        $driver = $this->manager->resolve($provider, $overrideCredentials);

        return $driver->testConnection($testPhoneNumber);
    }
}
