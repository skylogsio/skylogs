<?php

namespace App\Helpers;

use App\interfaces\Messageable;
use App\Models\EndpointOTP;
use App\Services\ConfigEmailService;
use Config;
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
            $config = app(ConfigEmailService::class)->getDefault();

            if (! empty($config)) {
                Config::set('mail.mailers.smtp.host', $config->host);
                Config::set('mail.mailers.smtp.port', $config->port);
                Config::set('mail.mailers.smtp.username', $config->username);
                Config::set('mail.mailers.smtp.password', $config->password);
                Config::set('mail.from.address', $config->fromAddress);
            }

            Mail::raw($alert->emailMessage(), function (Message $message) use ($users) {
                $message->bcc($users)
                    ->subject('Skylogs Alert');
            });
        }

        return [];

    }

    public static function sendOTP(EndpointOTP $endpoint)
    {
        $config = app(ConfigEmailService::class)->getDefault();

        if (! empty($config)) {
            Config::set('mail.mailers.smtp.host', $config->host);
            Config::set('mail.mailers.smtp.port', $config->port);
            Config::set('mail.mailers.smtp.username', $config->username);
            Config::set('mail.mailers.smtp.password', $config->password);
            Config::set('mail.from.address', $config->fromAddress);
        }

        Mail::raw($endpoint->generateOTPMessage(), function (Message $message) use ($endpoint) {
            $message->to($endpoint->value)
                ->subject('Skylogs Endpoint Verification');
        });
    }
}
