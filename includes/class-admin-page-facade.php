<?php
if (!defined('ABSPATH')) { exit; }

/** Compatibilidad histórica para hooks y llamadas públicas del administrador. */
final class IDG_Admin_Page {
    public static function register_menu(): void { IDG_Workflow_Admin_Controller::register_menu(); }
    public static function handle_save_settings(): void { IDG_Workflow_Admin_Controller::handle_save_settings(); }
    public static function handle_save_prompts(): void { IDG_Workflow_Admin_Controller::handle_save_prompts(); }
    public static function handle_save_editorial_rules(): void { IDG_Workflow_Admin_Controller::handle_save_editorial_rules(); }
    public static function handle_submit_workflow(): void { IDG_Workflow_Admin_Controller::handle_submit_workflow(); }
    public static function handle_download_report(): void { IDG_Workflow_Admin_Controller::handle_download_report(); }
    public static function render_settings_page(): void { IDG_Workflow_Admin_Controller::render_settings_page(); }
    public static function render_prompts_page(): void { IDG_Workflow_Admin_Controller::render_prompts_page(); }
    public static function render_editorial_rules_page(): void { IDG_Workflow_Admin_Controller::render_editorial_rules_page(); }
    public static function render_workflow_page(): void { IDG_Workflow_Admin_View::render_workflow_page(); }
    private static function store_radar_partial_reset_snapshot(string $id, array $workflow): array { return IDG_Workflow_Admin_Controller::store_radar_partial_reset_snapshot($id, $workflow); }
    private static function restore_radar_partial_reset_snapshot(string $id): bool { return IDG_Workflow_Admin_Controller::restore_radar_partial_reset_snapshot($id); }
    private static function radar_partial_reset_snapshot_key(string $id): string { return IDG_Workflow_Admin_Controller::radar_partial_reset_snapshot_key($id); }
}
