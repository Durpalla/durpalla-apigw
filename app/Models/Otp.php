<?php

namespace App\Models;

use App\Constants\AuthConstant;
use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use App\Notifications\EmailOtp;
use App\Traits\Auditable;
use App\Traits\Auth2FaTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;
use Ramsey\Uuid\UuidInterface;

/**
 * Lightweight OTP rows for merchant login/reset (no Auth module package).
 */
class Otp extends Model
{
    use Auth2FaTrait, Auditable;

    protected $fillable = ['type', 'email', 'code', 'reference'];

    protected $logAttributes = ['type', 'email', 'code', 'reference'];

    protected $logOnlyDirty = true;

    public function getDescriptionForEvent(string $eventName): string
    {
        return "OTP {$eventName}";
    }

    public function getCreatedAtAttribute($datetime): string
    {
        return CommonHelper::parseLocalTimeZone($datetime);
    }

    public function revoked(): void
    {
        DB::table('otps')->where('id', $this->id)->update(['revoked' => true]);
    }

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (Otp $otp) {
            $otp->reference = $otp->uniqueId();
        });

        static::updating(function (Otp $otp) {
            $otp->reference = $otp->uniqueId();
        });

        static::created(function (Otp $otp) {
            $otp->sendMail();
        });

        static::updated(function (Otp $otp) {
            $otp->sendMail();
        });
    }

    public function sendMail(): void
    {
        if (! app()->environment(['production', 'staging', 'development'])) {
            return;
        }

        try {
            if (! in_array($this->type, [AuthConstant::LOGIN_OTP_TYPE, AuthConstant::RESET_OTP_TYPE], true)) {
                return;
            }

            $notification = new EmailOtp((string) $this->code);
            $user = User::query()->where('email', $this->email)->first();
            if ($user) {
                $user->notify($notification);
            } else {
                NotificationFacade::route('mail', $this->email)->notify($notification);
            }
        } catch (\Throwable $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'OTP_MAIL_FAILED',
                'email' => $this->email,
                'type' => $this->type,
            ]);
        }
    }

    private function uniqueId(): UuidInterface
    {
        while (true) {
            $uuid = Str::uuid();
            if (! self::where('reference', $uuid)->exists()) {
                return $uuid;
            }
        }
    }
}
