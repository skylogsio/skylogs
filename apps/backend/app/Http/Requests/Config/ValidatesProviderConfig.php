<?php

namespace App\Http\Requests\Config;

use App\Enums\EndpointType;
use App\Services\Notification\NotificationProviderRegistry;
use Illuminate\Validation\Rule;

/**
 * Shared provider-field validation for SMS and call config writes.
 */
trait ValidatesProviderConfig
{
    abstract protected function endpointType(): EndpointType;

    /**
     * @return class-string
     */
    abstract protected function providerEnum(): string;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $registry = app(NotificationProviderRegistry::class);
        $provider = (string) $this->input('provider');

        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::enum($this->providerEnum())],
            ...($registry->has($provider) ? $registry->for($provider)->rules($this->endpointType()) : []),
        ];
    }
}
