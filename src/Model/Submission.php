<?php

declare(strict_types=1);

namespace Kalakotra\EFormAnmeldungFrankfurt\Model;

use app\forms\basemodel\BaseSubmission;
use mikehaertl\pdftk\Pdf;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SilverStripe\Core\Injector\Injector;
use setasign\Fpdi\Fpdi;

/**
 * Anmeldung Frankfurt am Main (Meldeschein BMG, Bürgeramt Frankfurt 01/2016) submission.
 *
 * Mapping conventions (see BaseSubmission::getArrayDataForPDF):
 *   'PDF field' => 'DbField'                       plain value
 *   'PDF field' => 'Method:DbField:Arg'            computed value
 *
 * Every checkbox of this PDF is its own field with the on-state "Ja"; the two combo boxes per person
 * (Familienstand, Ausweisart) need their exact option strings. Both go through StateValue + $state_map.
 * Dates are split into Tag/Monat/Jahr text fields -> DateFormating:Field:d|m|Y.
 * Page 5 (Ausfertigung für die meldepflichtige Person) reuses the field names of page 3.
 *
 * The Religion fields of the PDF are named "1" ... "5". Numeric array keys are renumbered by
 * SilverStripe's config merge, so they are written as "#1" ... "#5" in $field_mapping / $state_map
 * and the prefix is stripped in getArrayDataForPDF().
 */
class Submission extends BaseSubmission
{
    private static string $table_name = 'Kalakotra_EFormAnmeldungFrankfurt_Submission';

    private const CHECKED = ['1' => 'Ja'];

    /** Erläuterungen zu Nummer 6 (Frankfurt). */
    private const RELIGION = [
        'rk' => 'rk',
        'ak' => 'ak',
        'ev' => 'ev',
        'lt' => 'lt',
        'rf' => 'rf',
        'fr' => 'fr',
        'ishe' => 'ishe',
        'il' => 'il',
        'fa' => 'fa',
        'fb' => 'fb',
        'fg' => 'fg',
        'fm' => 'fm',
        'fs' => 'fs',
        'so' => 'so',
        'none' => '- -',
        'oa' => 'oa',
    ];

    /** Combo box options of Dropdown37 / Dropdown41. */
    private const MARITAL_STATUS = [
        'single' => 'ledig',
        'married' => 'verheiratet',
        'widowed' => 'verwitwet',
        'divorced' => 'geschieden',
        'partnership' => 'Lebenspartnerschaft',
        'partnership_dissolved' => 'Lebenspartnerschaft aufgehoben',
        'partner_deceased' => 'Lebenspartner verstorben',
        'unknown' => 'nicht bekannt',
    ];

    /** Dropdown38-40 carry " nicht bekannt" with a leading space. */
    private const MARITAL_STATUS_SPACED = [...self::MARITAL_STATUS, 'unknown' => ' nicht bekannt'];

    /** Familienstand values that have a "seit" date / a last marriage or partnership event. */
    private const MARITAL_EVENT = ['married', 'widowed', 'divorced', 'partnership', 'partnership_dissolved', 'partner_deceased'];

    private const DOCUMENT_TYPE = [
        'id_card' => 'PA',
        'passport' => 'RP',
        'child_passport' => 'KRP',
        'travel_document' => 'RA',
        'arrival_certificate' => 'AN',
    ];

    private const WITH_DOCUMENT = ['id_card', 'passport', 'child_passport', 'travel_document', 'arrival_certificate'];

    private const DOCTORATE = ['dr' => 'Dr.', 'dr_hc' => 'Dr. h. c.', 'dr_eh' => 'Dr. E. h.'];

    private static array $db = [
        // Neue Wohnung
        'RegistrationType' => 'Varchar(20)',
        'MoveInDate' => 'Date',
        'PostalCode' => 'Varchar(10)',
        'District' => 'Varchar(100)',
        'Street' => 'Varchar(150)',
        'AddressAddition' => 'Varchar(150)',

        // Bisherige Wohnung
        'PreviousResidence' => 'Varchar(20)',
        'PreviousPostalCode' => 'Varchar(10)',
        'PreviousCity' => 'Varchar(100)',
        'PreviousStreet' => 'Varchar(150)',
        'PreviousKept' => 'Varchar(10)',
        'PreviousFutureStatus' => 'Varchar(20)',
        'PreviousCountry' => 'Varchar(100)',
        'PreviousForeignAddress' => 'Varchar(255)',
        'PreviousLastDomesticAddress' => 'Varchar(255)',

        // Weitere Wohnungen in Deutschland
        'OtherResidences' => 'Varchar(10)',
        'Other1PostalCode' => 'Varchar(10)',
        'Other1City' => 'Varchar(100)',
        'Other1Street' => 'Varchar(150)',
        'Other1WasSecondary' => 'Boolean',
        'Other1Kept' => 'Varchar(10)',
        'Other1FutureStatus' => 'Varchar(20)',
        'Other2PostalCode' => 'Varchar(10)',
        'Other2City' => 'Varchar(100)',
        'Other2Street' => 'Varchar(150)',
        'Other2WasSecondary' => 'Boolean',
        'Other2Kept' => 'Varchar(10)',
        'Other2FutureStatus' => 'Varchar(20)',

        // Person 1
        'Person1LastName' => 'Varchar(100)',
        'Person1BirthName' => 'Varchar(100)',
        'Person1ReligiousName' => 'Varchar(100)',
        'Person1Doctorate' => 'Varchar(10)',
        'Person1FirstNames' => 'Varchar(150)',
        'Person1Gender' => 'Varchar(20)',
        'Person1BirthDate' => 'Date',
        'Person1BirthPlace' => 'Varchar(150)',
        'Person1Religion' => 'Varchar(10)',
        'Person1MaritalStatus' => 'Varchar(30)',
        'Person1MaritalSince' => 'Date',
        'Person1LastMarriageDate' => 'Date',
        'Person1LastMarriagePlace' => 'Varchar(150)',
        'Person1Nationality' => 'Varchar(150)',
        'Person1DocumentType' => 'Varchar(30)',
        'Person1DocumentAuthority' => 'Varchar(150)',
        'Person1DocumentNumber' => 'Varchar(30)',
        'Person1DocumentIssued' => 'Date',
        'Person1DocumentValidUntil' => 'Date',
        'Person1Block1' => 'Boolean',
        'Person1Block2' => 'Boolean',
        'Person1Block3' => 'Boolean',
        'Person1Block4' => 'Boolean',
        'Person1Block5' => 'Boolean',
        'Person1Block6' => 'Boolean',
        'Person1More' => 'Varchar(10)',

        // Person 2
        'Person2LastName' => 'Varchar(100)',
        'Person2BirthName' => 'Varchar(100)',
        'Person2ReligiousName' => 'Varchar(100)',
        'Person2Doctorate' => 'Varchar(10)',
        'Person2FirstNames' => 'Varchar(150)',
        'Person2Gender' => 'Varchar(20)',
        'Person2BirthDate' => 'Date',
        'Person2BirthPlace' => 'Varchar(150)',
        'Person2Religion' => 'Varchar(10)',
        'Person2MaritalStatus' => 'Varchar(30)',
        'Person2MaritalSince' => 'Date',
        'Person2Nationality' => 'Varchar(150)',
        'Person2DocumentType' => 'Varchar(30)',
        'Person2DocumentAuthority' => 'Varchar(150)',
        'Person2DocumentNumber' => 'Varchar(30)',
        'Person2DocumentIssued' => 'Date',
        'Person2DocumentValidUntil' => 'Date',
        'Person2Block1' => 'Boolean',
        'Person2Block2' => 'Boolean',
        'Person2Block3' => 'Boolean',
        'Person2Block4' => 'Boolean',
        'Person2Block5' => 'Boolean',
        'Person2Block6' => 'Boolean',
        'Person2More' => 'Varchar(10)',

        // Person 3
        'Person3LastName' => 'Varchar(100)',
        'Person3BirthName' => 'Varchar(100)',
        'Person3ReligiousName' => 'Varchar(100)',
        'Person3Doctorate' => 'Varchar(10)',
        'Person3FirstNames' => 'Varchar(150)',
        'Person3Gender' => 'Varchar(20)',
        'Person3BirthDate' => 'Date',
        'Person3BirthPlace' => 'Varchar(150)',
        'Person3Religion' => 'Varchar(10)',
        'Person3MaritalStatus' => 'Varchar(30)',
        'Person3MaritalSince' => 'Date',
        'Person3Nationality' => 'Varchar(150)',
        'Person3DocumentType' => 'Varchar(30)',
        'Person3DocumentAuthority' => 'Varchar(150)',
        'Person3DocumentNumber' => 'Varchar(30)',
        'Person3DocumentIssued' => 'Date',
        'Person3DocumentValidUntil' => 'Date',
        'Person3Block1' => 'Boolean',
        'Person3Block2' => 'Boolean',
        'Person3Block3' => 'Boolean',
        'Person3Block4' => 'Boolean',
        'Person3Block5' => 'Boolean',
        'Person3Block6' => 'Boolean',
        'Person3More' => 'Varchar(10)',

        // Person 4
        'Person4LastName' => 'Varchar(100)',
        'Person4BirthName' => 'Varchar(100)',
        'Person4ReligiousName' => 'Varchar(100)',
        'Person4Doctorate' => 'Varchar(10)',
        'Person4FirstNames' => 'Varchar(150)',
        'Person4Gender' => 'Varchar(20)',
        'Person4BirthDate' => 'Date',
        'Person4BirthPlace' => 'Varchar(150)',
        'Person4Religion' => 'Varchar(10)',
        'Person4MaritalStatus' => 'Varchar(30)',
        'Person4MaritalSince' => 'Date',
        'Person4Nationality' => 'Varchar(150)',
        'Person4DocumentType' => 'Varchar(30)',
        'Person4DocumentAuthority' => 'Varchar(150)',
        'Person4DocumentNumber' => 'Varchar(30)',
        'Person4DocumentIssued' => 'Date',
        'Person4DocumentValidUntil' => 'Date',
        'Person4Block1' => 'Boolean',
        'Person4Block2' => 'Boolean',
        'Person4Block3' => 'Boolean',
        'Person4Block4' => 'Boolean',
        'Person4Block5' => 'Boolean',
        'Person4Block6' => 'Boolean',
        'Person4More' => 'Varchar(10)',

        // Person 5
        'Person5LastName' => 'Varchar(100)',
        'Person5BirthName' => 'Varchar(100)',
        'Person5ReligiousName' => 'Varchar(100)',
        'Person5Doctorate' => 'Varchar(10)',
        'Person5FirstNames' => 'Varchar(150)',
        'Person5Gender' => 'Varchar(20)',
        'Person5BirthDate' => 'Date',
        'Person5BirthPlace' => 'Varchar(150)',
        'Person5Religion' => 'Varchar(10)',
        'Person5MaritalStatus' => 'Varchar(30)',
        'Person5MaritalSince' => 'Date',
        'Person5Nationality' => 'Varchar(150)',
        'Person5DocumentType' => 'Varchar(30)',
        'Person5DocumentAuthority' => 'Varchar(150)',
        'Person5DocumentNumber' => 'Varchar(30)',
        'Person5DocumentIssued' => 'Date',
        'Person5DocumentValidUntil' => 'Date',
        'Person5Block1' => 'Boolean',
        'Person5Block2' => 'Boolean',
        'Person5Block3' => 'Boolean',
        'Person5Block4' => 'Boolean',
        'Person5Block5' => 'Boolean',
        'Person5Block6' => 'Boolean',

        // Beiblatt (Ziffer 16 - 18)
        'SpouseNotMoving' => 'Varchar(10)',
        'SpouseLastName' => 'Varchar(150)',
        'SpouseFirstNames' => 'Varchar(150)',
        'SpouseBirthDate' => 'Date',
        'SpouseAddress' => 'Varchar(255)',
        'HasRepresentative' => 'Varchar(10)',
        'RepresentativeLastName' => 'Varchar(150)',
        'RepresentativeFirstNames' => 'Varchar(150)',
        'RepresentativeBirthDate' => 'Date',
        'RepresentativeAddress' => 'Varchar(255)',
        'ChildNotMoving' => 'Varchar(10)',
        'ChildLastName' => 'Varchar(150)',
        'ChildFirstNames' => 'Varchar(150)',
        'ChildBirthDate' => 'Date',
        'ChildAddress' => 'Varchar(255)',
        'WeaponPermit' => 'Varchar(10)',
        'ExplosivesPermit' => 'Varchar(10)',
        'Address1939' => 'Varchar(255)',
    ];

    private static array $field_mapping = [
        // Kopf: Anmeldung einer ... (must match "die Wohnung soll sein" of the new residence)
        'Check Box1' => 'StateValue:RegistrationType:Check Box1',
        'Check Box2' => 'StateValue:RegistrationType:Check Box2',

        // Neue Wohnung ("Frankfurt am Main" is pre-printed)
        'Text3' => 'DateFormating:MoveInDate:d',
        'Text4' => 'DateFormating:MoveInDate:m',
        'Text5' => 'DateFormating:MoveInDate:Y',
        'Text6' => 'NewPlaceLine:PostalCode:1',
        'Text7' => 'NewStreetLine:Street:1',
        'Check Box3' => 'StateValue:RegistrationType:Check Box3',
        'Check Box4' => 'StateValue:RegistrationType:Check Box4',

        // Bisherige Wohnung
        'Bisherige Wohnung Zuzug von bisheriger oder weiter bestehender Wohnung falls Zuzug aus dem Ausland Angabe des Staates sowie letzte frühere Anschrift im Inland' => 'PreviousLine:PreviousResidence:1',
        'Bisherige Wohnung Zuzug von bisheriger oder weiter bestehender Wohnung falls Zuzug aus dem Ausland Angabe des Staates sowie letzte frühere Anschrift im Inland_2' => 'PreviousLine:PreviousResidence:2',
        'Check Box5' => 'StateValue:PreviousKept:Check Box5',
        'Check Box6' => 'StateValue:PreviousKept:Check Box6',
        'Check Box7' => 'StateValue:PreviousFutureStatus:Check Box7',
        'Check Box8' => 'StateValue:PreviousFutureStatus:Check Box8',

        // Weitere Wohnungen in Deutschland
        'Weitere Wohnungen in Deutschland' => 'OtherLine:Other1PostalCode:1',
        'Weitere Wohnungen in Deutschland_2' => 'OtherLine:Other1PostalCode:2',
        'Check Box9' => 'StateValue:Other1WasSecondary:Check Box9',
        'Check Box10' => 'StateValue:Other1Kept:Check Box10',
        'Check Box11' => 'StateValue:Other1Kept:Check Box11',
        'Check Box12' => 'StateValue:Other1FutureStatus:Check Box12',
        'Check Box13' => 'StateValue:Other1FutureStatus:Check Box13',
        'Weitere Wohnungen in Deutschland_3' => 'OtherLine:Other2PostalCode:1',
        'Weitere Wohnungen in Deutschland_4' => 'OtherLine:Other2PostalCode:2',
        'Check Box14' => 'StateValue:Other2WasSecondary:Check Box14',
        'Check Box15' => 'StateValue:Other2Kept:Check Box15',
        'Check Box16' => 'StateValue:Other2Kept:Check Box16',
        'Check Box17' => 'StateValue:Other2FutureStatus:Check Box17',
        'Check Box18' => 'StateValue:Other2FutureStatus:Check Box18',

        // Person 1
        '1  Familienname ggf auch abweichende Geburtsnamen Ordensund Künstlernamen Doktorgrad1' => 'FamilyName:Person1LastName:1',
        '2 Vornamen Rufnamen unterstreichen1' => 'Person1FirstNames',
        'Check Box19' => 'StateValue:Person1Gender:Check Box19',
        'Check Box20' => 'StateValue:Person1Gender:Check Box20',
        'Text33' => 'DateFormating:Person1BirthDate:d',
        'Text34' => 'DateFormating:Person1BirthDate:m',
        'Text01' => 'DateFormating:Person1BirthDate:Y',
        'Text47' => 'Person1BirthPlace',
        '#1' => 'StateValue:Person1Religion:#1',
        'Dropdown37' => 'StateValue:Person1MaritalStatus:Dropdown37',
        'Text02' => 'DateFormating:Person1MaritalSince:d',
        'Text52' => 'DateFormating:Person1MaritalSince:m',
        'Text53' => 'DateFormating:Person1MaritalSince:Y',
        '9 Staatsangehörigkeiten1' => 'Person1Nationality',
        '11 Ausstellungsbehörde1' => 'Person1DocumentAuthority',
        'Dropdown10' => 'StateValue:Person1DocumentType:Dropdown10',
        '12 PassAusweisart und SerienNr s Erläuterungen Art Seriennummer1_2' => 'Person1DocumentNumber',
        'Text66' => 'DateFormating:Person1DocumentIssued:d',
        'Text67' => 'DateFormating:Person1DocumentIssued:m',
        'Text68' => 'DateFormating:Person1DocumentIssued:Y',
        'Text81' => 'DateFormating:Person1DocumentValidUntil:d',
        'Text82' => 'DateFormating:Person1DocumentValidUntil:m',
        'Text83' => 'DateFormating:Person1DocumentValidUntil:Y',
        'Check Box29' => 'StateValue:Person1Block1:Check Box29',
        'Check Box30' => 'StateValue:Person1Block2:Check Box30',
        'Check Box31' => 'StateValue:Person1Block3:Check Box31',
        'Check Box32' => 'StateValue:Person1Block4:Check Box32',
        'Check Box33' => 'StateValue:Person1Block5:Check Box33',
        'Check Box34' => 'StateValue:Person1Block6:Check Box34',

        // Person 2
        '1  Familienname ggf auch abweichende Geburtsnamen Ordensund Künstlernamen Doktorgrad2' => 'FamilyName:Person2LastName:2',
        '2 Vornamen Rufnamen unterstreichen2' => 'Person2FirstNames',
        'Check Box21' => 'StateValue:Person2Gender:Check Box21',
        'Check Box22' => 'StateValue:Person2Gender:Check Box22',
        'Text35' => 'DateFormating:Person2BirthDate:d',
        'Text36' => 'DateFormating:Person2BirthDate:m',
        'Text37' => 'DateFormating:Person2BirthDate:Y',
        'Text48' => 'Person2BirthPlace',
        '#2' => 'StateValue:Person2Religion:#2',
        'Dropdown38' => 'StateValue:Person2MaritalStatus:Dropdown38',
        'Text54' => 'DateFormating:Person2MaritalSince:d',
        'Text55' => 'DateFormating:Person2MaritalSince:m',
        'Text56' => 'DateFormating:Person2MaritalSince:Y',
        '9 Staatsangehörigkeiten2' => 'Person2Nationality',
        '11 Ausstellungsbehörde2' => 'Person2DocumentAuthority',
        'Dropdown11' => 'StateValue:Person2DocumentType:Dropdown11',
        '12 PassAusweisart und SerienNr s Erläuterungen Art Seriennummer2_2' => 'Person2DocumentNumber',
        'Text69' => 'DateFormating:Person2DocumentIssued:d',
        'Text70' => 'DateFormating:Person2DocumentIssued:m',
        'Text71' => 'DateFormating:Person2DocumentIssued:Y',
        'Text84' => 'DateFormating:Person2DocumentValidUntil:d',
        'Text85' => 'DateFormating:Person2DocumentValidUntil:m',
        'Text86' => 'DateFormating:Person2DocumentValidUntil:Y',
        'Check Box35' => 'StateValue:Person2Block1:Check Box35',
        'Check Box36' => 'StateValue:Person2Block2:Check Box36',
        'Check Box37' => 'StateValue:Person2Block3:Check Box37',
        'Check Box38' => 'StateValue:Person2Block4:Check Box38',
        'Check Box39' => 'StateValue:Person2Block5:Check Box39',
        'Check Box40' => 'StateValue:Person2Block6:Check Box40',

        // Person 3
        '1  Familienname ggf auch abweichende Geburtsnamen Ordensund Künstlernamen Doktorgrad3' => 'FamilyName:Person3LastName:3',
        '2 Vornamen Rufnamen unterstreichen3' => 'Person3FirstNames',
        'Check Box23' => 'StateValue:Person3Gender:Check Box23',
        'Check Box24' => 'StateValue:Person3Gender:Check Box24',
        'Text38' => 'DateFormating:Person3BirthDate:d',
        'Text39' => 'DateFormating:Person3BirthDate:m',
        'Text40' => 'DateFormating:Person3BirthDate:Y',
        'Text49' => 'Person3BirthPlace',
        '#3' => 'StateValue:Person3Religion:#3',
        'Dropdown39' => 'StateValue:Person3MaritalStatus:Dropdown39',
        'Text57' => 'DateFormating:Person3MaritalSince:d',
        'Text58' => 'DateFormating:Person3MaritalSince:m',
        'Text59' => 'DateFormating:Person3MaritalSince:Y',
        '9 Staatsangehörigkeiten3' => 'Person3Nationality',
        '11 Ausstellungsbehörde3' => 'Person3DocumentAuthority',
        'Dropdown12' => 'StateValue:Person3DocumentType:Dropdown12',
        '12 PassAusweisart und SerienNr s Erläuterungen Art Seriennummer3_2' => 'Person3DocumentNumber',
        'Text72' => 'DateFormating:Person3DocumentIssued:d',
        'Text73' => 'DateFormating:Person3DocumentIssued:m',
        'Text74' => 'DateFormating:Person3DocumentIssued:Y',
        'Text87' => 'DateFormating:Person3DocumentValidUntil:d',
        'Text88' => 'DateFormating:Person3DocumentValidUntil:m',
        'Text89' => 'DateFormating:Person3DocumentValidUntil:Y',
        'Check Box41' => 'StateValue:Person3Block1:Check Box41',
        'Check Box42' => 'StateValue:Person3Block2:Check Box42',
        'Check Box43' => 'StateValue:Person3Block3:Check Box43',
        'Check Box44' => 'StateValue:Person3Block4:Check Box44',
        'Check Box45' => 'StateValue:Person3Block5:Check Box45',
        'Check Box46' => 'StateValue:Person3Block6:Check Box46',

        // Person 4
        '1  Familienname ggf auch abweichende Geburtsnamen Ordensund Künstlernamen Doktorgrad4' => 'FamilyName:Person4LastName:4',
        '2 Vornamen Rufnamen unterstreichen4' => 'Person4FirstNames',
        'Check Box25' => 'StateValue:Person4Gender:Check Box25',
        'Check Box26' => 'StateValue:Person4Gender:Check Box26',
        'Text41' => 'DateFormating:Person4BirthDate:d',
        'Text42' => 'DateFormating:Person4BirthDate:m',
        'Text43' => 'DateFormating:Person4BirthDate:Y',
        'Text50' => 'Person4BirthPlace',
        '#4' => 'StateValue:Person4Religion:#4',
        'Dropdown40' => 'StateValue:Person4MaritalStatus:Dropdown40',
        'Text60' => 'DateFormating:Person4MaritalSince:d',
        'Text61' => 'DateFormating:Person4MaritalSince:m',
        'Text62' => 'DateFormating:Person4MaritalSince:Y',
        '9 Staatsangehörigkeiten4' => 'Person4Nationality',
        '11 Ausstellungsbehörde4' => 'Person4DocumentAuthority',
        'Dropdown13' => 'StateValue:Person4DocumentType:Dropdown13',
        '12 PassAusweisart und SerienNr s Erläuterungen Art Seriennummer4_2' => 'Person4DocumentNumber',
        'Text75' => 'DateFormating:Person4DocumentIssued:d',
        'Text76' => 'DateFormating:Person4DocumentIssued:m',
        'Text77' => 'DateFormating:Person4DocumentIssued:Y',
        'Text90' => 'DateFormating:Person4DocumentValidUntil:d',
        'Text91' => 'DateFormating:Person4DocumentValidUntil:m',
        'Text92' => 'DateFormating:Person4DocumentValidUntil:Y',
        'Check Box47' => 'StateValue:Person4Block1:Check Box47',
        'Check Box48' => 'StateValue:Person4Block2:Check Box48',
        'Check Box49' => 'StateValue:Person4Block3:Check Box49',
        'Check Box50' => 'StateValue:Person4Block4:Check Box50',
        'Check Box51' => 'StateValue:Person4Block5:Check Box51',
        'Check Box52' => 'StateValue:Person4Block6:Check Box52',

        // Person 5
        '1  Familienname ggf auch abweichende Geburtsnamen Ordensund Künstlernamen Doktorgrad5' => 'FamilyName:Person5LastName:5',
        '2 Vornamen Rufnamen unterstreichen5' => 'Person5FirstNames',
        'Check Box27' => 'StateValue:Person5Gender:Check Box27',
        'Check Box28' => 'StateValue:Person5Gender:Check Box28',
        'Text44' => 'DateFormating:Person5BirthDate:d',
        'Text45' => 'DateFormating:Person5BirthDate:m',
        'Text46' => 'DateFormating:Person5BirthDate:Y',
        'Text51' => 'Person5BirthPlace',
        '#5' => 'StateValue:Person5Religion:#5',
        'Dropdown41' => 'StateValue:Person5MaritalStatus:Dropdown41',
        'Text63' => 'DateFormating:Person5MaritalSince:d',
        'Text64' => 'DateFormating:Person5MaritalSince:m',
        'Text65' => 'DateFormating:Person5MaritalSince:Y',
        '9 Staatsangehörigkeiten5' => 'Person5Nationality',
        '11 Ausstellungsbehörde5' => 'Person5DocumentAuthority',
        'Dropdown14' => 'StateValue:Person5DocumentType:Dropdown14',
        '12 PassAusweisart und SerienNr s Erläuterungen Art Seriennummer5_2' => 'Person5DocumentNumber',
        'Text78' => 'DateFormating:Person5DocumentIssued:d',
        'Text79' => 'DateFormating:Person5DocumentIssued:m',
        'Text80' => 'DateFormating:Person5DocumentIssued:Y',
        'Text93' => 'DateFormating:Person5DocumentValidUntil:d',
        'Text94' => 'DateFormating:Person5DocumentValidUntil:m',
        'Text95' => 'DateFormating:Person5DocumentValidUntil:Y',
        'Check Box53' => 'StateValue:Person5Block1:Check Box53',
        'Check Box54' => 'StateValue:Person5Block2:Check Box54',
        'Check Box55' => 'StateValue:Person5Block3:Check Box55',
        'Check Box56' => 'StateValue:Person5Block4:Check Box56',
        'Check Box57' => 'StateValue:Person5Block5:Check Box57',
        'Check Box58' => 'StateValue:Person5Block6:Check Box58',

        // Ziffer 10
        'Text1' => 'MarriageLine:Person1LastMarriagePlace:1',

        // Beiblatt 16 a) Ehegatte/Lebenspartner
        'Text14' => 'SpouseLastName',
        'VornamenRow1' => 'SpouseFirstNames',
        'Text11' => 'DateFormating:SpouseBirthDate:d',
        'Text12' => 'DateFormating:SpouseBirthDate:m',
        'Text13' => 'DateFormating:SpouseBirthDate:Y',
        'Text16' => 'SpouseAddress',

        // Beiblatt 16 b) Gesetzliche Vertreter
        'Text15' => 'RepresentativeLastName',
        'VornamenRow1_2' => 'RepresentativeFirstNames',
        'Text20' => 'DateFormating:RepresentativeBirthDate:d',
        'Text21' => 'DateFormating:RepresentativeBirthDate:m',
        'Text22' => 'DateFormating:RepresentativeBirthDate:Y',
        'Text17' => 'RepresentativeAddress',

        // Beiblatt 16 c) minderjährige Kinder
        'Text19' => 'ChildLastName',
        'VornamenRow1_3' => 'ChildFirstNames',
        'Text23' => 'DateFormating:ChildBirthDate:d',
        'Text24' => 'DateFormating:ChildBirthDate:m',
        'Text25' => 'DateFormating:ChildBirthDate:Y',
        'Text18' => 'ChildAddress',

        // Beiblatt 17, 18
        'Check Box59' => 'StateValue:WeaponPermit:Check Box59',
        'Check Box60' => 'StateValue:WeaponPermit:Check Box60',
        'Check Box61' => 'StateValue:ExplosivesPermit:Check Box61',
        'Check Box62' => 'StateValue:ExplosivesPermit:Check Box62',
        '18 Anschrift am 1 September 1939 nur bei Flüchtlingen und VertriebenenRow1' => 'Address1939',

        // Not filled on purpose: Tagesstempel and "Meldebehörde Ort, Datum, Stempel, Unterschrift" (authority).
    ];

    /**
     * PDF field => [stored value => PDF value]. Values not listed leave the box unchecked / the field empty.
     * Checkbox on-state "Ja" and combo option strings verified with `pdftk dump_data_fields` and a rendered test fill.
     */
    private static array $state_map = [
        'Check Box1' => ['main' => 'Ja'],
        'Check Box2' => ['secondary' => 'Ja'],
        'Check Box3' => ['main' => 'Ja'],
        'Check Box4' => ['secondary' => 'Ja'],
        'Check Box5' => ['no' => 'Ja'],
        'Check Box6' => ['yes' => 'Ja'],
        'Check Box7' => ['main' => 'Ja'],
        'Check Box8' => ['secondary' => 'Ja'],
        'Check Box9' => self::CHECKED,
        'Check Box10' => ['no' => 'Ja'],
        'Check Box11' => ['yes' => 'Ja'],
        'Check Box12' => ['main' => 'Ja'],
        'Check Box13' => ['secondary' => 'Ja'],
        'Check Box14' => self::CHECKED,
        'Check Box15' => ['no' => 'Ja'],
        'Check Box16' => ['yes' => 'Ja'],
        'Check Box17' => ['main' => 'Ja'],
        'Check Box18' => ['secondary' => 'Ja'],
        'Check Box19' => ['female' => 'Ja'],
        'Check Box20' => ['male' => 'Ja'],
        '#1' => self::RELIGION,
        'Dropdown37' => self::MARITAL_STATUS,
        'Dropdown10' => self::DOCUMENT_TYPE,
        'Check Box29' => self::CHECKED,
        'Check Box30' => self::CHECKED,
        'Check Box31' => self::CHECKED,
        'Check Box32' => self::CHECKED,
        'Check Box33' => self::CHECKED,
        'Check Box34' => self::CHECKED,
        'Check Box21' => ['female' => 'Ja'],
        'Check Box22' => ['male' => 'Ja'],
        '#2' => self::RELIGION,
        'Dropdown38' => self::MARITAL_STATUS_SPACED,
        'Dropdown11' => self::DOCUMENT_TYPE,
        'Check Box35' => self::CHECKED,
        'Check Box36' => self::CHECKED,
        'Check Box37' => self::CHECKED,
        'Check Box38' => self::CHECKED,
        'Check Box39' => self::CHECKED,
        'Check Box40' => self::CHECKED,
        'Check Box23' => ['female' => 'Ja'],
        'Check Box24' => ['male' => 'Ja'],
        '#3' => self::RELIGION,
        'Dropdown39' => self::MARITAL_STATUS_SPACED,
        'Dropdown12' => self::DOCUMENT_TYPE,
        'Check Box41' => self::CHECKED,
        'Check Box42' => self::CHECKED,
        'Check Box43' => self::CHECKED,
        'Check Box44' => self::CHECKED,
        'Check Box45' => self::CHECKED,
        'Check Box46' => self::CHECKED,
        'Check Box25' => ['female' => 'Ja'],
        'Check Box26' => ['male' => 'Ja'],
        '#4' => self::RELIGION,
        'Dropdown40' => self::MARITAL_STATUS_SPACED,
        'Dropdown13' => self::DOCUMENT_TYPE,
        'Check Box47' => self::CHECKED,
        'Check Box48' => self::CHECKED,
        'Check Box49' => self::CHECKED,
        'Check Box50' => self::CHECKED,
        'Check Box51' => self::CHECKED,
        'Check Box52' => self::CHECKED,
        'Check Box27' => ['female' => 'Ja'],
        'Check Box28' => ['male' => 'Ja'],
        '#5' => self::RELIGION,
        'Dropdown41' => self::MARITAL_STATUS,
        'Dropdown14' => self::DOCUMENT_TYPE,
        'Check Box53' => self::CHECKED,
        'Check Box54' => self::CHECKED,
        'Check Box55' => self::CHECKED,
        'Check Box56' => self::CHECKED,
        'Check Box57' => self::CHECKED,
        'Check Box58' => self::CHECKED,
        'Check Box59' => ['no' => 'Ja'],
        'Check Box60' => ['yes' => 'Ja'],
        'Check Box61' => ['no' => 'Ja'],
        'Check Box62' => ['yes' => 'Ja'],
    ];

    /**
     * Dependency rules, mirrored by the display-logic in the steps (see FieldConditions for the format).
     * Order matters: parents before children (Person N is gated by Person N-1 "weitere Person").
     */
    private static array $field_conditions = [
        // Bisherige Wohnung
        [['PreviousPostalCode', 'PreviousCity', 'PreviousStreet', 'PreviousKept'], ['PreviousResidence' => 'domestic']],
        [['PreviousFutureStatus'], ['PreviousKept' => 'yes']],
        [['PreviousCountry', 'PreviousForeignAddress', 'PreviousLastDomesticAddress'], ['PreviousResidence' => 'abroad']],

        // Weitere Wohnungen
        [
            ['Other1PostalCode', 'Other1City', 'Other1Street', 'Other1WasSecondary', 'Other1Kept'],
            ['OtherResidences' => ['one', 'two']],
        ],
        [['Other1FutureStatus'], ['Other1Kept' => 'yes']],
        [
            ['Other2PostalCode', 'Other2City', 'Other2Street', 'Other2WasSecondary', 'Other2Kept'],
            ['OtherResidences' => 'two'],
        ],
        [['Other2FutureStatus'], ['Other2Kept' => 'yes']],

        // Person 1
        [['Person1MaritalSince'], ['Person1MaritalStatus' => self::MARITAL_EVENT]],
        [['Person1LastMarriageDate', 'Person1LastMarriagePlace'], ['Person1MaritalStatus' => self::MARITAL_EVENT]],
        [
            ['Person1DocumentAuthority', 'Person1DocumentNumber', 'Person1DocumentIssued', 'Person1DocumentValidUntil'],
            ['Person1DocumentType' => self::WITH_DOCUMENT],
        ],
        // Person 2
        [
            [
                'Person2LastName', 'Person2BirthName', 'Person2ReligiousName', 'Person2Doctorate',
                'Person2FirstNames', 'Person2Gender', 'Person2BirthDate', 'Person2BirthPlace',
                'Person2Religion', 'Person2MaritalStatus', 'Person2MaritalSince', 'Person2Nationality',
                'Person2DocumentType', 'Person2DocumentAuthority', 'Person2DocumentNumber',
                'Person2DocumentIssued', 'Person2DocumentValidUntil', 'Person2Block1', 'Person2Block2',
                'Person2Block3', 'Person2Block4', 'Person2Block5', 'Person2Block6', 'Person2More',
            ],
            ['Person1More' => 'yes'],
        ],
        [['Person2MaritalSince'], ['Person2MaritalStatus' => self::MARITAL_EVENT]],
        [
            ['Person2DocumentAuthority', 'Person2DocumentNumber', 'Person2DocumentIssued', 'Person2DocumentValidUntil'],
            ['Person2DocumentType' => self::WITH_DOCUMENT],
        ],
        // Person 3
        [
            [
                'Person3LastName', 'Person3BirthName', 'Person3ReligiousName', 'Person3Doctorate',
                'Person3FirstNames', 'Person3Gender', 'Person3BirthDate', 'Person3BirthPlace',
                'Person3Religion', 'Person3MaritalStatus', 'Person3MaritalSince', 'Person3Nationality',
                'Person3DocumentType', 'Person3DocumentAuthority', 'Person3DocumentNumber',
                'Person3DocumentIssued', 'Person3DocumentValidUntil', 'Person3Block1', 'Person3Block2',
                'Person3Block3', 'Person3Block4', 'Person3Block5', 'Person3Block6', 'Person3More',
            ],
            ['Person2More' => 'yes'],
        ],
        [['Person3MaritalSince'], ['Person3MaritalStatus' => self::MARITAL_EVENT]],
        [
            ['Person3DocumentAuthority', 'Person3DocumentNumber', 'Person3DocumentIssued', 'Person3DocumentValidUntil'],
            ['Person3DocumentType' => self::WITH_DOCUMENT],
        ],
        // Person 4
        [
            [
                'Person4LastName', 'Person4BirthName', 'Person4ReligiousName', 'Person4Doctorate',
                'Person4FirstNames', 'Person4Gender', 'Person4BirthDate', 'Person4BirthPlace',
                'Person4Religion', 'Person4MaritalStatus', 'Person4MaritalSince', 'Person4Nationality',
                'Person4DocumentType', 'Person4DocumentAuthority', 'Person4DocumentNumber',
                'Person4DocumentIssued', 'Person4DocumentValidUntil', 'Person4Block1', 'Person4Block2',
                'Person4Block3', 'Person4Block4', 'Person4Block5', 'Person4Block6', 'Person4More',
            ],
            ['Person3More' => 'yes'],
        ],
        [['Person4MaritalSince'], ['Person4MaritalStatus' => self::MARITAL_EVENT]],
        [
            ['Person4DocumentAuthority', 'Person4DocumentNumber', 'Person4DocumentIssued', 'Person4DocumentValidUntil'],
            ['Person4DocumentType' => self::WITH_DOCUMENT],
        ],
        // Person 5
        [
            [
                'Person5LastName', 'Person5BirthName', 'Person5ReligiousName', 'Person5Doctorate',
                'Person5FirstNames', 'Person5Gender', 'Person5BirthDate', 'Person5BirthPlace',
                'Person5Religion', 'Person5MaritalStatus', 'Person5MaritalSince', 'Person5Nationality',
                'Person5DocumentType', 'Person5DocumentAuthority', 'Person5DocumentNumber',
                'Person5DocumentIssued', 'Person5DocumentValidUntil', 'Person5Block1', 'Person5Block2',
                'Person5Block3', 'Person5Block4', 'Person5Block5', 'Person5Block6',
            ],
            ['Person4More' => 'yes'],
        ],
        [['Person5MaritalSince'], ['Person5MaritalStatus' => self::MARITAL_EVENT]],
        [
            ['Person5DocumentAuthority', 'Person5DocumentNumber', 'Person5DocumentIssued', 'Person5DocumentValidUntil'],
            ['Person5DocumentType' => self::WITH_DOCUMENT],
        ],

        // Beiblatt
        [['SpouseLastName', 'SpouseFirstNames', 'SpouseBirthDate', 'SpouseAddress'], ['SpouseNotMoving' => 'yes']],
        [
            ['RepresentativeLastName', 'RepresentativeFirstNames', 'RepresentativeBirthDate', 'RepresentativeAddress'],
            ['HasRepresentative' => 'yes'],
        ],
        [['ChildLastName', 'ChildFirstNames', 'ChildBirthDate', 'ChildAddress'], ['ChildNotMoving' => 'yes']],
    ];

    /**
     * Signature per page ("page<N>", string keys survive the config merge) in mm from the top-left corner (3 = Meldeschein, 4 = Beiblatt, 5 = Ausfertigung
     * für die meldepflichtige Person). PDFMultiFormPage.SignaturePositionX/Y override x/y on pages 3 and 5.
     *
     * @var array<int, array{x: float, y: float, width: float}>
     */
    private static array $signature_positions = [
        'page3' => ['x' => 135.0, 'y' => 273.0, 'width' => 36.0],
        'page4' => ['x' => 134.0, 'y' => 231.5, 'width' => 44.0],
        'page5' => ['x' => 135.0, 'y' => 273.0, 'width' => 36.0],
    ];

    /**
     * TTF used by pdftk for field appearances (needed for umlauts in auto-sized fields).
     * Relative to the module root or absolute; set to '' to disable. Liberation Sans: SIL OFL 1.1, see fonts/.
     */
    private static string $replacement_font = 'fonts/LiberationSans-Regular.ttf';

    /** Pages whose signature position follows the CMS override (same layout). */
    private const CMS_POSITION_PAGES = [3, 5];

    /** Characters that fit the fixed-size first line of the new residence ("Text6", 40 mm at 12 pt). */
    private const NEW_PLACE_LINE_MAX = 16;

    /** Characters that fit the fixed-size first line of "Bisherige Wohnung" (83 mm at 12 pt). */
    private const PREVIOUS_LINE_MAX = 36;

    /** Marks a numeric PDF field name in the config (see class doc). */
    private const NUMERIC_FIELD_PREFIX = '#';

    /**
     * Safety net: values of inactive (hidden) fields are never stored, whatever the steps delivered.
     */
    protected function onBeforeWrite(): void
    {
        parent::onBeforeWrite();

        $rules = (array) $this->config()->get('field_conditions');
        $data = [];
        foreach (FieldConditions::referencedFields($rules) as $field) {
            $data[$field] = $this->getField($field);
        }

        $cleaned = FieldConditions::apply($data, $rules);
        foreach ($cleaned as $field => $value) {
            if ($value === null && $data[$field] !== null) {
                $this->setField($field, null);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getArrayDataForPDF(): array
    {
        $data = [];
        foreach (parent::getArrayDataForPDF() as $pdfField => $value) {
            $pdfField = (string) $pdfField;
            if (str_starts_with($pdfField, self::NUMERIC_FIELD_PREFIX)) {
                $pdfField = substr($pdfField, strlen(self::NUMERIC_FIELD_PREFIX));
            }
            $data[$pdfField] = $value;
        }

        return $data;
    }

    // ---------------------------------------------------------------------------------------------
    // Mapping helpers (signature fixed by BaseSubmission::getArrayDataForPDF: method($field, $arg))
    // ---------------------------------------------------------------------------------------------

    /** Checkbox / combo box: stored value -> PDF value of $pdfField (via $state_map). */
    public function StateValue(string $fieldName, string $pdfField): string
    {
        $map = (array) ($this->config()->get('state_map')[$pdfField] ?? []);
        $value = $this->getField($fieldName);

        if (is_bool($value)) {
            $value = (int) $value;
        }

        return (string) ($map[(string) ($value ?? '')] ?? '');
    }

    /**
     * Ziffer 1 "Familienname (ggf. auch abweichende Geburtsnamen), Ordens- und Künstlernamen, Doktorgrad"
     * for person $person, e.g. "Dr. Mustermann geb. Musterfrau (Schwester Maria)".
     */
    public function FamilyName(string $fieldName, string $person): string
    {
        $p = 'Person' . (int) $person;
        $doctorate = self::DOCTORATE[(string) $this->getField($p . 'Doctorate')] ?? '';
        $birthName = $this->text($p . 'BirthName');
        $religiousName = $this->text($p . 'ReligiousName');

        $name = trim($doctorate . ' ' . $this->text($fieldName));
        if ($birthName !== '') {
            $name .= ' geb. ' . $birthName;
        }
        if ($religiousName !== '') {
            $name .= ' (' . $religiousName . ')';
        }

        return $name;
    }

    /**
     * New residence, first line: PLZ + Ortsteil (the PDF prints "Frankfurt am Main" right behind it).
     * The field has a fixed 12 pt font and clips at ~16 characters; a longer Ortsteil moves to line 2.
     */
    public function NewPlaceLine(string $fieldName, string $unused = ''): string
    {
        return $this->districtFitsFirstLine()
            ? $this->join(' ', [$this->text($fieldName), $this->text('District')])
            : $this->text($fieldName);
    }

    /** New residence, second line: Straße, Hausnummer, Adressierungszusatz (+ Ortsteil if it did not fit line 1). */
    public function NewStreetLine(string $fieldName, string $unused = ''): string
    {
        $line = $this->join(', ', [$this->text($fieldName), $this->text('AddressAddition')]);
        $district = $this->text('District');

        if ($district !== '' && !$this->districtFitsFirstLine()) {
            $line = $this->join(' ', [$line, '(' . $district . ')']);
        }

        return $line;
    }

    private function districtFitsFirstLine(): bool
    {
        $line = $this->join(' ', [$this->text('PostalCode'), $this->text('District')]);

        return mb_strlen($line) <= self::NEW_PLACE_LINE_MAX;
    }

    /**
     * Bisherige Wohnung, line 1/2. Inland: "PLZ Ort" / "Straße".
     * Ausland: "Staat, Anschrift" / "letzte Anschrift im Inland". Line 1 has a fixed 12 pt font (~36 characters);
     * a foreign address that does not fit moves to line 2 (auto-sized).
     */
    public function PreviousLine(string $fieldName, string $line): string
    {
        $type = (string) $this->getField($fieldName);

        if ($type === 'domestic') {
            return $line === '1'
                ? $this->join(' ', [$this->text('PreviousPostalCode'), $this->text('PreviousCity')])
                : $this->text('PreviousStreet');
        }

        if ($type !== 'abroad') {
            return '';
        }

        $country = $this->text('PreviousCountry');
        $foreign = $this->text('PreviousForeignAddress');
        $fits = mb_strlen($this->join(', ', [$country, $foreign])) <= self::PREVIOUS_LINE_MAX;

        if ($line === '1') {
            return $fits ? $this->join(', ', [$country, $foreign]) : $country;
        }

        $domestic = $this->text('PreviousLastDomesticAddress');

        return $this->join('; ', [
            $fits ? '' : $foreign,
            $domestic !== '' ? 'Letzte Anschrift im Inland: ' . $domestic : '',
        ]);
    }

    /** Weitere Wohnung (Other1 / Other2), line 1 "PLZ Ort", line 2 "Straße". */
    public function OtherLine(string $fieldName, string $line): string
    {
        $prefix = substr($fieldName, 0, 6); // Other1 / Other2

        return $line === '1'
            ? $this->join(' ', [$this->text($fieldName), $this->text($prefix . 'City')])
            : $this->text($prefix . 'Street');
    }

    /** Ziffer 10: "Tag, Ort" of the last marriage / divorce / partnership event (Person 1). */
    public function MarriageLine(string $fieldName, string $unused = ''): string
    {
        return $this->join(', ', [(string) $this->DateFormating('Person1LastMarriageDate'), $this->text($fieldName)]);
    }

    private function text(string $fieldName): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) ($this->getField($fieldName) ?? '')));
    }

    /**
     * @param array<int, string> $parts
     */
    private function join(string $glue, array $parts): string
    {
        return implode($glue, array_filter($parts, static fn (string $part): bool => $part !== ''));
    }

    // ---------------------------------------------------------------------------------------------
    // PDF generation
    // ---------------------------------------------------------------------------------------------

    public function generateFilledPDF(): Pdf
    {
        $basePdfPath = getcwd() . '/assets/' . $this->FormSubmission()->getBasePDFLink('PDFFile');
        $filled = $this->fillPdf($basePdfPath);

        $signature = trim((string) $this->Signature);
        if ($signature === '') {
            return new Pdf($filled);
        }

        $tmpSignature = $this->writeSignatureImage($signature);

        try {
            $outputPath = $this->stampSignature($filled, $tmpSignature);
        } finally {
            @unlink($tmpSignature);
            @unlink($filled);
        }

        return new Pdf($outputPath);
    }

    /**
     * Fills + flattens into a temp file. Auto-sized fields of this PDF lose non-ASCII glyphs (ä, ö, ü, ž ...)
     * in pdftk's own appearance generation, so a replacement font is passed; pdftk builds without
     * "replacement_font" (pdftk < 3.0) fall back to the plain fill.
     */
    private function fillPdf(string $basePdfPath): string
    {
        $target = sys_get_temp_dir() . '/anmeldung_ffm_filled_' . uniqid('', true) . '.pdf';
        $font = $this->replacementFontPath();

        $pdf = $this->newFilledPdf($basePdfPath, $font);
        if ($pdf->saveAs($target)) {
            return $target;
        }

        $error = $pdf->getError();
        if ($font !== null) {
            Injector::inst()->get(LoggerInterface::class)->warning(
                'Anmeldung Frankfurt: pdftk fill with replacement_font failed, retrying without: ' . $error
            );
            $pdf = $this->newFilledPdf($basePdfPath, null);
            if ($pdf->saveAs($target)) {
                return $target;
            }
            $error = $pdf->getError();
        }

        throw new RuntimeException('pdftk error: ' . $error);
    }

    private function newFilledPdf(string $basePdfPath, ?string $font): Pdf
    {
        $pdf = new Pdf($basePdfPath);
        $pdf->fillForm($this->getArrayDataForPDF())
            ->needAppearances();

        if ($font !== null) {
            $pdf->replacementFont($font);
        }

        return $pdf->flatten();
    }

    /** Config replacement_font: absolute path or relative to the module root; empty disables it. */
    private function replacementFontPath(): ?string
    {
        $font = trim((string) $this->config()->get('replacement_font'));
        if ($font === '') {
            return null;
        }

        $path = str_starts_with($font, '/') ? $font : dirname(__DIR__, 2) . '/' . $font;

        return is_file($path) ? $path : null;
    }

    /**
     * Places the signature on every page listed in $signature_positions
     * ("Meldepflichtige Person - Unterschrift" on both Ausfertigungen, Beiblatt signature box).
     */
    private function stampSignature(string $sourcePdf, string $signaturePng): string
    {
        $positions = (array) $this->config()->get('signature_positions');
        $holder = $this->getMyHolderPage();
        $overrideX = (float) ($holder?->SignaturePositionX ?: 0);
        $overrideY = (float) ($holder?->SignaturePositionY ?: 0);

        $fpdi = new Fpdi();
        $fpdi->SetAutoPageBreak(false);
        $pageCount = $fpdi->setSourceFile($sourcePdf);

        for ($i = 1; $i <= $pageCount; $i++) {
            $template = $fpdi->importPage($i);
            $size = $fpdi->getTemplateSize($template);

            $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $fpdi->useTemplate($template, 0, 0, $size['width'], $size['height']);

            $position = $positions['page' . $i] ?? null;
            if (!is_array($position)) {
                continue;
            }

            $x = (float) $position['x'];
            $y = (float) $position['y'];
            if (in_array($i, self::CMS_POSITION_PAGES, true)) {
                $x = $overrideX > 0 ? $overrideX : $x;
                $y = $overrideY > 0 ? $overrideY : $y;
            }

            $fpdi->Image($signaturePng, $x, $y, (float) $position['width'], 0, 'PNG');
        }

        $outputPath = sys_get_temp_dir() . '/anmeldung_ffm_signed_' . uniqid('', true) . '.pdf';
        $fpdi->Output('F', $outputPath);

        return $outputPath;
    }

    /**
     * jSignature delivers a PNG data URI; base64 SVG is supported as fallback (needs Imagick).
     */
    private function writeSignatureImage(string $signature): string
    {
        $path = sys_get_temp_dir() . '/anmeldung_ffm_sig_' . uniqid('', true) . '.png';

        if (preg_match('#^data:image/png;base64,(.+)$#i', $signature, $matches)) {
            $png = base64_decode($matches[1], true);
            if ($png === false || file_put_contents($path, $png) === false) {
                throw new RuntimeException('Invalid PNG signature payload.');
            }

            return $path;
        }

        $svgBase64 = preg_match('#^data:image/svg\+xml(?:;charset=[^;]+)?;base64,(.+)$#i', $signature, $matches)
            ? $matches[1]
            : $signature;
        $svg = base64_decode($svgBase64, true);
        if ($svg === false) {
            throw new RuntimeException('Invalid signature payload.');
        }

        $imagick = new \Imagick();
        $imagick->readImageBlob($svg);
        $imagick->setBackgroundColor(new \ImagickPixel('white'));
        $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
        $imagick->setImageFormat('png');
        $imagick->writeImage($path);
        $imagick->clear();

        return $path;
    }
}
