<?php

namespace Modules\Auth\Entities;

use App\Helpers\CommonHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Activity\App\Traits\ActivityTrait;
use Modules\Auth\Constants\AuthConstant;
use Modules\Auth\Notifications\OtpNotification;
use Modules\Auth\Traits\Auth2FaTrait;
use Modules\Vendor\Entities\Vendor;
use Ramsey\Uuid\UuidInterface;

class Otp extends Model
{
    use Auth2FaTrait, ActivityTrait;

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

    public function revoked()
    {
        DB::table('otps')->where('id', $this->id)->update(['revoked' => true]);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function (Otp $otp) {
            $otp->reference = $otp->uniqueId();
        });

        static::updating(function (Otp $otp) {
            $otp->reference = $otp->uniqueId();
        });

        static::created(function (Otp $otp) {
            $otp->notify($otp);
        });

        static::updated(function (Otp $otp) {
            $otp->notify($otp);
        });
    }

    public function notify($otp): void
    {
        if($otp && app()->environment(['production', 'staging', 'development'])) {
            if (in_array($otp->type, [AuthConstant::LOGIN_OTP_TYPE, AuthConstant::RESET_OTP_TYPE])) {
                $user = User::where('email', $otp->email)
                    ->first();
                    $user->notify(new OtpNotification($otp));
            }
            if ($otp->type == AuthConstant::RESELLER_LOGIN_OTP_TYPE) {
                Vendor::where('email', $otp->email)->first()->notify(new OtpNotification($otp));
            }
        }
    }

    private function uniqueId(): UuidInterface
    {
        while(1) {
            $uuid = Str::uuid();
            if(!self::where('reference', $uuid)->count()) {
                break;
            }
        }
        return $uuid;
    }
}
