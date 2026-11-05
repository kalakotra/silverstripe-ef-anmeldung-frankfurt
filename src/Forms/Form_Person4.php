<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Meldeschein row 4 (Ziffer 1-9, 11-15). Only reached when Person3More = yes.
 * Branches to Person 5 or to the Beiblatt (Step3).
 */
class Form_Person4 extends MultiFormStep
{
    use StepTrait;
    use PersonStepTrait;

    public const PERSON = 4;

    private static $default_sort_order = 6;

    private static array $conditional_required = [
        'Person4DocumentAuthority',
        'Person4DocumentNumber',
        'Person4DocumentIssued',
        'Person4DocumentValidUntil',
    ];

    public function getNextStep()
    {
        return $this->personNextStep(Form_Person5::class);
    }
}
