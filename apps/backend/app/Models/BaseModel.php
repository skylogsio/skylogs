<?php

namespace App\Models;

use App\Observers\Ha\HaConfigObserver;
use MongoDB\Laravel\Eloquent\Model;

class BaseModel extends Model
{
    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    const DELETED_AT = 'deletedAt';

    /**
     * The driver writes the array and then marks it unchanged, so save() does
     * not fire updated and a follower never hears about the assignment.
     */
    public function push()
    {
        $parameters = func_get_args();

        if ($parameters === []) {
            return parent::push();
        }

        $modified = parent::push(...$parameters);

        if ($modified) {
            app(HaConfigObserver::class)->touch($this, (string) $parameters[0]);
        }

        return $modified;
    }

    /**
     * @param  string  $column
     * @param  mixed  $values
     */
    public function pull($column, $values)
    {
        $modified = parent::pull($column, $values);

        if ($modified) {
            app(HaConfigObserver::class)->touch($this, (string) $column);
        }

        return $modified;
    }
}
