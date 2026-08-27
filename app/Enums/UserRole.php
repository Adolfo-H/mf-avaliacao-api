<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Evaluator = 'evaluator';
    case Reception = 'reception';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Evaluator => 'Avaliador',
            self::Reception => 'Recepção',
        };
    }
}
