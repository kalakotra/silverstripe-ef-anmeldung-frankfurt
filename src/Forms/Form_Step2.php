<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * "Bisherige Wohnung" and "Weitere Wohnungen in Deutschland" (max. two rows on the form).
 */
class Form_Step2 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 2;

    private static $next_steps = Form_Person1::class;

    private static array $conditional_required = [
        'PreviousPostalCode',
        'PreviousCity',
        'PreviousStreet',
        'PreviousKept',
        'PreviousFutureStatus',
        'PreviousCountry',
        'Other1PostalCode',
        'Other1City',
        'Other1Street',
        'Other1Kept',
        'Other1FutureStatus',
        'Other2PostalCode',
        'Other2City',
        'Other2Street',
        'Other2Kept',
        'Other2FutureStatus',
    ];

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Bisherige und weitere Wohnungen'), 4),

            DropdownField::create('PreviousResidence', _t(__CLASS__ . '.PreviousResidence', 'Woher ziehen Sie zu?'))
                ->setSource([
                    'domestic' => _t(__CLASS__ . '.PreviousResidence.domestic', 'aus einer Wohnung in Deutschland'),
                    'abroad' => _t(__CLASS__ . '.PreviousResidence.abroad', 'aus dem Ausland'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                TextField::create('PreviousPostalCode', _t(__CLASS__ . '.PreviousPostalCode', 'Postleitzahl der bisherigen Wohnung'))
                    ->setMaxLength(5),
                TextField::create('PreviousCity', _t(__CLASS__ . '.PreviousCity', 'Gemeinde, ggf. Ortsteil')),
                TextField::create('PreviousStreet', _t(__CLASS__ . '.PreviousStreet', 'Straße, Hausnummer, Adressierungszusatz')),
                DropdownField::create('PreviousKept', _t(__CLASS__ . '.PreviousKept', 'Wird die bisherige Wohnung beibehalten?'))
                    ->setSource([
                        'no' => _t(__CLASS__ . '.PreviousKept.no', 'nein'),
                        'yes' => _t(__CLASS__ . '.PreviousKept.yes', 'ja'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                Wrapper::create(
                    DropdownField::create('PreviousFutureStatus', _t(__CLASS__ . '.PreviousFutureStatus', 'Die bisherige Wohnung soll künftig sein'))
                        ->setSource([
                            'main' => _t(__CLASS__ . '.PreviousFutureStatus.main', 'alleinige Wohnung oder Hauptwohnung'),
                            'secondary' => _t(__CLASS__ . '.PreviousFutureStatus.secondary', 'Nebenwohnung'),
                        ])
                        ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                )
                    ->displayIf('PreviousKept')->isEqualTo('yes')->end(),
            )
                ->displayIf('PreviousResidence')->isEqualTo('domestic')->end(),
            Wrapper::create(
                TextField::create('PreviousCountry', _t(__CLASS__ . '.PreviousCountry', 'Staat')),
                TextField::create('PreviousForeignAddress', _t(__CLASS__ . '.PreviousForeignAddress', 'Bisherige Anschrift im Ausland (optional)')),
                TextField::create('PreviousLastDomesticAddress', _t(__CLASS__ . '.PreviousLastDomesticAddress', 'Letzte frühere Anschrift in Deutschland (falls vorhanden)')),
            )
                ->displayIf('PreviousResidence')->isEqualTo('abroad')->end(),

            HeaderField::create('OtherHeader', _t(__CLASS__ . '.OtherHeader', 'Weitere Wohnungen in Deutschland'), 5),
            DropdownField::create('OtherResidences', _t(__CLASS__ . '.OtherResidences', 'Haben Sie außer der neuen (und der bisherigen) Wohnung weitere Wohnungen in Deutschland?'))
                ->setSource([
                    'none' => _t(__CLASS__ . '.OtherResidences.none', 'keine'),
                    'one' => _t(__CLASS__ . '.OtherResidences.one', 'eine weitere Wohnung'),
                    'two' => _t(__CLASS__ . '.OtherResidences.two', 'zwei weitere Wohnungen'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                HeaderField::create('Other1Header', _t(__CLASS__ . '.Other1Header', 'Weitere Wohnung 1'), 6),
                TextField::create('Other1PostalCode', _t(__CLASS__ . '.Other1PostalCode', 'Postleitzahl'))
                    ->setMaxLength(5),
                TextField::create('Other1City', _t(__CLASS__ . '.Other1City', 'Gemeinde, ggf. Ortsteil')),
                TextField::create('Other1Street', _t(__CLASS__ . '.Other1Street', 'Straße, Hausnummer, Adressierungszusatz')),
                CheckboxField::create('Other1WasSecondary', _t(__CLASS__ . '.Other1WasSecondary', 'Die Wohnung war bisher Nebenwohnung')),
                DropdownField::create('Other1Kept', _t(__CLASS__ . '.Other1Kept', 'Wird die Wohnung beibehalten?'))
                    ->setSource([
                        'no' => _t(__CLASS__ . '.Other1Kept.no', 'nein'),
                        'yes' => _t(__CLASS__ . '.Other1Kept.yes', 'ja'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                Wrapper::create(
                    DropdownField::create('Other1FutureStatus', _t(__CLASS__ . '.Other1FutureStatus', 'Die Wohnung soll künftig sein'))
                        ->setSource([
                            'main' => _t(__CLASS__ . '.Other1FutureStatus.main', 'alleinige Wohnung oder Hauptwohnung'),
                            'secondary' => _t(__CLASS__ . '.Other1FutureStatus.secondary', 'Nebenwohnung'),
                        ])
                        ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                )
                    ->displayIf('Other1Kept')->isEqualTo('yes')->end(),
            )
                ->displayIf('OtherResidences')->isEqualTo('one')->orIf('OtherResidences')->isEqualTo('two')->end(),
            Wrapper::create(
                HeaderField::create('Other2Header', _t(__CLASS__ . '.Other2Header', 'Weitere Wohnung 2'), 6),
                TextField::create('Other2PostalCode', _t(__CLASS__ . '.Other2PostalCode', 'Postleitzahl'))
                    ->setMaxLength(5),
                TextField::create('Other2City', _t(__CLASS__ . '.Other2City', 'Gemeinde, ggf. Ortsteil')),
                TextField::create('Other2Street', _t(__CLASS__ . '.Other2Street', 'Straße, Hausnummer, Adressierungszusatz')),
                CheckboxField::create('Other2WasSecondary', _t(__CLASS__ . '.Other2WasSecondary', 'Die Wohnung war bisher Nebenwohnung')),
                DropdownField::create('Other2Kept', _t(__CLASS__ . '.Other2Kept', 'Wird die Wohnung beibehalten?'))
                    ->setSource([
                        'no' => _t(__CLASS__ . '.Other2Kept.no', 'nein'),
                        'yes' => _t(__CLASS__ . '.Other2Kept.yes', 'ja'),
                    ])
                    ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                Wrapper::create(
                    DropdownField::create('Other2FutureStatus', _t(__CLASS__ . '.Other2FutureStatus', 'Die Wohnung soll künftig sein'))
                        ->setSource([
                            'main' => _t(__CLASS__ . '.Other2FutureStatus.main', 'alleinige Wohnung oder Hauptwohnung'),
                            'secondary' => _t(__CLASS__ . '.Other2FutureStatus.secondary', 'Nebenwohnung'),
                        ])
                        ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
                )
                    ->displayIf('Other2Kept')->isEqualTo('yes')->end(),
            )
                ->displayIf('OtherResidences')->isEqualTo('two')->end(),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'PreviousResidence',
            'OtherResidences',
        ]);
    }
}
