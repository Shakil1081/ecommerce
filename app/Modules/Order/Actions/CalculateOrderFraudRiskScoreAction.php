<?php

declare(strict_types=1);

namespace App\Modules\Order\Actions;

use App\Modules\Common\Security\HoneypotTimingGuardService;
use App\Modules\Customer\Actions\CheckCustomerFraudListAction;
use App\Modules\Customer\Rules\BangladeshPhoneNumberRule;
use App\Modules\Customer\Services\TextEntropyService;
use App\Modules\Logistics\Services\AggregatedCourierIntelligenceService;
use App\Modules\Order\DTOs\OrderFraudRiskAssessmentDto;
use App\Modules\Order\Models\Order;

final class CalculateOrderFraudRiskScoreAction
{
    public function __construct(
        private readonly HoneypotTimingGuardService $honeypotGuard,
        private readonly TextEntropyService $textEntropyService,
        private readonly CheckOrderVelocityAction $checkOrderVelocity,
        private readonly AggregatedCourierIntelligenceService $courierIntelligence,
        private readonly CheckCustomerFraudListAction $checkFraudList
    ) {}

    /**
     * Compute a comprehensive 0-100 fraud risk score across all 7 defense layers.
     *
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_email?: ?string,
     *     delivery_address: string,
     *     payment_method?: string,
     *     ip_address?: ?string,
     *     device_fingerprint?: ?string,
     *     honeypot_field?: ?string,
     *     honeypot_value?: ?string,
     *     user_company_fax?: ?string,
     *     timing_token?: ?string,
     *     _form_timing_token?: ?string,
     *     skip_courier_lookup?: bool
     * }  $orderData
     */
    public function __invoke(array $orderData): OrderFraudRiskAssessmentDto
    {
        $score = 0;
        $signals = [];
        $reasons = [];
        $isCourierHighRisk = false;

        // 0. Layer 0: Merchant Directory (Whitelist / Blacklist)
        $directoryCheck = ($this->checkFraudList)(
            phone: $orderData['customer_phone'] ?? null,
            ipAddress: $orderData['ip_address'] ?? null,
            deviceFingerprint: $orderData['device_fingerprint'] ?? null
        );

        if ($directoryCheck->isWhitelisted) {
            return new OrderFraudRiskAssessmentDto(
                score: 0,
                riskLevel: OrderFraudRiskAssessmentDto::LEVEL_LOW,
                isCodAllowed: true,
                requiresOtp: false,
                requiresAdvanceDeliveryCharge: false,
                signals: [
                    'merchant_whitelist' => [
                        'entry_id' => $directoryCheck->matchedWhitelistEntry?->id,
                        'reason' => $directoryCheck->reason,
                    ],
                ],
                reasons: []
            );
        }

        if ($directoryCheck->isBlacklisted) {
            return new OrderFraudRiskAssessmentDto(
                score: 100,
                riskLevel: OrderFraudRiskAssessmentDto::LEVEL_BLACKLISTED,
                isCodAllowed: false,
                requiresOtp: false,
                requiresAdvanceDeliveryCharge: false,
                signals: [
                    'merchant_blacklist' => [
                        'entry_id' => $directoryCheck->matchedBlacklistEntry?->id,
                        'reason' => $directoryCheck->reason,
                        'points' => 100,
                    ],
                ],
                reasons: [
                    $directoryCheck->reason ? "Merchant blacklist: {$directoryCheck->reason}" : 'Customer identifier is blacklisted by merchant.',
                ]
            );
        }

        // 1. Layer 1: Invisible Honeypot Trap
        $honeypotKey = $orderData['honeypot_field'] ?? HoneypotTimingGuardService::DEFAULT_HONEYPOT_FIELD;
        $honeypotValue = $orderData['honeypot_value'] ?? $orderData[$honeypotKey] ?? null;

        if (! empty($honeypotValue)) {
            $score += 100;
            $signals['honeypot'] = [
                'triggered' => true,
                'field' => $honeypotKey,
                'points' => 100,
            ];
            $reasons[] = 'Automated bot submission detected via invisible honeypot trap.';
        }

        // 2. Layer 2: Client Submission Timing Guard
        $timingToken = $orderData['timing_token'] ?? $orderData['_form_timing_token'] ?? null;
        if (! empty($timingToken)) {
            $timingResult = $this->honeypotGuard->validateTiming([
                HoneypotTimingGuardService::DEFAULT_TIMING_FIELD => $timingToken,
            ]);

            if (! $timingResult['valid']) {
                $score += 50;
                $signals['submission_timing'] = [
                    'triggered' => true,
                    'elapsed' => $timingResult['elapsed'],
                    'points' => 50,
                ];
                $reasons[] = "Submission completed abnormally fast ({$timingResult['elapsed']}s), indicative of scripted automation.";
            } else {
                $signals['submission_timing'] = [
                    'triggered' => false,
                    'elapsed' => $timingResult['elapsed'],
                    'points' => 0,
                ];
            }
        }

        // 3. Layer 3: Bangladesh Phone Number Integrity & Dummy Check
        $phone = $orderData['customer_phone'];
        $cleanedPhone = BangladeshPhoneNumberRule::cleanNumber($phone);
        $isValidFormat = BangladeshPhoneNumberRule::isValidFormat($cleanedPhone);

        if (! $isValidFormat) {
            $score += 40;
            $signals['phone_format'] = [
                'valid' => false,
                'points' => 40,
            ];
            $reasons[] = "Customer phone number [{$phone}] is not a valid Bangladesh mobile operator format.";
        } else {
            $localPhone = BangladeshPhoneNumberRule::toLocalFormat($cleanedPhone);
            $isDummy = BangladeshPhoneNumberRule::isDummyOrRepeatingNumber($localPhone);

            if ($isDummy) {
                $score += 50;
                $signals['phone_dummy'] = [
                    'triggered' => true,
                    'points' => 50,
                ];
                $reasons[] = 'Customer phone number is a known dummy, sequential, or repetitive test pattern.';
            } else {
                $signals['phone_integrity'] = [
                    'valid' => true,
                    'operator' => BangladeshPhoneNumberRule::getOperator($localPhone),
                    'points' => 0,
                ];
            }
        }

        // 4. Layer 4: Text Entropy & Gibberish Scoring (Name & Delivery Address)
        $name = $orderData['customer_name'] ?? '';
        $nameEval = $this->textEntropyService->evaluate($name);
        if ($nameEval['is_gibberish']) {
            $score += 25;
            $signals['name_entropy'] = [
                'is_gibberish' => true,
                'score' => $nameEval['gibberish_score'],
                'points' => 25,
            ];
            $reasons[] = "Customer name appears to be gibberish or keyboard spam: '{$name}'.";
        }

        $address = $orderData['delivery_address'] ?? '';
        $addressEval = $this->textEntropyService->evaluate($address);
        if ($addressEval['is_gibberish']) {
            $score += 30;
            $signals['address_entropy'] = [
                'is_gibberish' => true,
                'score' => $addressEval['gibberish_score'],
                'points' => 30,
            ];
            $reasons[] = "Delivery address appears nonsensical or incomplete: '{$address}'.";
        }

        // 5. Layer 5: Order Velocity Throttling Guard
        $velocity = ($this->checkOrderVelocity)(
            phone: $phone,
            ipAddress: $orderData['ip_address'] ?? null,
            deviceFingerprint: $orderData['device_fingerprint'] ?? null,
            paymentMethod: $orderData['payment_method'] ?? 'cash_on_delivery'
        );

        if (! $velocity->isAllowed) {
            if (in_array('active_cod_limit_exceeded', $velocity->violations, true)) {
                $score += 35;
                $signals['active_cod_velocity'] = [
                    'active_orders' => $velocity->activeCodOrdersCount,
                    'points' => 35,
                ];
                $reasons[] = $velocity->primaryReason ?? 'Customer has existing active unfulfilled Cash on Delivery orders.';
            }

            if (in_array('ip_velocity_exceeded', $velocity->violations, true) ||
                in_array('device_velocity_exceeded', $velocity->violations, true)) {
                $score += 35;
                $signals['burst_velocity'] = [
                    'daily_ip_orders' => $velocity->dailyOrdersFromIpCount,
                    'daily_device_orders' => $velocity->dailyOrdersFromDeviceCount,
                    'points' => 35,
                ];
                $reasons[] = 'Order velocity burst limit exceeded for IP address or device fingerprint.';
            }
        }

        // 6. Layer 6: Cross-Courier Delivery Intelligence
        $skipCourier = $orderData['skip_courier_lookup'] ?? false;
        if (! $skipCourier && $isValidFormat) {
            $courierProfile = $this->courierIntelligence->getAggregatedProfile($phone);

            $signals['courier_intelligence'] = [
                'total_parcels' => $courierProfile->totalParcels,
                'return_rate_percent' => $courierProfile->returnRatePercent,
                'is_high_risk' => $courierProfile->isHighRisk,
                'risk_tier' => $courierProfile->riskTier,
            ];

            if ($courierProfile->isHighRisk) {
                $score += 45;
                $isCourierHighRisk = true;
                $reasons[] = "Customer courier return rate is critically high ({$courierProfile->returnRatePercent}% on {$courierProfile->totalParcels} parcels across national networks).";
            } elseif ($courierProfile->returnRatePercent >= 20.0 && $courierProfile->totalParcels >= 2) {
                $score += 20;
                $reasons[] = "Customer has a moderate courier return rate ({$courierProfile->returnRatePercent}%).";
            }
        }

        // Cap score at 100
        $finalScore = min(100, $score);
        $riskLevel = OrderFraudRiskAssessmentDto::determineRiskLevel($finalScore, $isCourierHighRisk);

        $isCodAllowed = ! in_array($riskLevel, [
            OrderFraudRiskAssessmentDto::LEVEL_HIGH,
            OrderFraudRiskAssessmentDto::LEVEL_BLACKLISTED,
        ], true);

        $requiresOtp = in_array($riskLevel, [
            OrderFraudRiskAssessmentDto::LEVEL_MEDIUM,
            OrderFraudRiskAssessmentDto::LEVEL_HIGH,
        ], true);

        $requiresAdvanceDeliveryCharge = $riskLevel === OrderFraudRiskAssessmentDto::LEVEL_HIGH;

        return new OrderFraudRiskAssessmentDto(
            score: $finalScore,
            riskLevel: $riskLevel,
            isCodAllowed: $isCodAllowed,
            requiresOtp: $requiresOtp,
            requiresAdvanceDeliveryCharge: $requiresAdvanceDeliveryCharge,
            signals: $signals,
            reasons: $reasons
        );
    }

    /**
     * Compute fraud risk score for an existing Order model.
     */
    public function assessOrder(Order $order, bool $skipCourierLookup = false): OrderFraudRiskAssessmentDto
    {
        return $this->__invoke([
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'delivery_address' => $order->delivery_address,
            'payment_method' => $order->payment_method,
            'ip_address' => $order->ip_address,
            'device_fingerprint' => $order->device_fingerprint,
            'skip_courier_lookup' => $skipCourierLookup,
        ]);
    }
}
