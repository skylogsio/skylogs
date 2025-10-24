<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BaseModel extends Model
{
    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    const DELETED_AT = 'deletedAt';
}
