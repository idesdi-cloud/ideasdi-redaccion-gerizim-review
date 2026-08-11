<?php
if (!defined('ABSPATH')) {
    exit;
}

abstract class IDG_Abstract_Workflow_Stage_Input_Adapter implements IDG_Workflow_Stage_Input_Adapter_Contract {
    public function adapt(array $workflow): array {
        IDG_Workflow_Contract::inspect($workflow);
        return IDG_Workflow_Contract::preserve($workflow);
    }
}

final class IDG_Planning_Workflow_Stage_Input_Adapter extends IDG_Abstract_Workflow_Stage_Input_Adapter {
    public function stage(): string {
        return 'planning';
    }
}

final class IDG_Redaction_Workflow_Stage_Input_Adapter extends IDG_Abstract_Workflow_Stage_Input_Adapter {
    public function stage(): string {
        return 'redaction';
    }
}

final class IDG_Workflow_Stage_Input_Adapter_Registry {
    /** @var array<string, IDG_Workflow_Stage_Input_Adapter_Contract>|null */
    private static ?array $adapters = null;

    public static function for_stage(string $stage): IDG_Workflow_Stage_Input_Adapter_Contract {
        if (self::$adapters === null) {
            self::$adapters = [];
            foreach ([
                new IDG_Planning_Workflow_Stage_Input_Adapter(),
                new IDG_Redaction_Workflow_Stage_Input_Adapter(),
            ] as $adapter) {
                self::$adapters[$adapter->stage()] = $adapter;
            }
        }

        $stage = sanitize_key($stage);
        return self::$adapters[$stage] ?? self::$adapters['redaction'];
    }
}
