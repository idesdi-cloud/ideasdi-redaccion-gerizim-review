<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class IDG_Abstract_Workflow_Input_Adapter implements IDG_Workflow_Input_Adapter_Contract {
    final public function adapt(array $workflow): array {
        // El adaptador es deliberadamente transparente durante la migración
        // progresiva. Las normalizaciones existentes continúan en sus clases
        // originales para evitar cambios editoriales o de persistencia.
        IDG_Workflow_Contract::inspect($workflow);
        return IDG_Workflow_Contract::preserve($workflow);
    }
}

final class IDG_Legacy_Workflow_Input_Adapter extends IDG_Abstract_Workflow_Input_Adapter {
    public function source(): string {
        return 'legacy';
    }
}

final class IDG_Admin_Workflow_Input_Adapter extends IDG_Abstract_Workflow_Input_Adapter {
    public function source(): string {
        return 'admin';
    }
}

final class IDG_Radar_Workflow_Input_Adapter extends IDG_Abstract_Workflow_Input_Adapter {
    public function source(): string {
        return 'radar';
    }
}

final class IDG_Recurring_Workflow_Input_Adapter extends IDG_Abstract_Workflow_Input_Adapter {
    public function source(): string {
        return 'recurring';
    }
}

final class IDG_Traceability_Workflow_Input_Adapter extends IDG_Abstract_Workflow_Input_Adapter {
    public function source(): string {
        return 'traceability';
    }
}

final class IDG_Workflow_Input_Adapter_Registry {
    /** @var array<string, IDG_Workflow_Input_Adapter_Contract>|null */
    private static ?array $adapters = null;

    public static function for_source(string $source): IDG_Workflow_Input_Adapter_Contract {
        if (self::$adapters === null) {
            self::$adapters = [];
            foreach ([
                new IDG_Legacy_Workflow_Input_Adapter(),
                new IDG_Admin_Workflow_Input_Adapter(),
                new IDG_Radar_Workflow_Input_Adapter(),
                new IDG_Recurring_Workflow_Input_Adapter(),
                new IDG_Traceability_Workflow_Input_Adapter(),
            ] as $adapter) {
                self::$adapters[$adapter->source()] = $adapter;
            }
        }

        $source = IDG_Workflow_Contract::normalize_source($source);
        return self::$adapters[$source] ?? self::$adapters['legacy'];
    }
}
