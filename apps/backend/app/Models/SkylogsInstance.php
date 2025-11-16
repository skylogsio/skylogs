<?php

namespace App\Models;

class SkylogsInstance extends BaseModel
{
    public $timestamps = true;

    public const STATE_FIRING = 'firing';

    public const DOWN = 1;

    public const UP = 2;

    protected $guarded = ['id', '_id'];

    public function getBaseUrl()
    {
        return \Str::startsWith($this->url, 'http') ? $this->url : 'http://'.$this->url;
    }

    public function getHealthUrl()
    {
        return $this->getBaseUrl().'/api/health';
    }

    public function getPingUrl()
    {
        return $this->getBaseUrl().'/api/leaderPing';
    }
}
