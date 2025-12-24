### DIRTY BUT WORKING STARTPOINT
```php
<?php
/**
 * Plugin Name: Elementor Submission Cleanup (no HTML fields)
 * Description: Rimuove i campi di tipo "html" da Submission/Email Elementor + lascia solo i nostri hidden puliti.
 */

if (!defined('ABSPATH'))
    exit;

!defined('CC_CHF_DEBUG') && define('CC_CHF_DEBUG', false);
/** ============ DEBUG HELPER ============ */
if (!function_exists('cc_epv_log')) {
    function cc_epv_log($message, $context = null)
    {
        if (!CC_CHF_DEBUG) {
            return;
        }
        $prefix = '[CC_EPV] ';
        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }
        if ($context !== null) {
            if (is_array($context) || is_object($context)) {
                $context = print_r($context, true);
            }
            error_log($prefix . $message . ' | CONTEXT: ' . $context);
        } else {
            error_log($prefix . $message);
        }
    }
}

/**
 * Rileva se la richiesta è la REST di Elementor Submissions (edit/update) → in tal caso NON tocchiamo i campi.
 */
function cc_epv_is_elementor_submissions_rest(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    // pattern principali usati da Elementor Pro per le submissions (index, item, update)
    if (stripos($uri, '/wp-json/elementor/v1/forms/submissions') !== false)
        return true;
    if (stripos($uri, '/elementor/v1/forms/submissions') !== false)
        return true;
    return false;
}

/**
 * Filtra i campi rimuovendo quelli type=html in modo type-safe e restituendo SEMPRE un array.
 * Supporta input come array o Elementor\Core\Utils\Collection.
 */
function cc_epv_filter_fields_to_array($fields_in): array
{
    // Se è una Collection, recupero il contenuto come array preservando le chiavi
    if ($fields_in instanceof \Elementor\Core\Utils\Collection) {
        // Metodo sicuro: estraggo tutte le coppie chiave=>valore
        $fields_arr = method_exists($fields_in, 'all') ? $fields_in->all() : iterator_to_array($fields_in);
    } else {
        // Altrimenti normalizzo ad array
        $fields_arr = (array) $fields_in;
    }

    $before = count($fields_arr);
    foreach ($fields_arr as $id => $field) {
        $type = '';
        if (is_array($field) && isset($field['type'])) {
            $type = strtolower((string) $field['type']);
        } elseif (is_object($field) && isset($field->type)) {
            $type = strtolower((string) $field->type);
        }
        if ($type === 'html') {
            unset($fields_arr[$id]);
            cc_epv_log("filter → rimosso campo HTML: {$id}");
        }
    }
    $after = count($fields_arr);
    cc_epv_log("filter → count prima: {$before}, dopo: {$after}");

    return $fields_arr;
}

/** ================= HOOKS ================= */

/**
 * VALIDATION
 */
add_action('elementor_pro/forms/validation', function ($record, $ajax_handler) {
    if (cc_epv_is_elementor_submissions_rest()) {
        cc_epv_log('validation → salto (REST Submissions rilevata)');
        return;
    }

    try {
        $fields_in = $record->get('fields');
        $fields_out = cc_epv_filter_fields_to_array($fields_in);
        $record->set('fields', $fields_out); // settiamo SEMPRE un array (evita bug Collection::only)
        cc_epv_log('validation → set fields (array) completato', array_keys($fields_out));
    } catch (\Throwable $e) {
        cc_epv_log('validation → EXCEPTION: ' . $e->getMessage(), $e->getTraceAsString());
    }
}, 99, 2);

/**
 * PROCESS
 */
add_action('elementor_pro/forms/process', function ($record, $ajax_handler) {
    if (cc_epv_is_elementor_submissions_rest()) {
        cc_epv_log('process → salto (REST Submissions rilevata)');
        return;
    }

    try {
        $fields_in = $record->get('fields');
        $fields_out = cc_epv_filter_fields_to_array($fields_in);
        $record->set('fields', $fields_out); // manteniamo array
        cc_epv_log('process → set fields (array) completato', array_keys($fields_out));
    } catch (\Throwable $e) {
        cc_epv_log('process → EXCEPTION: ' . $e->getMessage(), $e->getTraceAsString());
    }
}, 99, 2);


// /**
//  * Funzione helper per loggare con prefisso
//  */
// if (!function_exists('cc_epv_log')) {
//     function cc_epv_log($message, $context = null) {
//         $prefix = '[CC_EPV] ';
//         if (is_array($message) || is_object($message)) {
//             $message = print_r($message, true);
//         }
//         if ($context) {
//             if (is_array($context) || is_object($context)) {
//                 $context = print_r($context, true);
//             }
//             error_log($prefix . $message . ' | CONTEXT: ' . $context);
//         } else {
//             error_log($prefix . $message);
//         }
//     }
// }

// /**
//  * Hook: Validation
//  */
// add_action('elementor_pro/forms/validation', function ($record, $ajax_handler) {
//     $fields = (array) $record->get('fields');
//     $before_count = count($fields);

//     foreach ($fields as $id => $field) {
//         if (!empty($field['type']) && strtolower($field['type']) === 'html') {
//             cc_epv_log("validation → rimuovo campo HTML: $id", $field);
//             unset($fields[$id]);
//         }
//     }

//     $after_count = count($fields);
//     cc_epv_log("validation completata - campi iniziali: $before_count, dopo pulizia: $after_count");

//     $record->set('fields', $fields);
// }, 99, 2);

// /**
//  * Hook: Process (post-validation)
//  */
// add_action('elementor_pro/forms/process', function ($record, $ajax_handler) {
//     $fields = (array) $record->get('fields');
//     $before_count = count($fields);

//     foreach ($fields as $id => $field) {
//         if (!empty($field['type']) && strtolower($field['type']) === 'html') {
//             cc_epv_log("process → rimuovo campo HTML: $id", $field);
//             unset($fields[$id]);
//         }
//     }

//     $after_count = count($fields);
//     cc_epv_log("process completato - campi iniziali: $before_count, dopo pulizia: $after_count");

//     $record->set('fields', $fields);
// }, 99, 2);

/* *
 * Hook: Mail (per debug finale)
add_action('elementor_pro/forms/mail', function ($mail, $record) {
    // $fields = (array) $record->get('fields');
    // $names = array_keys($fields);
    // cc_epv_log("mail → campi presenti dopo pulizia", $names);
}, 10, 2);
*/


/**
 * 5) Rifirmiamo e spostiamo la voce submission di elemento nel menu admin
 */

/**
 * ===========================================================
 * 🔧 CC Admin Integration — Preventivi Menu + Toolbar
 * ===========================================================
 */

// === Costanti condivise ===
!defined('CC_PREVENTIVI_SLUG') && define('CC_PREVENTIVI_SLUG', 'cc-preventivi');
!defined('CC_PREVENTIVI_TARGET_SLUG') && define('CC_PREVENTIVI_TARGET_SLUG', 'e-form-submissions');
!defined('CC_PREVENTIVI_ICON') && define('CC_PREVENTIVI_ICON', 'dashicons-clipboard');
!defined('CC_PREVENTIVI_CAPABILITY') && define('CC_PREVENTIVI_CAPABILITY', 'manage_options');
!defined('CC_PREVENTIVI_LABEL') && define('CC_PREVENTIVI_LABEL', __('Preventivi', 'cc'));

// === Menu laterale (redirect morbido) ===
add_action('admin_menu', function () {
    add_menu_page(
        CC_PREVENTIVI_LABEL,
        CC_PREVENTIVI_LABEL,
        CC_PREVENTIVI_CAPABILITY,
        CC_PREVENTIVI_SLUG,
        function () {
            $target_url = admin_url('admin.php?page=' . CC_PREVENTIVI_TARGET_SLUG);
            wp_safe_redirect($target_url);
            exit;
        },
        CC_PREVENTIVI_ICON,
        2 // posizione (subito sotto Bacheca)
    );
}, 1);

// === Toolbar (Admin Bar top) ===
add_action('admin_bar_menu', function ($wp_admin_bar) {
    if (!current_user_can(CC_PREVENTIVI_CAPABILITY)) {
        return;
    }

    $href = admin_url('admin.php?page=' . CC_PREVENTIVI_TARGET_SLUG);

    $args = [
        'id' => CC_PREVENTIVI_SLUG,
        'parent' => false, // puoi cambiarlo per annidarlo sotto altro nodo
        'title' => sprintf(
            '<span class="ab-icon %s"></span><span class="ab-label">%s</span>',
            esc_attr(CC_PREVENTIVI_ICON),
            esc_html(CC_PREVENTIVI_LABEL)
        ),
        'href' => esc_url($href),
        'meta' => [
            'title' => sprintf(__('Vai a %s', 'cc'), CC_PREVENTIVI_LABEL),
            'class' => 'cc-toolbar-preventivi',
        ],
    ];

    $wp_admin_bar->add_node($args);
}, 100);

// === (Opzionale) CSS mini per centrare l’icona ===
add_action('admin_head', function () {
    ?>
    <style>
        #wpadminbar #wp-admin-bar-cc-preventivi .ab-icon:before {
            top: 2px;
        }
    </style>
    <?php
});


// (Opzionale) rimuovi la voce dal sottomenu di Elementor per evitare duplicati
//add_action('admin_menu', function () {
// Elementor menu slug è "elementor"; la pagina submissions è "e-form-submissions"
// remove_submenu_page('elementor', CC_PREVENTIVI_TARGET_SLUG);
//}, 99);

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


///===== test on the FLY
/**
 * Colonna "Letta" nella schermata elenco di Elementor > Submissions (page=e-form-submissions).
 * Non tocchiamo il core: iniettiamo la colonna via JS e chiediamo lo stato via admin-ajax.
 */

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'elementor_page_e-form-submissions')
        return;

    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');

    // handle vuoto a cui attacchiamo inline + localize
    wp_register_script('cc-sub-read', false, ['jquery'], null, true);
    wp_enqueue_script('cc-sub-read');

    wp_localize_script('cc-sub-read', 'CCSUB', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cc-sub-read'),
    ]);

    wp_add_inline_script('cc-sub-read', <<<'JS'
    jQuery(function($){
        var table = $('.wp-list-table'); if(!table.length) return;

        // aggiungi TH in testa e coda
        var th = $('<th class="column-cc-read" style="width:70px;text-align:center">Letta</th>');
        table.find('thead tr, tfoot tr').each(function(){ $(this).append(th.clone()); });

        function rowId($tr){
            // prova con la colonna ID (WordPress aggiunge data-colname="ID")
            var idCell = $tr.find("td[data-colname='ID'], td.column-id");
            var id = $.trim(idCell.text());
            if (id && /^\d+$/.test(id)) return id;

            // fallback: link con #/ID
            var href = $tr.find("a[href*='e-form-submissions#/']").attr('href') || '';
            var m = href.match(/#\/(\d+)/);
            return m ? m[1] : '';
        }
        setTimeout(() => {
            
            // gira su ogni riga
            table.find('tbody tr').each(function(){
                var $tr = $(this);
                if(!$tr.is(':visible')) return;
                
                console.log({$tr})
                var id  = rowId($tr);
                console.log({id})
                var $td = $('<td class="column-cc-read" style="text-align:center"></td>')
                .append('<span class="dashicons dashicons-update spin"></span>');
                $tr.append($td);
                
                if(!id){
                    $td.html('<span class="dashicons dashicons-warning" title="ID non trovato"></span>');
                    return;
                }
                
                $.get(CCSUB.ajax, { action:'cc_sub_read', nonce: CCSUB.nonce, id: id }, function(resp){
                    if(!resp || !resp.success){
                        $td.html('<span class="dashicons dashicons-no" title="Errore"></span>');
                        return;
                    }
                    var on = parseInt(resp.data.is_read, 10) ? 1 : 0;
                    var ic = $('<span class="dashicons"></span>')
                    .addClass(on ? 'dashicons-yes-alt' : 'dashicons-email');
                    $td.empty().append(ic).css('color', on ? '#16a34a' : '#ef4444')
                    .attr('title', on ? 'Letta' : 'Non letta');
                });
            });
        }, 650 );
    });
JS);
});
/** AJAX: dato un ID submission, restituisce is_read (0/1) */

add_action('wp_ajax_cc_sub_read', function () {
    check_ajax_referer('cc-sub-read', 'nonce');
    if (!current_user_can('manage_options'))
        wp_send_json_error('forbidden', 403);

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0)
        wp_send_json_error();

    global $wpdb;
    $table = $wpdb->prefix . 'e_submissions';
    // safety: esiste la tabella?

    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table)
        wp_send_json_error(['msg' => 'table_missing']);

    $is_read = (int) $wpdb->get_var($wpdb->prepare("SELECT is_read FROM {$table} WHERE id=%d", $id));
    wp_send_json_success(['is_read' => $is_read]);
});

add_action('admin_enqueue_scripts', function ($hook) {
    // log server-side
    error_log("[CC_DEBUG] admin_enqueue_scripts → hook={$hook}");

    // log client-side (in console JS)
    wp_add_inline_script('jquery', "console.log('[CC_DEBUG] admin_enqueue_scripts hook: {$hook}');", 'before');
});

```


================================================================================================
================================================================================================
================================================================================================
================================================================================================
================================================================================================

### QGì
️🔥 luce verde, zì. Ecco la **documentazione breve + hardening** del modulo “Colonna Letta” con delay regolabile (niente più numeri magici) e qualche accorgimento per renderla a prova di filtro/paginazione.

# Cos’è / dove metterlo

* Codice da incollare nel tuo MU plugin (o plugin custom): aggiunge la colonna **“Letta”** nella lista **Elementor → Submissions** (`admin.php?page=e-form-submissions`).
* Non tocca il core: inietta la colonna via **JS** e legge lo stato **`is_read`** dalla tabella `{$wpdb->prefix}e_submissions` con **admin-ajax**.

# Permessi & sicurezza

* Visualizzazione e AJAX limitati a `manage_options`.
* Nonce `cc-sub-read` verificato server-side.

# Come ricava l’ID

1. Prova la cella ID (`td[data-colname="ID"]` o `td.column-id`).
2. Fallback: estrae dal link `e-form-submissions#/NN`.

# Cosa mostrano le icone

* ✅ `dashicons-yes-alt` → letta (`is_read=1`)
* ✉️ `dashicons-email` → non letta (`is_read=0`)
* ⚠️ `dashicons-warning` → ID non trovato
* ⛔ `dashicons-no` → errore chiamata

# Timeout regolabile + resilienza

Ti lascio la versione con:

* **Delay configurabile** da PHP (`CCSUB.delay`).
* **MutationObserver** per ri-disegnare la colonna quando cambi pagina/filtri nella lista (Elementor rigenera la tabella).

```php
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'elementor_page_e-form-submissions') return;

    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');

    // Script shell a cui attacchiamo inline e dati
    wp_register_script('cc-sub-read', false, ['jquery'], null, true);
    wp_enqueue_script('cc-sub-read');

    wp_localize_script('cc-sub-read', 'CCSUB', [
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cc-sub-read'),
        // Regola qui il delay: 500–700ms è lo sweet spot che hai visto
        'delay' => 650,
    ]);

    wp_add_inline_script('cc-sub-read', <<<'JS'
(function($){
  if (!$('.wp-list-table').length) return;

  function ensureHeader(){
    var table = $('.wp-list-table');
    if (!table.length) return;
    if (table.find('th.column-cc-read').length) return;
    var th = $('<th class="column-cc-read" style="width:70px;text-align:center">Letta</th>');
    table.find('thead tr, tfoot tr').each(function(){ $(this).append(th.clone()); });
  }

  function findRowId($tr){
    var idCell = $tr.find("td[data-colname='ID'], td.column-id");
    var id = $.trim(idCell.text());
    if (id && /^\d+$/.test(id)) return id;
    var href = $tr.find("a[href*='e-form-submissions#/']").attr('href') || '';
    var m = href.match(/#\/(\d+)/);
    return m ? m[1] : '';
  }

  function paintRow($tr){
    if(!$tr.is(':visible')) return;
    if($tr.data('cc-read-done')) return; // evita doppioni
    $tr.data('cc-read-done', 1);

    var id  = findRowId($tr);
    var $td = $('<td class="column-cc-read" style="text-align:center"></td>')
                .append('<span class="dashicons dashicons-update spin"></span>');
    $tr.append($td);

    if(!id){
      $td.html('<span class="dashicons dashicons-warning" title="ID non trovato"></span>');
      return;
    }

    $.get(CCSUB.ajax, { action:'cc_sub_read', nonce:CCSUB.nonce, id:id }, function(resp){
      if(!resp || !resp.success){
        $td.html('<span class="dashicons dashicons-no" title="Errore"></span>');
        return;
      }
      var on = parseInt(resp.data.is_read, 10) ? 1 : 0;
      var ic = $('<span class="dashicons"></span>').addClass(on ? 'dashicons-yes-alt' : 'dashicons-email');
      $td.empty().append(ic)
         .css('color', on ? '#16a34a' : '#ef4444')
         .attr('title', on ? 'Letta' : 'Non letta');
    });
  }

  function paintAll(){
    ensureHeader();
    $('.wp-list-table tbody tr').each(function(){ paintRow($(this)); });
  }

  // primo giro con delay configurabile
  setTimeout(paintAll, parseInt(CCSUB.delay || 600, 10));

  // osserva cambi DOM (paginazione/filtri ricaricano la lista)
  var listWrap = document.querySelector('.wp-list-table')?.closest('.wrap') || document.body;
  var mo = new MutationObserver(function(muts){
    for (var i=0;i<muts.length;i++){
      if (muts[i].addedNodes && muts[i].addedNodes.length){
        // micro delay per consentire il render
        setTimeout(paintAll, 100);
        break;
      }
    }
  });
  mo.observe(listWrap, { childList:true, subtree:true });

})(jQuery);
JS);
});

// AJAX: restituisce is_read
add_action('wp_ajax_cc_sub_read', function () {
    check_ajax_referer('cc-sub-read', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('forbidden', 403);

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) wp_send_json_error();

    global $wpdb;
    $table = $wpdb->prefix . 'e_submissions';
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) wp_send_json_error(['msg' => 'table_missing']);

    $is_read = (int) $wpdb->get_var($wpdb->prepare("SELECT is_read FROM {$table} WHERE id=%d", $id));
    wp_send_json_success(['is_read' => $is_read]);
});
```

# Troubleshooting veloce

* 404 su admin-ajax? → controlla che i placeholder JS siano localizzati (niente `{$ajax}` literal).
* Colonna che non compare? → verifica `elementor_page_e-form-submissions` come `$hook` e che non ci siano CSS che nascondono il `th`.
* Icone che non si popolano dopo cambio pagina? → l’Observer in alto sistema quasi tutti i casi; in estrema ratio aggiungi anche:

  ```js
  $(document).ajaxComplete(function(){ setTimeout(paintAll, 120); });
  ```

Se vuoi, metto `CCSUB.delay` anche su costante PHP e filtro (`apply_filters('cc_sub_read_delay', 650)`) per renderla regolabile da wp-config o theme.
