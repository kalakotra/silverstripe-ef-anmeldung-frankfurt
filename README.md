# Anmeldung Frankfurt am Main (Meldeschein)

Form module for the e-formulare.de PDF filler (`kalakotra/pdf-editor`).
Fills the **Anmeldung bei der Meldebehörde** of the Bürgeramt Frankfurt am Main (Meldeschein nach BMG,
Stand 01/2016, 5 pages, 198 fields) for up to five persons, incl. the Beiblatt (Ziffer 16–18), and stamps
the applicant's signature.

Requires the pdf-editor app base classes (`app\forms\basemodel\*`, `app\models\FormSubmission`).

## Installation

pdf-editor `composer.json`:

```json
"repositories": [
    { "type": "git", "url": "https://github.com/kalakotra/silverstripe-ef-anmeldung-frankfurt" }
],
"require": {
    "kalakotra/silverstripe-ef-anmeldung-frankfurt": "^1.0"
}
```

```bash
composer update kalakotra/silverstripe-ef-anmeldung-frankfurt
vendor/bin/sake db:build --flush
```

`db:build` is mandatory: `DBClassName` is an enum, without it the steps load as plain `MultiFormStep`.

## CMS setup (PDFMultiFormPage)

| Tab | Setting |
|---|---|
| PDF | `PDF File` = original `Anmeldeformular_BMG_1.pdf` (fillable, owner-password protected – works as is) |
| Form | `Form Class Name` = **Anmeldung Frankfurt am Main (Meldeschein)** |
| Customising | `SignaturePositionX/Y` – leave empty (module defaults, see below); if set, they apply to pages 3 and 5 |

## Flow

| Step | Meldeschein | Branching |
|---|---|---|
| Form_Step1 | Kopf (Haupt-/Nebenwohnung), Neue Wohnung, Einzugsdatum | |
| Form_Step2 | Bisherige Wohnung (Inland/Ausland), weitere Wohnungen in Deutschland (0–2) | |
| Form_Person1 … Form_Person5 | Ziffer 1–9, 11–15 per person, Ziffer 10 with Person 1 | "weitere Person?" → next person or Step3 |
| Form_Step3 | Beiblatt: 16 a/b/c, 17 Waffen/Sprengstoff, 18 Anschrift 1939 | |
| Form_Preview | E-Mail, Unterschrift, AGB | |

Page 5 (*Ausfertigung für die meldepflichtige Person*) shares the field names of page 3 and is filled automatically.
Not filled on purpose: Tagesstempel and "Meldebehörde – Ort, Datum, Stempel, Unterschrift".

## How it works

- **`Submission::$field_mapping`** – `'PDF field' => 'DbField'` or `'PDF field' => 'Method:DbField:Arg'`.
  Dates are three fields (Tag/Monat/Jahr) → `DateFormating:Field:d|m|Y`.
- **`Submission::$state_map`** – every checkbox is its own field with on-state `Ja`; the combo boxes
  Familienstand (`Dropdown37–41`, `Dropdown38–40` use `" nicht bekannt"` with a leading space) and
  Ausweisart (`Dropdown10–14`) need their exact option strings. All go through `StateValue:DbField:PdfField`.
- **Numeric PDF field names** – the Religion fields are named `1`…`5`. SilverStripe's config merge renumbers
  integer array keys, so they are configured as `'#1'`…`'#5'`; `getArrayDataForPDF()` strips the prefix.
- **`Submission::$field_conditions`** – single source of truth for field dependencies (mirrors the
  display-logic in the steps): step cleanup (`StepTrait::saveData`), conditional required fields
  (`$conditional_required`) and `onBeforeWrite()` cleanup, so hidden values never reach the PDF.
  Persons 2–5 are gated by the previous person's "weitere Person".
- **`PersonStepTrait`** – one field definition for all five persons (`Person{N}…`, `__TRAIT__` lang keys).
  `Form::resolveFieldLabel()/resolveStepTitle()` read the preview labels from the field titles.
- **Replacement font** – auto-sized fields of this PDF lose ä/ö/ü/ž/ć in pdftk's appearance generation.
  The module passes `fonts/LiberationSans-Regular.ttf` (SIL OFL 1.1) as pdftk `replacement_font`;
  pdftk builds without that option fall back to a plain fill (warning in the log).
  Config: `Submission.replacement_font` (module-relative or absolute path, `''` disables).
- **Fixed-size lines** – "PLZ, Ortsteil" (new residence) and the first line of "Bisherige Wohnung" have a fixed
  12 pt font and clip; a long Ortsteil / foreign address moves to the (auto-sized) second line.

## Signature

Stamped with FPDI on pages 3, 4 and 5 (mm from the top-left corner):

```yaml
Kalakotra\EFormAnmeldungFrankfurt\Model\Submission:
  signature_positions:
    page3: { x: 135.0, y: 273.0, width: 36.0 }
    page4: { x: 134.0, y: 231.5, width: 44.0 }
    page5: { x: 135.0, y: 273.0, width: 36.0 }
```

## Translations

`lang/` – ar, bg, bs, cs, de, en, es, fr, it, pl, ro, ru, tr, uk. All labels are literal
`_t(__CLASS__ . '.Key', 'Deutsch')` / `_t(__TRAIT__ . '.Key', …)` calls (i18nTextCollectorTask-compatible).

## License

BSD-3-Clause, see [LICENSE](LICENSE). `fonts/LiberationSans-Regular.ttf`: SIL Open Font License 1.1,
see [fonts/LICENSE-LiberationSans.txt](fonts/LICENSE-LiberationSans.txt).
