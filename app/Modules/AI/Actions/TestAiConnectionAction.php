<?php

declare(strict_types=1);

namespace App\Modules\AI\Actions;

use App\Modules\AI\DTOs\AiConnectionTestResultDto;
use App\Modules\AI\Models\AiModelSetting;
use App\Modules\AI\Services\AiManager;
use InvalidArgumentException;

class TestAiConnectionAction
{
    public function __construct(
        protected AiManager $aiManager
    ) {}

    /**
     * Run an end-to-end connectivity test for the specified AI model setting or provider.
     */
    public function __invoke(AiModelSetting|string $target): AiConnectionTestResultDto
    {
        if (is_string($target)) {
            $setting = $this->aiManager->getSetting($target);
            if (! $setting) {
                throw new InvalidArgumentException("AI model setting for provider [{$target}] not found.");
            }
        } else {
            $setting = $target;
        }

        return $this->aiManager->testConnection($setting);
    }
}
