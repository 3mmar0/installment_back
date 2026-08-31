<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Admin = 'admin';
}
