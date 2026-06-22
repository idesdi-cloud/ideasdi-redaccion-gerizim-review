<?php
/**
 * Plugin Name: ideasDi Redacción Gerizim
 * Plugin URI: https://ideasdi.com
 * Description: Flujo editorial interno para generar artículo base, revisión editorial, revisión SEO y creación de borradores en ideasDi usando OpenAI API.
 * Version: 0.4.0-RC1.4.8
 * Author: ideasDi
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: ideasdi-gerizim
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IDG_VERSION', '0.4.0-RC1.4.8');
define('IDG_EDITORIAL_RULES_OPTION_KEY', 'idg_editorial_rules');
define('IDG_EDITORIAL_RULES_HISTORY_OPTION_KEY', 'idg_editorial_rules_history');
define('IDG_PLUGIN_FILE', __FILE__);
define('IDG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IDG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IDG_OPTION_KEY', 'idg_settings');
define('IDG_PROMPTS_OPTION_KEY', 'idg_prompt_settings');
define('IDG_SESSION_KEY_PREFIX', 'idg_workflow_user_');
define('IDG_ACTION_HOOK', 'idg_process_workflow_action');

require_once IDG_PLUGIN_DIR . 'includes/class-sanitizer.php';
require_once IDG_PLUGIN_DIR . 'includes/class-internal-links.php';
require_once IDG_PLUGIN_DIR . 'includes/class-assignment-card.php';
require_once IDG_PLUGIN_DIR . 'includes/class-temporary-material.php';
require_once IDG_PLUGIN_DIR . 'includes/class-web-research.php';
require_once IDG_PLUGIN_DIR . 'includes/class-priority-readings.php';
require_once IDG_PLUGIN_DIR . 'includes/class-editorial-recipe-builder.php';
require_once IDG_PLUGIN_DIR . 'includes/class-radar-importer.php';
require_once IDG_PLUGIN_DIR . 'includes/class-recurring-updates.php';
require_once IDG_PLUGIN_DIR . 'includes/class-usage-estimator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-editorial-rules.php';
require_once IDG_PLUGIN_DIR . 'includes/class-prompt-library.php';
require_once IDG_PLUGIN_DIR . 'includes/class-openai-client.php';
require_once IDG_PLUGIN_DIR . 'includes/class-logger.php';
require_once IDG_PLUGIN_DIR . 'includes/class-post-creator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-validator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-final-guard.php';
require_once IDG_PLUGIN_DIR . 'includes/class-job-runner.php';
require_once IDG_PLUGIN_DIR . 'includes/class-admin-page.php';
require_once IDG_PLUGIN_DIR . 'includes/class-metabox.php';

final class IDG_Plugin {
    private static ?IDG_Plugin $instance = null;

    public static function instance(): IDG_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_meta']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_idg_save_settings', ['IDG_Admin_Page', 'handle_save_settings']);
        add_action('admin_post_idg_save_prompts', ['IDG_Admin_Page', 'handle_save_prompts']);
        add_action('admin_post_idg_save_editorial_rules', ['IDG_Admin_Page', 'handle_save_editorial_rules']);
        add_action('admin_post_idg_submit_workflow', ['IDG_Admin_Page', 'handle_submit_workflow']);
        add_action('admin_post_idg_download_report', ['IDG_Admin_Page', 'handle_download_report']);
        add_action(IDG_ACTION_HOOK, ['IDG_Job_Runner', 'process_scheduled_action'], 10, 2);
        add_action('admin_menu', ['IDG_Admin_Page', 'register_menu']);
        add_action('add_meta_boxes', ['IDG_Metabox', 'register']);
        add_action('save_post_post', ['IDG_Metabox', 'save'], 10, 2);
    }

    public static function activate(): void {
        IDG_Logger::log('plugin_activated', 'Plugin activado.', ['version' => IDG_VERSION]);
    }

    public static function deactivate(): void {
        IDG_Logger::log('plugin_deactivated', 'Plugin desactivado.', ['version' => IDG_VERSION]);
    }

    public function register_meta(): void {
        $meta_keys = [
            '_idg_keyword' => 'string',
            '_idg_entity' => 'string',
            '_idg_piece_type' => 'string',
            '_idg_brief_summary' => 'string',
            '_idg_official_source' => 'string',
            '_idg_internal_links' => 'string',
            '_idg_assignment_card' => 'string',
            '_idg_final_validation' => 'string',
            '_idg_meta_description' => 'string',
            '_idg_seo_report' => 'string',
            '_idg_social_copy' => 'string',
            '_idg_reel_package' => 'string',
            '_idg_feedback_notes' => 'string',
            '_idg_prompt_versions' => 'string',
            '_idg_execution_history' => 'string',
            '_idg_sponsor_client' => 'string',
            '_idg_sponsored_link' => 'string',
            '_idg_sponsored_anchor' => 'string',
            '_idg_sponsored_rel' => 'string',
            '_idg_sponsored_notes' => 'string',
            '_idg_sponsored_visible_label' => 'string',
        ];

        foreach ($meta_keys as $key => $type) {
            register_post_meta('post', $key, [
                'type' => $type,
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'wp_kses_post',
                'auth_callback' => function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }

    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'ideasdi-gerizim') === false && !in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        wp_enqueue_style('idg-admin', IDG_PLUGIN_URL . 'assets/admin.css', [], IDG_VERSION);
        wp_enqueue_script('idg-admin', IDG_PLUGIN_URL . 'assets/admin.js', [], IDG_VERSION, true);
    }
}

register_activation_hook(__FILE__, ['IDG_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['IDG_Plugin', 'deactivate']);

IDG_Plugin::instance();
