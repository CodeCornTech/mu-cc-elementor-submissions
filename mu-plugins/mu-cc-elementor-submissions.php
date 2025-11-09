<?php

/**
 * Plugin Name: MU CC Elementor Submissions
 * Description: Strumenti MU per Elementor Pro Submissions : pulizia campi HTML , menu Preventivi , colonna "Letta" , anteprima media nella scheda submission .
 * Author: CodeCorn™
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 */

namespace MU_CC\ElementorSubmissions;

defined('ABSPATH') || exit;

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
            define('MU_CC_ES_VERSION', '1.0.0');
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
    }

    private function hooks(): void
    {
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
        }
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
}

// bootstrap
Plugin::instance();
