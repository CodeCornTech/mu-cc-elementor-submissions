<?php
/**
 * Plugin Name: Elementor Submission Cleanup (no HTML fields)
 * Description: Rimuove i campi di tipo "html" da Submission/Email Elementor + lascia solo i nostri hidden puliti.
 */

if (!defined('ABSPATH'))
    exit;

add_action('elementor_pro/forms/validation', function ($record, $ajax_handler) {
    $fields = (array) $record->get('fields');
    foreach ($fields as $id => $field) {
        // Togli tutti i campi HTML (titoletti, helper, ecc.)
        if (!empty($field['type']) && strtolower($field['type']) === 'html') {
            unset($fields[$id]);
        }
    }
    $record->set('fields', $fields);
}, 99, 2);

add_action('elementor_pro/forms/process', function ($record, $ajax_handler) {
    $fields = (array) $record->get('fields');
    foreach ($fields as $id => $field) {
        if (!empty($field['type']) && strtolower($field['type']) === 'html') {
            unset($fields[$id]);
        }
    }
    $record->set('fields', $fields);
}, 99, 2);

add_action('elementor_pro/forms/mail', function ($mail, $record) {
    // Se usi [all-fields], i campi HTML sono già stati tolti sopra.
    // Se hai un template custom, nulla da fare qui.
}, 10, 2);

/**
 * 5) Rifirmiamo e spostiamo la voce submission di elemento nel menu admin
 */
// Slug della pagina Submissions (confermato dall’URL: admin.php?page=e-form-submissions)
!defined('CC_PREVENTIVI_TARGET_SLUG') && define('CC_PREVENTIVI_TARGET_SLUG', 'e-form-submissions');

// Aggiunge voce top-level e redireziona alla pagina Submissions
add_action('admin_menu', function () {
    // Posizione alta (2 = subito sotto “Bacheca/Dashboard”); regola se serve
    $position = 2;
    add_menu_page(
        __('Preventivi', 'cc'),
        __('Preventivi', 'cc'),
        'can_manage',                 // capability: cambia se vuoi più restrittivo
        'cc-preventivi',              // nostro slug top-level
        function () {
            // Redirect “morbido” alla pagina di Elementor Submissions
            $url = admin_url('admin.php?page=' . CC_PREVENTIVI_TARGET_SLUG);
            wp_safe_redirect($url);
            exit;
        },
        'dashicons-clipboard',        // icona (puoi usare anche SVG data-uri)
        $position
    );
}, 11);

// (Opzionale) rimuovi la voce dal sottomenu di Elementor per evitare duplicati
add_action('admin_menu', function () {
    // Elementor menu slug è "elementor"; la pagina submissions è "e-form-submissions"
    //remove_submenu_page('elementor', CC_PREVENTIVI_TARGET_SLUG);
}, 99);

// Evidenzia correttamente il menu “Preventivi” quando sei in Submissions
add_filter('parent_file', function ($parent_file) {
    global $pagenow;
    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === CC_PREVENTIVI_TARGET_SLUG) {
        $parent_file = 'cc-preventivi';
    }
    return $parent_file;
});

add_filter('submenu_file', function ($submenu_file) {
    global $pagenow;
    if ($pagenow === 'admin.php' && isset($_GET['page']) && $_GET['page'] === CC_PREVENTIVI_TARGET_SLUG) {
        // non abbiamo un vero submenu sotto “cc-preventivi”, ma questo evita highlight strani
        $submenu_file = 'cc-preventivi';
    }
    return $submenu_file;
});