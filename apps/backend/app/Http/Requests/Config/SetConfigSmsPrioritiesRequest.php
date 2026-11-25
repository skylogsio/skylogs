<?php

namespace App\Http\Requests\Config;

use App\Models\Config\ConfigSms;
use Illuminate\Database\Eloquent\Model;

class SetConfigSmsPrioritiesRequest extends SetConfigPrioritiesRequest
{
    /**
     * @return class-string<Model>
     */
    protected function modelClass(): string
    {
        return ConfigSms::class;
    }
}
