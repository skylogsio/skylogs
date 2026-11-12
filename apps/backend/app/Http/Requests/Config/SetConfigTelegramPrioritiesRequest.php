<?php

namespace App\Http\Requests\Config;

use App\Models\Config\ConfigTelegram;
use Illuminate\Database\Eloquent\Model;

class SetConfigTelegramPrioritiesRequest extends SetConfigPrioritiesRequest
{
    /**
     * @return class-string<Model>
     */
    protected function modelClass(): string
    {
        return ConfigTelegram::class;
    }
}
