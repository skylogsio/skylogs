<?php

namespace App\Models;

use App\Observers\EndpointObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use MongoDB\Laravel\Relations\BelongsTo;

#[ObservedBy(EndpointObserver::class)]
class Endpoint extends BaseModel
{
    public $timestamps = true;

    public static $title = 'Endpoint';

    public static $KEY = 'endpoint';

    public const EMAIL = 'email';

    public const SMS = 'sms';

    public const CALL = 'call';

    public const TELEGRAM = 'telegram';

    public const TEAMS = 'teams';

    protected $guarded = ['id', '_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alertRules()
    {
        return $this->belongsToMany(AlertRule::class);
    }

    public function isVerifiedRequired()
    {
        return in_array($this->type, [self::SMS, self::CALL, self::EMAIL]);
    }

    public function generateOTPMessage()
    {
        $text = "Your verification code is  $this->otpCode .\nSkylogs ";

        return $text;
    }

    public function generateOtpCode()
    {
        $this->verfied = false;
        $this->otpCode = rand(1000, 9999);
        $this->otpSentAt = time();

    }

    public static array $types = [
        'sms' => 'SMS',
        'email' => 'Email',
        'call' => 'Call',
        'telegram' => 'Telegram',
        'teams' => 'Teams',
    ];
}
