<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Forms;

use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use UncleCheese\DisplayLogic\Forms\Wrapper;

/**
 * One person of the Meldeschein (rows 1-5 of Ziffer 1-9, 11-15). Field names are "Person{N}<Field>",
 * N = the using class's PERSON constant. Labels use __TRAIT__ keys (one translation for all five persons);
 * Form::resolveFieldLabel() takes the preview labels from these field titles.
 *
 * Using classes define: public const PERSON, $default_sort_order, $conditional_required, getNextStep().
 */
trait PersonStepTrait
{
    public function getFields(): FieldList
    {
        $n = static::PERSON;
        $p = 'Person' . $n;
        $select = '-- ' . _t(__TRAIT__ . '.DEFAULT_SELECT', 'Bitte auswählen') . ' --';

        $fields = FieldList::create(
            HeaderField::create('IntroText', _t(__TRAIT__ . '.IntroText', 'Person {number}', ['number' => $n]), 4),
        );

        if ($n === 1) {
            $fields->push(LiteralField::create('FamilyNotice', '<div class="alert alert-info">' . _t(__TRAIT__ . '.FamilyNotice', 'Familienangehörige und Lebenspartner können auf einem Meldeschein gemeinsam angemeldet werden (bis zu 5 Personen), wenn alle die gleiche bisherige und neue Wohnung haben. Für Personen mit abweichenden Meldeverhältnissen ist ein eigener Meldeschein auszufüllen.') . '</div>'));
        }

        $fields->push(TextField::create($p . 'LastName', _t(__TRAIT__ . '.LastName', 'Familienname')));
        $fields->push(TextField::create($p . 'BirthName', _t(__TRAIT__ . '.BirthName', 'Abweichender Geburtsname (optional)')));
        $fields->push(TextField::create($p . 'ReligiousName', _t(__TRAIT__ . '.ReligiousName', 'Ordens- oder Künstlername (optional)')));
        $fields->push(
            DropdownField::create($p . 'Doctorate', _t(__TRAIT__ . '.Doctorate', 'Doktorgrad (optional)'))
                ->setSource([
                    'dr' => _t(__TRAIT__ . '.Doctorate.dr', 'Dr.'),
                    'dr_hc' => _t(__TRAIT__ . '.Doctorate.dr_hc', 'Dr. h. c.'),
                    'dr_eh' => _t(__TRAIT__ . '.Doctorate.dr_eh', 'Dr. E. h.'),
                ])
                ->setEmptyString('-- ' . _t(__TRAIT__ . '.DoctorateNone', 'kein Doktorgrad') . ' --')
                ->setDescription(_t(__TRAIT__ . '.DoctorateDescription', 'Andere akademische Grade oder Titel werden nicht in das Melderegister aufgenommen.'))
        );
        $fields->push(
            TextField::create($p . 'FirstNames', _t(__TRAIT__ . '.FirstNames', 'Vorname(n)'))
                ->setDescription(_t(__TRAIT__ . '.FirstNamesDescription', 'Alle Vornamen, Rufname zuerst'))
        );
        $fields->push(
            DropdownField::create($p . 'Gender', _t(__TRAIT__ . '.Gender', 'Geschlecht'))
                ->setSource([
                    'female' => _t(__TRAIT__ . '.Gender.female', 'weiblich'),
                    'male' => _t(__TRAIT__ . '.Gender.male', 'männlich'),
                    'diverse' => _t(__TRAIT__ . '.Gender.diverse', 'divers'),
                    'unspecified' => _t(__TRAIT__ . '.Gender.unspecified', 'ohne Angabe'),
                ])
                ->setEmptyString($select)
                ->setDescription(_t(__TRAIT__ . '.GenderDescription', 'Der Meldeschein sieht nur „w“ und „m“ vor; bei „divers“ oder „ohne Angabe“ bleibt das Feld leer.'))
        );
        $fields->push(DateField::create($p . 'BirthDate', _t(__TRAIT__ . '.BirthDate', 'Geburtsdatum')));
        $fields->push(
            TextField::create($p . 'BirthPlace', _t(__TRAIT__ . '.BirthPlace', 'Geburtsort'))
                ->setDescription(_t(__TRAIT__ . '.BirthPlaceDescription', 'Wenn im Ausland, bitte auch den Staat angeben'))
        );
        $fields->push(
            DropdownField::create($p . 'Religion', _t(__TRAIT__ . '.Religion', 'Religion'))
                ->setSource([
                    'rk' => _t(__TRAIT__ . '.Religion.rk', 'römisch-katholisch (rk)'),
                    'ak' => _t(__TRAIT__ . '.Religion.ak', 'alt-katholisch (ak)'),
                    'ev' => _t(__TRAIT__ . '.Religion.ev', 'evangelisch (ev)'),
                    'lt' => _t(__TRAIT__ . '.Religion.lt', 'evangelisch-lutherisch (lt)'),
                    'rf' => _t(__TRAIT__ . '.Religion.rf', 'evangelisch-reformiert (rf)'),
                    'fr' => _t(__TRAIT__ . '.Religion.fr', 'französisch-reformiert (fr)'),
                    'ishe' => _t(__TRAIT__ . '.Religion.ishe', 'Jüdische Gemeinde Frankfurt (ishe)'),
                    'il' => _t(__TRAIT__ . '.Religion.il', 'Jüdische Gemeinden im Landesverband Hessen (il)'),
                    'fa' => _t(__TRAIT__ . '.Religion.fa', 'Freie Religionsgemeinschaft Alzey (fa)'),
                    'fb' => _t(__TRAIT__ . '.Religion.fb', 'Freireligiöse Landesgemeinde Baden (fb)'),
                    'fg' => _t(__TRAIT__ . '.Religion.fg', 'Freireligiöse Landesgemeinde Pfalz (fg)'),
                    'fm' => _t(__TRAIT__ . '.Religion.fm', 'Freireligiöse Gemeinde Mainz (fm)'),
                    'fs' => _t(__TRAIT__ . '.Religion.fs', 'Freireligiöse Gemeinde Offenbach (fs)'),
                    'so' => _t(__TRAIT__ . '.Religion.so', 'Hugenotten, Kirche Jesu Christi der Heiligen der letzten Tage, Johanniskirche, Sonstige (so)'),
                    'none' => _t(__TRAIT__ . '.Religion.none', 'keiner steuererhebenden Religionsgesellschaft angehörend (- -)'),
                    'oa' => _t(__TRAIT__ . '.Religion.oa', 'ohne Angaben, gemeinschaftslos, keiner Religionsgemeinschaft angehörig (oa)'),
                ])
                ->setEmptyString($select)
                ->setDescription(_t(__TRAIT__ . '.ReligionDescription', 'Maßgeblich für die Kirchensteuer. Wer einer nicht steuererhebenden Religionsgemeinschaft angehört, wählt „- -“.'))
        );
        $fields->push(
            DropdownField::create($p . 'MaritalStatus', _t(__TRAIT__ . '.MaritalStatus', 'Familienstand'))
                ->setSource([
                    'single' => _t(__TRAIT__ . '.MaritalStatus.single', 'ledig'),
                    'married' => _t(__TRAIT__ . '.MaritalStatus.married', 'verheiratet'),
                    'widowed' => _t(__TRAIT__ . '.MaritalStatus.widowed', 'verwitwet'),
                    'divorced' => _t(__TRAIT__ . '.MaritalStatus.divorced', 'geschieden'),
                    'partnership' => _t(__TRAIT__ . '.MaritalStatus.partnership', 'Lebenspartnerschaft'),
                    'partnership_dissolved' => _t(__TRAIT__ . '.MaritalStatus.partnership_dissolved', 'Lebenspartnerschaft aufgehoben'),
                    'partner_deceased' => _t(__TRAIT__ . '.MaritalStatus.partner_deceased', 'Lebenspartner verstorben'),
                    'unknown' => _t(__TRAIT__ . '.MaritalStatus.unknown', 'nicht bekannt'),
                ])
                ->setEmptyString($select)
        );
        $fields->push(
            Wrapper::create(
                DateField::create($p . 'MaritalSince', _t(__TRAIT__ . '.MaritalSince', 'Familienstand seit (optional)')),
            )
                ->displayIf($p . 'MaritalStatus')->isEqualTo('married')
                ->orIf($p . 'MaritalStatus')->isEqualTo('widowed')
                ->orIf($p . 'MaritalStatus')->isEqualTo('divorced')
                ->orIf($p . 'MaritalStatus')->isEqualTo('partnership')
                ->orIf($p . 'MaritalStatus')->isEqualTo('partnership_dissolved')
                ->orIf($p . 'MaritalStatus')->isEqualTo('partner_deceased')
                ->end()
        );

        if ($n === 1) {
            // Ziffer 10 - one line for the whole Meldeschein
            $fields->push(
                Wrapper::create(
                    LiteralField::create('LastMarriageNotice', '<p>' . _t(__TRAIT__ . '.LastMarriageNotice', 'Tag und Ort der letzten Eheschließung/-scheidung oder der Begründung/Aufhebung der letzten Lebenspartnerschaft:') . '</p>'),
                    DateField::create('Person1LastMarriageDate', _t(__TRAIT__ . '.LastMarriageDate', 'Tag (optional)')),
                    TextField::create('Person1LastMarriagePlace', _t(__TRAIT__ . '.LastMarriagePlace', 'Ort (optional)')),
                )
                    ->displayIf('Person1MaritalStatus')->isEqualTo('married')
                    ->orIf('Person1MaritalStatus')->isEqualTo('widowed')
                    ->orIf('Person1MaritalStatus')->isEqualTo('divorced')
                    ->orIf('Person1MaritalStatus')->isEqualTo('partnership')
                    ->orIf('Person1MaritalStatus')->isEqualTo('partnership_dissolved')
                    ->orIf('Person1MaritalStatus')->isEqualTo('partner_deceased')
                    ->end()
            );
        }

        $fields->push(
            TextField::create($p . 'Nationality', _t(__TRAIT__ . '.Nationality', 'Staatsangehörigkeit(en)'))
                ->setDescription(_t(__TRAIT__ . '.NationalityDescription', 'Bei mehreren Staatsangehörigkeiten bitte alle angeben'))
        );

        $fields->push(HeaderField::create($p . 'DocumentHeader', _t(__TRAIT__ . '.DocumentHeader', 'Personalausweis / Pass / Passersatz / Ankunftsnachweis'), 5));
        $fields->push(
            DropdownField::create($p . 'DocumentType', _t(__TRAIT__ . '.DocumentType', 'Art des Dokuments'))
                ->setSource([
                    'id_card' => _t(__TRAIT__ . '.DocumentType.id_card', 'Personalausweis (PA)'),
                    'passport' => _t(__TRAIT__ . '.DocumentType.passport', 'Reisepass (RP)'),
                    'child_passport' => _t(__TRAIT__ . '.DocumentType.child_passport', 'Kinderreisepass (KRP)'),
                    'travel_document' => _t(__TRAIT__ . '.DocumentType.travel_document', 'Reiseausweis für Ausländer, Flüchtlinge oder Staatenlose (RA)'),
                    'arrival_certificate' => _t(__TRAIT__ . '.DocumentType.arrival_certificate', 'Ankunftsnachweis (AN)'),
                    'none' => _t(__TRAIT__ . '.DocumentType.none', 'kein Dokument'),
                ])
                ->setEmptyString($select)
        );
        $fields->push(
            Wrapper::create(
                TextField::create($p . 'DocumentAuthority', _t(__TRAIT__ . '.DocumentAuthority', 'Ausstellungsbehörde')),
                TextField::create($p . 'DocumentNumber', _t(__TRAIT__ . '.DocumentNumber', 'Seriennummer'))
                    ->setMaxLength(20),
                DateField::create($p . 'DocumentIssued', _t(__TRAIT__ . '.DocumentIssued', 'Ausstellungsdatum')),
                DateField::create($p . 'DocumentValidUntil', _t(__TRAIT__ . '.DocumentValidUntil', 'Gültig bis')),
            )
                ->displayIf($p . 'DocumentType')->isEqualTo('id_card')
                ->orIf($p . 'DocumentType')->isEqualTo('passport')
                ->orIf($p . 'DocumentType')->isEqualTo('child_passport')
                ->orIf($p . 'DocumentType')->isEqualTo('travel_document')
                ->orIf($p . 'DocumentType')->isEqualTo('arrival_certificate')
                ->end()
        );

        $fields->push(HeaderField::create($p . 'BlockHeader', _t(__TRAIT__ . '.BlockHeader', 'Übermittlungs- und Auskunftssperren (optional)'), 5));
        $fields->push(CheckboxField::create($p . 'Block1', _t(__TRAIT__ . '.Block1', 'Nr. 1: gegen die Übermittlung von Daten an eine öffentlich-rechtliche Religionsgesellschaft durch Familienangehörige eines Mitglieds (§ 42 Abs. 3 Satz 2 BMG)')));
        $fields->push(CheckboxField::create($p . 'Block2', _t(__TRAIT__ . '.Block2', 'Nr. 2: gegen die Übermittlung von Daten aus Anlass von Alters- oder Ehejubiläen (§ 50 Abs. 2 BMG)')));
        $fields->push(CheckboxField::create($p . 'Block3', _t(__TRAIT__ . '.Block3', 'Nr. 3: gegen die Übermittlung von Daten an Parteien, Wählergruppen u. a. bei Wahlen und Abstimmungen (§ 50 Abs. 1 BMG)')));
        $fields->push(CheckboxField::create($p . 'Block4', _t(__TRAIT__ . '.Block4', 'Nr. 4: gegen die Übermittlung von Daten an Adressbuchverlage (§ 50 Abs. 3 BMG)')));
        $fields->push(CheckboxField::create($p . 'Block5', _t(__TRAIT__ . '.Block5', 'Nr. 5: gegen die Übermittlung von Daten an das Bundesamt für das Personalmanagement der Bundeswehr (§ 36 Abs. 2 BMG)')));
        $fields->push(CheckboxField::create($p . 'Block6', _t(__TRAIT__ . '.Block6', 'Nr. 6: Sperre jeder Melderegisterauskunft (§ 51 BMG)')));
        $fields->push(
            Wrapper::create(
                LiteralField::create($p . 'Block6Notice', '<div class="alert alert-warning">' . _t(__TRAIT__ . '.Block6Notice', 'Die Auskunftssperre Nr. 6 ist nur bei einer Gefahr für Leben, Gesundheit, persönliche Freiheit oder ähnliche schutzwürdige Interessen möglich. Sie ist gesondert schriftlich zu begründen und auf zwei Jahre befristet (§ 51 Abs. 4 BMG).') . '</div>'),
            )
                ->displayIf($p . 'Block6')->isChecked()->end()
        );

        if ($n < 5) {
            $fields->push(
                DropdownField::create($p . 'More', _t(__TRAIT__ . '.More', 'Möchten Sie eine weitere Person auf diesem Meldeschein anmelden?'))
                    ->setSource([
                        'no' => _t(__TRAIT__ . '.More.no', 'nein'),
                        'yes' => _t(__TRAIT__ . '.More.yes', 'ja'),
                    ])
                    ->setEmptyString($select)
            );
        }

        return $fields;
    }

    public function getValidator(): RequiredFieldsValidator
    {
        $p = 'Person' . static::PERSON;
        $required = [
            $p . 'LastName',
            $p . 'FirstNames',
            $p . 'Gender',
            $p . 'BirthDate',
            $p . 'BirthPlace',
            $p . 'Religion',
            $p . 'MaritalStatus',
            $p . 'Nationality',
            $p . 'DocumentType',
        ];

        if (static::PERSON < 5) {
            $required[] = $p . 'More';
        }

        return RequiredFieldsValidator::create($required);
    }

    /**
     * Next person when "weitere Person" = yes, otherwise the Beiblatt step.
     * Own step data is read directly: the cross-step cache could be stale right after saveData().
     */
    protected function personNextStep(string $nextPersonClass): string
    {
        $more = $this->loadData()['Person' . static::PERSON . 'More'] ?? null;

        return $more === 'yes' ? $nextPersonClass : Form_Step3::class;
    }
}
