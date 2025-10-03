<?php

namespace App\Enums;

enum Constants: string
{
    case ROLE_OWNER = 'owner';
    case ROLE_MANAGER = 'manager';
    case ROLE_MEMBER = 'member';

    case PERMISSION_MANAGE_ADMIN_USER = 'manage_admin_user';
    case PERMISSION_MANAGE_MEMBER_USER = 'manage_member_user';

    case ADMIN = "admin";

}
