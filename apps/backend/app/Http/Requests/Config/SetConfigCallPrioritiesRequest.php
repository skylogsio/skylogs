<?php

namespace App\Http\Requests\Config;

use App\Models\Config\ConfigCall;
use Illuminate\Database\Eloquent\Model;

class SetConfigCallPrioritiesRequest extends SetConfigPrioritiesRequest
{
    /**
     * @return class-string<Model>
     */
    protected function modelClass(): string
    {
        return ConfigCall::class;
    }
}
