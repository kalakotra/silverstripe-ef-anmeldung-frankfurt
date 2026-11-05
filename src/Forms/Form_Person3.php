<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Meldeschein row 3 (Ziffer 1-9, 11-15). Only reached when Person2More = yes.
 * Branches to Person 4 or to the Beiblatt (Step3).
 */
class Form_Person3 extends MultiFormStep
{
    use StepTrait;
    use PersonStepTrait;

    public const PERSON = 3;

    private static $default_sort_order = 5;

    private static array $conditional_required = [
        'Person3DocumentAuthority',
        'Person3DocumentNumber',
        'Person3DocumentIssued',
        'Person3DocumentValidUntil',
    ];

    public function getNextStep()
    {
        return $this->personNextStep(Form_Person4::class);
    }
}
