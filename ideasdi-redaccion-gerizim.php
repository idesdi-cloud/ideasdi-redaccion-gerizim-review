<?php
/**
 * Plugin Name: ideasDi Redacción Gerizim
 * Plugin URI: https://ideasdi.com
 * Description: Flujo editorial interno para generar artículo base, revisión editorial, revisión SEO y creación de entradas pendientes en ideasDi usando OpenAI API.
 * Version: 0.4.0-RC1.6.3.2
 * Author: ideasDi
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: ideasdi-gerizim
 */

if (!defined('ABSPATH')) {
    exit;
}

define('IDG_VERSION', '0.4.0-RC1.6.3.2');
define('IDG_TRACEABILITY_DB_VERSION', '1.2.0');
define('IDG_EDITORIAL_RULES_OPTION_KEY', 'idg_editorial_rules');
define('IDG_EDITORIAL_RULES_HISTORY_OPTION_KEY', 'idg_editorial_rules_history');
define('IDG_PLUGIN_FILE', __FILE__);
define('IDG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IDG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IDG_OPTION_KEY', 'idg_settings');
define('IDG_PROMPTS_OPTION_KEY', 'idg_prompt_settings');
define('IDG_SESSION_KEY_PREFIX', 'idg_workflow_user_');
define('IDG_ACTION_HOOK', 'idg_process_workflow_action');
define('IDG_TRACEABILITY_ACTION_HOOK', 'idg_traceability_process_outbox');
define('IDG_TRACEABILITY_RECONCILE_HOOK', 'idg_traceability_reconcile');

require_once IDG_PLUGIN_DIR . 'includes/class-sanitizer.php';
require_once IDG_PLUGIN_DIR . 'includes/class-internal-links.php';
require_once IDG_PLUGIN_DIR . 'includes/class-assignment-card.php';
require_once IDG_PLUGIN_DIR . 'includes/class-temporary-material.php';
require_once IDG_PLUGIN_DIR . 'includes/class-web-research.php';
require_once IDG_PLUGIN_DIR . 'includes/class-priority-readings.php';
require_once IDG_PLUGIN_DIR . 'includes/class-disciplinary-library.php';
require_once IDG_PLUGIN_DIR . 'includes/class-editorial-recipe-builder.php';
require_once IDG_PLUGIN_DIR . 'includes/class-editorial-plan.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-input-adapter.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-orchestrator.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-action-strategy.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-stage-input-adapter.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-planning-pipeline.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-redaction-pipeline.php';
require_once IDG_PLUGIN_DIR . 'includes/contracts/interface-workflow-stage-orchestrator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-policies.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-contract.php';
require_once IDG_PLUGIN_DIR . 'includes/adapters/class-workflow-input-adapters.php';
require_once IDG_PLUGIN_DIR . 'includes/adapters/class-workflow-stage-input-adapters.php';
require_once IDG_PLUGIN_DIR . 'includes/class-radar-importer.php';
require_once IDG_PLUGIN_DIR . 'includes/class-recurring-updates.php';
require_once IDG_PLUGIN_DIR . 'includes/class-usage-estimator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-editorial-rules.php';
require_once IDG_PLUGIN_DIR . 'includes/class-prompt-library.php';
require_once IDG_PLUGIN_DIR . 'includes/class-openai-client.php';
require_once IDG_PLUGIN_DIR . 'includes/class-logger.php';
require_once IDG_PLUGIN_DIR . 'includes/class-traceability-client.php';
require_once IDG_PLUGIN_DIR . 'includes/class-traceability-outbox.php';
require_once IDG_PLUGIN_DIR . 'includes/class-traceability-recapture.php';
require_once IDG_PLUGIN_DIR . 'includes/class-traceability.php';
require_once IDG_PLUGIN_DIR . 'includes/class-traceability-admin.php';
require_once IDG_PLUGIN_DIR . 'includes/class-post-creator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-validator.php';
require_once IDG_PLUGIN_DIR . 'includes/class-final-guard.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-prompt-data.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-output-parser.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-planning-pipeline.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-redaction-pipeline.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-stage-orchestrator.php';
require_once IDG_PLUGIN_DIR . 'includes/strategies/class-workflow-action-strategies.php';
require_once IDG_PLUGIN_DIR . 'includes/class-job-runner.php';
require_once IDG_PLUGIN_DIR . 'includes/class-workflow-orchestrator.php';
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
        add_action('admin_post_idg_traceability_process_queue', ['IDG_Traceability_Admin', 'handle_process_queue']);
        add_action('admin_post_idg_traceability_retry_temporary', ['IDG_Traceability_Admin', 'handle_retry_temporary']);
        add_action('admin_post_idg_traceability_reactivate_event', ['IDG_Traceability_Admin', 'handle_reactivate_event']);
        add_action('admin_post_idg_analyze_recurring_update', ['IDG_Recurring_Updates', 'handle_analyze']);
        add_action('admin_post_idg_apply_recurring_update', ['IDG_Recurring_Updates', 'handle_apply']);
        add_action('admin_post_idg_download_recurring_report', ['IDG_Recurring_Updates', 'handle_download_report']);
        add_action('admin_post_idg_prepare_recurring_workflow', ['IDG_Recurring_Updates', 'handle_prepare_workflow']);
        add_filter('acf/load_field/name=pais', ['IDG_Recurring_Updates', 'filter_country_field_choices']);
        add_filter('acf/load_field', ['IDG_Recurring_Updates', 'filter_country_field_choices']);
        add_action(IDG_ACTION_HOOK, ['IDG_Workflow_Orchestrator', 'process_scheduled_action'], 10, 2);
        add_action('admin_menu', ['IDG_Admin_Page', 'register_menu']);
        add_action('add_meta_boxes', ['IDG_Metabox', 'register']);
        add_action('save_post_post', ['IDG_Metabox', 'save'], 10, 2);
        IDG_Traceability::boot();
        add_action('plugins_loaded', ['IDG_Traceability', 'maybe_upgrade_database']);
    }

    public static function activate(): void {
        IDG_Traceability::maybe_upgrade_database();
        IDG_Logger::log('plugin_activated', 'Plugin activado.', ['version' => IDG_VERSION]);
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook(IDG_TRACEABILITY_ACTION_HOOK);
        wp_clear_scheduled_hook(IDG_TRACEABILITY_RECONCILE_HOOK);
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(IDG_TRACEABILITY_ACTION_HOOK, [], 'ideasdi-gerizim-traceability');
            as_unschedule_all_actions(IDG_TRACEABILITY_RECONCILE_HOOK, [], 'ideasdi-gerizim-traceability');
        }
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
                'auth_callback' => static function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }

        foreach (['_idg_radar_brief_id', '_idg_radar_hallazgo_id'] as $key) {
            register_post_meta('post', $key, [
                'type' => 'integer',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'absint',
                'auth_callback' => static function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }

        foreach ([
            '_idg_workflow_id',
            '_idg_traceability_contract_version',
            '_idg_traceability_post_created_key',
            '_idg_traceability_post_created_status',
            '_idg_traceability_post_created_synced_at_utc',
            '_idg_traceability_published_key',
            '_idg_traceability_published_status',
            '_idg_traceability_published_synced_at_utc',
            '_idg_published_at_utc',
        ] as $key) {
            register_post_meta('post', $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => static function () {
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
