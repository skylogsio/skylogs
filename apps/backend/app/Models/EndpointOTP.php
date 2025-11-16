<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class EndpointOTP extends BaseModel
{
    public $timestamps = true;

    protected $guarded = ['id', '_id'];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    protected $table = 'endpoint_otp';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generateOTPMessage()
    {
        $text = "Your verification code is  $this->otpCode .\nSkylogs ";

        return $text;
    }

    public function generateOtpCode()
    {
        $this->verfied = false;
        $this->otpCode = rand(10000, 99999);
        $this->otpSentAt = time();
    }
}
