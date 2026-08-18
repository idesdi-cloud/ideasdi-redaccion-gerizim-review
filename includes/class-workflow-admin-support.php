<?php
if (!defined('ABSPATH')) { exit; }

/** Punto de extensión para helpers administrativos compartidos. */
final class IDG_Workflow_Admin_Support {
    public static function has_generated_workflow_content(array $workflow): bool {
        return IDG_Workflow_Admin_Controller::has_generated_workflow_content($workflow);
    }
}
