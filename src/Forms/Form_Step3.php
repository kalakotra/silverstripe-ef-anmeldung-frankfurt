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
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * Beiblatt zum Meldeschein (Ziffer 16-18). One row each for 16 a), b), c) on the form.
 */
class Form_Step3 extends MultiFormStep
{
    use StepTrait;

    private static $default_sort_order = 8;

    private static $next_steps = Form_Preview::class;

    private static array $conditional_required = [
        'SpouseLastName',
        'SpouseFirstNames',
        'RepresentativeLastName',
        'RepresentativeFirstNames',
        'ChildLastName',
        'ChildFirstNames',
    ];

    public function getFields(): FieldList
    {
        return FieldList::create(
            HeaderField::create('IntroText', _t(__CLASS__ . '.IntroText', 'Beiblatt zum Meldeschein'), 4),
            LiteralField::create('FamilyNotice', '<div class="alert alert-info">' . _t(__CLASS__ . '.FamilyNotice', 'Ziffer 16: Hier werden Ehegatten/Lebenspartner und minderjährige Kinder eingetragen, die nicht mit Ihnen in die neue Wohnung ziehen oder dort bereits gemeldet sind, sowie gesetzliche Vertreter Minderjähriger.') . '</div>'),

            DropdownField::create('SpouseNotMoving', _t(__CLASS__ . '.SpouseNotMoving', 'a) Gibt es einen Ehegatten/Lebenspartner, der nicht mit angemeldet wird?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.SpouseNotMoving.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.SpouseNotMoving.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                TextField::create('SpouseLastName', _t(__CLASS__ . '.SpouseLastName', 'Familienname (ggf. abweichender Geburtsname, Doktorgrad)')),
                TextField::create('SpouseFirstNames', _t(__CLASS__ . '.SpouseFirstNames', 'Vorname(n)')),
                DateField::create('SpouseBirthDate', _t(__CLASS__ . '.SpouseBirthDate', 'Geburtsdatum (optional)')),
                TextField::create('SpouseAddress', _t(__CLASS__ . '.SpouseAddress', 'Anschrift (PLZ, Wohnort, Straße, Hausnr.) (optional)')),
            )
                ->displayIf('SpouseNotMoving')->isEqualTo('yes')->end(),

            DropdownField::create('HasRepresentative', _t(__CLASS__ . '.HasRepresentative', 'b) Wird ein Minderjähriger angemeldet, dessen gesetzlicher Vertreter nicht mit angemeldet wird?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.HasRepresentative.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.HasRepresentative.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --')
                ->setDescription(_t(__CLASS__ . '.HasRepresentativeDescription', 'Vertreten die Eltern das Kind gemeinsam, sind beide einzutragen; ist ein Jugendamt gesetzlicher Vertreter, dieses.')),
            Wrapper::create(
                TextField::create('RepresentativeLastName', _t(__CLASS__ . '.RepresentativeLastName', 'Familienname (ggf. abweichender Geburtsname, Doktorgrad) bzw. Jugendamt')),
                TextField::create('RepresentativeFirstNames', _t(__CLASS__ . '.RepresentativeFirstNames', 'Vorname(n)')),
                DateField::create('RepresentativeBirthDate', _t(__CLASS__ . '.RepresentativeBirthDate', 'Geburtsdatum (optional)')),
                TextField::create('RepresentativeAddress', _t(__CLASS__ . '.RepresentativeAddress', 'Anschrift (PLZ, Wohnort, Straße, Hausnr.) (optional)')),
            )
                ->displayIf('HasRepresentative')->isEqualTo('yes')->end(),

            DropdownField::create('ChildNotMoving', _t(__CLASS__ . '.ChildNotMoving', 'c) Gibt es ein minderjähriges, unverheiratetes Kind, das nicht mit angemeldet wird?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.ChildNotMoving.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.ChildNotMoving.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            Wrapper::create(
                TextField::create('ChildLastName', _t(__CLASS__ . '.ChildLastName', 'Familienname des Kindes')),
                TextField::create('ChildFirstNames', _t(__CLASS__ . '.ChildFirstNames', 'Vorname(n)')),
                DateField::create('ChildBirthDate', _t(__CLASS__ . '.ChildBirthDate', 'Geburtsdatum (optional)')),
                TextField::create('ChildAddress', _t(__CLASS__ . '.ChildAddress', 'Anschrift (PLZ, Wohnort, Straße, Hausnr.) (optional)')),
            )
                ->displayIf('ChildNotMoving')->isEqualTo('yes')->end(),

            HeaderField::create('PermitHeader', _t(__CLASS__ . '.PermitHeader', 'Ziffer 17'), 5),
            DropdownField::create('WeaponPermit', _t(__CLASS__ . '.WeaponPermit', 'Sind Sie Inhaberin/Inhaber einer waffenrechtlichen Erlaubnis?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.WeaponPermit.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.WeaponPermit.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),
            DropdownField::create('ExplosivesPermit', _t(__CLASS__ . '.ExplosivesPermit', 'Sind Sie Inhaberin/Inhaber einer sprengstoffrechtlichen Erlaubnis oder eines Befähigungsscheins nach § 20 Sprengstoffgesetz?'))
                ->setSource([
                    'no' => _t(__CLASS__ . '.ExplosivesPermit.no', 'nein'),
                    'yes' => _t(__CLASS__ . '.ExplosivesPermit.yes', 'ja'),
                ])
                ->setEmptyString('-- ' . _t(__CLASS__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --'),

            HeaderField::create('Address1939Header', _t(__CLASS__ . '.Address1939Header', 'Ziffer 18'), 5),
            TextField::create('Address1939', _t(__CLASS__ . '.Address1939', 'Anschrift am 1. September 1939 (nur bei Flüchtlingen und Vertriebenen, optional)')),
        );
    }

    public function getValidator(): RequiredFieldsValidator
    {
        return RequiredFieldsValidator::create([
            'SpouseNotMoving',
            'HasRepresentative',
            'ChildNotMoving',
            'WeaponPermit',
            'ExplosivesPermit',
        ]);
    }
}
