<?php

namespace App\Modules\Identity\Enums;

enum RoleCode: string
{
    case Admin = 'admin';
    case Registrar = 'registrar';
    case Accounting = 'accounting';
    case Teacher = 'teacher';
    case Guardian = 'guardian';
    case Student = 'student';

    public function isStaff(): bool
    {
        return match ($this) {
            self::Admin, self::Registrar, self::Accounting, self::Teacher => true,
            self::Guardian, self::Student => false,
        };
    }
}
