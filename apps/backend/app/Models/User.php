<?php

namespace App\Models;

use App\Enums\Constants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Auth\User as Authenticatable;
use MongoDB\Laravel\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    const DELETED_AT = 'deletedAt';

    protected $guard_name = 'api';
    //    protected $guard_name = ['web', 'api'];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function isAdmin()
    {
        return $this->hasAnyRole([Constants::ROLE_OWNER, Constants::ROLE_MANAGER]);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(Endpoint::class, 'userId');
    }
}
