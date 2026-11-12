<?php

namespace App\Services\Notification;

use App\Enums\EndpointType;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Tries notification profiles in order and stops when another profile cannot help.
 *
 * A step that fails over (this account or transport is the broken part) continues
 * the chain. Acceptance, and a rejection of the recipient or the message, stops it.
 */
class ProfileChain
{
    public function __construct(private readonly NotificationMetricsRecorder $recorder) {}

    /**
     * @param  iterable<int, Model>  $profiles
     * @param  callable(Model): DeliveryResult  $send
     */
    public function run(EndpointType $type, iterable $profiles, callable $send): DeliveryResult
    {
        $profiles = array_values($profiles instanceof \Traversable
            ? iterator_to_array($profiles)
            : (array) $profiles);

        $steps = [];
        $last = DeliveryResult::failed('No notification profile to try');
        $tried = 0;

        foreach ($profiles as $index => $profile) {
            $startedAt = hrtime(true);

            try {
                $result = $send($profile);
            } catch (Throwable $exception) {
                $result = DeliveryResult::fromException($exception);
            }

            $tried++;
            $continues = ! $result->isSent() && $result->failover && array_key_exists($index + 1, $profiles);
            $key = $profile->getKey();
            $profileId = $key === null ? null : (string) $key;
            $priority = $profile->getAttribute('priority') === null ? null : (int) $profile->getAttribute('priority');
            $provider = (string) ($profile->getAttribute('provider') ?? $profile->getAttribute('type') ?? '');

            $this->recorder->recordAttempt(
                $type,
                $profileId,
                $priority,
                $provider,
                match (true) {
                    $result->isSent() => 'sent',
                    $continues => 'failed_over',
                    default => 'failed',
                },
                (hrtime(true) - $startedAt) / 1_000_000_000,
            );

            $steps[] = [
                'profileId' => $profileId,
                'priority' => $priority,
                'provider' => $provider,
                'status' => $result->status->value,
                'error' => $result->error,
                'httpStatus' => $result->httpStatus,
            ];

            $last = $result;

            if (! $continues) {
                break;
            }
        }

        $this->recorder->recordChainRun($type, $this->chainOutcome($last, $tried));

        return $last->withSteps($steps);
    }

    private function chainOutcome(DeliveryResult $last, int $tried): string
    {
        if ($tried === 0 || ($last->failover && ! $last->isSent())) {
            return 'failed_exhausted';
        }

        if ($last->isSent()) {
            return $tried === 1 ? 'sent_first' : 'sent_after_failover';
        }

        return 'failed_rejected';
    }
}
