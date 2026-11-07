<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Redacción por fases, con validación real y una reparación localizada máxima. */
final class IDG_Workflow_Redaction_Pipeline implements IDG_Workflow_Redaction_Pipeline_Contract {
    public function phases(): array {
        return [IDG_Workflow_Policies::ACTION_GENERATE, IDG_Workflow_Policies::ACTION_EDITORIAL, IDG_Workflow_Policies::ACTION_SEO];
    }

    public function supports(string $phase): bool {
        return in_array($phase, $this->phases(), true);
    }

    public function execute(string $workflow_id, string $phase, array $workflow, IDG_OpenAI_Client $client): void {
        if ($phase === IDG_Workflow_Policies::ACTION_GENERATE) {
            $this->run_generate($workflow_id, $workflow, $client);
        } elseif ($phase === IDG_Workflow_Policies::ACTION_EDITORIAL) {
            $this->run_editorial($workflow_id, $workflow, $client);
        } elseif ($phase === IDG_Workflow_Policies::ACTION_SEO) {
            $this->run_seo($workflow_id, $workflow, $client);
        }
    }

    private function run_generate(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        if (class_exists('IDG_Assignment_Card')) {
            $workflow = IDG_Assignment_Card::attach($workflow);
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        }
        $data = IDG_Workflow_Prompt_Data::prepare($workflow, '', 'generate');
        $result = $client->complete(IDG_Prompt_Library::generate_prompt($data));
        if (empty($result['success'])) {
            $this->save_model_failure($workflow_id, $workflow, 'generate', (string) ($result['message'] ?? 'Falló la generación.'));
            return;
        }

        $candidate = IDG_Workflow_Output_Parser::normalize_editorial_box_markdown(
            IDG_Workflow_Output_Parser::sanitize((string) $result['content'])
        );
        $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'generate');
        $repair = ['attempted' => false, 'applied' => false, 'usage' => [], 'model' => ''];
        if (empty($validation['ok'])) {
            $repair = $this->attempt_localized_repair($workflow_id, 'generate', $candidate, $validation, $workflow, $client);
            if (!empty($repair['applied'])) {
                $candidate = (string) $repair['content'];
                $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'generate');
            }
        }

        $workflow['base_article'] = $candidate;
        // RC1.7.8.5: this is the sole authority for newly generated work.
        // base_article remains a legacy projection for adapters and history.
        if (class_exists('IDG_Editorial_Document')) {
            $workflow['editorial_document'] = IDG_Editorial_Document::from_markdown($candidate, $workflow);
        }
        $workflow['generate_validation'] = $validation;
        $workflow['generate_repair'] = $this->repair_audit($repair);
        $workflow['usage_generate'] = $result['usage'] ?? [];
        $workflow['usage_generate_estimate'] = IDG_Usage_Estimator::record('generate', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        if (empty($validation['ok'])) {
            $this->save_validation_failure($workflow_id, $workflow, 'generate', $validation);
            return;
        }

        $workflow['generated_from_brief'] = true;
        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'generate');
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'generate_completed', 'Artículo base generado y validado para estructura de fase; enlaces y Reel se validan en SEO.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('generate_completed', 'Artículo base validado por fase.', ['workflow_id' => $workflow_id]);
    }

    private function run_editorial(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        $document_mode = class_exists('IDG_Editorial_Document') && IDG_Editorial_Document::exists($workflow);
        $source = $document_mode ? IDG_Editorial_Document::markdown($workflow['editorial_document']) : (string) ($workflow['base_article'] ?? '');
        $data = IDG_Workflow_Prompt_Data::prepare($workflow, $source, 'editorial');
        if ($document_mode && empty($data['editorial_document_manifest_ok'])) {
            $this->save_validation_failure($workflow_id, $workflow, 'editorial', ['ok'=>false, 'errors'=>['Revisión editorial: documento por bloques inválido o desactualizado; no se llamó al modelo.'], 'warnings'=>[], 'summary'=>'- ERROR: Documento editorial inválido o desactualizado.']);
            return;
        }
        $result = $client->complete(IDG_Prompt_Library::editorial_prompt($data));
        if (empty($result['success'])) {
            $this->save_model_failure($workflow_id, $workflow, 'editorial', (string) ($result['message'] ?? 'Falló la revisión editorial.'));
            return;
        }

        $raw = IDG_Workflow_Output_Parser::sanitize((string) $result['content']);
        if ($document_mode) {
            $applied = IDG_Editorial_Document::apply_editorial_response($workflow['editorial_document'], $raw);
            if (empty($applied['ok'])) {
                $reason = sanitize_text_field((string) ($applied['reason'] ?? 'respuesta_incompatible'));
                $warning = 'ADVERTENCIA: respuesta editorial no aplicable (' . $reason . '); se conserva el documento base válido para SEO y revisión manual.';
                $workflow['editorial_output_raw'] = $raw;
                $workflow['editorial_result'] = $source; // Safe legacy projection; never an approval of the raw response.
                $workflow['editorial_diagnosis'] = '- ' . $warning;
                $workflow['editorial_notes'] = (string) (IDG_Workflow_Output_Parser::parse_editorial($raw)['notes'] ?? '');
                $workflow['editorial_validation'] = ['ok' => false, 'errors' => [$warning], 'warnings' => [$warning], 'summary' => '- ' . $warning];
                $workflow['editorial_changed'] = false;
                $workflow['editorial_changed_block_ids'] = [];
                $workflow['editorial_approved'] = false;
                $workflow['editorial_applied'] = false;
                $workflow['editorial_requires_manual_review'] = true;
                $workflow['editorial_repair'] = ['attempted' => false, 'applied' => false, 'reason' => 'document_mode_fail_closed'];
                $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'editorial_safe_base_available');
                $workflow['last_error'] = $warning;
                $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_unapplied', $warning);
                IDG_Job_Runner::save_workflow($workflow_id, $workflow);
                IDG_Logger::log('editorial_unapplied', $warning, ['workflow_id' => $workflow_id, 'reason' => $reason]);
                return;
            }
            $workflow['editorial_document'] = $applied['document'];
            $candidate = IDG_Editorial_Document::markdown($applied['document']);
            $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'editorial');
            $changed_ids = (array) $applied['changed_ids'];
            $changed = !empty($changed_ids);
            $workflow['editorial_output_raw'] = $raw;
            $workflow['editorial_result'] = $candidate; // legacy projection, never authority.
            $workflow['editorial_diagnosis'] = $changed ? '- Comparación real por bloques: changed; IDs: ' . implode(', ', $changed_ids) . '.' : '- Comparación real por bloques: unchanged; no se atribuyen correcciones inexistentes.';
            $workflow['editorial_notes'] = (string) (IDG_Workflow_Output_Parser::parse_editorial($raw)['notes'] ?? '');
            $workflow['editorial_changed'] = $changed;
            $workflow['editorial_changed_block_ids'] = $changed_ids;
            $workflow['editorial_validation'] = $validation;
            $workflow['editorial_approved'] = !empty($validation['ok']);
            $workflow['editorial_applied'] = !empty($validation['ok']);
            $workflow['editorial_requires_manual_review'] = empty($validation['ok']);
            $workflow['editorial_repair'] = ['attempted' => false, 'applied' => false, 'reason' => 'document_mode_no_blind_repair'];
            $workflow['usage_editorial'] = $result['usage'] ?? [];
            $workflow['usage_editorial_estimate'] = IDG_Usage_Estimator::record('editorial', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
            if (empty($validation['ok'])) { $this->save_validation_failure($workflow_id, $workflow, 'editorial', $validation); return; }
            $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'editorial');
            $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_completed', 'Documento editorial revisado por bloques; comparación ' . ($changed ? 'changed.' : 'unchanged.'));
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            return;
        }
        $sections = IDG_Workflow_Output_Parser::parse_editorial($raw);
        $candidate = IDG_Workflow_Output_Parser::normalize_editorial_box_markdown((string) ($sections['article'] ?? $raw));
        $links = IDG_Workflow_Output_Parser::preserve_approved_article_links($source, $candidate, $workflow);
        $candidate = (string) $links['content'];
        $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'editorial');
        $validation = $this->with_link_preservation_errors($validation, (array) ($links['errors'] ?? []));
        $repair = ['attempted' => false, 'applied' => false, 'usage' => [], 'model' => ''];
        if (empty($validation['ok']) && !empty($links['ok'])) {
            $repair = $this->attempt_localized_repair($workflow_id, 'editorial', $candidate, $validation, $workflow, $client);
            if (!empty($repair['applied'])) {
                $candidate = (string) $repair['content'];
                $links = IDG_Workflow_Output_Parser::preserve_approved_article_links($source, $candidate, $workflow);
                $candidate = (string) $links['content'];
                $validation = $this->with_link_preservation_errors(IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'editorial'), (array) ($links['errors'] ?? []));
            }
        }

        $changed = $candidate !== $source;
        $diagnosis = trim((string) ($sections['diagnosis'] ?? ''));
        if (!$changed) {
            $diagnosis = '- Comparación real de contenido: unchanged; no se atribuyen correcciones ni mejoras no realizadas.';
        } else {
            $diagnosis .= ($diagnosis !== '' ? "\n" : '') . '- Comparación real de contenido: changed; la versión revisada difiere byte a byte del artículo base. Esta comparación no demuestra por sí sola la veracidad de cada mejora descrita.';
        }
        $workflow['editorial_output_raw'] = $raw;
        $workflow['editorial_result'] = $candidate;
        $workflow['editorial_diagnosis'] = $diagnosis;
        $workflow['editorial_notes'] = (string) ($sections['notes'] ?? '');
        $workflow['editorial_changed'] = $changed;
        $workflow['editorial_validation'] = $validation;
        $workflow['editorial_repair'] = $this->repair_audit($repair);
        $workflow['usage_editorial'] = $result['usage'] ?? [];
        $workflow['usage_editorial_estimate'] = IDG_Usage_Estimator::record('editorial', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        if (empty($validation['ok'])) {
            $this->save_validation_failure($workflow_id, $workflow, 'editorial', $validation);
            return;
        }

        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'editorial');
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'editorial_completed', 'Revisión editorial validada; comparación registrada como ' . ($changed ? 'changed.' : 'unchanged.'));
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('editorial_completed', 'Revisión editorial validada.', ['workflow_id' => $workflow_id, 'changed' => $changed]);
    }

    private function run_seo(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        $document_mode = class_exists('IDG_Editorial_Document') && IDG_Editorial_Document::exists($workflow);
        $article = $document_mode ? IDG_Editorial_Document::markdown($workflow['editorial_document']) : (string) ($workflow['editorial_result'] ?? $workflow['base_article'] ?? '');
        $data = IDG_Workflow_Prompt_Data::prepare($workflow, $article, 'seo');
        if ($document_mode && empty($data['editorial_document_manifest_ok'])) {
            $this->save_validation_failure($workflow_id, $workflow, 'seo', ['ok'=>false, 'errors'=>['SEO: documento por bloques inválido o desactualizado; no se llamó al modelo.'], 'warnings'=>[], 'summary'=>'- ERROR: Documento editorial inválido o desactualizado.']);
            return;
        }
        $result = $client->complete(IDG_Prompt_Library::seo_prompt($data));
        if (empty($result['success'])) {
            $this->save_model_failure($workflow_id, $workflow, 'seo', (string) ($result['message'] ?? 'Falló la revisión SEO.'));
            return;
        }

        if ($document_mode) {
            $applied = IDG_Editorial_Document::apply_seo_response($workflow['editorial_document'], (string) $result['content'], $workflow);
            if (empty($applied['ok'])) { $this->save_validation_failure($workflow_id, $workflow, 'seo', ['ok'=>false,'errors'=>['SEO: la respuesta altera la forma de bloques del documento; no se sustituyó la prosa.'],'warnings'=>[],'summary'=>'- ERROR: SEO: la respuesta altera la forma de bloques del documento; no se sustituyó la prosa.']); return; }
            $workflow['editorial_document'] = $applied['document'];
            $completed = IDG_Editorial_Document::complete_missing_authorized_links($workflow['editorial_document'], $workflow);
            if (empty($completed['ok'])) { $this->save_validation_failure($workflow_id, $workflow, 'seo', ['ok'=>false,'errors'=>['SEO: no fue posible completar inequívocamente los enlaces autorizados; se conserva el documento previo.'],'warnings'=>[],'summary'=>'- ERROR: Enlaces autorizados no reconciliables.']); return; }
            $workflow['editorial_document'] = $completed['document'];
            $workflow['seo_result'] = IDG_Editorial_Document::projection($applied['document']);
            $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
        }
        $candidate = $document_mode ? $workflow['seo_result'] : IDG_Workflow_Output_Parser::normalize_editorial_box_markdown(
            IDG_Workflow_Output_Parser::sanitize((string) $result['content']),
            true
        );
        $links = IDG_Workflow_Output_Parser::preserve_approved_article_links($article, $candidate, $workflow, true);
        $candidate = (string) $links['content'];
        $workflow['seo_model_response'] = $candidate;
        $workflow['seo_result'] = $candidate;
        $preflight = IDG_Post_Creator::preflight_public($workflow);
        $validation = (array) ($preflight['validation'] ?? []);
        $validation = $this->with_link_preservation_errors($validation, (array) ($links['errors'] ?? []));
        if (empty($validation['ok'])) {
            $preflight['ok'] = false;
            $preflight['validation'] = $validation;
            $preflight['errors'] = (array) $validation['errors'];
        }
        $repair = ['attempted' => false, 'applied' => false, 'usage' => [], 'model' => ''];
        if (empty($preflight['ok']) && !empty($links['ok'])) {
            $repair = $this->attempt_localized_repair($workflow_id, 'seo', $candidate, $validation, $workflow, $client);
            if (!empty($repair['applied'])) {
                $candidate = (string) $repair['content'];
                if ($document_mode) {
                    $repaired = IDG_Editorial_Document::apply_seo_response($workflow['editorial_document'], $candidate, $workflow);
                    if (empty($repaired['ok'])) { $this->save_validation_failure($workflow_id, $workflow, 'seo', ['ok'=>false,'errors'=>['SEO: reparación Reel no aplicable; se conserva el documento previo.'],'warnings'=>[],'summary'=>'- ERROR: Reparación Reel no aplicable.']); return; }
                    $workflow['editorial_document'] = $repaired['document'];
                    $candidate = IDG_Editorial_Document::projection($workflow['editorial_document']);
                }
                $workflow['seo_result'] = $candidate;
                $links = IDG_Workflow_Output_Parser::preserve_approved_article_links($article, $candidate, $workflow, true);
                $candidate = (string) $links['content'];
                $workflow['seo_result'] = $candidate;
                $preflight = IDG_Post_Creator::preflight_public($workflow);
                $validation = (array) ($preflight['validation'] ?? []);
                $validation = $this->with_link_preservation_errors($validation, (array) ($links['errors'] ?? []));
                if (empty($validation['ok'])) {
                    $preflight['ok'] = false;
                    $preflight['validation'] = $validation;
                    $preflight['errors'] = (array) $validation['errors'];
                }
            }
        }

        $prepared = (array) ($preflight['prepared'] ?? []);
        $reel = class_exists('IDG_Reel_Contract') ? IDG_Reel_Contract::inspect((string) ($prepared['reel_package'] ?? '')) : ['valid' => false, 'issues' => ['Reel Contract no disponible.']];
        $workflow['seo_result'] = $this->verified_seo_result(
            (string) ($prepared['processed_seo_result'] ?? $candidate),
            $validation,
            $reel
        );
        // El informe forma parte del sello de escritura. Recalcula el mismo
        // preflight tras sustituir únicamente esa línea interna, sin reparar
        // ni alterar artículo, Reel ni Gutenberg.
        $preflight = IDG_Post_Creator::preflight_public($workflow);
        $validation = (array) ($preflight['validation'] ?? []);
        $validation = $this->with_link_preservation_errors($validation, (array) ($links['errors'] ?? []));
        if (empty($validation['ok'])) {
            $preflight['ok'] = false;
            $preflight['validation'] = $validation;
            $preflight['errors'] = (array) $validation['errors'];
        }
        $prepared = (array) ($preflight['prepared'] ?? []);
        $reel = class_exists('IDG_Reel_Contract') ? IDG_Reel_Contract::inspect((string) ($prepared['reel_package'] ?? '')) : ['valid' => false, 'issues' => ['Reel Contract no disponible.']];
        $workflow['seo_validation'] = $validation;
        $workflow['seo_validation_summary'] = (string) ($validation['summary'] ?? '');
        $workflow['seo_article_validation'] = [
            'ok' => true,
            'errors' => array_values(array_filter((array) ($validation['errors'] ?? []), static fn($error): bool => !preg_match('/\A(?:VO |Falta VO |Falta paquete reel|Las escenas del paquete reel|Escena |Overlay |Línea no reconocida en paquete reel)/u', (string) $error))),
        ];
        $workflow['seo_article_validation']['ok'] = empty($workflow['seo_article_validation']['errors']);
        $workflow['seo_reel_validation'] = $reel;
        $workflow['seo_repair'] = $this->repair_audit($repair);
        $workflow['usage_seo'] = $result['usage'] ?? [];
        $workflow['usage_seo_estimate'] = IDG_Usage_Estimator::record('seo', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow['warnings'] = IDG_Validator::summarize($workflow['seo_result'], (string) ($workflow['keyword'] ?? ''));
        $workflow['feedback_notes'] = IDG_Workflow_Output_Parser::extract_feedback_notes($workflow['seo_result']);
        if (empty($preflight['ok'])) {
            $this->save_validation_failure($workflow_id, $workflow, 'seo', $validation);
            return;
        }

        $workflow['seo_preflight_payload_hash'] = IDG_Post_Creator::prepared_payload_hash($preflight);
        $workflow['seo_preflight_post_content'] = (string) ($prepared['post_content'] ?? '');
        $workflow['seo_preflight_approved_at'] = current_time('mysql');
        $workflow = IDG_Workflow_Policies::mark_completed($workflow, 'seo');
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, 'seo_completed', 'Revisión SEO completada después de aprobar el mismo preflight Gutenberg que usará la escritura.');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log('seo_completed', 'SEO y payload Gutenberg validados.', ['workflow_id' => $workflow_id, 'payload_hash' => $workflow['seo_preflight_payload_hash']]);
    }

    private function attempt_localized_repair(string $workflow_id, string $phase, string $content, array $validation, array $workflow, IDG_OpenAI_Client $client): array {
        $allowed = $this->allowed_segments($phase, $content, $validation);
        $audit = ['attempted' => true, 'applied' => false, 'content' => $content, 'usage' => [], 'model' => '', 'reason' => ''];
        if (empty($allowed)) {
            $audit['attempted'] = false;
            $audit['reason'] = 'no_permitted_segments';
            return $audit;
        }
        $data = IDG_Workflow_Prompt_Data::prepare($workflow, $content, $phase);
        $result = $client->complete(IDG_Prompt_Library::localized_repair_prompt($data, $phase, $content, $validation, $allowed));
        $audit['usage'] = (array) ($result['usage'] ?? []);
        $audit['model'] = (string) ($result['model'] ?? '');
        IDG_Usage_Estimator::record($phase . '_repair', $audit['usage'], $workflow_id, $audit['model']);
        if (empty($result['success'])) {
            $audit['reason'] = 'client_failure';
            return $audit;
        }
        $patched = $this->apply_segment_patches($content, (string) ($result['content'] ?? ''), $allowed);
        if (empty($patched['ok'])) {
            $audit['reason'] = (string) ($patched['reason'] ?? 'invalid_patch');
            return $audit;
        }
        $audit['content'] = (string) $patched['content'];
        $audit['applied'] = $audit['content'] !== $content;
        $audit['reason'] = $audit['applied'] ? 'applied' : 'no_change';
        return $audit;
    }

    private function with_link_preservation_errors(array $validation, array $errors): array {
        if (empty($errors)) return $validation;
        $validation['ok'] = false;
        $validation['errors'] = array_values(array_unique(array_merge((array) ($validation['errors'] ?? []), $errors)));
        $validation['summary'] = implode("\n", array_map(static fn($error): string => '- ERROR: ' . $error, $validation['errors']));
        $validation['error_codes'] = class_exists('IDG_Final_Guard') ? IDG_Final_Guard::classify_errors($validation['errors']) : [];
        return $validation;
    }

    private function allowed_segments(string $phase, string $content, array $validation): array {
        $lines = preg_split('/\n/', str_replace(["\r\n", "\r"], "\n", $content)) ?: [];
        $errors = implode("\n", array_map('strval', (array) ($validation['errors'] ?? [])));
        $allowed = [];
        if ($phase === 'seo') {
            $inspection = IDG_Reel_Contract::inspect($this->section_body($content, 'PAQUETE REEL'));
            foreach (range(1, 5) as $scene) {
                if ((int) ($inspection['vo_counts'][$scene] ?? 0) !== 14) {
                    $allowed[] = 'VO:' . $scene;
                }
            }
        }

        foreach ($lines as $index => $line) {
            $line = (string) $line;
            if (str_contains($errors, 'H1') && preg_match('/^\s*#\s+\S/u', $line)) {
                $allowed[] = 'LINE:' . ($index + 1);
            }
            if (str_contains($errors, 'caja editorial') && $this->is_box_body_line($lines, $index)) {
                $allowed[] = 'LINE:' . ($index + 1);
            }
            if (preg_match('/frase artificial, metaliteraria o documental: “([^”]+)”/u', $errors, $match)
                && str_contains(mb_strtolower(remove_accents($line)), mb_strtolower(remove_accents((string) $match[1])))) {
                $allowed[] = 'LINE:' . ($index + 1);
            }
        }
        return array_values(array_unique($allowed));
    }

    private function is_box_body_line(array $lines, int $index): bool {
        if (trim((string) ($lines[$index] ?? '')) === '') {
            return false;
        }
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $previous = trim((string) ($lines[$cursor] ?? ''));
            if ($previous === '') {
                continue;
            }
            return (bool) preg_match('/^(?:\*\*)?\s*Caja editorial\s*:?/iu', $previous);
        }
        return false;
    }

    private function apply_segment_patches(string $content, string $response, array $allowed): array {
        $response = preg_replace('/\A```(?:json)?\s*|\s*```\z/iu', '', trim($response));
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded) || !isset($decoded['patches']) || !is_array($decoded['patches']) || empty($decoded['patches'])) {
            return ['ok' => false, 'reason' => 'invalid_or_empty_patch'];
        }
        $lines = preg_split('/\n/', str_replace(["\r\n", "\r"], "\n", $content));
        if (!is_array($lines)) {
            return ['ok' => false, 'reason' => 'content_split_failed'];
        }
        $seen = [];
        foreach ($decoded['patches'] as $patch) {
            $segment = is_array($patch) ? trim((string) ($patch['segment'] ?? '')) : '';
            $replacement = is_array($patch) ? (string) ($patch['replacement'] ?? '') : '';
            if ($segment === '' || !in_array($segment, $allowed, true) || isset($seen[$segment]) || str_contains($replacement, "\n") || trim($replacement) === '') {
                return ['ok' => false, 'reason' => 'patch_outside_allowed_scope'];
            }
            $seen[$segment] = true;
            if (preg_match('/\ALINE:(\d+)\z/', $segment, $m)) {
                $index = (int) $m[1] - 1;
                if (!array_key_exists($index, $lines)) {
                    return ['ok' => false, 'reason' => 'line_not_found'];
                }
                $lines[$index] = $replacement;
                continue;
            }
            if (preg_match('/\AVO:([1-5])\z/', $segment, $m)) {
                $scene = (int) $m[1];
                $matches = [];
                foreach ($lines as $index => $line) {
                    if (preg_match('/^\s*VO\s+' . $scene . '\s*:/iu', (string) $line)) {
                        $matches[] = $index;
                    }
                }
                if (count($matches) !== 1) {
                    return ['ok' => false, 'reason' => 'vo_not_unique'];
                }
                $lines[$matches[0]] = 'VO ' . $scene . ': ' . trim($replacement);
                continue;
            }
            return ['ok' => false, 'reason' => 'unknown_segment'];
        }
        return ['ok' => true, 'content' => implode("\n", $lines)];
    }

    private function section_body(string $content, string $label): string {
        $pattern = '/^\s*(?:#{1,6}\s*)?' . preg_quote($label, '/') . '\s*:?\s*$\R(.*?)(?=^\s*(?:#{1,6}\s*)?(?:RETROALIMENTACIÓN GERIZIM|ARTÍCULO FINAL|META DESCRIPTION|INFORME SEO INTERNO|COPY PARA REDES)\s*:?\s*$|\z)/imsu';
        return preg_match($pattern, $content, $m) ? trim((string) $m[1]) : '';
    }

    private function save_model_failure(string $workflow_id, array $workflow, string $phase, string $message): void {
        $workflow = IDG_Workflow_Policies::mark_failed($workflow, $message);
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, $phase . '_failed', $message);
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log($phase . '_failed', $message, ['workflow_id' => $workflow_id]);
    }

    private function save_validation_failure(string $workflow_id, array $workflow, string $phase, array $validation): void {
        $message = ucfirst($phase) . ' requiere intervención: ' . implode(' · ', (array) ($validation['errors'] ?? []));
        $workflow = IDG_Workflow_Policies::mark_failed($workflow, $message);
        $workflow[$phase . '_requires_intervention'] = true;
        $workflow = IDG_Job_Runner::append_history_snapshot($workflow, $phase . '_validation_failed', $message);
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        IDG_Logger::log($phase . '_validation_failed', $message, ['workflow_id' => $workflow_id]);
    }

    private function repair_audit(array $repair): array {
        return [
            'attempted' => !empty($repair['attempted']),
            'applied' => !empty($repair['applied']),
            'reason' => (string) ($repair['reason'] ?? ''),
            'usage' => (array) ($repair['usage'] ?? []),
            'model' => (string) ($repair['model'] ?? ''),
        ];
    }

    /** Reemplaza únicamente el claim del modelo por el resultado real del plugin. */
    private function verified_seo_result(string $result, array $validation, array $reel): string {
        $preflight = !empty($validation['ok']) ? 'preflight Gutenberg aprobado' : 'preflight Gutenberg requiere intervención';
        $reel_status = !empty($reel['valid']) ? 'Reel validado' : 'Reel requiere intervención';
        $line = '- Validaciones verificadas por plugin: ' . $preflight . '; ' . $reel_status . '.';
        $label = 'Declaraciones del modelo (no equivalen a verificación del plugin):';
        return (string) preg_replace_callback(
            '/(^INFORME SEO INTERNO[ \t]*\R)(.*?)(?=^COPY PARA REDES[ \t]*\r?$)/msu',
            static function (array $match) use ($line, $label): string {
                $body = str_replace($label, '', $match[2]);
                $body = preg_replace('/^[ \t]*[-*][ \t]*Validaciones(?:[ \t]+verificadas[ \t]+por[ \t]+plugin)?[ \t]*:.*$/imu', '', $body);
                return $match[1] . $label . "\n" . trim((string) $body) . "\n\n" . $line . "\n\n";
            },
            $result,
            1
        );
    }
}
