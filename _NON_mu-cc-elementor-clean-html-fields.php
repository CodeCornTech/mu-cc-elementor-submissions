<?php
/**
 * Plugin Name: Elementor – Clean/Map Visual Fields to Hidden
 * Description: Rimuove campi visuali (es. HTML) dalla submission Elementor e mappa i relativi hidden (con normalizzazione opzionale).
 * Author: CodeCorn
 * Version: 2.0.0
 */

if (!defined('ABSPATH'))
    exit;

/**
 * 1) TIPI e ID da eliminare sempre (a prescindere dalla mappa)
 *    - eliminiamo per default i campi di tipo 'html' (widget HTML del Form)
 *    - puoi aggiungere ID specifici da forzare all'unset
 */
const CC_EPV_ALWAYS_UNSET_TYPES = ['html'];
const CC_EPV_ALWAYS_UNSET_IDS = [
    // 'note_html', 'qualcosa_html'
];

/**
 * 2) Mappa dei campi: visual -> hidden
 *    Ogni voce:
 *      - from   : ID del campo “visuale/fake” (es. input dentro HTML o un text che non vuoi salvare)
 *      - to     : ID del campo HIDDEN (o altro campo reale) da tenere
 *      - label  : (opz.) override etichetta in output (email / [all-fields] / submissions)
 *      - normalize: (opz.) nome funzione di normalizzazione (string) chiamata sul valore del campo "to"
 *
 *  Nota: la mappa NON può recuperare il valore del "from" se questo non è un campo reale di Elementor.
 *        Serve a: 1) togliere il “from” dalla submission, 2) mantenere/normalizzare “to”, 3) opzionale rinominare label.
 */
function cc_epv_default_mapping(): array
{
    return [
        [
            'from' => 'epv_field',
            'to' => 'epv_field_hidden', // esempio: un hidden che hai aggiunto al form
            'label' => 'Targa',
            'normalize' => 'cc_epv_normalize_plate',
        ],
        // [
        //     'from'      => 'epv_country',
        //     'to'        => 'epv_country_hidden',
        //     'label'     => 'Nazione',
        //     'normalize' => 'cc_epv_normalize_country',
        // ],
        // aggiungi altri mapping qui...
    ];
}

/**
 * 3) Normalizzatori di esempio (puoi rinominare/estendere)
 */
function cc_epv_normalize_plate(string $v): string
{
    $v = strtoupper($v);
    $v = preg_replace('/[^A-Z0-9 ]+/u', '', $v);
    $v = preg_replace('/\s+/u', ' ', $v);
    return trim($v);
}
function cc_epv_normalize_country(string $v): string
{
    $v = strtoupper(trim($v));
    // opzionale: riduci a 2-3 lettere
    if (preg_match('/^[A-Z]{2,3}$/', $v))
        return $v;
    // tenta di estrarre codici tra parentesi es. "Italia (IT)" -> IT
    if (preg_match('/\(([A-Z]{2,3})\)/', $v, $m))
        return $m[1];
    return $v;
}
function cc_epv_normalize_trim(string $v): string
{
    return trim($v);
}

/**
 * 4) Guard: attiva solo con Elementor Pro
 */
add_action('plugins_loaded', function () {
    if (!defined('ELEMENTOR_PRO_VERSION')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p><strong>⚠️ Elementor Clean/Map:</strong> Elementor Pro non è attivo: il filtro submission è disabilitato.</p></div>';
        });
        return;
    }

    /**
     * Hook principale: prima che email/webhook/submission vengano generati
     */
    add_action('elementor_pro/forms/new_record', function ($record, $ajax_handler) {

        $fields = $record->get('fields'); // [ field_id => [ 'id','title','type','value','raw_value', ... ] ]
        if (!is_array($fields) || empty($fields))
            return;

        // carica la mappa (filtrabile)
        $mapping = apply_filters('cc_epv_field_mapping', cc_epv_default_mapping());

        // index veloce per "from" -> mapping row
        $mapByFrom = [];
        foreach ($mapping as $row) {
            if (!empty($row['from']) && !empty($row['to'])) {
                $mapByFrom[$row['from']] = $row;
            }
        }

        // 1) rimuovi per tipo/ID sempre
        foreach ($fields as $fid => $f) {
            $type = isset($f['type']) ? strtolower((string) $f['type']) : '';
            if (in_array($type, CC_EPV_ALWAYS_UNSET_TYPES, true)) {
                unset($fields[$fid]);
                continue;
            }
            if (in_array($fid, CC_EPV_ALWAYS_UNSET_IDS, true)) {
                unset($fields[$fid]);
            }
        }

        // 2) applica la mappa: togli i "from", preserva/normalizza i "to" e opzionalmente rinomina label
        foreach ($mapByFrom as $fromId => $row) {
            $toId = (string) ($row['to'] ?? '');
            $newLabel = isset($row['label']) ? (string) $row['label'] : null;
            $normalFn = isset($row['normalize']) ? (string) $row['normalize'] : null;

            // elimina SEMPRE il “from” se presente
            if (isset($fields[$fromId])) {
                unset($fields[$fromId]);
            }

            // se il campo "to" esiste: normalizza e rinomina
            if ($toId && isset($fields[$toId])) {
                $val = (string) ($fields[$toId]['value'] ?? '');

                if ($normalFn && is_callable($normalFn)) {
                    $val = (string) call_user_func($normalFn, $val);
                }

                $fields[$toId]['value'] = $val;
                $fields[$toId]['raw_value'] = $val;

                if ($newLabel !== null && $newLabel !== '') {
                    $fields[$toId]['title'] = $newLabel;
                }
            }
        }

        // 3) riscrivi i campi ripuliti
        $record->set('fields', $fields);

        // 4) rigenera "sent_data" (Label => Valore) coerente per email/[all-fields]/webhook
        $sent_data = [];
        foreach ($fields as $fid => $f) {
            $label = (string) ($f['title'] ?? $fid);
            $val = $f['value'] ?? '';
            $sent_data[$label] = is_array($val) ? implode(', ', $val) : $val;
        }
        $record->set('sent_data', $sent_data);

    }, 5, 2);
});
/**
 * 4b) Esponiamo un SyncJS per copiare il valore da un campo visuale (es. HTML) a un hidden
 *     (da usare in "After Submit" -> "Actions" -> "SyncJS")
 */


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
        'can_edit',                 // capability: cambia se vuoi più restrittivo
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
    remove_submenu_page('elementor', CC_PREVENTIVI_TARGET_SLUG);
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