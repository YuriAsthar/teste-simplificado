<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentType: string
{
    case Cpf = 'cpf';
    case Cnpj = 'cnpj';
    case Passport = 'passport';
    case DriverLicense = 'driver_license';
    case NationalId = 'national_id';
}
