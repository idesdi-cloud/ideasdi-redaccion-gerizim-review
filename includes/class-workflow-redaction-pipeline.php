<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Etapa desacoplada de redacción. Solo genera, revisa y optimiza texto;
 * no contiene creación ni actualización de publicaciones WordPress.
 */
final class IDG_Workflow_Redaction_Pipeline implements IDG_Workflow_Redaction_Pipeline_Contract {
    public function phases(): array {
        return [
            IDG_Workflow_Policies::ACTION_GENERATE,
            IDG_Workflow_Policies::ACTION_EDITORIAL,
            IDG_Workflow_Policies::ACTION_SEO,
        ];
    }

    public function supports(string $phase): bool {
        return in_array($phase, $this->phases(), true);
    }

    public function execute(string $workflow_id, string $phase, array $workflow, IDG_OpenAI_Client $client): void {
        if ($phase === IDG_Workflow_Policies::ACTION_GENERATE) {
            $this->run_generate($workflow_id, $workflow, $client);
            return;
        }
        if ($phase === IDG_Workflow_Policies::ACTION_EDITORIAL) {
            $this->run_editorial($workflow_id, $workflow, $client);
            return;
        }
        if ($phase === IDG_Workflow_Policies::ACTION_SEO) {
            $this->run_seo($workflow_id, $workflow, $client);
        }
    }

    private function run_generate(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        }
        $prompt = IDG_Prompt_Library::generate_prompt(IDG_Workflow_Prompt_Data::prepare($workflow, '', 'generate'));
        $result = $client->complete($prompt);

        if (!$result['success']) {
            $workflow = IDG_Workflow_Policies::mark_failed($workflow, (string) $result['message']);
            $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'generate_failed', $result['message']);
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('generate_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow['base_article'] = IDG_Workflow_Output_Parser::sanitize((string) $result['content']);
        $workflow['generated_from_brief'] = true;
        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'generate');
        $workflow['usage_generate'] = $result['usage'] ?? [];
        $workflow['usage_generate_estimate'] = IDG_Usage_Estimator::record('generate', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'generate_completed', 'Artículo base generado desde el brief editorial.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('generate_completed', 'Artículo base generado desde WordPress.', ['workflow_id' => $workflow_id]);
    }

    private function run_editorial(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        $prompt = IDG_Prompt_Library::editorial_prompt(IDG_Workflow_Prompt_Data::prepare($workflow, (string) ($workflow['base_article'] ?? ''), 'editorial'));
        $result = $client->complete($prompt);

        if (!$result['success']) {
            $workflow = IDG_Workflow_Policies::mark_failed($workflow, (string) $result['message']);
            $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_failed', $result['message']);
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('editorial_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $editorial_output = IDG_Workflow_Output_Parser::sanitize((string) $result['content']);
        $editorial_sections = IDG_Workflow_Output_Parser::parse_editorial($editorial_output);
        $workflow['editorial_output_raw'] = $editorial_output;
        $workflow['editorial_result'] = (string) ($editorial_sections['article'] ?? $editorial_output);
        $workflow['editorial_diagnosis'] = (string) ($editorial_sections['diagnosis'] ?? '');
        $workflow['editorial_notes'] = (string) ($editorial_sections['notes'] ?? '');
        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'editorial');
        $workflow['usage_editorial'] = $result['usage'] ?? [];
        $workflow['usage_editorial_estimate'] = IDG_Usage_Estimator::record('editorial', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_completed', 'Revisión editorial completada.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('editorial_completed', 'Revisión editorial completada.', ['workflow_id' => $workflow_id]);
    }

    private function run_seo(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        $article = (string) ($workflow['editorial_result'] ?? $workflow['base_article'] ?? '');
        $prompt = IDG_Prompt_Library::seo_prompt(IDG_Workflow_Prompt_Data::prepare($workflow, $article, 'seo'));
        $result = $client->complete($prompt);

        if (!$result['success']) {
            $workflow = IDG_Workflow_Policies::mark_failed($workflow, (string) $result['message']);
            $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'seo_failed', $result['message']);
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            IDG_Logger::log('seo_failed', $result['message'], ['workflow_id' => $workflow_id]);
            return;
        }

        $workflow['seo_result'] = IDG_Workflow_Output_Parser::sanitize((string) $result['content']);
        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'seo');
        $workflow['usage_seo'] = $result['usage'] ?? [];
        $workflow['usage_seo_estimate'] = IDG_Usage_Estimator::record('seo', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow['warnings'] = IDG_Validator::summarize($result['content'], (string) ($workflow['keyword'] ?? ''));
        $workflow['feedback_notes'] = IDG_Workflow_Output_Parser::extract_feedback_notes($result['content']);
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'seo_completed', 'Revisión SEO completada.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('seo_completed', 'Revisión SEO completada.', ['workflow_id' => $workflow_id]);
    }
}
