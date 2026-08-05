<?php

namespace App\Enums;

enum UserRole: string
{
    case Photographer = 'photographer';
    case Client = 'client';
    case Superadmin = 'superadmin';
}
