<?php

namespace App\Http\Requests\Config;

use App\Models\Config\ConfigTelegram;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfigTelegramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['direct', 'http', 'socks5'])],
            'host' => ['required_unless:type,direct', 'nullable', 'string', 'max:255'],
            'port' => ['required_unless:type,direct', 'nullable'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('type') !== 'direct' || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $query = ConfigTelegram::query()->where('type', 'direct');
                $id = $this->route('id');

                if (is_string($id) && $id !== '') {
                    $query->where('_id', '!=', $id);
                }

                if ($query->exists()) {
                    $validator->errors()->add('type', 'Only one direct Telegram config can exist.');
                }
            },
        ];
    }
}
