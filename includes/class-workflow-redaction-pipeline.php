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

    /** Revalida un expediente editorial persistido sin modelo, posts ni efectos SEO. */
    public function revalidate_without_ai(string $workflow_id, array $workflow_argument): array {
        $workflow = IDG_Job_Runner::get_workflow($workflow_id);
        $deny = static function (string $message): array {
            return ['ok' => false, 'message' => $message, 'errors' => [$message]];
        };
        if ($workflow === [] || (string) ($workflow['workflow_id'] ?? '') !== $workflow_id
            || serialize($workflow_argument) !== serialize($workflow)) {
            return $deny('El expediente persistido no coincide con la solicitud.');
        }
        if ((int) ($workflow['user_id'] ?? 0) !== (int) get_current_user_id()
            || !current_user_can('edit_posts')) {
            return $deny('El editor actual no es el propietario autorizado.');
        }
        if ((string) ($workflow['workflow_origin'] ?? '') !== 'manual'
            || !in_array((string) ($workflow['status'] ?? ''), ['failed', 'completed'], true)
            || (string) ($workflow['status'] ?? '') === 'processing'
            || !empty($workflow['processing'])
            || !empty($workflow['draft_post_id'])
            || !empty($workflow['post_id'])
            || !empty($workflow['target_post_id'])
            || !empty($workflow['recurring_target_post_id'])
            || !empty($workflow['recurring_target_post_type'])
            || (string) ($workflow['workflow_origin'] ?? '') === 'recurring_update') {
            return $deny('El expediente no es elegible para revalidación local.');
        }
        if (!class_exists('IDG_Editorial_Document') || !IDG_Editorial_Document::exists($workflow)) {
            return $deny('Falta un documento editorial v1 válido.');
        }
        $document = (array) $workflow['editorial_document'];
        $stored_hash = (string) ($document['hash'] ?? '');
        $check = $document;
        unset($check['hash'], $check['revision']);
        $this->sort_revalidation_value($check);
        $computed_hash = hash('sha256', (string) wp_json_encode($check, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if ($stored_hash === '' || !hash_equals($stored_hash, $computed_hash)
            || (string) ($workflow['seo_result'] ?? '') !== IDG_Editorial_Document::projection($document)) {
            return $deny('El documento editorial o su proyección SEO fueron manipulados.');
        }
        $manifest = IDG_Editorial_Document::prompt_manifest($workflow, IDG_Editorial_Document::markdown($document), 'seo');
        if (empty($manifest['ok'])) return $deny('El manifiesto estructural del documento es inválido.');
        $diagnostics = (array) ($workflow['seo_response_diagnostics'] ?? []);
        $patch = (array) ($diagnostics['patch_proposal'] ?? []);
        if (!empty($patch['received']) && empty($patch['applied'])) {
            unset($workflow['seo_preflight_payload_hash'], $workflow['seo_preflight_post_content'], $workflow['seo_preflight_approved_at']);
            $message = 'SEO: PATCH explícito rechazado; revisión manual obligatoria.';
            $workflow['status'] = 'failed';
            $workflow['last_error'] = $message;
            $workflow['seo_validation'] = ['ok' => false, 'errors' => [$message], 'warnings' => [], 'summary' => '- ERROR: ' . $message];
            $workflow['seo_validation_summary'] = $workflow['seo_validation']['summary'];
            $workflow['seo_requires_intervention'] = true;
            $workflow['seo_response_diagnostics']['final_preflight'] = 'requires_intervention';
            IDG_Job_Runner::save_workflow($workflow_id, $workflow);
            return $deny($message);
        }
        $stamps = [
            (string) ($workflow['seo_preflight_payload_hash'] ?? ''),
            (string) ($workflow['seo_preflight_post_content'] ?? ''),
            (string) ($workflow['seo_preflight_approved_at'] ?? ''),
        ];
        $has_stamps = $stamps[0] !== '' || $stamps[1] !== '' || $stamps[2] !== '';
        if ((string) ($workflow['status'] ?? '') === 'failed' && $has_stamps) {
            return $deny('El expediente fallido conserva sellos residuales.');
        }
        $preflight = IDG_Post_Creator::preflight_public($workflow);
        $prepared = (array) ($preflight['prepared'] ?? []);
        $errors = array_merge((array) ($preflight['errors'] ?? []), (array) (($preflight['validation']['errors'] ?? [])));
        if (empty($preflight['ok']) || empty($preflight['validation']['ok']) || $errors !== []
            || trim((string) ($prepared['post_content'] ?? '')) === ''
            || trim((string) IDG_Post_Creator::prepared_payload_hash($preflight)) === '') {
            return $deny('El preflight público actual no es aprobable.');
        }
        $payload_hash = (string) IDG_Post_Creator::prepared_payload_hash($preflight);
        $post_content = (string) ($prepared['post_content'] ?? '');
        if ((string) ($workflow['status'] ?? '') === 'completed') {
            if (!$stamps[0] || !$stamps[1] || !$stamps[2]
                || !hash_equals($stamps[0], $payload_hash)
                || !hash_equals($stamps[1], $post_content)) {
                return $deny('Los sellos de aprobación no coinciden con el preflight actual.');
            }
            return ['ok' => true, 'message' => 'Revalidación local ya aprobada.', 'preflight' => $preflight];
        }
        $workflow['status'] = 'completed';
        $workflow['last_error'] = '';
        $workflow['seo_requires_intervention'] = false;
        $workflow['seo_validation'] = $preflight['validation'];
        $workflow['seo_validation_summary'] = (string) ($preflight['validation']['summary'] ?? '');
        $workflow['seo_response_diagnostics']['final_preflight'] = 'approved';
        $workflow['seo_preflight_payload_hash'] = $payload_hash;
        $workflow['seo_preflight_post_content'] = $post_content;
        $workflow['seo_preflight_approved_at'] = current_time('mysql');
        IDG_Job_Runner::save_workflow($workflow_id, $workflow);
        return ['ok' => true, 'message' => 'Revalidación local aprobada.', 'preflight' => $preflight];
    }

    private function sort_revalidation_value(array &$value): void {
        ksort($value);
        foreach ($value as &$item) if (is_array($item)) $this->sort_revalidation_value($item);
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
            // P0R1: a strictly whitelisted, meaning-preserving contraction can
            // repair a marginal box-length overflow without any extra AI call.
            // The REAL Final Guard is the acceptance authority, not this helper.
            $repair = $this->attempt_safe_box_compaction($candidate, $validation, $workflow);
            if (!empty($repair['applied'])) {
                $candidate = (string) $repair['content'];
                $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'generate');
            }
            if (empty($validation['ok'])) {
                $repair = $this->attempt_localized_repair($workflow_id, 'generate', $candidate, $validation, $workflow, $client);
                if (!empty($repair['applied'])) {
                    $candidate = (string) $repair['content'];
                    $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'generate');
                }
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
        // P1Q2: record a simulated route, never branch on it. The normal
        // editorial model call below MUST run for every valid workflow.
        $workflow['editorial_shadow_decision'] = (array) ($data['editorial_shadow_decision'] ?? []);
        $workflow['editorial_shadow_decision']['actual_editorial_call_attempted'] = true;
        $result = $client->complete(IDG_Prompt_Library::editorial_prompt($data));
        if (empty($result['success'])) {
            $this->save_model_failure($workflow_id, $workflow, 'editorial', (string) ($result['message'] ?? 'Falló la revisión editorial.'));
            return;
        }

        $raw = IDG_Workflow_Output_Parser::sanitize((string) $result['content']);
        $parsed_editorial = IDG_Workflow_Output_Parser::parse_editorial($raw);
        $workflow['editorial_model_diagnosis'] = trim((string) ($parsed_editorial['diagnosis'] ?? ''));
        $workflow['editorial_review_signals'] = (string) ($data['editorial_review_signals'] ?? '');
        $workflow['editorial_objective_checks'] = (array) ($data['editorial_objective_checks'] ?? []);
        if ($document_mode) {
            $applied = IDG_Editorial_Document::apply_editorial_response($workflow['editorial_document'], $raw);
            if (empty($applied['ok'])) {
                $reason = sanitize_text_field((string) ($applied['reason'] ?? 'respuesta_incompatible'));
                $warning = 'ADVERTENCIA: respuesta editorial no aplicable (' . $reason . '); se conserva el documento base válido para SEO y revisión manual.';
                $workflow['editorial_output_raw'] = $raw;
                $workflow['editorial_result'] = $source; // Safe legacy projection; never an approval of the raw response.
                $workflow['editorial_diagnosis'] = '- ' . $warning;
                $workflow['editorial_notes'] = $this->verified_editorial_link_notes((string) ($parsed_editorial['notes'] ?? ''), $source, $workflow);
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
            // P0: explicitly documented research sources are not article citations.
            // A deterministic, fail-closed cleanup counts as an editorial edit;
            // unlike a model echo, it must appear in the real changed-block audit.
            $source_curation = IDG_Editorial_Document::curate_documentary_source_mentions($applied['document'], $workflow);
            if (empty($source_curation['ok'])) {
                $reason = sanitize_text_field((string) ($source_curation['reason'] ?? 'ambiguous_documentary_reference'));
                $message = 'Revisión editorial: fuente secundaria en el artículo sin reparación segura (' . $reason . ').';
                $workflow['editorial_source_curation'] = ['ok' => false, 'reason' => $reason];
                $workflow['editorial_requires_manual_review'] = true;
                $workflow['editorial_approved'] = false;
                $workflow['editorial_applied'] = false;
                $this->save_validation_failure($workflow_id, $workflow, 'editorial', ['ok' => false, 'errors' => [$message], 'warnings' => [], 'summary' => '- ERROR: ' . $message]);
                return;
            }
            $applied['document'] = $source_curation['document'];
            $applied['changed_ids'] = array_values(array_unique(array_merge(
                (array) ($applied['changed_ids'] ?? []),
                (array) ($source_curation['changed_ids'] ?? [])
            )));
            $workflow['editorial_source_curation'] = [
                'ok' => true,
                'removed' => (int) ($source_curation['removed'] ?? 0),
            ];
            // The technical link colleague works beside the language editor,
            // before SEO. It silently repairs only exact authorized links;
            // unresolved/ambiguous cases remain available for the dedicated
            // SEO paragraph editor. It never blocks the editorial review.
            $before_link_alignment = (array) $applied['document'];
            $aligned = IDG_Editorial_Document::complete_missing_authorized_links($before_link_alignment,$workflow);
            $applied['document'] = (array)($aligned['document'] ?? $before_link_alignment);
            $alignment_ids = [];
            foreach ((array)($applied['document']['blocks'] ?? []) as $i=>$block) {
                $before = (array)($before_link_alignment['blocks'][$i] ?? []);
                if ((string)($block['text'] ?? '') !== (string)($before['text'] ?? '')) {
                    $alignment_ids[] = (string)($block['id'] ?? '');
                }
            }
            $applied['changed_ids']=array_values(array_unique(array_merge(
                (array)($applied['changed_ids']??[]),array_filter($alignment_ids)
            )));
            $workflow['editorial_link_team']=[
                'state'=>!empty($aligned['ok'])?'authorized_links_complete':'remaining_for_seo',
                'deterministic_links'=>(array)($aligned['applied']??[]),
                'changed_block_ids'=>$alignment_ids,
                'remaining'=>array_map(static fn(array $r):array=>[
                    'internal'=>!empty($r['internal']),
                    'reason'=>(string)($r['reason']??'unresolved')
                ],(array)($aligned['remaining']??[])),
            ];
            $workflow['editorial_document'] = $applied['document'];
            $candidate = IDG_Editorial_Document::markdown($applied['document']);
            $validation = IDG_Final_Guard::validate_article_phase($candidate, $workflow, 'editorial');
            $changed_ids = (array) $applied['changed_ids'];
            $changed = !empty($changed_ids);
            $workflow['editorial_output_raw'] = $raw;
            $workflow['editorial_result'] = $candidate; // legacy projection, never authority.
            $workflow['editorial_diagnosis'] = $changed ? '- Comparación real por bloques: changed; IDs: ' . implode(', ', $changed_ids) . '.' : '- Comparación real por bloques: unchanged; no se atribuyen correcciones inexistentes.';
            if (!empty($source_curation['removed'])) {
                $workflow['editorial_diagnosis'] .= "\n" . '- Curación documental: retirada localizada de ' . (int) $source_curation['removed'] . ' referencia(s) a fuentes de investigación; enlaces oficiales y P2 conservados.';
            }
            if (!empty($alignment_ids)) {
                $workflow['editorial_diagnosis'] .= "\n" . '- Alineación técnica de enlaces en Revisión editorial: ' . count($alignment_ids) . ' bloque(s) ajustado(s) con destinos autorizados; sin cambios de hechos o redacción atribuibles al modelo.';
            }
            $workflow['editorial_notes'] = $this->verified_editorial_link_notes((string) ($parsed_editorial['notes'] ?? ''), $candidate, $workflow);
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
        $sections = $parsed_editorial;
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
            $diagnosis = '- Comparación real de contenido: unchanged; no se atribuyen correcciones ni mejoras no realizadas. El diagnóstico original del modelo se conserva por separado.';
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

    /** Model prose about links is declarative, never evidence. Derive the V2
     * link counts from the actual article Markdown, including a zero result.
     */
    private function verified_editorial_link_notes(string $notes, string $article, array $workflow): string {
        $lines = preg_split('/\r?\n/u', trim($notes)) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            // Remove model claims of link preservation/insertion; the audited
            // summary below replaces them without inventing model changes.
            if (preg_match('/\b(?:enlace|enlaces|anchor|anchors|href)\b/iu', (string) $line)) continue;
            $kept[] = $line;
        }
        $official = (string) (($workflow['canonical_context']['responsible_official_url'] ?? '') ?: ($workflow['responsible_official_url'] ?? ''));
        $internal = class_exists('IDG_Internal_Links') ? IDG_Internal_Links::normalize($workflow) : [];
        $target = (string) ($internal[0]['url'] ?? '');
        $count_official = 0; $count_internal = 0;
        if (preg_match_all('/\[([^\]\r\n]+)\]\((https?:\/\/[^\s)]+)\)/u', $article, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $url = (string) $match[2];
                if ($official !== '' && IDG_Internal_Links::equivalent_url($url, $official)) $count_official++;
                if ($target !== '' && IDG_Internal_Links::equivalent_url($url, $target)) $count_internal++;
            }
        }
        $kept[] = '- Enlaces comprobados en V2: ' . $count_internal . ' internos P2 y ' . $count_official . ' externos al responsable. La comprobación no atribuye cambios al modelo.';
        return trim(implode("\n", array_filter($kept, static fn($line): bool => trim((string) $line) !== '')));
    }

    private function run_seo(string $workflow_id, array $workflow, IDG_OpenAI_Client $client): void {
        $document_mode = class_exists('IDG_Editorial_Document') && IDG_Editorial_Document::exists($workflow);
        // Capture a previously approved Reel before asking the model for a new SEO
        // response. Approval is proven against the exact stored preflight payload;
        // a mere `reel.valid` flag or an old success message is not evidence.
        $prior_reel = $document_mode ? $this->approved_reel_snapshot($workflow) : ['ok' => false];
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
        // Keep the model evidence before any validation path can fail. It is
        // internal-only, normalized for storage and bounded to avoid turning a
        // workflow option into an unbounded transcript.
        $workflow['seo_model_response'] = $this->bounded_model_response((string) ($result['content'] ?? ''));

        $patch_errors = [];
        $link_reconciliation_failed = false;
        if ($document_mode) {
            $applied = IDG_Editorial_Document::apply_seo_response($workflow['editorial_document'], (string) $result['content'], $workflow);
            $workflow['seo_response_diagnostics'] = [
                'response_received' => true,
                'article_proposal' => (array) ($applied['prose'] ?? []),
                'patch_proposal' => (array) ($applied['patch'] ?? []),
                // Compatibilidad con diagnósticos anteriores del mismo candidato.
                'prose_applied' => !empty($applied['prose']['applied']),
                'prose_reason' => (string) ($applied['prose']['reason'] ?? 'not_proposed'),
                'received_components' => array_values((array) ($applied['received_components'] ?? [])),
                'validated_components' => [],
                'reel_validated' => !empty($applied['reel_validated']),
                'final_preflight' => 'not_run',
            ];
            if (empty($applied['ok'])) { $this->save_validation_failure($workflow_id, $workflow, 'seo', ['ok'=>false,'errors'=>['SEO: no fue posible interpretar los componentes de la respuesta; se conserva el documento previo.'],'warnings'=>[],'summary'=>'- ERROR: Componentes SEO no interpretables.']); return; }
            $workflow['editorial_document'] = $applied['document'];
            // A rejected explicit PATCH is a mandatory manual-review condition.
            // Independent SEO fields are retained, but seo_completed stays blocked.
            if (!empty($applied['patch']['received']) && empty($applied['patch']['applied'])) {
                $patch_reason = preg_replace('/[^a-z0-9_]/', '', (string) ($applied['patch']['reason'] ?? 'invalid_patch'));
                $patch_errors[] = 'SEO: PATCH explícito rechazado (' . $patch_reason . '); revisión manual obligatoria.';
            }
            $completed = IDG_Editorial_Document::complete_missing_authorized_links($workflow['editorial_document'], $workflow);
            // Keep independently successful authorized links even when a
            // second destination still needs bounded repair.
            $workflow['editorial_document'] = (array) ($completed['document'] ?? $workflow['editorial_document']);
            if (empty($completed['ok'])) {
                $link_reconciliation_failed = true;
                // No invent an anchor or a destination.  The independently
                // received SEO components remain in the sealed document, and
                // the normal server preflight below records the real guard
                // errors that decide whether a pending-only exception exists.
                // Lead Bridge reads this legacy field as a non-empty string to
                // signal an incident. Keep its type stable; richer local UI
                // data belongs in the separate detail field.
                $workflow['seo_response_diagnostics']['authorized_links'] = $this->authorized_link_reason((string) ($completed['reason'] ?? 'unresolved'));
                $workflow['seo_response_diagnostics']['authorized_links_detail'] = [
                    'status' => 'unreconciled',
                    'reason' => $workflow['seo_response_diagnostics']['authorized_links'],
                    'editor_message' => 'No se añadió ningún enlace: falta una frase contextual inequívoca o la reconciliación no es segura. Revisa el texto y los enlaces autorizados.',
                ];
                $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
            } else {
                $workflow['editorial_document'] = $completed['document'];
                // An empty legacy value must not simulate a Bridge incident.
                $workflow['seo_response_diagnostics']['authorized_links'] = '';
                $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
            }
            // Compact only objectively redundant metadata; never truncate
            // unknown technical details or depend on successful link repair.
            $meta_safe = IDG_Editorial_Document::compact_meta_if_safe($workflow['editorial_document'], $workflow);
            $workflow['seo_response_diagnostics']['meta_safe_compaction'] = (string) ($meta_safe['reason'] ?? 'unavailable');
            if (!empty($meta_safe['applied'])) {
                $workflow['editorial_document'] = $meta_safe['document'];
                $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
            }
            // A missing link can be repaired by a strictly additive insertion
            // into one authorized paragraph; no general article rewrite.
            if ($link_reconciliation_failed && empty($patch_errors)) {
                $missing = (array) ($completed['remaining'] ?? []);
                $link_repair = $this->attempt_bounded_missing_links($workflow_id, $workflow, $missing, $client);
                $workflow['seo_response_diagnostics']['link_repair'] = $link_repair['audit'];
                if (!empty($link_repair['applied'])) {
                    $workflow['editorial_document'] = $link_repair['document'];
                    $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
                    $link_reconciliation_failed = !empty($link_repair['remaining']);
                    if (!$link_reconciliation_failed) {
                        $workflow['seo_response_diagnostics']['authorized_links'] = '';
                        $workflow['seo_response_diagnostics']['authorized_links_detail'] = [
                            'status'=>'reconciled','reason'=>'additive_paragraph_link_repair'
                        ];
                    }
                }
            }
            // Never let an invalid fresh VO silently evict a previously validated
            // Reel when both the approved SEO payload and the factual article
            // remain unchanged. If evidence or context differ, fail closed and
            // keep the ordinary one-shot localized repair path available.
            $retained = $this->retain_approved_reel_if_safe($prior_reel, $workflow['editorial_document']);
            $workflow['seo_response_diagnostics']['reel_retention'] = (string) $retained['reason'];
            if (!empty($retained['applied'])) {
                $workflow['editorial_document'] = $retained['document'];
                $workflow['seo_response_diagnostics']['reel_validated'] = true;
                $workflow['seo_response_diagnostics']['reel_model_proposal_rejected'] = true;
            }
            $workflow['editorial_document'] = IDG_Editorial_Document::reconcile_seo_report_links($workflow['editorial_document'], $workflow);
            $workflow['editorial_document'] = IDG_Editorial_Document::reconcile_seo_feedback_links($workflow['editorial_document'], $workflow);
            $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
        }
        $candidate = $document_mode ? $workflow['seo_result'] : IDG_Workflow_Output_Parser::normalize_editorial_box_markdown(
            IDG_Workflow_Output_Parser::sanitize((string) $result['content']),
            true
        );
        $links = $document_mode
            ? ['content' => $candidate, 'ok' => true, 'errors' => [], 'restored' => []]
            : IDG_Workflow_Output_Parser::preserve_approved_article_links($article, $candidate, $workflow, true);
        $candidate = (string) $links['content'];
        $workflow['seo_result'] = $candidate;
        $preflight = IDG_Post_Creator::preflight_public($workflow);
        $validation = (array) ($preflight['validation'] ?? []);
        $validation = $this->with_link_preservation_errors($validation, array_merge((array) ($links['errors'] ?? []), $patch_errors));
        if (empty($validation['ok'])) {
            $preflight['ok'] = false;
            $preflight['validation'] = $validation;
            $preflight['errors'] = (array) $validation['errors'];
        }
        // A Reel with otherwise valid structure but one overlong/short VO
        // gets a compact, targeted repair *before* the generic SEO repair.
        // Other defects follow the original path, including manual override.
        $workflow['seo_reel_repair'] = ['attempted' => false, 'applied' => false, 'reason' => 'not_required'];
        if ($document_mode && empty($preflight['ok']) && empty($patch_errors)
            && $this->only_reel_length_preflight_errors($validation)) {
            $reel_before = (string) (($workflow['editorial_document']['reel'] ?? [])['normalized'] ?? '');
            $segments = IDG_Reel_Contract::vo_length_segments_only($reel_before);
            if ($segments !== []) {
                $targeted = $this->attempt_targeted_reel_repair($workflow_id, $candidate, $reel_before, $segments, $workflow, $client);
                $workflow['seo_reel_repair'] = $targeted['audit'];
                if (!empty($targeted['applied'])) {
                    $workflow['editorial_document'] = $targeted['document'];
                    $candidate = IDG_Editorial_Document::projection($workflow['editorial_document']);
                    $workflow['seo_result'] = $candidate;
                    $preflight = IDG_Post_Creator::preflight_public($workflow);
                    $validation = (array) ($preflight['validation'] ?? []);
                }
            }
        }
        $repair = ['attempted' => false, 'applied' => false, 'usage' => [], 'model' => ''];
        // An unresolved article link does not prevent independent repair of
        // metadata or Reel. Never treat that repair as resolution of the link.
        if (empty($preflight['ok']) && !empty($links['ok'])) {
            $repair = $this->attempt_localized_repair($workflow_id, 'seo', $candidate, $validation, $workflow, $client);
            if (!empty($repair['applied'])) {
                $candidate = (string) $repair['content'];
                if ($document_mode) {
                    // Commit independent repaired fields ONLY after their own
                    // contract accepts them. An invalid Reel may not erase a
                    // valid metadata repair or hide the original guard errors.
                    $committed = $this->commit_seo_component_repairs($workflow['editorial_document'], $candidate);
                    $workflow['editorial_document'] = $committed['document'];
                    $repair['applied'] = !empty($committed['applied']);
                    $repair['reason'] = (string) $committed['reason'];
                    $repair['components_applied'] = $committed['components_applied'];
                    $repair['components_rejected'] = $committed['components_rejected'];
                    $candidate = IDG_Editorial_Document::projection($workflow['editorial_document']);
                }
                $workflow['seo_result'] = $candidate;
                $links = IDG_Workflow_Output_Parser::preserve_approved_article_links($article, $candidate, $workflow, true);
                $candidate = (string) $links['content'];
                $workflow['seo_result'] = $candidate;
                $preflight = IDG_Post_Creator::preflight_public($workflow);
                $validation = (array) ($preflight['validation'] ?? []);
                $validation = $this->with_link_preservation_errors($validation, array_merge((array) ($links['errors'] ?? []), $patch_errors));
                if (empty($validation['ok'])) {
                    $preflight['ok'] = false;
                    $preflight['validation'] = $validation;
                    $preflight['errors'] = (array) $validation['errors'];
                }
            }
        }

        $prepared = (array) ($preflight['prepared'] ?? []);
        $reel = class_exists('IDG_Reel_Contract') ? IDG_Reel_Contract::inspect((string) ($prepared['reel_package'] ?? '')) : ['valid' => false, 'issues' => ['Reel Contract no disponible.']];
        if ($document_mode) {
            $workflow['editorial_document'] = IDG_Editorial_Document::normalize_seo_report_validation(
                $workflow['editorial_document'], !empty($validation['ok']), !empty($reel['valid'])
            );
            $workflow['seo_result'] = IDG_Editorial_Document::projection($workflow['editorial_document']);
        }
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
        $validation = $this->with_link_preservation_errors($validation, array_merge((array) ($links['errors'] ?? []), $patch_errors));
        if (empty($validation['ok'])) {
            $preflight['ok'] = false;
            $preflight['validation'] = $validation;
            $preflight['errors'] = (array) $validation['errors'];
        }
        $prepared = (array) ($preflight['prepared'] ?? []);
        $reel = class_exists('IDG_Reel_Contract') ? IDG_Reel_Contract::inspect((string) ($prepared['reel_package'] ?? '')) : ['valid' => false, 'issues' => ['Reel Contract no disponible.']];
        $workflow['seo_validation'] = $validation;
        if ($document_mode) {
            $workflow['seo_response_diagnostics']['final_preflight'] = !empty($preflight['ok']) ? 'approved' : 'requires_intervention';
            if (!empty($preflight['ok'])) {
                $verified_components = (array) $workflow['seo_response_diagnostics']['received_components'];
                if (!empty($workflow['seo_response_diagnostics']['reel_model_proposal_rejected'])) {
                    // The final Reel is valid, but it is NOT the model's new Reel.
                    $verified_components = array_values(array_diff($verified_components, ['reel_package']));
                }
                $workflow['seo_response_diagnostics']['validated_components'] = $verified_components;
            }
        }
        $workflow['seo_validation_summary'] = (string) ($validation['summary'] ?? '');
        // Partition real Final Guard findings by their typed codes rather
        // than independently matching human-readable error message prefixes.
        // Unclassified errors remain on the blocking article/SEO side.
        $all_errors = array_values((array) ($validation['errors'] ?? []));
        $error_codes = IDG_Final_Guard::classify_errors($all_errors);
        $article_errors = [];
        foreach ($all_errors as $index => $error) {
            if (($error_codes[$index] ?? 'unknown') !== 'reel_contract') {
                $article_errors[] = $error;
            }
        }
        $workflow['seo_article_validation'] = [
            'ok' => empty($article_errors),
            'errors' => $article_errors,
        ];
        // Presence is a component signal, not approval of the complete copy.
        $social_copy_present = trim((string) ($prepared['social_copy'] ?? '')) !== '';
        $workflow['seo_social_copy_presence'] = [
            'present' => $social_copy_present,
        ];
        $workflow['seo_reel_validation'] = $reel;
        $workflow['seo_repair'] = $this->repair_audit($repair);
        $workflow['usage_seo'] = $result['usage'] ?? [];
        $workflow['usage_seo_estimate'] = IDG_Usage_Estimator::record('seo', (array) ($result['usage'] ?? []), $workflow_id, (string) ($result['model'] ?? ''));
        $workflow['warnings'] = IDG_Validator::summarize($workflow['seo_result'], (string) ($workflow['keyword'] ?? ''));
        $workflow['feedback_notes'] = IDG_Workflow_Output_Parser::extract_feedback_notes($workflow['seo_result']);
        if (empty($preflight['ok'])) {
            // A Reel-only failure never upgrades seo_validation or Final Guard.
            // It may nevertheless create one manually reviewed *new* pending
            // post, but only with seals for the exact document, SEO projection,
            // Reel and Gutenberg payload inspected here.
            // Keep this pipeline read-only with respect to post creation; the
            // creator remains the sole authority for the eventual write.
            if (call_user_func(['IDG_Post_Creator', 'reel_only_pending_eligible'], $workflow, $validation)) {
                $payload_hash = IDG_Post_Creator::prepared_payload_hash($preflight);
                $workflow['seo_reel_pending_payload_hash'] = $payload_hash;
                $workflow['seo_reel_pending_post_content'] = (string) ($prepared['post_content'] ?? '');
                $workflow['seo_reel_pending_reel_hash'] = hash('sha256', (string) ($prepared['reel_package'] ?? ''));
                $workflow['seo_reel_pending_document_hash'] = (string) ($prepared['document_hash'] ?? '');
                $workflow['seo_reel_requires_repair'] = true;
                $workflow['seo_response_diagnostics']['reel_status'] = 'requires_repair';
            }
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

    /**
     * P0R1: accepted small contractions in the factual editorial box.
     * Do not paraphrase, change numbers, names, links or any other paragraph.
     * Unsupported cases preserve the existing localized-model repair path.
     */
    private function attempt_safe_box_compaction(string $content, array $validation, array $workflow): array {
        $audit = ['attempted' => false, 'applied' => false, 'content' => $content,
            'usage' => [], 'model' => '', 'reason' => 'no_safe_box_compaction'];
        $errors = (array) ($validation['errors'] ?? []);
        if (count($errors) !== 1
            || !preg_match('/^(?:Generación|Revisión editorial): la caja editorial debe tener entre 40 y 55 palabras; detectadas: (5[6-9]|60)\.$/u', (string) $errors[0])) {
            return $audit;
        }
        $matches = [];
        if (preg_match_all('/^Caja editorial[ \t]*\R(?:[ \t]*\R)*([^\r\n]+)$/mu', $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return $audit;
        }
        $body = (string) $matches[1][0][0];
        $offset = (int) $matches[1][0][1];
        if (str_starts_with(trim($body), '#') || preg_match('/https?:\/\/|\[[^\]]+\]\(/iu', $body)) {
            return $audit;
        }
        // Literal editorial economies whose meaning and attribution remain the same.
        // No blind word deletion, content synthesis or unbounded iterations.
        $edits = [
            ['/\blo sitúa con[ \t]+(?=\d)/iu', 'indica '],
            ['/\b(contempla|incluye|reúne|presenta) un total de[ \t]+(?=\d)/iu', '$1 '],
            ['/\bde acuerdo con\b/iu', 'según'],
        ];
        $working = $body;
        $audit['attempted'] = true;
        foreach ($edits as [$pattern, $replacement]) {
            if (preg_match_all($pattern, $working) !== 1) continue;
            $changed = preg_replace($pattern, $replacement, $working, 1);
            if (!is_string($changed) || $changed === $working) continue;
            // Numbers, units and protected punctuation cannot be modified.
            preg_match_all('/\d+(?:[.,]\d+)*/u', $working, $before_numbers);
            preg_match_all('/\d+(?:[.,]\d+)*/u', $changed, $after_numbers);
            if ($before_numbers[0] !== $after_numbers[0]) continue;
            $proposal = substr_replace($content, $changed, $offset, strlen($body));
            $check = IDG_Final_Guard::validate_article_phase($proposal, $workflow, 'generate');
            if (!empty($check['ok'])) {
                $audit['content'] = $proposal;
                $audit['applied'] = true;
                $audit['reason'] = 'safe_box_compaction_real_guard_passed';
                return $audit;
            }
            // Continue only if the sole remaining failure is the length limit.
            $next_errors = (array) ($check['errors'] ?? []);
            if (count($next_errors) !== 1 || !str_contains((string) $next_errors[0], 'la caja editorial debe tener entre 40 y 55 palabras')) {
                continue;
            }
            $working = $changed;
        }
        return $audit;
    }

    /**
     * Read-only proof that the current canonical document already passed SEO
     * and Reel validation with an identical prepared WordPress payload.
     * No new model call, option update or post write is performed here.
     */
    private function approved_reel_snapshot(array $workflow): array {
        $deny = static fn(string $reason): array => ['ok' => false, 'reason' => $reason];
        if (!class_exists('IDG_Editorial_Document') || !IDG_Editorial_Document::exists($workflow)
            || !class_exists('IDG_Reel_Contract') || !class_exists('IDG_Post_Creator')) {
            return $deny('missing_contract');
        }
        $document = (array) $workflow['editorial_document'];
        $reel = (string) (($document['reel'] ?? [])['normalized'] ?? '');
        if ($reel === '' || empty($document['reel']['valid'])
            || empty(IDG_Reel_Contract::inspect($reel)['valid'])) {
            return $deny('no_valid_prior_reel');
        }
        if (empty($workflow['seo_validation']['ok'])
            || trim((string) ($workflow['seo_preflight_approved_at'] ?? '')) === '') {
            return $deny('no_previous_approval');
        }
        $old_hash = (string) ($workflow['seo_preflight_payload_hash'] ?? '');
        $old_content = (string) ($workflow['seo_preflight_post_content'] ?? '');
        if ($old_hash === '' || $old_content === '') return $deny('missing_approval_seals');
        $baseline = IDG_Post_Creator::preflight_public($workflow);
        if (empty($baseline['ok']) || empty($baseline['validation']['ok'])) {
            return $deny('previous_preflight_no_longer_valid');
        }
        $actual_hash = IDG_Post_Creator::prepared_payload_hash($baseline);
        $actual_content = (string) ($baseline['prepared']['post_content'] ?? '');
        if ($actual_hash === '' || $actual_content === ''
            || !hash_equals($old_hash, $actual_hash)
            || !hash_equals($old_content, $actual_content)) {
            return $deny('previous_approval_seals_changed');
        }
        return ['ok' => true, 'reason' => 'previous_preflight_verified',
            'reel' => $reel, 'document' => $document];
    }

    /**
     * Same article means byte-identical text after unwrapping only Markdown
     * links; every original link destination must also be preserved in order.
     * Punctuation, facts, layout and section boundaries are never normalized.
     */
    private static function same_reel_article_context(array $before, array $after): bool {
        $extract = static function (array $document): ?array {
            $markdown = IDG_Editorial_Document::markdown($document);
            $pattern = '~\[([^]\r\n]+)\]\((https?://[^)\s\r\n]+)\)~u';
            $matches = [];
            if (preg_match_all($pattern, $markdown, $matches) === false) return null;
            $text = preg_replace($pattern, '$1', $markdown);
            if (!is_string($text) || $text === '') return null;
            return ['text' => $text, 'urls' => (array) ($matches[2] ?? [])];
        };
        $left = $extract($before);
        $right = $extract($after);
        return $left !== null && $right !== null && $left === $right;
    }

    /** Preserve an earlier approved Reel only for an identical article context. */
    private function retain_approved_reel_if_safe(array $prior, array $document): array {
        if (empty($prior['ok'])) return ['applied' => false, 'reason' => (string) ($prior['reason'] ?? 'no_verified_previous_reel')];
        $proposed = IDG_Reel_Contract::inspect((string) (($document['reel'] ?? [])['normalized'] ?? ''));
        if (!empty($proposed['valid'])) return ['applied' => false, 'reason' => 'new_reel_valid'];
        if (!self::same_reel_article_context((array) $prior['document'], $document)) {
            return ['applied' => false, 'reason' => 'article_or_link_context_changed'];
        }
        $updated = IDG_Editorial_Document::apply_reel_package($document, (string) $prior['reel']);
        if (empty($updated['ok'])) return ['applied' => false, 'reason' => 'previous_reel_revalidation_failed'];
        return ['applied' => true, 'reason' => 'previous_approved_reel_preserved',
            'document' => $updated['document']];
    }

    /** No new workflow phase or UI restriction; one small model request only on a VO-length failure. */
    private function only_reel_length_preflight_errors(array $validation): bool {
        $errors = (array) ($validation['errors'] ?? []);
        if ($errors === []) return false;
        foreach ($errors as $error) {
            if (!preg_match('/^VO [1-5] debe tener entre 12 y 16 palabras \(objetivo 14\)\. Detectadas: \d+\.$/u', (string) $error)) return false;
        }
        return true;
    }

    private function attempt_targeted_reel_repair(string $workflow_id, string $projection, string $reel,
        array $segments, array $workflow, IDG_OpenAI_Client $client): array {
        $audit = ['attempted' => true, 'applied' => false, 'reason' => 'targeted_repair_rejected', 'usage' => [], 'model' => ''];
        $result = $client->complete(IDG_Prompt_Library::reel_vo_length_repair_prompt($reel, $segments, $workflow));
        $audit['usage'] = (array) ($result['usage'] ?? []);
        $audit['model'] = (string) ($result['model'] ?? '');
        IDG_Usage_Estimator::record('seo_reel_vo_repair', $audit['usage'], $workflow_id, $audit['model']);
        if (empty($result['success'])) {
            $audit['reason'] = 'model_unavailable';
            return ['applied' => false, 'audit' => $audit];
        }
        $patched = $this->apply_segment_patches($projection, (string) ($result['content'] ?? ''), $segments, $workflow);
        if (empty($patched['ok'])) {
            $audit['reason'] = (string) ($patched['reason'] ?? 'patch_invalid');
            return ['applied' => false, 'audit' => $audit];
        }
        $reel_after = $this->section_body((string) $patched['content'], 'PAQUETE REEL');
        $inspect = IDG_Reel_Contract::inspect($reel_after);
        if (empty($inspect['valid'])) {
            $audit['reason'] = 'reel_contract_rejected';
            return ['applied' => false, 'audit' => $audit];
        }
        $document = IDG_Editorial_Document::apply_reel_package((array) $workflow['editorial_document'], $reel_after);
        if (empty($document['ok'])) {
            $audit['reason'] = 'canonical_reel_rejected';
            return ['applied' => false, 'audit' => $audit];
        }
        $audit['applied'] = true;
        $audit['reason'] = 'vo_only_contract_validated';
        return ['applied' => true, 'audit' => $audit, 'document' => $document['document']];
    }

    /**
     * Exactly one small model request per missing authorized link, max two.
     * An unrecognized response cannot modify the document. This is not a
     * new workflow phase, restriction or WordPress write.
     */
    private function attempt_bounded_missing_links(string $workflow_id, array $workflow, array $missing, IDG_OpenAI_Client $client): array {
        $document=(array)($workflow['editorial_document']??[]);
        $audit=['attempted'=>false,'applied'=>[],'rejected'=>[],'usage'=>[]];
        $left=[];
        foreach (array_slice($missing,0,2) as $need) {
            // EDITOR + VERIFIER: If P2 already exists, its paragraph owns the
            // correction. Never append a duplicate link to a different block.
            if (!empty($need['internal']) && (string)($need['reason']??'')==='invalid_internal_anchor') {
                $target=IDG_Editorial_Document::contextual_anchor_revision_target($document,(array)$need);
                if ($target === null) {
                    $left[]=$need;
                    $audit['rejected'][]=['url'=>(string)($need['url']??''),'reason'=>'linked_paragraph_unavailable'];
                    continue;
                }
                $audit['attempted']=true;
                $response=$client->complete(IDG_Prompt_Library::contextual_anchor_revision_prompt($workflow,(array)$need,$target));
                $audit['usage'][]=(array)($response['usage']??[]);
                IDG_Usage_Estimator::record('seo_link_local_editor',(array)($response['usage']??[]),$workflow_id,(string)($response['model']??''));
                $edited=!empty($response['success']) ? IDG_Editorial_Document::apply_contextual_anchor_revision(
                    $document,$workflow,(array)$need,$target,(string)($response['content']??'')) : ['ok'=>false,'reason'=>'model_unavailable'];
                if (empty($edited['ok'])) {
                    $left[]=$need;
                    $audit['rejected'][]=['url'=>(string)($need['url']??''),'reason'=>(string)($edited['reason']??'editor_rejected')];
                } else {
                    $document=$edited['document'];
                    $audit['applied'][]=(string)($need['url']??'');
                }
                continue;
            }
            $paragraph=IDG_Editorial_Document::anchor_insertion_target($document, $workflow, (array)$need);
            if (!$paragraph) {
                $left[]=$need;
                $audit['rejected'][]=['url'=>(string)($need['url']??''),'reason'=>'no_safe_paragraph'];
                continue;
            }
            $audit['attempted']=true;
            $result=$client->complete(IDG_Prompt_Library::bounded_link_insertion_prompt($workflow, (array)$need, $paragraph));
            $audit['usage'][]=(array)($result['usage']??[]);
            IDG_Usage_Estimator::record('seo_link_paragraph_repair',(array)($result['usage']??[]),$workflow_id,(string)($result['model']??''));
            if (empty($result['success'])) {
                $left[]=$need;
                $audit['rejected'][]=['url'=>(string)($need['url']??''),'reason'=>'model_unavailable'];
                continue;
            }
            $applied=IDG_Editorial_Document::apply_bounded_link_insertion($document,$workflow,(array)$need,$paragraph,(string)($result['content']??''));
            if (empty($applied['ok'])) {
                $left[]=$need;
                $audit['rejected'][]=['url'=>(string)($need['url']??''),'reason'=>(string)($applied['reason']??'invalid_model_insertion')];
                continue;
            }
            $document=$applied['document'];
            $audit['applied'][]=(string)($need['url']??'');
        }
        foreach (array_slice($missing,2) as $need) $left[]=$need;
        return ['applied'=>!empty($audit['applied']), 'document'=>$document,'remaining'=>$left,'audit'=>$audit];
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
        $patched = $this->apply_segment_patches($content, (string) ($result['content'] ?? ''), $allowed, $workflow);
        if (empty($patched['ok'])) {
            $audit['reason'] = (string) ($patched['reason'] ?? 'invalid_patch');
            return $audit;
        }
        $audit['content'] = (string) $patched['content'];
        $audit['applied'] = $audit['content'] !== $content;
        $audit['reason'] = $audit['applied'] ? 'applied' : 'no_change';
        return $audit;
    }

    /**
     * Commit the Reel and the meta description independently. The localized
     * patcher has already restricted edited lines to the preflight findings;
     * this second gate validates each resulting component before sealing it.
     */
    private function commit_seo_component_repairs(array $document, string $patched_projection): array {
        $applied = [];
        $rejected = [];
        $meta = $this->section_body($patched_projection, 'META DESCRIPTION');
        $original_meta = (string) (($document['seo'] ?? [])['meta_description'] ?? '');
        if ($meta !== $original_meta) {
            $result = IDG_Editorial_Document::apply_meta_description($document, $meta);
            if (!empty($result['ok'])) {
                $document = $result['document'];
                $applied[] = 'meta_description';
            } else {
                $rejected[] = 'meta_description:' . (string) ($result['reason'] ?? 'invalid');
            }
        }
        $reel = $this->section_body($patched_projection, 'PAQUETE REEL');
        $original_reel = (string) (($document['reel'] ?? [])['normalized'] ?? '');
        if ($reel !== '' && $reel !== $original_reel) {
            $result = IDG_Editorial_Document::apply_reel_package($document, $reel);
            if (!empty($result['ok'])) {
                $document = $result['document'];
                $applied[] = 'reel_package';
            } else {
                $rejected[] = 'reel_package:' . (string) ($result['reason'] ?? 'invalid');
            }
        }
        return [
            'document' => $document,
            'applied' => !empty($applied),
            'components_applied' => $applied,
            'components_rejected' => $rejected,
            'reason' => $rejected ? 'partial_or_invalid_component_repair' : ($applied ? 'applied' : 'no_valid_component_change'),
        ];
    }

    private function bounded_model_response(string $response): string {
        $response = IDG_Workflow_Output_Parser::sanitize($response);
        return mb_strlen($response) > 60000 ? mb_substr($response, 0, 60000) . "\n[respuesta truncada para almacenamiento interno]" : $response;
    }

    /** Only known reconciliation outcomes are persisted; never retain model text as a reason. */
    private function authorized_link_reason(string $reason): string {
        $allowed = ['authorized_links_unresolved', 'duplicate_authorized_link', 'missing_unique_authorized_anchor'];
        return in_array($reason, $allowed, true) ? $reason : 'unresolved';
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
            if (str_contains($errors, 'La meta description debe tener entre 106 y 150 caracteres.')) {
                $allowed[] = 'META_DESCRIPTION';
            }
            $inspection = IDG_Reel_Contract::inspect($this->section_body($content, 'PAQUETE REEL'));
            foreach (range(1, 5) as $scene) {
                $count = (int) ($inspection['vo_counts'][$scene] ?? 0);
                if ($count < 12 || $count > 16) {
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

    private function apply_segment_patches(string $content, string $response, array $allowed, array $workflow = []): array {
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
            // Text integrity is checked before any branch can commit a patch.
            if (preg_match('//u', $replacement) !== 1 || preg_match('/[\x00-\x1F\x7F]/', $replacement) === 1) {
                return ['ok' => false, 'reason' => 'invalid_patch_text'];
            }
            $seen[$segment] = true;
            if (preg_match('/\ALINE:(\d+)\z/', $segment, $m)) {
                $index = (int) $m[1] - 1;
                if (!array_key_exists($index, $lines)) {
                    return ['ok' => false, 'reason' => 'line_not_found'];
                }
                if (!self::localized_line_preserves_protected_facts((string) $lines[$index], $replacement, $workflow)
                    || ($this->is_box_body_line($lines, $index)
                        && !self::editorial_box_substance_unchanged((string) $lines[$index], $replacement))) {
                    return ['ok' => false, 'reason' => 'protected_content_changed'];
                }
                $lines[$index] = $replacement;
                continue;
            }
            if ($segment === 'META_DESCRIPTION') {
                $positions = [];
                foreach ($lines as $index => $line) {
                    if (preg_match('/^\s*META DESCRIPTION\s*:?\s*$/iu', (string) $line)) $positions[] = $index;
                }
                if (count($positions) !== 1 || !array_key_exists($positions[0] + 1, $lines)
                    || trim((string) $lines[$positions[0] + 1]) === '') {
                    return ['ok' => false, 'reason' => 'meta_section_not_unique'];
                }
                $next = $positions[0] + 2;
                while (isset($lines[$next]) && trim((string) $lines[$next]) === '') $next++;
                if (!isset($lines[$next]) || !preg_match('/^\s*INFORME SEO INTERNO\s*:?\s*$/iu', (string) $lines[$next])) {
                    return ['ok' => false, 'reason' => 'meta_section_not_single_line'];
                }
                $lines[$positions[0] + 1] = trim($replacement);
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
                $before_vo = (string) $lines[$matches[0]];
                $after_vo = 'VO ' . $scene . ': ' . trim($replacement);
                if (!self::localized_line_preserves_protected_facts($before_vo, $after_vo, $workflow)) {
                    return ['ok' => false, 'reason' => 'protected_vo_facts_changed'];
                }
                $lines[$matches[0]] = $after_vo;
                continue;
            }
            return ['ok' => false, 'reason' => 'unknown_segment'];
        }
        return ['ok' => true, 'content' => implode("\n", $lines)];
    }

    /** Minimal fail-closed protection for literal factual tokens in model line repairs.
     * It does not prove semantic correctness; the real Final Guard must still revalidate.
     */
    private static function localized_line_preserves_protected_facts(string $before, string $after, array $workflow = []): bool {
        foreach ([
            // Numeric amounts, dates, dimensions, percentages (same ordered literals).
            '/\d+(?:[.,]\d+)*/u',
            // Multiword proper names, including those embedded in titles.
            '/\b\p{Lu}[\p{L}\p{M}]*(?:[ \t]+\p{Lu}[\p{L}\p{M}]*)+/u',
            // Acronyms / abbreviated named organizations (single uppercase token).
            '/\b\p{Lu}{2,}\b/u',
            // Original URLs may not be replaced during a wording fix.
            '~https?://[^\s)\]]+~u',
            // Negations and factual qualifiers cannot appear or disappear silently.
            '/\b(?:no|sin|nunca|antes|después|solo|únicamente)\b/iu',
        ] as $pattern) {
            $old = []; $new = [];
            if (preg_match_all($pattern, $before, $old) === false || preg_match_all($pattern, $after, $new) === false) {
                return false;
            }
            if (($old[0] ?? []) !== ($new[0] ?? [])) return false;
        }
        // The workflow names can be single words (e.g. Skyreach); generic
        // capitalization heuristics cannot safely distinguish them from headings.
        foreach (['entity', 'keyword'] as $field) {
            $protected = trim((string) ($workflow[$field] ?? ''));
            if ($protected === '') continue;
            $name_pattern = '~(?<![\p{L}\p{N}])' . preg_quote($protected, '~') . '(?![\p{L}\p{N}])~iu';
            $before_count = preg_match_all($name_pattern, $before);
            $after_count = preg_match_all($name_pattern, $after);
            if ($before_count === false || $after_count === false || $before_count !== $after_count) return false;
        }
        // A wording repair must not silently re-label already authorized links;
        // link reconciliation is handled independently by the link component.
        $anchor_pattern = '~\[[^]\r\n]+\]\(https?://[^)\r\n]+\)~u';
        $before_links = []; $after_links = [];
        if (preg_match_all($anchor_pattern, $before, $before_links) === false || preg_match_all($anchor_pattern, $after, $after_links) === false) return false;
        if (($before_links[0] ?? []) !== ($after_links[0] ?? [])) return false;
        return true;
    }

    /** Box repairs may remove function words, never silently replace a factual
     * noun, material, unit, descriptor, person or place. Conservative by design.
     */
    private static function editorial_box_substance_unchanged(string $before, string $after): bool {
        $ignored = array_fill_keys([
            'a', 'al', 'ante', 'bajo', 'con', 'contra', 'de', 'del', 'desde', 'el',
            'ella', 'en', 'entre', 'es', 'esta', 'está', 'este', 'estos', 'estas',
            'fue', 'ha', 'han', 'hacia', 'la', 'las', 'le', 'les', 'lo', 'los',
            'o', 'para', 'por', 'que', 'se', 'sobre', 'su', 'sus', 'un', 'una',
            'unos', 'unas', 'y',
        ], true);
        $signature = static function (string $value) use ($ignored): ?array {
            if (preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($value), $m) === false) return null;
            return array_values(array_filter((array) ($m[0] ?? []), static fn(string $token): bool => !isset($ignored[$token])));
        };
        $left = $signature($before); $right = $signature($after);
        return $left !== null && $right !== null && $left === $right;
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
            'components_applied' => (array) ($repair['components_applied'] ?? []),
            'components_rejected' => (array) ($repair['components_rejected'] ?? []),
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
