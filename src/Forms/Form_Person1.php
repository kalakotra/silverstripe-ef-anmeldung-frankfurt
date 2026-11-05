<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Meldeschein row 1 (Ziffer 1-9, 11-15).
 * Branches to Person 2 or to the Beiblatt (Step3).
 */
class Form_Person1 extends MultiFormStep
{
    use StepTrait;
    use PersonStepTrait;

    public const PERSON = 1;

    private static $default_sort_order = 3;

    private static array $conditional_required = [
        'Person1DocumentAuthority',
        'Person1DocumentNumber',
        'Person1DocumentIssued',
        'Person1DocumentValidUntil',
    ];

    public function getNextStep()
    {
        return $this->personNextStep(Form_Person2::class);
    }
}
