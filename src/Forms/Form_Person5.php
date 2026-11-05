<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Meldeschein row 5 (Ziffer 1-9, 11-15). Only reached when Person4More = yes.
 * Last person row of the Meldeschein; always continues with the Beiblatt (Step3).
 */
class Form_Person5 extends MultiFormStep
{
    use StepTrait;
    use PersonStepTrait;

    public const PERSON = 5;

    private static $default_sort_order = 7;

    private static array $conditional_required = [
        'Person5DocumentAuthority',
        'Person5DocumentNumber',
        'Person5DocumentIssued',
        'Person5DocumentValidUntil',
    ];

    public function getNextStep()
    {
        return Form_Step3::class;
    }
}
