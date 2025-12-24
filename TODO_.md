Yes! I’d keep the Hidden-field approach (works 100%), and fix the “GUI brutta” by **removing the HTML field from submissions & emails** so you don’t get that empty “Targa (opzionale)” row.

Here’s the clean setup:

# What stays

* Keep your EPV widget (HTML field) for the UI.
* Keep the two Hidden fields in Elementor: `epv_field` (label: “Targa”), `epv_country` (label: “Paese targa”).
* Keep the small JS that syncs Hidden values (already working).

# Make the backend pretty (no useless HTML rows)

## Option A — Drop **all HTML fields** from Submission/Email (recommended)

Create (or append to) a small MU plugin:

**`/wp-content/mu-plugins/elementor-submission-cleanup.php`**

```php
<?php
/**
 * Plugin Name: Elementor Submission Cleanup (no HTML fields)
 * Description: Rimuove i campi di tipo "html" da Submission/Email Elementor + lascia solo i nostri hidden puliti.
 */

if (!defined('ABSPATH')) exit;

add_action('elementor_pro/forms/validation', function($record, $ajax_handler){
    $fields = (array) $record->get('fields');
    foreach ($fields as $id => $field) {
        // Togli tutti i campi HTML (titoletti, helper, ecc.)
        if (!empty($field['type']) && strtolower($field['type']) === 'html') {
            unset($fields[$id]);
        }
    }
    $record->set('fields', $fields);
}, 99, 2);

add_action('elementor_pro/forms/process', function($record, $ajax_handler){
    $fields = (array) $record->get('fields');
    foreach ($fields as $id => $field) {
        if (!empty($field['type']) && strtolower($field['type']) === 'html') {
            unset($fields[$id]);
        }
    }
    $record->set('fields', $fields);
}, 99, 2);

add_action('elementor_pro/forms/mail', function($mail, $record){
    // Se usi [all-fields], i campi HTML sono già stati tolti sopra.
    // Se hai un template custom, nulla da fare qui.
}, 10, 2);
```

## Option B — Drop only a **specific** HTML field (se vuoi lasciarne altri)

Sostituisci la condizione con l’ID del tuo HTML field (es. `field_430f8cd`):

```php
if (!empty($field['id']) && $field['id'] === 'field_430f8cd') {
    unset($fields[$id]);
}
```

> Risultato: in **Submission** ed **Email** sparisce la riga “Targa (opzionale)” vuota; restano solo:
>
> * **Targa** → (Hidden compilato)
> * **Paese targa** → (Hidden compilato)

# Bonus: micro-polish lato frontend (solo UX)

Se vuoi, aggiungi un’aria-label interna e togli il titolo HTML visibile:

```html
<!-- nel tuo Field HTML, usa solo: -->
<div id="epv_box" class="plate-epv-wrapper" aria-label="Targa"></div>
```

(La label vera in backend sarà il **label del Hidden** “Targa”, quindi hai coerenza anche su email/log.)

---

Se vuoi, ti preparo una variante del MU plugin che **rinomina** anche i label dei due hidden in modo coerente (es. “Targa (EU)” se il country ≠ IT).
