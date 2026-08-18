<?php
if (!defined('ABSPATH')) { exit; }

/** Fachada de renderizado para la pantalla del flujo editorial. */
final class IDG_Workflow_Admin_View {
    public static function render_workflow_page(): void {
        IDG_Workflow_Admin_Controller::render_workflow_page();
    }
}
