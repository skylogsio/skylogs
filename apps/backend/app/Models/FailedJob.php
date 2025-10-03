<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;


class FailedJob extends BaseModel {

    protected $guarded = [
        "uid",
        "id",
        "deleted_by",
    ];

}
