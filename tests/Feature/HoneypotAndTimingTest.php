<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Common\Exceptions\BotSubmissionDetectedException;
use App\Modules\Common\Security\HoneypotTimingGuardService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class HoneypotAndTimingTest extends TestCase
{
    private HoneypotTimingGuardService $guardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardService = new HoneypotTimingGuardService;

        Route::post('/test-protected-action', function () {
            return response()->json(['success' => true]);
        })->middleware('ensure.human');
    }

    public function test_timing_token_generation_and_validation(): void
    {
        $token = $this->guardService->generateTimingToken();
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Validating with 0s threshold immediately should be valid
        $result = $this->guardService->validateTiming(
            ['_form_timing_token' => $token],
            minimumSeconds: 0.0
        );

        $this->assertTrue($result['valid']);
        $this->assertGreaterThanOrEqual(0.0, $result['elapsed']);
    }

    public function test_timing_guard_rejects_submissions_under_threshold(): void
    {
        // Token rendered 1.0 second ago
        $token = $this->guardService->generateTimingToken(microtime(true) - 1.0);

        // Required threshold is 3.5s
        $result = $this->guardService->validateTiming(
            ['_form_timing_token' => $token],
            minimumSeconds: 3.5
        );

        $this->assertFalse($result['valid']);
        $this->assertLessThan(3.5, $result['elapsed']);
        $this->assertStringContainsString('faster than threshold', $result['error'] ?? '');
    }

    public function test_timing_guard_approves_submissions_meeting_threshold(): void
    {
        // Token rendered 4.5 seconds ago
        $token = $this->guardService->generateTimingToken(microtime(true) - 4.5);

        $result = $this->guardService->validateTiming(
            ['_form_timing_token' => $token],
            minimumSeconds: 3.5
        );

        $this->assertTrue($result['valid']);
        $this->assertGreaterThanOrEqual(4.0, $result['elapsed']);
    }

    public function test_honeypot_trap_detects_bot_when_field_is_filled(): void
    {
        $payload = [
            'user_company_fax' => 'automated_spammer_bot_12345',
        ];

        $isValid = $this->guardService->validateHoneypot($payload);
        $this->assertFalse($isValid);

        $inspection = $this->guardService->inspect($payload);
        $this->assertTrue($inspection['is_bot']);
        $this->assertStringContainsString('Honeypot trap triggered', $inspection['reason'] ?? '');
    }

    public function test_honeypot_passes_when_field_is_empty_or_null(): void
    {
        $this->assertTrue($this->guardService->validateHoneypot(['user_company_fax' => '']));
        $this->assertTrue($this->guardService->validateHoneypot(['user_company_fax' => null]));
        $this->assertTrue($this->guardService->validateHoneypot([]));
    }

    public function test_verify_or_fail_throws_exception_on_honeypot_trigger(): void
    {
        $this->expectException(BotSubmissionDetectedException::class);

        $this->guardService->verifyOrFail([
            'user_company_fax' => 'spambot',
        ]);
    }

    public function test_verify_or_fail_throws_exception_on_fast_submission(): void
    {
        $fastToken = $this->guardService->generateTimingToken(microtime(true) - 0.5);

        $this->expectException(BotSubmissionDetectedException::class);

        $this->guardService->verifyOrFail([
            'user_company_fax' => '',
            '_form_timing_token' => $fastToken,
        ], minimumSeconds: 3.5);
    }

    public function test_middleware_blocks_bot_honeypot_and_returns_422(): void
    {
        $validToken = $this->guardService->generateTimingToken(microtime(true) - 5.0);

        $response = $this->postJson('/test-protected-action', [
            'user_company_fax' => 'bot_entry',
            '_form_timing_token' => $validToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Automated bot activity detected.')
            ->assertJsonStructure(['errors' => ['bot_protection']]);
    }

    public function test_middleware_blocks_fast_submission_and_returns_422(): void
    {
        $fastToken = $this->guardService->generateTimingToken(microtime(true) - 0.8);

        $response = $this->postJson('/test-protected-action', [
            'user_company_fax' => '',
            '_form_timing_token' => $fastToken,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Automated bot activity detected.');
    }

    public function test_middleware_allows_human_submission(): void
    {
        $humanToken = $this->guardService->generateTimingToken(microtime(true) - 4.0);

        $response = $this->postJson('/test-protected-action', [
            'user_company_fax' => '',
            '_form_timing_token' => $humanToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
