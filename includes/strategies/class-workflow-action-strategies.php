<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base inmutable para estrategias de acciones.
 */
abstract class IDG_Abstract_Workflow_Action_Strategy implements IDG_Workflow_Action_Strategy_Contract {
    /** @var string[] */
    private array $supported_actions;

    /**
     * @param string[] $supported_actions
     */
    public function __construct(array $supported_actions) {
        $this->supported_actions = array_values($supported_actions);
    }

    final public function actions(): array {
        return $this->supported_actions;
    }

    final public function supports(string $action): bool {
        return in_array($action, $this->supported_actions, true);
    }
}

final class IDG_Generate_Workflow_Action_Strategy extends IDG_Abstract_Workflow_Action_Strategy {
    public function __construct() {
        parent::__construct([IDG_Workflow_Policies::ACTION_GENERATE]);
    }

    public function execute(string $workflow_id, string $action, array $workflow): void {
        IDG_Job_Runner::execute_generate_stage($workflow_id, $workflow);
    }
}

final class IDG_Editorial_Workflow_Action_Strategy extends IDG_Abstract_Workflow_Action_Strategy {
    public function __construct() {
        parent::__construct([IDG_Workflow_Policies::ACTION_EDITORIAL]);
    }

    public function execute(string $workflow_id, string $action, array $workflow): void {
        IDG_Job_Runner::execute_editorial_stage($workflow_id, $workflow);
    }
}

final class IDG_Seo_Workflow_Action_Strategy extends IDG_Abstract_Workflow_Action_Strategy {
    public function __construct() {
        parent::__construct([IDG_Workflow_Policies::ACTION_SEO]);
    }

    public function execute(string $workflow_id, string $action, array $workflow): void {
        IDG_Job_Runner::execute_seo_stage($workflow_id, $workflow);
    }
}

final class IDG_Draft_Workflow_Action_Strategy extends IDG_Abstract_Workflow_Action_Strategy {
    public function __construct() {
        parent::__construct([IDG_Workflow_Policies::ACTION_DRAFT, IDG_Workflow_Policies::ACTION_DRAFT_FORCE]);
    }

    public function execute(string $workflow_id, string $action, array $workflow): void {
        if (IDG_Workflow_Policies::is_force_action($action)) {
            $workflow['force_validation_override'] = true;
            $workflow = IDG_Job_Runner::append_history_snapshot(
                $workflow,
                'draft_force_requested',
                'El editor solicitó crear una entrada pendiente ignorando la validación real.'
            );
        } else {
            unset($workflow['force_validation_override']);
        }

        IDG_Job_Runner::execute_draft_stage($workflow_id, $workflow);
    }
}

final class IDG_Recurring_Content_Workflow_Action_Strategy extends IDG_Abstract_Workflow_Action_Strategy {
    public function __construct() {
        parent::__construct([IDG_Workflow_Policies::ACTION_RECURRING_CONTENT, IDG_Workflow_Policies::ACTION_RECURRING_CONTENT_FORCE]);
    }

    public function execute(string $workflow_id, string $action, array $workflow): void {
        if (IDG_Workflow_Policies::is_force_action($action)) {
            $workflow['force_validation_override'] = true;
            $workflow = IDG_Job_Runner::append_history_snapshot(
                $workflow,
                'recurring_content_force_requested',
                'El editor solicitó aplicar la redacción a la publicación ignorando la validación real.'
            );
        } else {
            unset($workflow['force_validation_override']);
        }

        IDG_Job_Runner::execute_recurring_content_stage($workflow_id, $workflow);
    }
}

/**
 * Centro único de selección de estrategias de RC1.6.1.
 *
 * Una acción desconocida conserva la semántica histórica: no ejecuta etapa y
 * no se convierte automáticamente en error.
 */
final class IDG_Workflow_Action_Strategy_Center {
    /** @var IDG_Workflow_Action_Strategy_Contract[]|null */
    private static ?array $strategies = null;

    /**
     * @return IDG_Workflow_Action_Strategy_Contract[]
     */
    public static function all(): array {
        if (self::$strategies === null) {
            self::$strategies = [
                new IDG_Generate_Workflow_Action_Strategy(),
                new IDG_Editorial_Workflow_Action_Strategy(),
                new IDG_Seo_Workflow_Action_Strategy(),
                new IDG_Draft_Workflow_Action_Strategy(),
                new IDG_Recurring_Content_Workflow_Action_Strategy(),
            ];
        }

        return self::$strategies;
    }

    public static function for_action(string $action): ?IDG_Workflow_Action_Strategy_Contract {
        foreach (self::all() as $strategy) {
            if ($strategy->supports($action)) {
                return $strategy;
            }
        }

        return null;
    }

    public static function execute(string $workflow_id, string $action, array $workflow): bool {
        $strategy = self::for_action($action);
        if ($strategy === null) {
            return false;
        }

        $strategy->execute($workflow_id, $action, $workflow);
        return true;
    }
}
