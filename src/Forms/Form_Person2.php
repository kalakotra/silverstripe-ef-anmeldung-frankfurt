<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Meldeschein row 2 (Ziffer 1-9, 11-15). Only reached when Person1More = yes.
 * Branches to Person 3 or to the Beiblatt (Step3).
 */
class Form_Person2 extends MultiFormStep
{
    use StepTrait;
    use PersonStepTrait;

    public const PERSON = 2;

    private static $default_sort_order = 4;

    private static array $conditional_required = [
        'Person2DocumentAuthority',
        'Person2DocumentNumber',
        'Person2DocumentIssued',
        'Person2DocumentValidUntil',
    ];

    public function getNextStep()
    {
        return $this->personNextStep(Form_Person3::class);
    }
}
