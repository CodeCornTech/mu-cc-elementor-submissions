<?php

/**
 * Plugin Name: MU CC Elementor Submissions
 * Description: Strumenti MU per Elementor Pro Submissions : pulizia campi HTML , menu Preventivi , colonna "Letta" , anteprima media nella scheda submission .
 * Author: CodeCorn™
 * Version: 1.0.3
 * License: GPL-2.0-or-later
 */

namespace MU_CC\ElementorSubmissions;

defined('ABSPATH') || exit;

use WP;

final class Plugin
{
    /** @var Plugin|null */
    private static $instance = null;

    private function __construct()
    {
        $this->define_constants();
        $this->hooks();
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants(): void
    {
        // base
        if (! defined('MU_CC_ES_VERSION')) {
            define('MU_CC_ES_VERSION', '1.0.3');
        }

        if (! defined('MU_CC_ES_DEBUG')) {
            define('MU_CC_ES_DEBUG', false);
        }

        if (! defined('MU_CC_ES_FILE')) {
            define('MU_CC_ES_FILE', __FILE__);
        }

        if (! defined('MU_CC_ES_PATH')) {
            define('MU_CC_ES_PATH', plugin_dir_path(MU_CC_ES_FILE));
        }

        if (! defined('MU_CC_ES_URL')) {
            define('MU_CC_ES_URL', plugin_dir_url(MU_CC_ES_FILE));
        }

        // preventivi
        if (! defined('CC_PREVENTIVI_SLUG')) {
            define('CC_PREVENTIVI_SLUG', 'cc-preventivi');
        }

        if (! defined('CC_PREVENTIVI_TARGET_SLUG')) {
            define('CC_PREVENTIVI_TARGET_SLUG', 'e-form-submissions');
        }

        if (! defined('CC_PREVENTIVI_ICON')) {
            define('CC_PREVENTIVI_ICON', 'dashicons-clipboard');
        }

        if (! defined('CC_PREVENTIVI_CAPABILITY')) {
            define('CC_PREVENTIVI_CAPABILITY', 'manage_options');
        }

        if (! defined('CC_PREVENTIVI_LABEL')) {
            define('CC_PREVENTIVI_LABEL', __('Preventivi', 'cc'));
        }
        if (! defined('MU_CC_ES_UPLOADS_SUBDIR')) {
            // sottocartella da proteggere , relativa a wp-content/uploads
            define('MU_CC_ES_UPLOADS_SUBDIR', 'elementor/forms');
        }
    }

    private function hooks(): void
    {
        add_action('init', [$this, 'define_cc_es_token']);

        // Elementor forms → pulizia campi HTML
        add_action('elementor_pro/forms/validation', [$this, 'on_forms_validation'], 99, 2);
        add_action('elementor_pro/forms/process',    [$this, 'on_forms_process'],    99, 2);

        // Admin UX : menu laterale + toolbar + highlight
        add_action('admin_menu',        [$this, 'register_admin_menu'], 1);
        add_action('admin_bar_menu',    [$this, 'register_admin_bar_node'], 100);
        add_filter('parent_file',       [$this, 'filter_parent_file']);
        add_filter('submenu_file',      [$this, 'filter_submenu_file']);

        // Admin assets ( CSS / JS ) solo dove serve
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Ajax colonna "Letta"
        add_action('wp_ajax_cc_sub_read', [$this, 'ajax_cc_sub_read']);

        // Ajax secure URL per media submissions
        add_action('wp_ajax_cc_sub_secure_url', [$this, 'ajax_cc_sub_secure_url']);

        add_action('init', [$this, 'maybe_serve_secure_file']);

        // 🔐 Auto-ensure .htaccess per uploads/elementor/forms
        add_action('admin_init', [$this, 'maybe_ensure_forms_htaccess']);

        // 🔒 filtro globale per URL media sicure (usato dal MU email)
        add_filter('cc_es_secure_media_url', [$this, 'filter_secure_media_url'], 10, 1);
    }

    /*
     * ======================================================
     *  Logging minimale
     * ======================================================
     */
    public static function log($message, $context = null): void
    {
        if (! MU_CC_ES_DEBUG) {
            return;
        }

        $prefix = '[MU_CC_ES] ';

        if (is_array($message) || is_object($message)) {
            $message = print_r($message, true);
        }

        if (null !== $context) {
            if (is_array($context) || is_object($context)) {
                $context = print_r($context, true);
            }
            error_log($prefix . $message . ' | CONTEXT: ' . $context);
        } else {
            error_log($prefix . $message);
        }
    }
    /*
     * ======================================================
     *  Helper interni
     * ======================================================
     */

    public function define_cc_es_token(): void
    {
        if (!defined('MU_CC_ES_TOKEN_KEY')) {
            // chiave di hashing → se vuoi puoi usare una tua stringa lunga
            define('MU_CC_ES_TOKEN_KEY', wp_salt('mu-cc-elementor-submissions'));
        }
    }
    /**
     * True se la richiesta è verso la REST di Elementor Submissions .
     * In quel caso NON tocchiamo i campi .
     */
    private function is_elementor_submissions_rest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($uri, '/wp-json/elementor/v1/forms/submissions') !== false) {
            return true;
        }
        if (stripos($uri, '/elementor/v1/forms/submissions') !== false) {
            return true;
        }
        return false;
    }

    /**
     * Normalizza e filtra i campi rimuovendo quelli type = html .
     * Restituisce sempre un array .
     *
     * @param mixed $fields_in
     * @return array
     */
    private function filter_fields_to_array($fields_in): array
    {
        if ($fields_in instanceof \Elementor\Core\Utils\Collection) {
            $fields_arr = method_exists($fields_in, 'all')
                ? $fields_in->all()
                : iterator_to_array($fields_in);
        } else {
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
                self::log("filter → rimosso campo HTML : {$id}");
            }
        }

        $after = count($fields_arr);
        self::log("filter → count prima : {$before} , dopo : {$after}");

        return $fields_arr;
    }
    /**
     * Contenuto canonical del .htaccess in uploads/elementor/forms
     */
    private function get_forms_htaccess_contents(): string
    {
        return <<<HTACCESS
# Niente listing directory
Options -Indexes

# Blocca accesso diretto ai file in questa cartella
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>

# Regola storica : forzava "attachment"
# Rimane qui per compatibilità , ma viene di fatto dopo il deny
<IfModule mod_headers.c>
    <Files "*">
        Header set Content-Disposition attachment
    </Files>
</IfModule>

HTACCESS;
    }

    /*
     * ======================================================
     *  Elementor Pro Forms
     * ======================================================
     */

    public function on_forms_validation($record, $ajax_handler): void
    {
        if ($this->is_elementor_submissions_rest()) {
            self::log('validation → salto ( REST Submissions rilevata )');
            return;
        }

        try {
            $fields_in  = $record->get('fields');
            $fields_out = $this->filter_fields_to_array($fields_in);

            $record->set('fields', $fields_out);

            self::log('validation → set fields ( array ) completato', array_keys($fields_out));
        } catch (\Throwable $e) {
            self::log('validation → EXCEPTION : ' . $e->getMessage(), $e->getTraceAsString());
        }
    }

    public function on_forms_process($record, $ajax_handler): void
    {
        if ($this->is_elementor_submissions_rest()) {
            self::log('process → salto ( REST Submissions rilevata )');
            return;
        }

        try {
            $fields_in  = $record->get('fields');
            $fields_out = $this->filter_fields_to_array($fields_in);

            $record->set('fields', $fields_out);

            self::log('process → set fields ( array ) completato', array_keys($fields_out));
        } catch (\Throwable $e) {
            self::log('process → EXCEPTION : ' . $e->getMessage(), $e->getTraceAsString());
        }
    }

    /*
     * ======================================================
     *  Admin menu + toolbar "Preventivi"
     * ======================================================
     */

    public function register_admin_menu(): void
    {
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
            2
        );
    }

    public function register_admin_bar_node($wp_admin_bar): void
    {
        if (! current_user_can(CC_PREVENTIVI_CAPABILITY)) {
            return;
        }

        $href = admin_url('admin.php?page=' . CC_PREVENTIVI_TARGET_SLUG);

        $args = [
            'id'     => CC_PREVENTIVI_SLUG,
            'parent' => false,
            'title'  => sprintf(
                '<span class="ab-icon %s"></span><span class="ab-label">%s</span>',
                esc_attr(CC_PREVENTIVI_ICON),
                esc_html(CC_PREVENTIVI_LABEL)
            ),
            'href'   => esc_url($href),
            'meta'   => [
                'title' => sprintf(__('Vai a %s', 'cc'), CC_PREVENTIVI_LABEL),
                'class' => 'cc-toolbar-preventivi',
            ],
        ];

        $wp_admin_bar->add_node($args);
    }

    public function filter_parent_file($parent_file)
    {
        global $pagenow;

        if (
            $pagenow === 'admin.php'
            && isset($_GET['page'])
            && $_GET['page'] === CC_PREVENTIVI_TARGET_SLUG
        ) {
            $parent_file = CC_PREVENTIVI_SLUG;
        }

        return $parent_file;
    }

    public function filter_submenu_file($submenu_file)
    {
        global $pagenow;

        if (
            $pagenow === 'admin.php'
            && isset($_GET['page'])
            && $_GET['page'] === CC_PREVENTIVI_TARGET_SLUG
        ) {
            $submenu_file = CC_PREVENTIVI_SLUG;
        }

        return $submenu_file;
    }

    /*
     * ======================================================
     *  Admin assets ( CSS + JS )
     * ======================================================
     */

    public function enqueue_admin_assets(string $hook): void
    {
        // debug hook lato client se ti serve
        if (MU_CC_ES_DEBUG) {
            wp_add_inline_script(
                'jquery',
                "console.log('[MU_CC_ES] admin_enqueue_scripts hook : " . esc_js($hook) . "');",
                'before'
            );
        }

        // CSS globale solo per backend admin ( logo Elementor + future styling )
        wp_enqueue_style(
            'mu-cc-es-admin',
            MU_CC_ES_URL . 'codecorn/elementor-submissions/assets/css/admin.css',
            [],
            MU_CC_ES_VERSION
        );

        // JS solo sulla lista Submissions ( page = e-form-submissions , lista globale )
        if ($hook === 'elementor_page_e-form-submissions') {

            // colonna "Letta" nella tabella elenco
            wp_enqueue_script(
                'mu-cc-es-submissions-list',
                MU_CC_ES_URL . 'codecorn/elementor-submissions/assets/js/submissions-list-read-column.js',
                ['jquery'],
                MU_CC_ES_VERSION,
                true
            );

            wp_localize_script('mu-cc-es-submissions-list', 'CCSUB', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cc-sub-read'),
            ]);

            // media preview + lightbox nella vista dettaglio submission
            wp_enqueue_script(
                'mu-cc-es-submissions-detail',
                MU_CC_ES_URL . 'codecorn/elementor-submissions/assets/js/submissions-detail-media.js',
                ['jquery'],
                MU_CC_ES_VERSION,
                true
            );

            // passa endpoint e nonce al JS di detail
            wp_localize_script('mu-cc-es-submissions-detail', 'CCSUB_SEC', [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cc-sub-sec'),
            ]);
        }
    }
    public function ajax_cc_sub_secure_url(): void
    {
        check_ajax_referer('cc-sub-sec', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }

        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : '';
        if ($url === '') {
            wp_send_json_error('missing_url');
        }

        $uploads = wp_get_upload_dir();
        $baseurl = trailingslashit($uploads['baseurl']);

        // deve essere un file negli uploads
        if (stripos($url, $baseurl) !== 0) {
            wp_send_json_error('not_uploads');
        }

        // path relativo rispetto a wp-content/uploads
        $relative = ltrim(substr($url, strlen($baseurl)), '/');

        // proteggiamo solo la nostra subdir elementor/forms
        if (strpos($relative, MU_CC_ES_UPLOADS_SUBDIR . '/') !== 0) {
            wp_send_json_error('not_protected_dir');
        }

        $secure = $this->build_secure_url($relative);

        wp_send_json_success([
            'secure'   => $secure,
            'relative' => $relative,
        ]);
    }

    /*
     * ======================================================
     *  Ajax colonna "Letta"
     * ======================================================
     */

    public function ajax_cc_sub_read(): void
    {
        check_ajax_referer('cc-sub-read', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            wp_send_json_error();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'e_submissions';

        // verifica esistenza tabella
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            wp_send_json_error(['msg' => 'table_missing']);
        }

        $is_read = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT is_read FROM {$table} WHERE id = %d", $id)
        );

        wp_send_json_success(['is_read' => $is_read]);
    }
    private function generate_file_token(string $relative): string
    {
        // normalizza
        $relative = ltrim($relative, '/');
        return substr(hash_hmac('sha256', $relative, MU_CC_ES_TOKEN_KEY), 0, 32);
    }

    private function verify_file_token(string $relative, string $token): bool
    {
        $expected = $this->generate_file_token($relative);
        // confronto constant time
        return hash_equals($expected, $token);
    }

    /**
     * Costruisce una URL sicura a partire da un path relativo agli uploads .
     * Esempio input : elementor/forms/2025/11/file.pdf
     */
    public function build_secure_url(string $relative): string
    {
        $relative = ltrim($relative, '/');
        $token    = $this->generate_file_token($relative);

        return add_query_arg(
            [
                'cc_esf' => rawurlencode($relative),
                'cc_t'   => $token,
            ],
            home_url('/')
        );
    }

    ### 4 . Gatekeeper per il download
    public function maybe_serve_secure_file(): void
    {
        if (empty($_GET['cc_esf']) || empty($_GET['cc_t'])) {
            return;
        }

        $relative = rawurldecode((string) $_GET['cc_esf']);
        $token    = (string) $_GET['cc_t'];

        // normalizza path
        $relative = ltrim($relative, '/');

        // blocca path strani
        if (strpos($relative, '..') !== false) {
            wp_die('Invalid path', 403);
        }

        // deve iniziare con la subdir che vogliamo proteggere
        if (
            strpos($relative, MU_CC_ES_UPLOADS_SUBDIR . '/') !== 0
            && $relative !== MU_CC_ES_UPLOADS_SUBDIR
        ) {
            wp_die('Not allowed', 403);
        }

        // path assoluto nel filesystem
        $uploads = wp_get_upload_dir();
        $file    = trailingslashit($uploads['basedir']) . $relative;

        if (! file_exists($file) || ! is_file($file)) {
            status_header(404);
            exit;
        }

        // 1 ) Admin loggato → bypass token
        if (is_user_logged_in() && current_user_can('manage_options')) {
            // ok
        } else {
            // 2 ) Non admin → deve avere token valido
            if (! $this->verify_file_token($relative, $token)) {
                wp_die('Forbidden', 403);
            }
        }

        // serve file
        $this->serve_file_download($file);
        exit;
    }

    /**
     * Output del file con header corretti .
     */
    private function serve_file_download(string $file): void
    {
        $mime = wp_check_filetype(basename($file));
        $type = $mime['type'] ?: 'application/octet-stream';

        if (! headers_sent()) {
            nocache_headers();
            header('Content-Type: ' . $type);
            header('Content-Length: ' . filesize($file));
            header('Content-Disposition: inline; filename="' . basename($file) . '"');
        }

        // pulisci buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        readfile($file);
    }
    /**
     * Filtro globale: converte una URL media in URL sicura, se punta a elementor/forms.
     * Usato dal MU dell’email template.
     *
     * @param string $url URL originale (uploads)
     * @return string URL sicura (cc_esf/cc_t) o l'originale se non tocca a noi
     */
    public function filter_secure_media_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        // se è già una nostra URL gateway, lascia stare
        if (strpos($url, 'cc_esf=') !== false && strpos($url, 'cc_t=') !== false) {
            return $url;
        }

        $uploads = wp_get_upload_dir();
        $baseurl = trailingslashit($uploads['baseurl']);

        // deve essere sotto uploads
        if (stripos($url, $baseurl) !== 0) {
            return $url;
        }

        // path relativo rispetto a wp-content/uploads
        $relative = ltrim(substr($url, strlen($baseurl)), '/');

        // proteggiamo solo la nostra subdir elementor/forms
        if (strpos($relative, MU_CC_ES_UPLOADS_SUBDIR . '/') !== 0) {
            return $url;
        }

        // costruisci URL sicura type-safe
        return $this->build_secure_url($relative);
    }
    /**
     * Crea / riallinea il .htaccess di uploads/elementor/forms in modo idempotente.
     * Gira solo in admin e solo per utenti con manage_options.
     */
    public function maybe_ensure_forms_htaccess(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        // evitiamo di fare I/O ad ogni admin_init: usiamo una firma in opzione
        $option_key   = 'mu_cc_es_forms_htaccess_sig';
        $desired      = $this->get_forms_htaccess_contents();
        $desired_sig  = md5($desired);
        $stored_sig   = (string) get_option($option_key, '');

        // se è già allineato secondo noi, stop
        if ($stored_sig === $desired_sig) {
            return;
        }

        $uploads = wp_get_upload_dir();
        if (empty($uploads['basedir'])) {
            return;
        }

        // cartella elementor/forms
        $forms_dir = trailingslashit($uploads['basedir']) . MU_CC_ES_UPLOADS_SUBDIR;

        // assicurati che la dir esista
        if (! is_dir($forms_dir)) {
            wp_mkdir_p($forms_dir);
        }

        $htaccess_path = trailingslashit($forms_dir) . '.htaccess';

        // prova a scrivere il file
        $result = @file_put_contents($htaccess_path, $desired);

        if ($result === false) {
            // opzionale: logga in debug se non riusciamo a scrivere
            self::log('HTACCESS_WRITE_FAIL', [
                'path' => $htaccess_path,
            ]);
            return;
        }

        // aggiorna la firma memorizzata
        update_option($option_key, $desired_sig);

        self::log('HTACCESS_SYNCED', [
            'path' => $htaccess_path,
            'sig'  => $desired_sig,
        ]);
    }
}

// bootstrap
Plugin::instance();
