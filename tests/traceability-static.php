<?php
$root=dirname(__DIR__);
function ok($c,$m){if(!$c){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "OK: $m\n";}
$main=file_get_contents($root.'/ideasdi-redaccion-gerizim.php');
$outbox=file_get_contents($root.'/includes/class-traceability-outbox.php');
$trace=file_get_contents($root.'/includes/class-traceability.php');
$client=file_get_contents($root.'/includes/class-traceability-client.php');
$post=file_get_contents($root.'/includes/class-post-creator.php');
$runner=file_get_contents($root.'/includes/class-job-runner.php');
$admin=file_get_contents($root.'/includes/class-traceability-admin.php');
$adminPage=file_get_contents($root.'/includes/class-admin-page.php');
$importer=file_get_contents($root.'/includes/class-radar-importer.php');
$recapture=file_get_contents($root.'/includes/class-traceability-recapture.php');
$logger=file_get_contents($root.'/includes/class-logger.php');
ok(str_contains($main,"0.4.0-RC1.6.2"),'versión RC1.6.1');
ok(str_contains($main,"IDG_TRACEABILITY_DB_VERSION"),'versión DB definida');
foreach(['idempotency_key','dependency_key','lock_token','locked_at','next_attempt_at'] as $field) ok(str_contains($outbox,$field),"esquema contiene $field");
ok(str_contains($outbox,'UNIQUE KEY idempotency_key'),'índice único de idempotencia');
ok(str_contains($outbox,"private const MAX_ATTEMPTS = 8"),'máximo 8 intentos');
ok(str_contains($trace,'schedule_at') && str_contains($outbox,'IDG_Traceability::schedule_at'),'reintentos programan horario específico');
foreach([60,300,900,3600,10800,21600,43200] as $delay) ok(str_contains($outbox,(string)$delay),"delay $delay presente");
ok(str_contains($trace,"wordpress_post_created:"),'evento post_created presente');
ok(!str_contains($trace,"wordpress_draft_created"),'evento draft_created ausente en trazabilidad');
ok(str_contains($trace,"transition_post_status"),'hook de publicación presente');
ok(str_contains($trace,"radar-editorial-ideasdi"),'elegibilidad Radar presente');
ok(str_contains($trace,"recurring_update"),'exclusión recurrente presente');
ok(str_contains($trace,'2026-07-04T14:53:06.000Z'),'corte histórico mínimo presente');
ok(str_contains($client,"traceability_event_already_recorded"),'already_recorded tratado');
ok(str_contains($client, '$status === 200') && str_contains($client, '$status === 201'),'contrato HTTP exacto');
ok(substr_count($post,"_idg_sponsor_client")===1,'sponsor_client no duplicado');
ok(str_contains($runner,'capture_wordpress_post_created'),'captura post_created tras workflow');
ok(str_contains($post,"'post_status' => 'pending'"),'estado pending conservado');
ok(!str_contains($outbox,'IDG_RADAR_TRACEABILITY_TOKEN') || str_contains($outbox,'clean_error'),'token no se persiste como campo');

foreach(['class-traceability.php','class-traceability-outbox.php','class-traceability-client.php','class-traceability-admin.php','class-traceability-recapture.php'] as $file) ok(str_contains($main,$file),"carga $file");
ok(str_contains($main,"register_activation_hook")&&str_contains($main,"maybe_upgrade_database"),'migración en activación');
ok(str_contains($main,"add_action('plugins_loaded'")&&str_contains($main,"maybe_upgrade_database"),'migración pendiente en arranque');
ok(str_contains($trace, '$dependency_key = \'gerizim_imported:'), 'creación depende de importación');
ok(str_contains($trace, '$dependency_key = \'wordpress_post_created:'), 'publicación depende de creación');
ok(str_contains($trace,"traceability_gerizim_imported_synced_at_utc"),'reflejo de importación sincronizado');
ok(str_contains($trace,"validate_event_payload"),'payload contractual validado');
ok(str_contains($outbox,"idempotency_payload_conflict"),'conflicto local de payload detectado');
ok(str_contains($outbox,"status='sending' AND lock_token=%s"),'finalización exige lock token');
ok(str_contains($outbox,"STALE_LOCK_SECONDS = 900"),'recuperación stale a 15 minutos');
ok(str_contains($admin,"current_user_can('manage_options')")&&str_contains($admin,'check_admin_referer'),'acciones administrativas protegidas');
ok(str_contains($admin, 'method="post"'), 'acciones administrativas por POST');
ok(str_contains($logger,'authorization')&&str_contains($logger,'password'),'logger filtra claves sensibles');
ok(str_contains($importer,"unset(")&&str_contains($importer,'traceability_gerizim_imported_key'),'nueva importación limpia reflejos anteriores');
ok(str_contains($importer,"radar_import_is_new")&&str_contains($importer,"invalid_import_occurred_at"),'mismo brief sin fecha se bloquea y solo identidad nueva marca importación actual');
$validatePos=strpos($adminPage, "if (\$step === 'validate_radar')");
$importPos=strpos($adminPage, "if (\$step === 'import_radar')");
$capturePos=strpos($adminPage,'safe_capture_gerizim_imported');
ok($validatePos!==false&&$importPos!==false&&$capturePos>$importPos&&$capturePos>$validatePos,'validación JSON no captura evento');


ok(str_contains($client,"['result']") || str_contains($client,"decoded['result']"),'result es campo canónico del receptor');
ok(str_contains($client,"decoded['code']"),'code se conserva como compatibilidad temporal');
ok(str_contains($client,'response_idempotency_key_mismatch'),'respuesta exige idempotency_key coincidente');
ok(str_contains($outbox,'schema_status')&&str_contains($outbox,'missing_unique_idempotency_index'),'migración verifica esquema e índice único');
ok(strpos($outbox,"update_option('idg_traceability_db_version'")>strpos($outbox,'schema_status();'),'versión DB se actualiza después de verificar');
ok(str_contains($outbox,'last_transport_error'),'último error de transporte se almacena aparte');
ok(str_contains($outbox,'retry_limit_reached'),'octavo fallo usa retry_limit_reached');
ok(str_contains($outbox, "unset(\$stored['observed_at']") && !str_contains($outbox, "unset(\$stored['occurred_at']"), 'idempotencia ignora observed_at pero conserva occurred_at');
ok(str_contains($outbox,"status IN ('queued','retry','blocked')")&&str_contains($outbox,"locked_at IS NULL"),'set_blocked protege sending y locks');
ok(str_contains($outbox,'reflection_synced_at IS NULL OR reflection_synced_at < updated_at'),'reconciliación limita reflejos pendientes');
ok(str_contains($outbox,"NOT (status='sending'")&&str_contains($outbox,'status=%s AND updated_at=%s'),'sincronización excluye locks y confirma estado/versión antes de marcar');
ok(str_contains($admin,'handle_reactivate_event')&&str_contains($admin,'Reactivar revisado'),'reactivación individual administrativa disponible');
ok(str_contains($trace,'validate_radar_url')&&str_contains($trace,'insecure_traceability_url'),'URL exige política HTTPS');
ok(str_contains($trace,'capture_or_defer')&&str_contains($trace,'schedule_reconciliation'),'fallo de insert programa recaptura');
ok(str_contains($main,'class-traceability-recapture.php'),'módulo de recaptura cargado');
ok(str_contains($adminPage,'radar_reimport_allowed')&&str_contains($importer,'radar_reimport_allowed'),'reinicio parcial habilita nuevo brief Radar');
ok(str_contains($recapture,"idg_traceability_recapture_event_")&&!str_contains($recapture,"private const OPTION_PREFIX = 'idg_traceability_recapture_'"),'intenciones de recaptura usan prefijo separado del cursor');
ok(str_contains($adminPage,'snapshot_hash')&&str_contains($adminPage,'snapshot_storage_verification_failed'),'snapshot de Reinicio parcial es durable y verificable');
ok(str_contains($trace,'traceability_recapture_persistence_error')&&str_contains($trace,'_idg_traceability_recapture_failure'),'fallo de recaptura deja error operativo y marca recuperable');


$partialResetPos = strpos($adminPage, "if (\$step === 'partial_reset'");
$firstPartialSavePos = strpos($adminPage, 'IDG_Job_Runner::save_workflow($workflow_id, $data);', $partialResetPos === false ? 0 : $partialResetPos);
$snapshotStorePos = strpos($adminPage, 'store_radar_partial_reset_snapshot', $partialResetPos === false ? 0 : $partialResetPos);
ok($partialResetPos !== false && $snapshotStorePos !== false && ($firstPartialSavePos === false || $snapshotStorePos < $firstPartialSavePos), 'snapshot se confirma antes de cualquier escritura del Reinicio parcial');
ok(str_contains($adminPage, 'delete_option($key)') && str_contains($adminPage, 'get_option($key, null) !== null'), 'eliminación del snapshot se verifica antes de cerrar restauración');
ok(str_contains($outbox, 'schema_version_persistence_failed') && str_contains($outbox, "get_option('idg_traceability_db_version'"), 'persistencia de versión DB se relee y verifica');
ok(str_contains($outbox, "fresh['lock_token']") && str_contains($outbox, "fresh['locked_at']"), 'reconciliación confirma lock_token y locked_at originales');
ok(str_contains($trace, 'recover_failure_markers') && str_contains($trace, 'idg_traceability_recapture_failure_cursor'), 'marcas alternativas se recuperan con cursor estable');
ok(str_contains($trace, 'marker_hash') && str_contains($trace, 'hash_equals'), 'marca alternativa de recaptura está protegida por hash');
ok(str_contains($admin, 'recoverable_failure_markers') && str_contains($admin, 'recapture_conflicts'), 'panel muestra marcas recuperables y conflictos de recaptura');
ok(str_contains($recapture, 'delete_option_verified') && str_contains($recapture, 'recapture_cleanup_failed'), 'intenciones se eliminan solo tras limpieza verificada');


ok(str_contains($outbox, "'candidate_ids' => []") && str_contains($outbox, "'claim_failure_reasons' => []"), 'resultado de cola incluye observabilidad de selección y claim');
foreach(['schema_not_ready','delivery_disabled','delivery_configuration_invalid','sql_selection_error','no_candidates','candidates_not_claimed','claim_verification_failed','completed'] as $reason) ok(str_contains($outbox, "'".$reason."'"), "motivo $reason presente");
ok(str_contains($admin, 'QUEUE_RESULT_TRANSIENT_PREFIX') && str_contains($admin, 'take_queue_result'), 'resultado administrativo usa transient asociado al usuario');
foreach(['Procesados:', 'Enviados:', 'Reintentos:', 'Fallidos:', 'Bloqueados:', 'Candidatos:', 'Reclamos correctos:', 'Reclamos fallidos:', 'Motivo:'] as $label) ok(str_contains($admin, $label), "aviso incluye $label");

echo "PASS static\n";
