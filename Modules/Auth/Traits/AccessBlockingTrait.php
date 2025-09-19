<?php

namespace Modules\Auth\Traits;

use App\Helpers\LogHelper;
use Modules\Auth\Entities\UserAccessBlock;

trait AccessBlockingTrait
{
    protected int $attempts = 3;
    protected ?string $blockedAt = null;
    protected ?string $unBlockedAt = null;
    protected bool $isBlocked = false;

    public function hasAlreadyBlocked(string $identity): bool
    {
        return UserAccessBlock::where('unblocked_at', '>=', now()->toDateTimeString())
                ->where('identity', $identity)
                ->where('is_blocked', true)
                ->count() >= 1;
    }

    public function failedAttempt($request, string $identity, string $type = 'LOGIN_ATTEMPT')
    {
        try {
            $accessBlock = UserAccessBlock::where('created_at', '>=', $this->failedAttemptDuration($type))
                ->where('type', $type)
                ->where('identity', $identity)
                ->where('is_blocked', false)
                ->where('attempts', '<', 3)
                ->firstOrNew();

            $this->initializeAttempts($accessBlock, $type);

            $accessBlock->type = $type;
            $accessBlock->identity = $identity;
            $accessBlock->attempts += 1;
            $accessBlock->blocked_at = $this->blockedAt;
            $accessBlock->unblocked_at = $this->unBlockedAt;
            $accessBlock->is_blocked = $this->isBlocked;
            $accessBlock->audit = $this->getAudit($accessBlock, $request);
            $accessBlock->save();
        } catch (\Exception $exception) {
            LogHelper::error($exception, [
                'keyword' => 'failedAttempt',
            ]);
        }
    }

    protected function getAudit($accessBlock, $request): array
    {
        $audit = [
            [
                'ip' => $request->getClientIp(),
                'data' => $request->all()
            ]
        ];

        if($accessBlock->audit) {
            foreach ($accessBlock->audit as $key => $value) {
                $audit[] = $value;
            }
        }

        return $audit;
    }

    protected function initializeAttempts($userAccess, $type): void
    {
        try {
            switch ($type) {
                case 'CHANGE_PASSWORD_ATTEMPT':
                case 'FORGOT_PASSWORD_ATTEMPT':
                case 'LOGIN_ATTEMPT':
                    $this->attempts = config('security.failed_attempt.password.failed_attempt_limit', 3);
                    if ($userAccess->attempts + 1 >= $this->attempts) {
                        $this->blockedAt = now()->toDateTimeString();
                        $this->unBlockedAt = now()->addHours(config('security.failed_attempt.password.blocking_time', 3))->toDateTimeString();
                        $this->isBlocked = true;
                    }
                    break;
                case 'OTP_ATTEMPT':
                    $this->attempts = config('security.failed_attempt.otp.failed_attempt_limit', 3);
                    if ($userAccess->attempts + 1 >= $this->attempts) {
                        $this->blockedAt = now()->toDateTimeString();
                        $this->unBlockedAt = now()->addHours(config('security.failed_attempt.otp.blocking_time', 3))->toDateTimeString();
                        $this->isBlocked = true;
                    }
                    break;
            };
        } catch (\Exception $exception) {
            LogHelper::error($exception, [
                'keyword' => 'failedAttempt',
            ]);
        }
    }

    protected function failedAttemptDuration(string $type): string
    {
        $duration = now()->subHour();
        try {
            switch ($type) {
                case 'CHANGE_PASSWORD_ATTEMPT':
                case 'FORGOT_PASSWORD_ATTEMPT':
                case 'LOGIN_ATTEMPT':
                    $duration = now()->subMinutes(config('security.failed_attempt.password.failed_attempt_time_range', 60));
                    break;
                case 'OTP_ATTEMPT':
                    $duration = now()->subMinutes(config('security.failed_attempt.otp.failed_attempt_time_range', 60));
                    break;
            };
        } catch (\Exception $exception) {
            LogHelper::error($exception, [
                'keyword' => 'failedAttemptDuration',
            ]);
        }
        return $duration->toDateTimeString();
    }

}
