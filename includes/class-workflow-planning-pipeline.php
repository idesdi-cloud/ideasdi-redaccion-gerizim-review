<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Etapa desacoplada de planificación documental y editorial.
 * Conserva orden, prompts, persistencia e historial de RC1.6.2.
 */
final class IDG_Workflow_Planning_Pipeline implements IDG_Workflow_Planning_Pipeline_Contract {
    public function prepare(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        $workflow = $this->ensure_document_card($workflow_id, $workflow, $client);
        return $this->ensure_editorial_plan($workflow_id, $workflow, $client);
    }

    private function ensure_web_research(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        if (!class_exists('IDG_Web_Research')) {
            return $workflow;
        }
        return IDG_Web_Research::run($workflow_id, $workflow, $client);
    }

    private function ensure_document_card(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        $workflow = $this->ensure_web_research($workflow_id, $workflow, $client);

        $material = IDG_Workflow_Prompt_Data::document_material_text($workflow);
        if (trim($material) === '') {
            $workflow['document_card'] = '';
            $workflow['document_card_hash'] = '';
            return $workflow;
        }

        $hash = IDG_Temporary_Material::hash($material);
        if ($hash !== '' && !empty($workflow['document_card']) && (string) ($workflow['document_card_hash'] ?? '') === $hash) {
            return $workflow;
        }

        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'material_card_started', 'Creando ficha documental temporal.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);

        $base_data = IDG_Workflow_Prompt_Data::prepare($workflow, '', 'material');
        $chunks = IDG_Temporary_Material::chunks($material);
        if (empty($chunks)) {
            return $workflow;
        }

        $partial_cards = [];
        $total = count($chunks);
        foreach ($chunks as $index => $chunk) {
            $prompt = IDG_Prompt_Library::material_card_prompt($base_data, $chunk, $index + 1, $total);
            $result = $client->complete($prompt);
            if (!$result['success']) {
                $workflow['temp_material_warnings'][] = 'No se pudo crear la ficha documental temporal: ' . $result['message'];
                $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'material_card_failed', $result['message']);
                IDG_Job_Runner::save_workflow($workflow_id, $workflow);
                return $workflow;
            }
            $partial_cards[] = trim((string) $result['content']);
            IDG_Usage_Estimator::record('material_card', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        }

        $card = trim(implode("\n\n", $partial_cards));
        if (count($partial_cards) > 1) {
            $merge_prompt = IDG_Prompt_Library::material_card_merge_prompt($base_data, $card);
            $merge_result = $client->complete($merge_prompt);
            if ($merge_result['success']) {
                IDG_Usage_Estimator::record('material_card_merge', (array) ($merge_result['usage'] ?? []), $workflow_id, (string) ($merge_result['model'] ?? ''));
                $card = trim((string) $merge_result['content']);
            } else {
                $workflow['temp_material_warnings'][] = 'La ficha documental temporal se creó por partes, pero no se pudo unificar automáticamente.';
            }
        }

        $workflow['document_card'] = $card;
        $workflow['document_card_hash'] = $hash;
        $workflow['document_card_created_at'] = current_time('mysql');
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'material_card_completed', 'Ficha documental temporal creada.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('material_card_completed', 'Ficha documental temporal creada.', ['workflow_id' => $workflow_id, 'chunks' => count($chunks)]);
        return $workflow;
    }

    private function ensure_editorial_plan(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): array {
        if (!class_exists('IDG_Editorial_Recipe_Builder') || !class_exists('IDG_Editorial_Plan')) {
            return $workflow;
        }
        $base = IDG_Editorial_Recipe_Builder::build($workflow);
        $stored_base = trim((string) ($workflow['recipe_base'] ?? $workflow['priority_readings'] ?? ''));
        $stored_plain = mb_strtolower(function_exists('remove_accents') ? remove_accents($stored_base) : $stored_base);
        if ($stored_base === '' || str_starts_with($stored_plain, 'leer ') || str_starts_with($stored_plain, 'territorio:') || str_contains($stored_plain, 'desde leer ')) {
            $stored_base = (string) ($base['base_recipe'] ?? $base['recipe'] ?? '');
        }
        $workflow['recipe_base'] = $stored_base;
        $workflow['priority_readings'] = $stored_base;
        $workflow['editorial_recipe'] = $stored_base;
        $workflow['recipe_base_structure'] = $base;
        $hash = IDG_Editorial_Plan::hash($workflow, $base);
        if (!empty($workflow['editorial_recipe_applied']) && (string) ($workflow['editorial_plan_hash'] ?? '') === $hash) {
            return $workflow;
        }

        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_plan_started', 'Construyendo receta aplicada y plan editorial después de la investigación.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        $data = IDG_Workflow_Prompt_Data::prepare($workflow, '', 'editorial_plan');
        $result = $client->complete(IDG_Prompt_Library::editorial_plan_prompt($data));
        if ($result['success']) {
            $plan = IDG_Editorial_Plan::parse(IDG_Workflow_Output_Parser::sanitize((string) $result['content']), $workflow, $base);
            IDG_Usage_Estimator::record('editorial_plan', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        } else {
            $plan = IDG_Editorial_Plan::fallback($workflow, $base);
            $workflow['temp_material_warnings'][] = 'No se pudo generar el plan editorial con IA; se aplicó un plan estructurado de respaldo: ' . (string) ($result['message'] ?? 'error desconocido');
        }
        $workflow = IDG_Editorial_Plan::apply_to_workflow($workflow, $plan, $hash);
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_plan_completed', 'Receta aplicada y plan editorial registrados antes del artículo base.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('editorial_plan_completed', 'Plan editorial aplicado creado.', [
            'workflow_id' => $workflow_id,
            'source' => (string) ($plan['source'] ?? ''),
            'axes' => count((array) ($plan['selected_axes'] ?? [])),
        ]);
        return $workflow;
    }
}
