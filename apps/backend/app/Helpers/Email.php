<?php

namespace App\Helpers;

use App\interfaces\Messageable;
use App\Models\EndpointOTP;
use Illuminate\Mail\Message;
use Mail;

/**
 * Signup form
 */
class Email
{
    public static function sendMessageAlert($users, Messageable $alert): array
    {

        if (! empty($users)) {
            Mail::raw($alert->emailMessage(), function (Message $message) use ($users) {
                $message->bcc($users)
                    ->subject('Skylogs Alert');
            });
        }

        return [];

    }

    public static function sendOTP(EndpointOTP $endpoint)
    {
        Mail::raw($endpoint->generateOTPMessage(), function (Message $message) use ($endpoint) {
            $message->to($endpoint->value)
                ->subject('Skylogs Endpoint Verification');
        });
    }
}
