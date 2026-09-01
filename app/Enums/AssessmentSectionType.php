<?php

namespace App\Enums;

enum AssessmentSectionType: string
{
    case Anamnesis = 'anamnesis';

    case BodyComposition =
        'body_composition';

    case Circumferences =
        'circumferences';

    case Vo2Max =
        'vo2_max';

    case NeuromotorTests =
        'neuromotor_tests';

    case ProgressPhotos =
        'progress_photos';

    case PosturalAssessment =
        'postural_assessment';

    public function label(): string
    {
        return match ($this) {
            self::Anamnesis => 'Anamnese',

            self::BodyComposition => 'Composição corporal',

            self::Circumferences => 'Perímetros',

            self::Vo2Max => 'VO2Max',

            self::NeuromotorTests => 'Testes neuromotores',

            self::ProgressPhotos => 'Fotos de evolução',

            self::PosturalAssessment => 'Avaliação postural',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Anamnesis => 1,

            self::BodyComposition => 2,

            self::Circumferences => 3,

            self::Vo2Max => 4,

            self::NeuromotorTests => 5,

            self::ProgressPhotos => 6,

            self::PosturalAssessment => 7,
        };
    }
}
