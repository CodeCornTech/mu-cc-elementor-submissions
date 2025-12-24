ok bro — vado dritto al sodo con due pezzi **plug-and-play** (mettili nel tuo MU plugin o in functions.php).

1. una colonna “Letta” nella lista **Submissions** (usa `is_read` da DB)
2. mini-gallery con lightbox nel **dettaglio** submission (thumb per foto, placeholder+play per video).

---

# 1) Lista Submissions → colonna “Letta”

```php
/**
 * Colonna "Letta" nella schermata elenco di Elementor > Submissions (page=e-form-submissions).
 * Non tocchiamo il core: iniettiamo la colonna via JS e chiediamo lo stato via admin-ajax.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_e-form-submissions') return;

    wp_enqueue_style('dashicons');
    wp_enqueue_script('jquery');

    // piccolo JS inline che aggiunge la colonna e fa la chiamata AJAX per ogni riga
    $nonce = wp_create_nonce('cc-sub-read');
    $ajax  = admin_url('admin-ajax.php');

    $js = <<<JS
jQuery(function($){
  var table = $('.wp-list-table'); if(!table.length) return;

  // aggiungi TH in testa e in coda
  var th = $('<th class="column-cc-read" style="width:70px;text-align:center">Letta</th>');
  table.find('thead tr, tfoot tr').each(function(){ $(this).append(th.clone()); });

  // helper: prova a ricavare l'ID submission dalla riga
  function rowId($tr){
    // colonna "ID" se presente
    var idCell = $tr.find('td:nth-child(4), td.column-id'); // fallback grezzo
    var id = $.trim(idCell.text());
    if(id && /^\\d+$/.test(id)) return id;

    // oppure da link "#/ID" nel titolo
    var href = $tr.find('a').filter(function(){ return /page=e-form-submissions/.test(this.href);}).attr('href')||'';
    var m = href.match(/#\\/(\\d+)/);
    return m ? m[1] : '';
  }

  table.find('tbody tr').each(function(){
    var $tr = $(this);
    var id  = rowId($tr);
    var $td = $('<td class="column-cc-read" style="text-align:center"></td>')
                .append('<span class="dashicons dashicons-update spin"></span>');
    $tr.append($td);
    if(!id) { $td.html('<span class="dashicons dashicons-warning" title="ID non trovato"></span>'); return; }

    $.get('{$ajax}', { action:'cc_sub_read', nonce:'{$nonce}', id:id }, function(resp){
      if(!resp || !resp.success) { $td.html('<span class="dashicons dashicons-no"></span>'); return; }
      var on = parseInt(resp.data.is_read,10) ? 1 : 0;
      var ic = $('<span class="dashicons"></span>')
                 .addClass(on ? 'dashicons-yes-alt' : 'dashicons-email');
      $td.empty().append(ic).css('color', on ? '#16a34a' : '#ef4444');
      $td.attr('title', on ? 'Letta' : 'Non letta');
    });
  });
});
JS;

    wp_add_inline_script('jquery', $js, 'after');
});

/** AJAX: dato un ID submission, restituisce is_read (0/1) */
add_action('wp_ajax_cc_sub_read', function () {
    check_ajax_referer('cc-sub-read', 'nonce');
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) wp_send_json_error();

    global $wpdb;
    $table = $wpdb->prefix . 'e_submissions';
    // safety: esiste la tabella?
    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
    if ($exists !== $table) wp_send_json_error(['msg' => 'table_missing']);

    $is_read = (int) $wpdb->get_var($wpdb->prepare("SELECT is_read FROM {$table} WHERE id=%d", $id));
    wp_send_json_success(['is_read' => $is_read]);
});
```

> Risultato: nuova colonna **Letta** con ✔︎ verde se `is_read=1`, busta rossa se `0`.
> Non tocchiamo Elementor: se domani cambiano la list-table, il fallback cerca comunque l’ID.

---

# 2) Dettaglio Submission → thumbnails + lightbox

Usiamo **Thickbox** nativo di WordPress per non introdurre dipendenze. Trasformiamo gli URL nella riga “Allega Foto o Video” in mini-thumb cliccabili; per i video mostriamo un placeholder con “play”.

```php
/**
 * Dettaglio Submission: sostituisci gli URL in "Allega Foto o Video"
 * con thumbnails e lightbox (Thickbox).
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_e-form-submissions') return;

    // Siamo nel dettaglio? (ha action=view & submission_id)
    $is_view = isset($_GET['action'], $_GET['submission_id']) && $_GET['action'] === 'view';
    if (!$is_view) return;

    wp_enqueue_style('thickbox');
    wp_enqueue_script('thickbox');
    wp_enqueue_script('jquery');

    $placeholder = esc_js( defined('CC_EMAIL_MEDIA_PLACEHOLDER') && CC_EMAIL_MEDIA_PLACEHOLDER ? CC_EMAIL_MEDIA_PLACEHOLDER
                           : 'https://www.croceautogroup.it/wp-content/uploads/woocommerce-placeholder.png' );

    $js = <<<JS
jQuery(function($){
  // trova la riga "Allega Foto o Video" (prima cella = etichetta)
  var $row = $('table.form-table tr').filter(function(){
    return $.trim($(this).find('th,td:first').text()).toLowerCase().indexOf('allega foto o video') !== -1;
  }).first();

  if(!$row.length) return;

  var $valCell = $row.find('td').last();
  var raw = $.trim($valCell.text());
  if(!raw) return;

  // separa per virgole/spazi e filtra URL
  var urls = raw.split(/[\\s,]+/).filter(function(t){ return /^https?:\\/\\//i.test(t); });

  if(!urls.length) return;

  var gallery = $('<div class="cc-admin-gallery" style="margin-top:6px;"></div>');
  urls.forEach(function(u){
    var ext = (u.split('?')[0].split('#')[0].split('.').pop() || '').toLowerCase();
    var isImg = ['jpg','jpeg','png','gif','webp','avif','svg'].indexOf(ext) !== -1;
    var thumb = isImg ? u : '{$placeholder}';
    var $a = $('<a>', { href: u, class: isImg ? 'thickbox' : '', target: isImg ? '' : '_blank',
                        style:'display:inline-block;margin:4px;text-decoration:none;' });

    var $img = $('<img>', { src: thumb, width:120,
              style:'width:120px;height:auto;border:1px solid #eee;border-radius:6px;display:block;' });

    $a.append($img);
    if(!isImg){
      $a.append('<span style="display:block;text-align:center;font:600 11px system-ui;color:#444;margin-top:4px;">Video</span>');
    }
    gallery.append($a);
  });

  // sostituisci il testo con la galleria
  $valCell.empty().append(gallery);
});
JS;

    wp_add_inline_script('jquery', $js, 'after');
});
```

> Risultato: nel dettaglio la cella degli allegati diventa una mini-galleria.
> Immagini: lightbox **Thickbox**.
> Video: miniatura placeholder, click = apre il file in nuova scheda (se vuoi, poi metti player ME.js).

---

## Note rapide

* La colonna “Letta” legge direttamente `{$wpdb->prefix}e_submissions.is_read`. Se vuoi anche **marcare come letta** quando apri il dettaglio, aggiungi in `admin_init`:

```php
add_action('admin_init', function(){
  if (isset($_GET['page'], $_GET['action'], $_GET['submission_id'])
      && $_GET['page']==='e-form-submissions' && $_GET['action']==='view') {
    global $wpdb;
    $table = $wpdb->prefix . 'e_submissions';
    $wpdb->update($table, ['is_read'=>1], ['id'=>(int)$_GET['submission_id']]);
  }
});
```

* Per il **placeholder** puoi riusare lo stesso del template email o ridefinire `CC_EMAIL_MEDIA_PLACEHOLDER`.

Se vuoi, dopo che lo provi, metto anche l’opzione per filtrare “Solo non letti” in testata (basta un `select` + `location.href` con query custom e una WHERE in una piccola pagina ponte).
perfetto — ecco **la patch minimale** per rendere il delay configurabile via **costante** e **filtro**.

## Unified diff (incolla dove hai già l’hook `admin_enqueue_scripts`)

```diff
 add_action('admin_enqueue_scripts', function ($hook) {
     if ($hook !== 'elementor_page_e-form-submissions') return;

     wp_enqueue_style('dashicons');
     wp_enqueue_script('jquery');

     // Script shell a cui attacchiamo inline e dati
     wp_register_script('cc-sub-read', false, ['jquery'], null, true);
     wp_enqueue_script('cc-sub-read');

-    wp_localize_script('cc-sub-read', 'CCSUB', [
-        'ajax'  => admin_url('admin-ajax.php'),
-        'nonce' => wp_create_nonce('cc-sub-read'),
-        // Regola qui il delay: 500–700ms è lo sweet spot che hai visto
-        'delay' => 650,
-    ]);
+    /**
+     * Delay di inizializzazione (ms) regolabile:
+     * - costante: define('CC_SUB_READ_DELAY', 650);
+     * - filtro:   add_filter('cc_sub_read_delay', fn() => 650);
+     */
+    $delay = defined('CC_SUB_READ_DELAY') ? (int) CC_SUB_READ_DELAY : 650;
+    $delay = (int) apply_filters('cc_sub_read_delay', $delay);
+
+    wp_localize_script('cc-sub-read', 'CCSUB', [
+        'ajax'  => admin_url('admin-ajax.php'),
+        'nonce' => wp_create_nonce('cc-sub-read'),
+        'delay' => $delay,
+    ]);

     wp_add_inline_script('cc-sub-read', <<<'JS'
 (function($){
   if (!$('.wp-list-table').length) return;
```

## Come impostarlo

### 1) via `wp-config.php` (o mu-plugin)

```php
define('CC_SUB_READ_DELAY', 650); // ms
```

### 2) via filtro (in `functions.php` o plugin)

```php
add_filter('cc_sub_read_delay', function($ms){
    // puoi variare a runtime (es. in base al ruolo, lingua, ecc.)
    return 600; // ms
});
```