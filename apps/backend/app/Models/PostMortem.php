<?php

namespace App\Models;

use MongoDB\Laravel\Relations\BelongsTo;

class PostMortem extends BaseModel
{
    public $timestamps = true;

    public static $title = 'Post Mortem';

    public static $KEY = 'post-portem';

    protected $guarded = ['id', '_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
