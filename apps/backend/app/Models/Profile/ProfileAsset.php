<?php

namespace App\Models\Profile;

use App\Models\BaseModel;
use App\Models\User;

class   ProfileAsset extends BaseModel
{

    public $timestamps = true;

    protected $guarded = ['id', '_id',];

    protected $appends = ["envs"];

    public function getEnvsAttribute()
    {
        $config = json_decode($this['config'], true);
        return array_keys($config);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'ownerId');
    }

    public function service()
    {
        return $this->belongsTo(ProfileService::class, 'profileServiceId');
    }

    public function environment()
    {
        return $this->belongsTo(ProfileEnvironment::class, 'profileEnvironmentId');
    }

}
