<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\MultiForm\Models\MultiFormStep;

/**
 * Kopf des Meldescheins + "Neue Wohnung" (Frankfurt am Main is pre-printed in the PDF).
 */
class Form_Step1 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 1;

    private static $next_steps = Form_Step2::class;

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Neue Wohnung in Frankfurt am Main'), 4),
            LiteralField::create('GeneralNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.GeneralNotice', 'Wer eine Wohnung bezieht, muss sich innerhalb von zwei Wochen bei der Meldebehörde anmelden. Bitte bringen Sie einen gültigen Ausweis und die Bestätigung des Wohnungsgebers (Wohnungsgeberbestätigung) mit.') . '</div>'),
            DropdownField::create('RegistrationType', _t(__CLASS__ . '.RegistrationType', 'Die neue Wohnung ist'))
                ->setSource([
                    'main' => _t(__CLASS__ . '.RegistrationType.main', 'alleinige Wohnung oder Hauptwohnung'),
                    'secondary' => _t(__CLASS__ . '.RegistrationType.secondary', 'Nebenwohnung'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --')
                ->setDescription(_t(__CLASS__ . '.RegistrationTypeDescription', 'Hauptwohnung ist die vorwiegend benutzte Wohnung; bei Verheirateten die vorwiegend benutzte Wohnung der Familie.')),
            DateField::create('MoveInDate', _t(__CLASS__ . '.MoveInDate', 'Einzug am')),
            TextField::create('PostalCode', _t(__CLASS__ . '.PostalCode', 'Postleitzahl'))
                ->setMaxLength(5),
            TextField::create('District', _t(__CLASS__ . '.District', 'Ortsteil (optional)')),
            TextField::create('Street', _t(__CLASS__ . '.Street', 'Straße, Hausnummer')),
            TextField::create('AddressAddition', _t(__CLASS__ . '.AddressAddition', 'Adressierungszusatz (optional)'))
                ->setDescription(_t(__CLASS__ . '.AddressAdditionDescription', 'z. B. Etage, Hinterhaus; bei Untermietern der Name des Hauptmieters, wenn der eigene Name nicht am Briefkasten steht')),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'RegistrationType',
            'MoveInDate',
            'PostalCode',
            'Street',
        ]);
    }
}
