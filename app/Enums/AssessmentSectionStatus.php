<?php

namespace App\Enums;

enum AssessmentSectionStatus: string
{
    case NotStarted =
        'not_started';

    case InProgress =
        'in_progress';

    case Completed =
        'completed';

    case Pending =
        'pending';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Não iniciada',

            self::InProgress => 'Em preenchimento',

            self::Completed => 'Concluída',

            self::Pending => 'Com pendências',
        };
    }
}
