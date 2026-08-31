<?php

namespace App\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Completed => 'Concluída',
        };
    }
}
