<?php

namespace App\Services\Notification\Channels;

use App\Enums\EndpointType;
use App\Models\Endpoint;
use App\Models\EndpointOTP;
use App\Services\ConfigEmailService;
use App\Services\Notification\DeliveryResult;
use App\Support\NotifyMessagePayload;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class EmailChannel extends AbstractChannel
{
    public function __construct(private readonly ConfigEmailService $configEmailService) {}

    public function type(): EndpointType
    {
        return EndpointType::EMAIL;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'email', 'max:255'],
        ];
    }

    public function requiresVerification(): bool
    {
        return true;
    }

    public function sendVerification(EndpointOTP $otp): DeliveryResult
    {
        return $this->guard(fn (): DeliveryResult => $this->mail(
            (string) $otp->value,
            'Skylogs Endpoint Verification',
            $otp->generateOTPMessage(),
        ));
    }

    protected function deliver(Endpoint $endpoint, NotifyMessagePayload $message): DeliveryResult
    {
        return $this->mail((string) $endpoint->value, 'Skylogs Alert', $message->textFor($this->type()));
    }

    /**
     * SMTP gives no delivery receipt; reaching the end without a transport
     * exception is the only success signal there is.
     */
    private function mail(string $address, string $subject, string $text): DeliveryResult
    {
        $this->applyConfiguredSmtp();

        Mail::raw($text, function (Message $message) use ($address, $subject) {
            $message->to($address)->subject($subject);
        });

        return DeliveryResult::sent();
    }

    private function applyConfiguredSmtp(): void
    {
        $config = $this->configEmailService->getDefault();

        if (empty($config)) {
            return;
        }

        Config::set('mail.mailers.smtp.host', $config->host);
        Config::set('mail.mailers.smtp.port', $config->port);
        Config::set('mail.mailers.smtp.username', $config->username);
        Config::set('mail.mailers.smtp.password', $config->password);
        Config::set('mail.from.address', $config->fromAddress);
    }
}
