<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backup extends BaseModel {

    use HasUuids;

    public static $title = "پشتیبان گیری";
    protected $table = "backups";
    protected $guarded = [
        "uid",
        "id",
        "deleted_by",
    ];

}
