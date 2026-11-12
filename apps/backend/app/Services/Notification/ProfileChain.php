<?php

namespace App\Services\Notification;

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
    /**
     * @param  iterable<int, Model>  $profiles
     * @param  callable(Model): DeliveryResult  $send
     */
    public function run(iterable $profiles, callable $send): DeliveryResult
    {
        $steps = [];
        $last = DeliveryResult::failed('No notification profile to try');

        foreach ($profiles as $profile) {
            try {
                $result = $send($profile);
            } catch (Throwable $exception) {
                $result = DeliveryResult::fromException($exception);
            }

            $key = $profile->getKey();

            $steps[] = [
                'profileId' => $key === null ? null : (string) $key,
                'priority' => $profile->getAttribute('priority') === null ? null : (int) $profile->getAttribute('priority'),
                'provider' => (string) ($profile->getAttribute('provider') ?? $profile->getAttribute('type') ?? ''),
                'status' => $result->status->value,
                'error' => $result->error,
                'httpStatus' => $result->httpStatus,
            ];

            $last = $result;

            if ($result->isSent() || ! $result->failover) {
                break;
            }
        }

        return $last->withSteps($steps);
    }
}
