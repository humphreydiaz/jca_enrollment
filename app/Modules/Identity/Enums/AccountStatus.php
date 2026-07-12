<?php

namespace App\Modules\Identity\Enums;

enum AccountStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Locked = 'locked';
    case Disabled = 'disabled';
}
