<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Módulo independiente para analizar y aplicar actualizaciones recurrentes.
 *
 * Alcance RC1.4.9.8:
 * - Buscar y seleccionar Eventos o Concursos existentes.
 * - Cargar sus campos actuales y analizar datos propuestos.
 * - Leer de forma segura una fuente externa, tolerando HTTP 403.
 * - Registrar ciudades auxiliares y países ampliables integrados con ACF.
 * - Aplicar, en un segundo paso confirmado, cambios estructurados sobre un Evento o Concurso existente.
 * - Preparar un workflow editorial normal de Gerizim vinculado a la publicación actualizada.
 * - Preservar estado, contenido, extracto, autor, taxonomías e imagen destacada durante la fase estructural.
 * - No crea nuevas ediciones. La redacción reutiliza el Flujo editorial existente.
 */
final class IDG_Recurring_Updates {
    private const CONTEST_CATEGORY_ID = 34;
    private const CITY_REGISTRY_OPTION = 'idg_recurring_city_registry';
    private const COUNTRY_REGISTRY_OPTION = 'idg_recurring_country_registry';
    private const ANALYSIS_TRANSIENT_PREFIX = 'idg_recurring_analysis_';
    private const ANALYSIS_TTL = 7200;

    /**
     * Añade al selector ACF País los países registrados desde Gerizim.
     * Se usa un registro auxiliar para no reescribir la definición física del grupo ACF.
     */
    public static function filter_country_field_choices(array $field): array {
        $name_key = self::country_key((string) ($field['name'] ?? ''));
        $label_key = self::country_key((string) ($field['label'] ?? ''));
        if (!in_array($name_key, ['pais', 'country', 'pais evento', 'country event'], true) && !in_array($label_key, ['pais', 'country'], true)) {
            return $field;
        }
        $registered = self::registered_country_choices();
        if (empty($registered)) {
            return $field;
        }
        $choices = isset($field['choices']) && is_array($field['choices']) ? $field['choices'] : [];
        foreach ($registered as $key => $label) {
            if (!array_key_exists($key, $choices)) {
                $choices[$key] = $label;
            }
        }
        $field['choices'] = $choices;
        return $field;
    }

    public static function render_page(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }

        $content_type = self::content_type_from_value(isset($_GET['content_type']) ? (string) wp_unslash($_GET['content_type']) : 'event');
        $search = isset($_GET['recurring_search']) ? sanitize_text_field((string) wp_unslash($_GET['recurring_search'])) : '';
        $source_post_id = isset($_GET['source_post_id']) ? absint($_GET['source_post_id']) : 0;
        $analysis_id = isset($_GET['analysis_id']) ? sanitize_text_field((string) wp_unslash($_GET['analysis_id'])) : '';
        $analysis = $analysis_id !== '' ? self::get_analysis($analysis_id) : [];
        $submitted = isset($analysis['submitted']) && is_array($analysis['submitted']) ? $analysis['submitted'] : [];
        $results = $search !== '' ? self::search_publications($content_type, $search) : [];
        $source = $source_post_id > 0 ? self::load_source($content_type, $source_post_id) : null;
        $cities = $content_type === 'event' ? self::existing_cities() : [];
        $countries = $content_type === 'event' ? self::existing_countries($source_post_id) : [];
        ?>
        <div class="wrap idg-wrap idg-recurring-wrap">
            <h1>Gerizim · Actualizaciones recurrentes</h1>
            <p class="idg-version-badge">Versión plugin: <?php echo esc_html(IDG_VERSION); ?></p>

            <div class="idg-card">
                <h2>Módulo independiente</h2>
                <p>Esta pantalla analiza ediciones recurrentes sin modificar el <strong>Flujo editorial</strong>.</p>
                <p class="description">Para Eventos y Concursos, el análisis crea un expediente temporal y habilita una segunda acción confirmada para actualizar la publicación existente. Se preservan estado y contenido. La creación de nuevas ediciones continúa desactivada.</p>
            </div>

            <?php self::render_analysis_notices($analysis); ?>

            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="idg-card idg-recurring-search-form">
                <input type="hidden" name="page" value="ideasdi-gerizim-recurring-updates">
                <h2>1. Buscar publicación existente</h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="recurring_content_type">Tipo de contenido</label></th>
                        <td>
                            <select id="recurring_content_type" name="content_type">
                                <option value="event" <?php selected($content_type, 'event'); ?>>Evento</option>
                                <option value="contest" <?php selected($content_type, 'contest'); ?>>Concurso o convocatoria</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="recurring_search">Buscar</label></th>
                        <td>
                            <input type="search" id="recurring_search" name="recurring_search" class="regular-text" value="<?php echo esc_attr($search); ?>" placeholder="Título, slug o ID">
                            <p class="description">La coincidencia exacta por slug tiene prioridad. Eventos: CPT <code>evento</code>. Concursos: entradas de la categoría existente ID 34.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Buscar publicación', 'secondary', '', false); ?>
            </form>

            <?php if ($search !== '') : ?>
                <div class="idg-card">
                    <h2>Resultados</h2>
                    <?php if (empty($results)) : ?>
                        <p>No se encontraron publicaciones coincidentes.</p>
                    <?php else : ?>
                        <div class="idg-recurring-results">
                            <?php foreach ($results as $item) : ?>
                                <?php
                                $select_url = add_query_arg([
                                    'page' => 'ideasdi-gerizim-recurring-updates',
                                    'content_type' => $content_type,
                                    'recurring_search' => $search,
                                    'source_post_id' => (int) $item['id'],
                                ], admin_url('admin.php'));
                                ?>
                                <div class="idg-recurring-result">
                                    <div>
                                        <strong><?php echo esc_html((string) $item['title']); ?></strong>
                                        <p class="description"><strong>ID <?php echo esc_html((string) $item['id']); ?></strong> · <?php echo esc_html((string) $item['status_label']); ?> · Creado <?php echo esc_html((string) $item['date']); ?> · Modificado <?php echo esc_html((string) ($item['modified'] ?? '')); ?></p>
                                        <code><?php echo esc_html((string) $item['slug']); ?></code>
                                        <?php if (!empty($item['event_summary'])) : ?><p class="description"><?php echo esc_html((string) $item['event_summary']); ?></p><?php endif; ?>
                                    </div>
                                    <p><a class="button button-primary" href="<?php echo esc_url($select_url); ?>">Seleccionar ID <?php echo esc_html((string) $item['id']); ?></a></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($source)) : ?>
                <?php self::render_selected_source($source, $content_type, $search, $cities, $countries, $analysis_id, $analysis, $submitted); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle_analyze(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }
        check_admin_referer('idg_analyze_recurring_update');

        $content_type = self::content_type_from_value(isset($_POST['content_type']) ? (string) wp_unslash($_POST['content_type']) : 'event');
        $source_post_id = isset($_POST['source_post_id']) ? absint($_POST['source_post_id']) : 0;
        $search = isset($_POST['recurring_search']) ? sanitize_text_field((string) wp_unslash($_POST['recurring_search'])) : '';
        $source = $source_post_id > 0 ? self::load_source($content_type, $source_post_id) : null;
        $submitted = self::collect_submitted_data($content_type);
        $submitted['original_search'] = $search;
        $errors = [];
        $warnings = [];
        $selection_token = isset($_POST['selection_token']) ? sanitize_text_field((string) wp_unslash($_POST['selection_token'])) : '';

        if (!is_array($source)) {
            $errors[] = 'La publicación seleccionada no existe, no corresponde al tipo indicado o no puede editarse con el usuario actual.';
        } else {
            $expected_selection_token = self::selection_token($source);
            if ($selection_token === '' || $expected_selection_token === '' || !hash_equals($expected_selection_token, $selection_token)) {
                $errors[] = 'La identidad de la publicación seleccionada no pudo confirmarse. Vuelve a buscarla y selecciónala por su ID exacto.';
            }
        }

        if ($content_type === 'event' && (string) ($submitted['update_mode'] ?? '') === '') {
            $errors[] = 'Selecciona obligatoriamente qué operación deseas realizar. En esta versión solo está disponible Actualizar publicación vigente.';
        }

        if ($content_type === 'event') {
            $errors = array_merge($errors, self::validate_event_dates(
                (string) ($submitted['fecha_inicio'] ?? ''),
                (string) ($submitted['fecha_fin'] ?? '')
            ));
        }

        if ($content_type === 'contest') {
            foreach (['fecha_inicio_convocatoria', 'fecha_cierre_convocatoria', 'fecha_premiacion_convocatoria'] as $date_key) {
                $value = (string) ($submitted[$date_key] ?? '');
                if ($value !== '' && !self::is_valid_iso_date($value)) {
                    $errors[] = 'Una de las fechas de la convocatoria no tiene un formato válido.';
                    break;
                }
            }
        }

        $new_source_url = (string) ($submitted['new_source'] ?? '');
        if ($new_source_url !== '' && !wp_http_validate_url($new_source_url)) {
            $errors[] = 'La URL oficial o fuente nueva no es válida o no utiliza un protocolo permitido.';
            $source_read = self::empty_source_read('URL no válida; no se intentó la lectura automática.');
        } else {
            $source_read = self::read_external_source($new_source_url);
            if ((string) ($source_read['status'] ?? '') === 'http_403') {
                $warnings[] = 'La fuente externa respondió HTTP 403. Gerizim no mostró una página Forbidden: conservó la información manual y completó el expediente sin lectura automática.';
            } elseif (in_array((string) ($source_read['status'] ?? ''), ['http_error', 'request_error'], true)) {
                $warnings[] = (string) ($source_read['message'] ?? 'La fuente externa no pudo leerse. Se conservó la información manual.');
            }
        }

        $city_registration = [
            'requested' => false,
            'city' => '',
            'registered' => false,
            'already_known' => false,
        ];
        $country_registration = [
            'requested' => false,
            'country' => '',
            'storage_key' => '',
            'registered' => false,
            'already_known' => false,
        ];
        if ($content_type === 'event') {
            $submitted['ciudad'] = self::canonical_city((string) ($submitted['ciudad'] ?? ''));
            $submitted['pais'] = self::canonical_country((string) ($submitted['pais'] ?? ''), $source_post_id);
            $country_registration['country'] = (string) $submitted['pais'];
            $country_registration['requested'] = !empty($submitted['register_new_country']);
            if ($country_registration['requested'] && $country_registration['country'] !== '' && !self::country_is_valid_for_field($source_post_id, $country_registration['country'])) {
                $country_registration = array_merge($country_registration, self::register_country($source_post_id, $country_registration['country']));
            } elseif ($country_registration['country'] !== '' && self::country_is_valid_for_field($source_post_id, $country_registration['country'])) {
                $country_registration['already_known'] = true;
                $country_registration['storage_key'] = (string) self::country_storage_value($source_post_id, $country_registration['country']);
            }
            if ((string) ($submitted['pais'] ?? '') !== '' && !self::country_is_valid_for_field($source_post_id, (string) $submitted['pais'])) {
                $errors[] = 'El país indicado no coincide con una opción válida del campo ACF País. Activa “Registrar para ACF si es un país nuevo” o selecciona una opción existente.';
            }
            $city_registration['city'] = (string) $submitted['ciudad'];
            $city_registration['requested'] = !empty($submitted['register_new_city']);
            if ($city_registration['requested'] && $city_registration['city'] !== '') {
                $registration_result = self::register_city((string) $city_registration['city']);
                $city_registration = array_merge($city_registration, $registration_result);
            }
        }

        $comparison = is_array($source) ? self::build_comparison($content_type, (array) ($source['raw_fields'] ?? []), $submitted) : [];
        if (is_array($source)) {
            $comparison = array_merge(self::build_identity_comparison($source, $submitted), $comparison);
        }
        if ((string) ($submitted['update_mode'] ?? '') === 'create_new') {
            $warnings[] = 'La creación de una nueva edición permanece desactivada. Selecciona Actualizar publicación vigente para aplicar cambios sobre la publicación existente.';
        }

        $analysis_id = wp_generate_uuid4();
        $analysis = [
            'analysis_id' => $analysis_id,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'plugin_version' => IDG_VERSION,
            'content_type' => $content_type,
            'source_post_id' => $source_post_id,
            'selected_post_id' => $source_post_id,
            'source' => is_array($source) ? self::source_snapshot($source) : [],
            'source_signature' => is_array($source) ? self::source_signature($source) : '',
            'source_target_fingerprint' => is_array($source) ? self::target_post_fingerprint($source_post_id, $content_type) : '',
            'source_immutable_fingerprint' => is_array($source) ? self::immutable_post_fingerprint($source_post_id) : '',
            'selection_token_verified' => is_array($source) && $selection_token !== '' && hash_equals(self::selection_token($source), $selection_token),
            'submitted' => $submitted,
            'source_read' => $source_read,
            'comparison' => $comparison,
            'city_registration' => $city_registration,
            'country_registration' => $country_registration,
            'country_normalization' => [
                'submitted' => (string) ($submitted['pais'] ?? ''),
                'canonical' => (string) ($submitted['pais'] ?? ''),
                'available' => self::existing_countries($source_post_id),
            ],
            'errors' => array_values(array_unique(array_filter($errors))),
            'warnings' => array_values(array_unique(array_filter($warnings))),
            'application' => [
                'status' => 'not_applied',
                'publication_updated' => false,
            ],
            'safety' => [
                'publication_updated' => false,
                'new_edition_created' => false,
                'contest_written' => false,
                'openai_called' => false,
                'editorial_workflow_modified' => false,
            ],
        ];
        set_transient(self::ANALYSIS_TRANSIENT_PREFIX . $analysis_id, $analysis, self::ANALYSIS_TTL);

        $redirect = add_query_arg([
            'page' => 'ideasdi-gerizim-recurring-updates',
            'content_type' => $content_type,
            'recurring_search' => $search,
            'source_post_id' => $source_post_id,
            'analysis_id' => $analysis_id,
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public static function handle_apply(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }

        $analysis_id = isset($_POST['analysis_id']) ? sanitize_text_field((string) wp_unslash($_POST['analysis_id'])) : '';
        if ($analysis_id === '') {
            wp_die('No se encontró el análisis que se debe aplicar.');
        }
        check_admin_referer('idg_apply_recurring_update_' . $analysis_id);

        $analysis = self::get_analysis($analysis_id);
        if (empty($analysis)) {
            wp_die('El análisis solicitado no existe, expiró o pertenece a otro usuario.');
        }

        $application = isset($analysis['application']) && is_array($analysis['application']) ? $analysis['application'] : [];
        if ((string) ($application['status'] ?? '') === 'success') {
            self::redirect_to_analysis($analysis_id, $analysis);
        }

        $analysis = self::apply_identity_overrides_from_request($analysis);
        $analysis['application'] = self::apply_structural_analysis($analysis);
        if (!isset($analysis['safety']) || !is_array($analysis['safety'])) {
            $analysis['safety'] = [];
        }
        $publication_updated = !empty($analysis['application']['publication_updated']);
        $content_type = self::content_type_from_value((string) ($analysis['content_type'] ?? 'event'));
        $analysis['safety']['publication_updated'] = $publication_updated;
        $analysis['safety']['contest_written'] = $publication_updated && $content_type === 'contest';
        $analysis['updated_at'] = current_time('mysql');

        if ($publication_updated) {
            $updated_source = self::load_source($content_type, (int) ($analysis['source_post_id'] ?? 0));
            if (is_array($updated_source)) {
                $analysis['source_after'] = self::source_snapshot($updated_source);
                $analysis['source_signature_after'] = self::source_signature($updated_source);
                $analysis['source_target_fingerprint'] = self::target_post_fingerprint((int) ($analysis['source_post_id'] ?? 0), $content_type);
                if ($content_type === 'event') {
                    // Renovar la huella histórica de Eventos después de escribir título, slug o metadatos.
                    $analysis['source_immutable_fingerprint'] = self::immutable_post_fingerprint((int) ($analysis['source_post_id'] ?? 0));
                }
            }
        }

        set_transient(self::ANALYSIS_TRANSIENT_PREFIX . $analysis_id, $analysis, self::ANALYSIS_TTL);

        if (class_exists('IDG_Logger')) {
            $label = $content_type === 'contest' ? 'concurso o convocatoria' : 'evento';
            IDG_Logger::log('recurring_update_applied', 'Aplicación de actualización recurrente sobre ' . $label . ' existente.', [
                'analysis_id' => $analysis_id,
                'content_type' => $content_type,
                'post_id' => (int) ($analysis['source_post_id'] ?? 0),
                'status' => (string) ($analysis['application']['status'] ?? ''),
                'publication_updated' => $publication_updated,
                'errors' => (array) ($analysis['application']['errors'] ?? []),
            ]);
        }

        self::redirect_to_analysis($analysis_id, $analysis);
    }

    public static function handle_prepare_workflow(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }

        $analysis_id = isset($_POST['analysis_id']) ? sanitize_text_field((string) wp_unslash($_POST['analysis_id'])) : '';
        if ($analysis_id === '') {
            wp_die('No se encontró el análisis que debe convertirse en encargo editorial.');
        }
        check_admin_referer('idg_prepare_recurring_workflow_' . $analysis_id);

        $analysis = self::get_analysis($analysis_id);
        if (empty($analysis)) {
            wp_die('El análisis solicitado no existe, expiró o pertenece a otro usuario.');
        }
        $content_type = self::content_type_from_value((string) ($analysis['content_type'] ?? 'event'));
        $target_label = $content_type === 'contest' ? 'concurso o convocatoria' : 'evento';

        $application = isset($analysis['application']) && is_array($analysis['application']) ? $analysis['application'] : [];
        if ((string) ($application['status'] ?? '') !== 'success') {
            wp_die('Primero debes aplicar correctamente la actualización estructural a la publicación seleccionada.');
        }

        $existing_workflow_id = (string) ($analysis['editorial_bridge']['workflow_id'] ?? '');
        if ($existing_workflow_id !== '' && class_exists('IDG_Job_Runner') && !empty(IDG_Job_Runner::get_workflow($existing_workflow_id))) {
            wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($existing_workflow_id) . '&message=recurring_workflow_ready'));
            exit;
        }

        $post_id = (int) ($analysis['source_post_id'] ?? 0);
        $selected_post_id = (int) ($analysis['selected_post_id'] ?? 0);
        if ($post_id <= 0 || $post_id !== $selected_post_id || !current_user_can('edit_post', $post_id)) {
            wp_die('La publicación de destino no coincide con el ID seleccionado originalmente o ya no tienes permisos para editarla.');
        }
        $expected_fingerprint = (string) ($analysis['source_target_fingerprint'] ?? '');
        if ($expected_fingerprint === '' && $content_type === 'event') {
            $expected_fingerprint = (string) ($analysis['source_immutable_fingerprint'] ?? '');
        }
        $current_fingerprint = self::target_post_fingerprint($post_id, $content_type);
        if ($expected_fingerprint === '' || $current_fingerprint === '' || !hash_equals($expected_fingerprint, $current_fingerprint)) {
            wp_die('La identidad de la publicación cambió. Vuelve a buscarla por ID exacto y genera un análisis nuevo.');
        }
        $source = self::load_source($content_type, $post_id);
        if (!is_array($source)) {
            wp_die('No fue posible volver a cargar la publicación actualizada.');
        }

        $workflow_data = self::build_editorial_workflow_data($analysis, $source);
        if (!class_exists('IDG_Job_Runner')) {
            wp_die('El Flujo editorial de Gerizim no está disponible.');
        }
        $workflow_id = IDG_Workflow_Orchestrator::create($workflow_data, 'recurring');
        IDG_Job_Runner::add_history($workflow_id, 'recurring_update_imported', 'Encargo editorial preparado desde Actualizaciones recurrentes para el ' . $target_label . ' ID ' . $post_id . '.');

        $analysis['editorial_bridge'] = [
            'status' => 'workflow_created',
            'workflow_id' => $workflow_id,
            'created_at' => current_time('mysql'),
            'target_post_id' => $post_id,
            'target_post_type' => (string) ($source['post_type'] ?? ''),
        ];
        if (!isset($analysis['safety']) || !is_array($analysis['safety'])) {
            $analysis['safety'] = [];
        }
        $analysis['safety']['editorial_workflow_modified'] = true;
        set_transient(self::ANALYSIS_TRANSIENT_PREFIX . $analysis_id, $analysis, self::ANALYSIS_TTL);

        if (class_exists('IDG_Logger')) {
            IDG_Logger::log('recurring_editorial_workflow_created', 'Workflow editorial creado desde Actualizaciones recurrentes.', [
                'analysis_id' => $analysis_id,
                'workflow_id' => $workflow_id,
                'content_type' => $content_type,
                'post_id' => $post_id,
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id) . '&message=recurring_workflow_ready'));
        exit;
    }

    public static function editorial_target_signature(int $post_id, string $target_post_type = ''): string {
        $content_type = self::content_type_for_post_type($target_post_type);
        if ($content_type === '') {
            $post = $post_id > 0 ? get_post($post_id) : null;
            $content_type = $post instanceof WP_Post ? self::content_type_for_post_type((string) $post->post_type) : '';
        }
        $source = $post_id > 0 && $content_type !== '' ? self::load_source($content_type, $post_id) : null;
        return is_array($source) ? self::source_signature($source) : '';
    }

    public static function handle_download_report(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }
        $analysis_id = isset($_GET['analysis_id']) ? sanitize_text_field((string) wp_unslash($_GET['analysis_id'])) : '';
        if ($analysis_id === '') {
            wp_die('No se encontró el análisis para exportar.');
        }
        check_admin_referer('idg_download_recurring_report_' . $analysis_id);
        $analysis = self::get_analysis($analysis_id);
        if (empty($analysis)) {
            wp_die('El análisis solicitado no existe, expiró o pertenece a otro usuario.');
        }

        $markdown = self::build_report($analysis);
        $source_title = sanitize_title((string) ($analysis['source']['title'] ?? 'actualizacion-recurrente'));
        if ($source_title === '') {
            $source_title = 'actualizacion-recurrente';
        }
        $filename = 'gerizim-actualizacion-' . $source_title . '-' . date_i18n('Ymd-His') . '.md';
        nocache_headers();
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        echo $markdown;
        exit;
    }

    private static function render_analysis_notices(array $analysis): void {
        if (empty($analysis)) {
            return;
        }
        $errors = isset($analysis['errors']) && is_array($analysis['errors']) ? $analysis['errors'] : [];
        $warnings = isset($analysis['warnings']) && is_array($analysis['warnings']) ? $analysis['warnings'] : [];
        $application = isset($analysis['application']) && is_array($analysis['application']) ? $analysis['application'] : [];
        $application_status = (string) ($application['status'] ?? 'not_applied');

        if ($application_status === 'success') {
            $post_id = (int) ($application['post_id'] ?? 0);
            $edit_url = (string) ($application['edit_url'] ?? '');
            echo '<div class="notice notice-success"><p><strong>Actualización aplicada correctamente.</strong> La publicación ID ' . esc_html((string) $post_id) . ' fue actualizada y conservó su estado y contenido.';
            if ($edit_url !== '') {
                echo ' <a href="' . esc_url($edit_url) . '">Abrir publicación actualizada</a>.';
            }
            echo '</p></div>';
        } elseif ($application_status === 'partial') {
            echo '<div class="notice notice-warning"><p><strong>La actualización se aplicó parcialmente.</strong> Revisa el detalle y el reporte antes de continuar.</p></div>';
        } elseif ($application_status === 'failed') {
            echo '<div class="notice notice-error"><p><strong>No se aplicó la actualización.</strong></p>';
            $application_errors = isset($application['errors']) && is_array($application['errors']) ? $application['errors'] : [];
            if (!empty($application_errors)) {
                echo '<ul class="idg-warning-list">';
                foreach ($application_errors as $error) {
                    echo '<li>' . esc_html((string) $error) . '</li>';
                }
                echo '</ul>';
            }
            echo '</div>';
        } elseif (empty($errors)) {
            if (in_array((string) ($analysis['content_type'] ?? ''), ['event', 'contest'], true) && (string) ($analysis['submitted']['update_mode'] ?? '') === 'update_existing') {
                echo '<div class="notice notice-success"><p><strong>Análisis completado.</strong> Revisa la comparación y usa “Aplicar actualización a la publicación” para ejecutar los cambios confirmados.</p></div>';
            } else {
                echo '<div class="notice notice-success"><p><strong>Análisis completado.</strong> El expediente se generó sin modificar publicaciones.</p></div>';
            }
        }

        if (!empty($errors)) {
            echo '<div class="notice notice-error"><p><strong>El expediente se generó con errores de validación:</strong></p><ul class="idg-warning-list">';
            foreach ($errors as $error) {
                echo '<li>' . esc_html((string) $error) . '</li>';
            }
            echo '</ul></div>';
        }
        if (!empty($warnings)) {
            echo '<div class="notice notice-warning"><p><strong>Avisos:</strong></p><ul class="idg-warning-list">';
            foreach ($warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul></div>';
        }
        $application_warnings = isset($application['warnings']) && is_array($application['warnings']) ? $application['warnings'] : [];
        if (!empty($application_warnings)) {
            echo '<div class="notice notice-warning"><p><strong>Avisos de aplicación:</strong></p><ul class="idg-warning-list">';
            foreach ($application_warnings as $warning) {
                echo '<li>' . esc_html((string) $warning) . '</li>';
            }
            echo '</ul></div>';
        }
    }

    private static function render_selected_source(array $source, string $content_type, string $search, array $cities, array $countries, string $analysis_id, array $analysis, array $submitted): void {
        $is_event = $content_type === 'event';
        ?>
        <div class="idg-card">
            <h2>2. Publicación seleccionada</h2>
            <div class="idg-recurring-source-header">
                <div>
                    <h3><?php echo esc_html((string) $source['title']); ?></h3>
                    <p class="description"><strong>ID bloqueado: <?php echo esc_html((string) $source['id']); ?></strong> · <?php echo esc_html((string) $source['post_type']); ?> · <?php echo esc_html((string) $source['status_label']); ?></p>
                    <p><code><?php echo esc_html((string) $source['slug']); ?></code></p>
                    <p><a href="<?php echo esc_url((string) $source['edit_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir exactamente la publicación ID <?php echo esc_html((string) $source['id']); ?> en una pestaña nueva</a></p>
                </div>
                <?php if (!empty($source['permalink'])) : ?>
                    <p><a class="button" href="<?php echo esc_url((string) $source['permalink']); ?>" target="_blank" rel="noopener noreferrer">Ver publicación</a></p>
                <?php endif; ?>
            </div>

            <h3>Campos actuales</h3>
            <table class="widefat striped idg-recurring-meta-table">
                <tbody>
                <?php foreach ((array) $source['fields'] as $label => $value) : ?>
                    <tr>
                        <th><?php echo esc_html((string) $label); ?></th>
                        <td><?php echo $value !== '' ? esc_html((string) $value) : '<span class="description">Sin información</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-card idg-recurring-preparation">
            <?php wp_nonce_field('idg_analyze_recurring_update'); ?>
            <input type="hidden" name="action" value="idg_analyze_recurring_update">
            <input type="hidden" name="content_type" value="<?php echo esc_attr($content_type); ?>">
            <input type="hidden" name="source_post_id" value="<?php echo esc_attr((string) $source['id']); ?>">
            <input type="hidden" name="selection_token" value="<?php echo esc_attr(self::selection_token($source)); ?>">
            <input type="hidden" name="recurring_search" value="<?php echo esc_attr($search); ?>">
            <h2>3. Preparar actualización</h2>
            <div class="notice notice-info inline idg-recurring-identity-lock"><p><strong><?php echo $is_event ? 'Evento' : 'Concurso'; ?> seleccionado:</strong> ID <?php echo esc_html((string) $source['id']); ?> · “<?php echo esc_html((string) $source['title']); ?>”. La actualización se aplicará únicamente sobre este registro.</p></div>
            <?php if ($is_event) : ?>
                <?php $selected_mode = (string) ($submitted['update_mode'] ?? ''); ?>
                <fieldset class="idg-recurring-mode-group">
                    <legend class="screen-reader-text">Operación requerida</legend>
                    <label><input type="radio" name="update_mode" value="update_existing" required <?php checked($selected_mode, 'update_existing'); ?>> <strong>Actualizar publicación vigente</strong></label><br>
                    <label><input type="radio" name="update_mode" value="create_new" disabled <?php checked($selected_mode, 'create_new'); ?>> Crear nueva edición desde anterior <span class="description">(próxima fase)</span></label>
                </fieldset>
                <div class="notice notice-warning inline idg-recurring-overwrite-warning" data-idg-overwrite-warning <?php echo $selected_mode === 'update_existing' ? '' : 'hidden'; ?>><p><strong>Advertencia:</strong> al aplicar la actualización se sobrescribirán en el evento seleccionado únicamente el título, slug y campos estructurados confirmados. El estado y el contenido editorial se conservarán hasta aplicar después la Versión 3.</p></div>
                <p class="description">Debes seleccionar una operación antes de analizar. La opción disponible actualiza el mismo ID; no crea un evento nuevo.</p>
            <?php else : ?>
                <input type="hidden" name="update_mode" value="update_existing">
                <div class="notice notice-warning inline idg-recurring-overwrite-warning"><p><strong>Advertencia:</strong> al aplicar la actualización se sobrescribirán únicamente el título, slug y los campos confirmados de la convocatoria. El estado, contenido, autor, categorías, etiquetas e imagen destacada se conservarán hasta aplicar después la redacción editorial.</p></div>
                <p class="description">La operación actualiza el mismo artículo de concurso; no crea una edición nueva.</p>
            <?php endif; ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="recurring_new_source">URL oficial o fuente nueva</label></th>
                    <td><input type="url" id="recurring_new_source" name="new_source" class="large-text" value="<?php echo esc_attr((string) ($submitted['new_source'] ?? '')); ?>" placeholder="https://..."><p class="description">Una respuesta HTTP 403 se registra como aviso y no interrumpe el análisis manual.</p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="recurring_new_information">Información nueva</label></th>
                    <td><textarea id="recurring_new_information" name="new_information" rows="6" class="large-text" placeholder="Pega aquí información de la nueva edición, nota de prensa o datos confirmados."><?php echo esc_textarea((string) ($submitted['new_information'] ?? '')); ?></textarea></td>
                </tr>
            </table>

            <h3><?php echo $is_event ? 'Datos del evento' : 'Datos de la convocatoria'; ?></h3>
            <p class="description">Completa solo los valores nuevos o confirmados. El reporte comparará la propuesta con los datos actuales.</p>
            <table class="form-table" role="presentation">
                <?php if ($is_event) : ?>
                    <?php self::render_event_preparation_fields($source, $cities, $countries, $submitted); ?>
                <?php else : ?>
                    <?php self::render_contest_preparation_fields($source, $submitted); ?>
                <?php endif; ?>
            </table>
            <?php $analysis_done = !empty($analysis) && (int) ($analysis['source_post_id'] ?? 0) === (int) $source['id']; ?>
            <p><button type="submit" class="button button-primary idg-recurring-analyze-button <?php echo $analysis_done ? 'idg-step-done' : ''; ?>" data-default-label="Analizar cambios" data-done-label="Analizado · volver a analizar"><?php echo $analysis_done ? 'Analizado · volver a analizar' : 'Analizar cambios'; ?></button></p>
        </form>

        <?php if (!empty($analysis) && $analysis_id !== '') : ?>
            <?php self::render_analysis_result($analysis, $analysis_id); ?>
        <?php endif; ?>
        <?php
    }

    private static function render_event_preparation_fields(array $source, array $cities, array $countries, array $submitted): void {
        $fields = (array) ($source['raw_fields'] ?? []);
        $start_value = (string) ($submitted['fecha_inicio'] ?? '');
        $end_value = (string) ($submitted['fecha_fin'] ?? '');
        self::render_preparation_row('fecha_inicio', 'Fecha de inicio', 'date', (string) ($fields['fecha_inicio'] ?? ''), $start_value, ['data-recurring-start-date' => '1']);
        self::render_preparation_row('fecha_fin', 'Fecha de fin', 'date', (string) ($fields['fecha_fin'] ?? ''), $end_value, ['min' => $start_value, 'data-recurring-end-date' => '1']);
        ?>
        <tr>
            <th scope="row"><label for="recurring_ciudad">Ciudad</label></th>
            <td>
                <input type="text" id="recurring_ciudad" name="recurring_ciudad" class="regular-text" list="idg-recurring-city-list" value="<?php echo esc_attr((string) ($submitted['ciudad'] ?? '')); ?>" placeholder="Selecciona o escribe una ciudad nueva" autocomplete="off">
                <datalist id="idg-recurring-city-list">
                    <?php foreach ($cities as $city) : ?>
                        <option value="<?php echo esc_attr((string) $city); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="description">Ciudades deduplicadas desde el CPT evento y el registro auxiliar. Alias iniciales: Milan y Milano se normalizan como Milán. Valor actual: <?php echo !empty($fields['ciudad']) ? esc_html((string) $fields['ciudad']) : 'Sin información'; ?>.</p>
                <label><input type="checkbox" name="register_new_city" value="1" <?php checked(!isset($submitted['register_new_city']) || !empty($submitted['register_new_city'])); ?>> Registrar para futuros autocompletados si es una ciudad nueva.</label>
            </td>
        </tr>
        <?php
        ?>
        <tr>
            <th scope="row"><label for="recurring_pais">País</label></th>
            <td>
                <input type="text" id="recurring_pais" name="recurring_pais" class="regular-text" list="idg-recurring-country-list" value="<?php echo esc_attr((string) ($submitted['pais'] ?? '')); ?>" placeholder="Selecciona o escribe un país" autocomplete="off">
                <datalist id="idg-recurring-country-list">
                    <?php foreach ($countries as $country) : ?>
                        <option value="<?php echo esc_attr((string) $country); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <p class="description">Países normalizados desde los eventos existentes, el registro auxiliar y las opciones del campo ACF. Se unifican tildes, mayúsculas e idioma. Valor actual: <?php echo !empty($fields['pais']) ? esc_html((string) $fields['pais']) : 'Sin información'; ?>.</p>
                <label><input type="checkbox" name="register_new_country" value="1" <?php checked(!isset($submitted['register_new_country']) || !empty($submitted['register_new_country'])); ?>> Registrar para ACF y futuros autocompletados si es un país nuevo.</label>
            </td>
        </tr>
        <?php
        self::render_preparation_row('ubicacion', 'Ubicación', 'text', (string) ($fields['ubicacion'] ?? ''), (string) ($submitted['ubicacion'] ?? ''));
        self::render_preparation_row('enlace_oficial', 'Enlace oficial', 'url', (string) ($fields['enlace_oficial'] ?? ''), (string) ($submitted['enlace_oficial'] ?? ''));
        self::render_preparation_row('resumen_editorial', 'Resumen editorial', 'textarea', (string) ($fields['resumen_editorial'] ?? ''), (string) ($submitted['resumen_editorial'] ?? ''));
    }

    private static function render_contest_preparation_fields(array $source, array $submitted): void {
        $fields = (array) ($source['raw_fields'] ?? []);
        $rows = [
            'fecha_inicio_convocatoria' => ['Fecha de inicio', 'date'],
            'fecha_cierre_convocatoria' => ['Fecha de cierre', 'date'],
            'fecha_premiacion_convocatoria' => ['Fecha de premiación', 'date'],
            'enlace_oficial_convocatoria' => ['Enlace oficial de la convocatoria', 'url'],
        ];
        foreach ($rows as $key => [$label, $type]) {
            self::render_preparation_row($key, $label, $type, (string) ($fields[$key] ?? ''), (string) ($submitted[$key] ?? ''));
        }
    }

    private static function render_preparation_row(string $key, string $label, string $type, string $current_value, string $proposed_value = '', array $attributes = []): void {
        $display_current = str_contains($key, 'fecha_') ? self::format_ymd_for_display($current_value) : $current_value;
        $attribute_html = '';
        foreach ($attributes as $attribute => $value) {
            if ($value === '') {
                continue;
            }
            $attribute_html .= ' ' . esc_attr($attribute) . '="' . esc_attr((string) $value) . '"';
        }
        ?>
        <tr>
            <th scope="row"><label for="recurring_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <?php if ($type === 'textarea') : ?>
                    <textarea id="recurring_<?php echo esc_attr($key); ?>" name="recurring_<?php echo esc_attr($key); ?>" rows="4" class="large-text" placeholder="Valor nuevo"<?php echo $attribute_html; ?>><?php echo esc_textarea($proposed_value); ?></textarea>
                <?php else : ?>
                    <input type="<?php echo esc_attr($type); ?>" id="recurring_<?php echo esc_attr($key); ?>" name="recurring_<?php echo esc_attr($key); ?>" class="regular-text" value="<?php echo esc_attr($proposed_value); ?>" placeholder="Valor nuevo"<?php echo $attribute_html; ?>>
                <?php endif; ?>
                <p class="description">Valor actual: <?php echo $display_current !== '' ? esc_html($display_current) : 'Sin información'; ?></p>
            </td>
        </tr>
        <?php
    }

    private static function render_analysis_result(array $analysis, string $analysis_id): void {
        $source_read = isset($analysis['source_read']) && is_array($analysis['source_read']) ? $analysis['source_read'] : [];
        $comparison = isset($analysis['comparison']) && is_array($analysis['comparison']) ? $analysis['comparison'] : [];
        $application = isset($analysis['application']) && is_array($analysis['application']) ? $analysis['application'] : [];
        $application_status = (string) ($application['status'] ?? 'not_applied');
        $analysis_errors = isset($analysis['errors']) && is_array($analysis['errors']) ? $analysis['errors'] : [];
        $content_type = (string) ($analysis['content_type'] ?? 'event');
        $mode = (string) ($analysis['submitted']['update_mode'] ?? '');
        $has_changes = self::comparison_has_changes($comparison);
        $download_url = wp_nonce_url(
            admin_url('admin-post.php?action=idg_download_recurring_report&analysis_id=' . rawurlencode($analysis_id)),
            'idg_download_recurring_report_' . $analysis_id
        );
        ?>
        <div class="idg-card idg-recurring-analysis">
            <div class="idg-recurring-source-header">
                <div>
                    <h2>4. Resultado del análisis</h2>
                    <p class="description">Expediente temporal creado: <?php echo esc_html((string) ($analysis['created_at'] ?? '')); ?></p>
                </div>
                <p><a class="button button-primary idg-button-report" href="<?php echo esc_url($download_url); ?>">Descargar reporte completo</a></p>
            </div>

            <h3>Lectura de fuente externa</h3>
            <p><strong>Estado:</strong> <?php echo esc_html((string) ($source_read['label'] ?? $source_read['status'] ?? 'Sin URL')); ?><br>
            <strong>HTTP:</strong> <?php echo !empty($source_read['http_code']) ? esc_html((string) $source_read['http_code']) : 'No aplica'; ?><br>
            <strong>Detalle:</strong> <?php echo esc_html((string) ($source_read['message'] ?? '')); ?></p>

            <?php if (!empty($comparison)) : ?>
                <h3>Comparación previa a la aplicación</h3>
                <table class="widefat striped idg-recurring-comparison-table">
                    <thead><tr><th>Campo</th><th>Actual</th><th>Propuesto</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($comparison as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) ($row['label'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) ($row['current_display'] ?? 'Sin información')); ?></td>
                            <td>
                                <?php $row_key = (string) ($row['key'] ?? ''); ?>
                                <?php if ($application_status === 'not_applied' && in_array($row_key, ['post_title', 'post_slug'], true)) : ?>
                                    <input type="text" class="regular-text <?php echo $row_key === 'post_slug' ? 'idg-recurring-proposed-slug' : 'idg-recurring-proposed-title'; ?>" name="<?php echo $row_key === 'post_title' ? 'proposed_title' : 'proposed_slug'; ?>" value="<?php echo esc_attr((string) ($row['proposed'] ?? '')); ?>" form="idg-recurring-apply-form" <?php echo $row_key === 'post_slug' ? 'data-manual-slug="0"' : ''; ?>>
                                <?php else : ?>
                                    <?php echo esc_html((string) ($row['proposed_display'] ?? 'Sin propuesta')); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) ($row['status_label'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if ($content_type === 'event') : ?>
                <div class="idg-recurring-temporality">
                    <h3>Temporalidad confirmada</h3>
                    <p class="description">El año editorial forma parte del nombre de la temporada; el año calendario indica cuándo sucede físicamente el evento. No se reemplazan entre sí.</p>
                </div>
            <?php endif; ?>

            <?php $city = isset($analysis['city_registration']) && is_array($analysis['city_registration']) ? $analysis['city_registration'] : []; ?>
            <?php if (!empty($city['requested']) && !empty($city['city'])) : ?>
                <p><strong>Ciudad:</strong> <?php echo esc_html((string) $city['city']); ?> · <?php echo !empty($city['registered']) ? 'registrada en el autocompletado auxiliar' : (!empty($city['already_known']) ? 'ya estaba disponible' : 'no registrada'); ?>.</p>
            <?php endif; ?>
            <?php $country_registration = isset($analysis['country_registration']) && is_array($analysis['country_registration']) ? $analysis['country_registration'] : []; ?>
            <?php if (!empty($country_registration['country'])) : ?>
                <p><strong>País:</strong> <?php echo esc_html((string) $country_registration['country']); ?> · <?php echo !empty($country_registration['registered']) ? 'registrado en ACF y en el autocompletado auxiliar' : (!empty($country_registration['already_known']) ? 'ya estaba disponible' : 'pendiente de registro'); ?><?php echo !empty($country_registration['storage_key']) ? ' · clave: ' . esc_html((string) $country_registration['storage_key']) : ''; ?>.</p>
            <?php endif; ?>

            <?php if (in_array($application_status, ['success', 'partial', 'failed'], true)) : ?>
                <?php self::render_application_result($application); ?>
            <?php elseif (in_array($content_type, ['event', 'contest'], true) && $mode === 'update_existing' && empty($analysis_errors)) : ?>
                <div class="idg-recurring-apply-panel">
                    <h3>5. Aplicar actualización a la publicación</h3>
                    <p>Edita, si hace falta, el <strong>Título</strong> y el <strong>Slug</strong> directamente en la comparación. La acción actualizará esos valores y los campos marcados como cambio; conservará contenido, extracto, autor, estado, taxonomías e imagen destacada.</p>
                    <?php if (!$has_changes) : ?><div class="notice notice-info inline"><p>No se detectaron cambios iniciales. Puedes ajustar el título o slug propuestos antes de aplicar.</p></div><?php endif; ?>
                    <form id="idg-recurring-apply-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idg-recurring-apply-form">
                        <?php wp_nonce_field('idg_apply_recurring_update_' . $analysis_id); ?>
                        <input type="hidden" name="action" value="idg_apply_recurring_update">
                        <input type="hidden" name="analysis_id" value="<?php echo esc_attr($analysis_id); ?>">
                        <p><button type="submit" class="button button-primary" data-confirm="¿Aplicar estos cambios a la publicación seleccionada? El estado y el contenido se conservarán.">Aplicar actualización a la publicación</button></p>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (in_array($content_type, ['event', 'contest'], true) && $application_status === 'success') : ?>
                <?php self::render_editorial_bridge($analysis, $analysis_id); ?>
            <?php endif; ?>

            <div class="notice notice-info inline"><p><strong>Protección activa:</strong> no se crean nuevas ediciones. La redacción se prepara en el Flujo editorial existente y queda vinculada únicamente a la publicación seleccionada por ID.</p></div>
        </div>
        <?php
    }

    private static function render_editorial_bridge(array $analysis, string $analysis_id): void {
        $bridge = isset($analysis['editorial_bridge']) && is_array($analysis['editorial_bridge']) ? $analysis['editorial_bridge'] : [];
        $workflow_id = (string) ($bridge['workflow_id'] ?? '');
        $workflow_exists = $workflow_id !== '' && class_exists('IDG_Job_Runner') && !empty(IDG_Job_Runner::get_workflow($workflow_id));
        $is_contest = (string) ($analysis['content_type'] ?? '') === 'contest';
        $target_label = $is_contest ? 'concurso o convocatoria' : 'evento';
        ?>
        <div class="idg-recurring-editorial-bridge">
            <h3>6. Preparar redacción en Flujo editorial</h3>
            <p>Gerizim transferirá el <?php echo esc_html($target_label); ?>, los datos confirmados, la fuente, el extracto técnico y el contenido anterior a un workflow editorial normal. La receta, investigación, revisión SEO, validaciones, enlaces y conversión Gutenberg seguirán usando el núcleo existente.</p>
            <?php if ($workflow_exists) : ?>
                <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=ideasdi-gerizim&workflow_id=' . rawurlencode($workflow_id))); ?>">Continuar redacción en Flujo editorial</a></p>
                <p class="description">Workflow vinculado: <code><?php echo esc_html($workflow_id); ?></code>. No se creará una entrada normal; el paso final actualizará el contenido de la publicación seleccionada.</p>
            <?php else : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('idg_prepare_recurring_workflow_' . $analysis_id); ?>
                    <input type="hidden" name="action" value="idg_prepare_recurring_workflow">
                    <input type="hidden" name="analysis_id" value="<?php echo esc_attr($analysis_id); ?>">
                    <p><button type="submit" class="button button-primary">Preparar redacción en Flujo editorial</button></p>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_application_result(array $application): void {
        $status = (string) ($application['status'] ?? 'failed');
        $updated_fields = isset($application['updated_fields']) && is_array($application['updated_fields']) ? $application['updated_fields'] : [];
        $errors = isset($application['errors']) && is_array($application['errors']) ? $application['errors'] : [];
        $warnings = isset($application['warnings']) && is_array($application['warnings']) ? $application['warnings'] : [];
        ?>
        <div class="idg-recurring-application-result">
            <h3>5. Resultado de la aplicación</h3>
            <p><strong>Estado:</strong> <?php echo esc_html($status === 'success' ? 'Actualización completa' : ($status === 'partial' ? 'Actualización parcial' : 'No aplicada')); ?><br>
            <strong>Fecha:</strong> <?php echo esc_html((string) ($application['applied_at'] ?? '')); ?><br>
            <strong>ID:</strong> <?php echo esc_html((string) ($application['post_id'] ?? '')); ?><br>
            <strong>Estado WordPress:</strong> <?php echo esc_html((string) ($application['post_status_after'] ?? $application['post_status_before'] ?? '')); ?><br>
            <strong>Contenido conservado:</strong> <?php echo !empty($application['content_preserved']) ? 'Sí' : 'Revisar'; ?></p>
            <?php if (!empty($updated_fields)) : ?>
                <ul class="idg-warning-list">
                    <?php foreach ($updated_fields as $field) : ?>
                        <li><strong><?php echo esc_html((string) ($field['label'] ?? $field['key'] ?? 'Campo')); ?>:</strong> <?php echo !empty($field['success']) ? 'actualizado y verificado' : 'no verificado'; ?><?php echo !empty($field['stored_display']) ? ' · ' . esc_html((string) $field['stored_display']) : ''; ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($errors)) : ?>
                <div class="notice notice-error inline"><ul class="idg-warning-list">
                    <?php foreach ($errors as $error) : ?><li><?php echo esc_html((string) $error); ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>
            <?php if (!empty($warnings)) : ?>
                <div class="notice notice-warning inline"><ul class="idg-warning-list">
                    <?php foreach ($warnings as $warning) : ?><li><?php echo esc_html((string) $warning); ?></li><?php endforeach; ?>
                </ul></div>
            <?php endif; ?>
            <?php if (!empty($application['edit_url'])) : ?>
                <p><a class="button button-primary" href="<?php echo esc_url((string) $application['edit_url']); ?>">Abrir publicación actualizada</a></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function comparison_has_changes(array $comparison): bool {
        foreach ($comparison as $row) {
            if ((string) ($row['status'] ?? '') === 'change') {
                return true;
            }
        }
        return false;
    }

    private static function redirect_to_analysis(string $analysis_id, array $analysis): void {
        $submitted = isset($analysis['submitted']) && is_array($analysis['submitted']) ? $analysis['submitted'] : [];
        $redirect = add_query_arg([
            'page' => 'ideasdi-gerizim-recurring-updates',
            'content_type' => (string) ($analysis['content_type'] ?? 'event'),
            'recurring_search' => (string) ($submitted['original_search'] ?? ''),
            'source_post_id' => (int) ($analysis['source_post_id'] ?? 0),
            'analysis_id' => $analysis_id,
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    private static function apply_structural_analysis(array $analysis): array {
        $content_type = self::content_type_from_value((string) ($analysis['content_type'] ?? 'event'));
        $target_label = $content_type === 'contest' ? 'concurso o convocatoria' : 'evento';
        $result = [
            'status' => 'failed',
            'publication_updated' => false,
            'content_type' => $content_type,
            'applied_at' => current_time('mysql'),
            'post_id' => (int) ($analysis['source_post_id'] ?? 0),
            'updated_fields' => [],
            'errors' => [],
            'warnings' => [],
            'content_preserved' => false,
        ];

        $analysis_errors = isset($analysis['errors']) && is_array($analysis['errors']) ? $analysis['errors'] : [];
        if (!empty($analysis_errors)) {
            $result['errors'][] = 'El análisis contiene errores de validación. Corrige los datos y genera un expediente nuevo antes de aplicar.';
            return $result;
        }

        $submitted = isset($analysis['submitted']) && is_array($analysis['submitted']) ? $analysis['submitted'] : [];
        if ((string) ($submitted['update_mode'] ?? '') !== 'update_existing') {
            $result['errors'][] = 'La creación de nuevas ediciones todavía no está habilitada. Genera un análisis en modo Actualizar publicación vigente.';
            return $result;
        }

        $post_id = (int) ($analysis['source_post_id'] ?? 0);
        $selected_post_id = (int) ($analysis['selected_post_id'] ?? 0);
        if ($post_id <= 0 || $selected_post_id <= 0 || $post_id !== $selected_post_id) {
            $result['errors'][] = 'El ID de la publicación cambió entre la selección y la aplicación. La operación fue bloqueada.';
            return $result;
        }
        $expected_fingerprint = (string) ($analysis['source_target_fingerprint'] ?? '');
        if ($expected_fingerprint === '' && $content_type === 'event') {
            $expected_fingerprint = (string) ($analysis['source_immutable_fingerprint'] ?? '');
        }
        $current_fingerprint = self::target_post_fingerprint($post_id, $content_type);
        if ($expected_fingerprint === '' || $current_fingerprint === '' || !hash_equals($expected_fingerprint, $current_fingerprint)) {
            $result['errors'][] = 'La identidad de la publicación ya no coincide con el registro seleccionado. Vuelve a buscarla por ID exacto.';
            return $result;
        }
        if (!current_user_can('edit_post', $post_id)) {
            $result['errors'][] = 'La publicación no existe o el usuario actual ya no tiene permisos para editarla.';
            return $result;
        }

        $source = self::load_source($content_type, $post_id);
        if (!is_array($source)) {
            $result['errors'][] = 'No fue posible volver a cargar el ' . $target_label . ' seleccionado.';
            return $result;
        }

        $expected_signature = (string) ($analysis['source_signature'] ?? '');
        $current_signature = self::source_signature($source);
        if ($expected_signature === '' || !hash_equals($expected_signature, $current_signature)) {
            $result['errors'][] = 'La publicación cambió después del análisis. Para evitar sobrescribir cambios recientes, vuelve a cargarla y analiza nuevamente.';
            return $result;
        }

        $comparison = isset($analysis['comparison']) && is_array($analysis['comparison']) ? $analysis['comparison'] : [];
        if (!self::comparison_has_changes($comparison)) {
            $result['errors'][] = 'El expediente no contiene cambios efectivos para aplicar.';
            return $result;
        }

        $post_before = get_post($post_id);
        if (!$post_before instanceof WP_Post) {
            $result['errors'][] = 'No se pudo leer la publicación antes de actualizarla.';
            return $result;
        }
        $result['post_status_before'] = (string) $post_before->post_status;
        $result['title_before'] = (string) $post_before->post_title;
        $result['slug_before'] = (string) $post_before->post_name;
        $content_hash_before = hash('sha256', (string) $post_before->post_content . '|' . (string) $post_before->post_excerpt);
        $protected_before = [
            'post_status' => (string) $post_before->post_status,
            'post_author' => (int) $post_before->post_author,
            'thumbnail_id' => (int) get_post_thumbnail_id($post_id),
            'taxonomies' => [],
        ];
        foreach (get_object_taxonomies((string) $post_before->post_type) as $taxonomy) {
            $protected_before['taxonomies'][$taxonomy] = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
        }

        $identity = self::identity_proposal($source, $submitted);
        $start_date = $content_type === 'contest'
            ? (string) ($submitted['fecha_inicio_convocatoria'] ?? '')
            : (string) ($submitted['fecha_inicio'] ?? '');
        $end_date = $content_type === 'contest'
            ? (string) ($submitted['fecha_cierre_convocatoria'] ?? '')
            : (string) ($submitted['fecha_fin'] ?? '');
        $title_validation = self::validate_event_title_years((string) ($identity['title'] ?? ''), $start_date, $end_date);
        if (!empty($title_validation['errors'])) {
            $result['errors'] = array_merge($result['errors'], $title_validation['errors']);
            return $result;
        }
        $result['warnings'] = array_merge($result['warnings'], (array) ($title_validation['warnings'] ?? []));
        $identity_changed = (string) ($identity['title'] ?? '') !== (string) $post_before->post_title
            || (string) ($identity['slug'] ?? '') !== (string) $post_before->post_name;
        if ($identity_changed) {
            $post_update = [
                'ID' => $post_id,
                'post_title' => (string) ($identity['title'] ?? $post_before->post_title),
                'post_name' => (string) ($identity['slug'] ?? $post_before->post_name),
            ];
            $updated_id = wp_update_post($post_update, true);
            if (is_wp_error($updated_id)) {
                $result['errors'][] = 'No se pudo actualizar el título o slug: ' . $updated_id->get_error_message();
                return $result;
            }
            if ((int) $updated_id !== $post_id) {
                $result['errors'][] = 'WordPress devolvió un ID distinto a la publicación seleccionada. La operación fue detenida.';
                return $result;
            }
            $post_identity = get_post($post_id);
            $identity_ok = $post_identity instanceof WP_Post
                && (string) $post_identity->post_title === (string) $post_update['post_title']
                && sanitize_title((string) $post_identity->post_name) === sanitize_title((string) $post_update['post_name']);
            $result['updated_fields'][] = [
                'key' => 'post_identity',
                'label' => 'Título y slug',
                'success' => $identity_ok,
                'stored_display' => $post_identity instanceof WP_Post ? (string) $post_identity->post_title . ' · ' . (string) $post_identity->post_name : '',
            ];
            if (!$identity_ok) {
                $result['errors'][] = 'WordPress no confirmó el título o slug propuestos.';
            }
        }

        $field_labels = self::structural_field_labels($content_type);
        foreach ($comparison as $row) {
            $key = (string) ($row['key'] ?? '');
            if (!isset($field_labels[$key]) || (string) ($row['status'] ?? '') !== 'change') {
                continue;
            }
            $write = self::write_structural_field($content_type, $post_id, $key, (string) ($row['proposed'] ?? ''));
            $result['updated_fields'][] = $write;
            if (empty($write['success'])) {
                $result['errors'][] = 'No se pudo verificar el campo ' . (string) ($write['label'] ?? $key) . '.';
            }
        }

        clean_post_cache($post_id);
        $post_after = get_post($post_id);
        if (!$post_after instanceof WP_Post) {
            $result['errors'][] = 'No se pudo verificar la publicación después de actualizarla.';
            return $result;
        }

        $content_hash_after = hash('sha256', (string) $post_after->post_content . '|' . (string) $post_after->post_excerpt);
        $result['content_preserved'] = hash_equals($content_hash_before, $content_hash_after);
        $result['post_status_after'] = (string) $post_after->post_status;
        $result['selected_post_id'] = $selected_post_id;
        $result['actual_updated_post_id'] = (int) $post_after->ID;
        $result['same_post_id'] = ((int) $post_after->ID === $selected_post_id);
        $result['title_after'] = (string) $post_after->post_title;
        $result['slug_after'] = (string) $post_after->post_name;
        $result['edit_url'] = get_edit_post_link($post_id, 'raw') ?: '';
        $result['permalink'] = get_permalink($post_id) ?: '';

        if (empty($result['same_post_id'])) {
            $result['errors'][] = 'La verificación final detectó un ID diferente al seleccionado.';
        }
        if ($protected_before['post_status'] !== (string) $post_after->post_status) {
            $result['errors'][] = 'El estado WordPress cambió de forma inesperada durante la actualización.';
        }
        if ($protected_before['post_author'] !== (int) $post_after->post_author) {
            $result['errors'][] = 'El autor cambió de forma inesperada durante la actualización.';
        }
        if ($protected_before['thumbnail_id'] !== (int) get_post_thumbnail_id($post_id)) {
            $result['errors'][] = 'La imagen destacada cambió de forma inesperada durante la actualización.';
        }
        foreach ($protected_before['taxonomies'] as $taxonomy => $term_ids) {
            $after_ids = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'ids']);
            $before_clean = is_wp_error($term_ids) ? [] : array_map('intval', (array) $term_ids);
            $after_clean = is_wp_error($after_ids) ? [] : array_map('intval', (array) $after_ids);
            sort($before_clean);
            sort($after_clean);
            if ($before_clean !== $after_clean) {
                $result['errors'][] = 'La taxonomía ' . $taxonomy . ' cambió de forma inesperada durante la actualización.';
                break;
            }
        }
        if (!$result['content_preserved']) {
            $result['errors'][] = 'El contenido o extracto cambió de forma inesperada durante la actualización.';
        }

        $successful_updates = array_filter($result['updated_fields'], static fn(array $field): bool => !empty($field['success']));
        $result['publication_updated'] = !empty($successful_updates);
        if (empty($result['errors']) && $result['publication_updated']) {
            $result['status'] = 'success';
        } elseif ($result['publication_updated']) {
            $result['status'] = 'partial';
        }
        return $result;
    }

    private static function structural_field_labels(string $content_type): array {
        return $content_type === 'contest' ? self::contest_field_labels() : self::event_field_labels();
    }

    private static function write_structural_field(string $content_type, int $post_id, string $key, string $value): array {
        return $content_type === 'contest'
            ? self::write_contest_field($post_id, $key, $value)
            : self::write_event_field($post_id, $key, $value);
    }

    private static function event_field_labels(): array {
        return [
            'fecha_inicio' => 'Fecha de inicio',
            'fecha_fin' => 'Fecha de fin',
            'ciudad' => 'Ciudad',
            'pais' => 'País',
            'ubicacion' => 'Ubicación',
            'enlace_oficial' => 'Enlace oficial',
            'resumen_editorial' => 'Resumen editorial',
        ];
    }

    private static function contest_field_labels(): array {
        return [
            'fecha_inicio_convocatoria' => 'Fecha de inicio',
            'fecha_cierre_convocatoria' => 'Fecha de cierre',
            'fecha_premiacion_convocatoria' => 'Fecha de premiación',
            'enlace_oficial_convocatoria' => 'Enlace oficial de la convocatoria',
        ];
    }

    private static function build_identity_comparison(array $source, array $submitted): array {
        $identity = self::identity_proposal($source, $submitted);
        $rows = [];
        foreach (['title' => 'Título', 'slug' => 'Slug'] as $key => $label) {
            $current = (string) ($source[$key] ?? '');
            $proposed = (string) ($identity[$key] ?? $current);
            $status = $current === $proposed ? 'same' : 'change';
            $rows[] = [
                'key' => 'post_' . $key,
                'label' => $label,
                'current' => $current,
                'proposed' => $proposed,
                'current_display' => $current !== '' ? $current : 'Sin información',
                'proposed_display' => $proposed !== '' ? $proposed : 'Sin propuesta',
                'status' => $status,
                'status_label' => $status === 'change' ? 'Cambio propuesto' : 'Sin cambios',
            ];
        }
        return $rows;
    }

    private static function identity_proposal(array $source, array $submitted): array {
        $source_title = trim((string) ($source['title'] ?? ''));
        $source_slug = trim((string) ($source['slug'] ?? ''));
        $manual_title = trim((string) ($submitted['proposed_title'] ?? ''));
        $manual_slug = sanitize_title((string) ($submitted['proposed_slug'] ?? ''));
        $title = $manual_title !== '' ? $manual_title : $source_title;
        $slug = $manual_slug !== '' ? $manual_slug : sanitize_title($title !== '' ? $title : $source_slug);
        return ['title' => $title, 'slug' => $slug];
    }

    private static function write_event_field(int $post_id, string $key, string $value): array {
        $label = self::event_field_labels()[$key] ?? $key;
        $field_object = self::acf_field_object($post_id, $key);
        $storage_value = self::event_storage_value($post_id, $key, $value);
        $method = 'post_meta';
        $selector = $key;
        $field_type = (string) ($field_object['type'] ?? 'meta');
        $acf_field_available = !empty($field_object['key']) && function_exists('update_field');

        if ($acf_field_available) {
            $selector = (string) $field_object['key'];
            update_field($selector, $storage_value, $post_id);
            $method = 'ACF por clave interna';
        } else {
            update_post_meta($post_id, $key, $storage_value);
        }

        clean_post_cache($post_id);
        $success = self::event_field_matches($post_id, $key, $value);
        if (!$success && !$acf_field_available) {
            update_post_meta($post_id, $key, $storage_value);
            clean_post_cache($post_id);
            $success = self::event_field_matches($post_id, $key, $value);
        }

        $stored = self::event_field_raw_value($post_id, $key);
        $formatted = self::event_field_formatted_value($post_id, $key);
        return [
            'key' => $key,
            'label' => $label,
            'success' => $success,
            'method' => $method,
            'selector' => $selector,
            'field_type' => $field_type,
            'storage_value' => is_scalar($storage_value) ? (string) $storage_value : wp_json_encode($storage_value),
            'stored' => $stored,
            'formatted' => $formatted,
            'stored_display' => $key === 'pais'
                ? self::canonical_country($formatted !== '' ? $formatted : $stored, $post_id)
                : self::display_comparison_value($key, $formatted !== '' ? $formatted : $stored, ''),
        ];
    }

    private static function write_contest_field(int $post_id, string $key, string $value): array {
        $label = self::contest_field_labels()[$key] ?? $key;
        $field_object = self::acf_field_object($post_id, $key);
        $storage_value = self::contest_storage_value($key, $value);
        $method = 'post_meta';
        $selector = $key;
        $field_type = (string) ($field_object['type'] ?? 'meta');
        $acf_field_available = !empty($field_object['key']) && function_exists('update_field');

        if ($acf_field_available) {
            $selector = (string) $field_object['key'];
            update_field($selector, $storage_value, $post_id);
            $method = 'ACF por clave interna';
        } else {
            update_post_meta($post_id, $key, $storage_value);
        }

        clean_post_cache($post_id);
        $success = self::contest_field_matches($post_id, $key, $value);
        if (!$success && !$acf_field_available) {
            update_post_meta($post_id, $key, $storage_value);
            clean_post_cache($post_id);
            $success = self::contest_field_matches($post_id, $key, $value);
        }

        $stored = self::contest_field_raw_value($post_id, $key);
        $formatted = self::contest_field_formatted_value($post_id, $key);
        return [
            'key' => $key,
            'label' => $label,
            'success' => $success,
            'method' => $method,
            'selector' => $selector,
            'field_type' => $field_type,
            'storage_value' => is_scalar($storage_value) ? (string) $storage_value : wp_json_encode($storage_value),
            'stored' => $stored,
            'formatted' => $formatted,
            'stored_display' => self::display_comparison_value($key, $formatted !== '' ? $formatted : $stored, ''),
        ];
    }

    private static function contest_storage_value(string $key, string $value): string {
        if (str_starts_with($key, 'fecha_')) {
            $iso = self::date_to_input($value);
            return $iso !== '' ? str_replace('-', '', $iso) : '';
        }
        return trim($value);
    }

    private static function contest_field_matches(int $post_id, string $key, string $expected): bool {
        $stored = self::contest_field_raw_value($post_id, $key);
        if (str_starts_with($key, 'fecha_')) {
            return self::date_to_input($stored) === self::date_to_input($expected);
        }
        if ($key === 'enlace_oficial_convocatoria') {
            return untrailingslashit(trim($stored)) === untrailingslashit(trim($expected));
        }
        return trim($stored) === trim($expected);
    }

    private static function contest_field_raw_value(int $post_id, string $key): string {
        $field_object = self::acf_field_object($post_id, $key);
        if (function_exists('get_field') && !empty($field_object['key'])) {
            $value = get_field((string) $field_object['key'], $post_id, false);
            $scalar = self::acf_value_to_string($value);
            if ($scalar !== '') {
                return $scalar;
            }
        }
        $raw = get_post_meta($post_id, $key, true);
        return is_scalar($raw) ? (string) $raw : '';
    }

    private static function contest_field_formatted_value(int $post_id, string $key): string {
        $field_object = self::acf_field_object($post_id, $key);
        if (function_exists('get_field') && !empty($field_object['key'])) {
            return self::acf_value_to_string(get_field((string) $field_object['key'], $post_id, true));
        }
        return self::contest_field_raw_value($post_id, $key);
    }

    private static function event_storage_value(int $post_id, string $key, string $value) {
        if (in_array($key, ['fecha_inicio', 'fecha_fin'], true)) {
            $iso = self::date_to_input($value);
            return $iso !== '' ? str_replace('-', '', $iso) : '';
        }
        if ($key === 'destacado_home') {
            return !empty($value) ? '1' : '0';
        }
        if ($key === 'ciudad') {
            return self::canonical_city($value);
        }
        if ($key === 'pais') {
            return self::country_storage_value($post_id, $value);
        }
        return $value;
    }

    private static function event_field_matches(int $post_id, string $key, string $expected): bool {
        $stored = self::event_field_raw_value($post_id, $key);
        if (in_array($key, ['fecha_inicio', 'fecha_fin'], true)) {
            return self::date_to_input($stored) === self::date_to_input($expected);
        }
        if ($key === 'destacado_home') {
            return (!empty($stored) ? '1' : '0') === (!empty($expected) ? '1' : '0');
        }
        if ($key === 'ciudad') {
            return self::city_key($stored) === self::city_key($expected);
        }
        if ($key === 'pais') {
            $formatted = self::event_field_formatted_value($post_id, $key);
            $expected_key = self::country_key(self::canonical_country($expected, $post_id));
            $raw_ok = self::country_key(self::canonical_country($stored, $post_id)) === $expected_key;
            $field_object = self::acf_field_object($post_id, $key);
            if (!empty($field_object['key'])) {
                $formatted_ok = $formatted !== '' && self::country_key(self::canonical_country($formatted, $post_id)) === $expected_key;
                return $raw_ok && $formatted_ok;
            }
            return $raw_ok;
        }
        return trim($stored) === trim($expected);
    }

    private static function event_field_raw_value(int $post_id, string $key): string {
        $field_object = self::acf_field_object($post_id, $key);
        if (function_exists('get_field') && !empty($field_object['key'])) {
            $value = get_field((string) $field_object['key'], $post_id, false);
            $scalar = self::acf_value_to_string($value);
            if ($scalar !== '') {
                return $scalar;
            }
        }
        $raw = get_post_meta($post_id, $key, true);
        if (is_scalar($raw)) {
            return (string) $raw;
        }
        return '';
    }

    private static function event_field_formatted_value(int $post_id, string $key): string {
        $field_object = self::acf_field_object($post_id, $key);
        if (function_exists('get_field') && !empty($field_object['key'])) {
            return self::acf_value_to_string(get_field((string) $field_object['key'], $post_id, true));
        }
        return self::event_field_raw_value($post_id, $key);
    }

    private static function acf_value_to_string($value): string {
        if (is_object($value) && isset($value->name)) {
            return sanitize_text_field((string) $value->name);
        }
        if (is_array($value)) {
            if (isset($value['label']) || isset($value['value']) || isset($value['name'])) {
                return sanitize_text_field((string) ($value['label'] ?? $value['name'] ?? $value['value'] ?? ''));
            }
            $parts = [];
            foreach ($value as $item) {
                $part = self::acf_value_to_string($item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
            return implode(', ', array_values(array_unique($parts)));
        }
        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    private static function acf_field_object(int $post_id, string $field_name): array {
        if (function_exists('get_field_object')) {
            $object = get_field_object($field_name, $post_id > 0 ? $post_id : false, false, false);
            if (is_array($object) && !empty($object['key'])) {
                return $object;
            }
        }
        $selector = self::acf_field_selector($post_id, $field_name);
        if ($selector !== '' && function_exists('get_field_object')) {
            $object = get_field_object($selector, $post_id > 0 ? $post_id : false, false, false);
            if (is_array($object)) {
                return $object;
            }
        }
        if ($selector !== '' && function_exists('acf_get_field')) {
            $object = acf_get_field($selector);
            if (is_array($object)) {
                return $object;
            }
        }
        return [];
    }

    private static function acf_field_selector(int $post_id, string $field_name): string {
        if (function_exists('get_field_object')) {
            $object = get_field_object($field_name, $post_id, false, false);
            if (is_array($object) && !empty($object['key'])) {
                return (string) $object['key'];
            }
        }
        if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
            return '';
        }
        $groups = acf_get_field_groups(['post_id' => $post_id]);
        foreach ((array) $groups as $group) {
            $fields = acf_get_fields($group);
            $selector = self::find_acf_field_key((array) $fields, $field_name);
            if ($selector !== '') {
                return $selector;
            }
        }
        return '';
    }

    private static function find_acf_field_key(array $fields, string $field_name): string {
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $candidate_name = (string) ($field['name'] ?? '');
            $candidate_label = (string) ($field['label'] ?? '');
            $wanted = self::country_key($field_name);
            $name_key = self::country_key($candidate_name);
            $label_key = self::country_key($candidate_label);
            $country_alias = $field_name === 'pais' && in_array($name_key, ['pais', 'country', 'pais evento', 'country event'], true);
            $country_label = $field_name === 'pais' && in_array($label_key, ['pais', 'country'], true);
            if (($candidate_name === $field_name || $country_alias || $country_label || ($wanted !== '' && $wanted === $name_key)) && !empty($field['key'])) {
                return (string) $field['key'];
            }
            if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $found = self::find_acf_field_key($field['sub_fields'], $field_name);
                if ($found !== '') {
                    return $found;
                }
            }
        }
        return '';
    }

    private static function build_editorial_workflow_data(array $analysis, array $source): array {
        $submitted = isset($analysis['submitted']) && is_array($analysis['submitted']) ? $analysis['submitted'] : [];
        $source_read = isset($analysis['source_read']) && is_array($analysis['source_read']) ? $analysis['source_read'] : [];
        if (self::content_type_from_value((string) ($analysis['content_type'] ?? 'event')) === 'contest') {
            return self::build_contest_editorial_workflow_data($analysis, $source, $submitted, $source_read);
        }
        $post_id = (int) ($source['id'] ?? 0);
        $post = $post_id > 0 ? get_post($post_id) : null;
        $fields = isset($source['raw_fields']) && is_array($source['raw_fields']) ? $source['raw_fields'] : [];
        $title = trim((string) ($source['title'] ?? ''));
        $official_url = trim((string) ($submitted['new_source'] ?? $submitted['enlace_oficial'] ?? $fields['enlace_oficial'] ?? ''));
        $event_taxonomies = self::event_taxonomy_context($post_id);
        $event_category_details = self::event_editorial_category_details($post_id, $event_taxonomies);
        $event_editorial_category = (string) ($event_category_details['category'] ?? '');
        if ($event_editorial_category !== '') {
            $event_taxonomies = self::append_event_editorial_category_context($event_taxonomies, $event_editorial_category, (string) ($event_category_details['source'] ?? ''));
        }
        $event_taxonomy_terms = self::event_taxonomy_term_names($event_taxonomies);

        $fact_lines = [];
        $fact_lines[] = $title !== '' ? $title . ' corresponde a una edición recurrente de calendario que debe actualizarse editorialmente.' : 'Evento recurrente que debe actualizarse editorialmente.';
        if (!empty($submitted['fecha_inicio'])) {
            $date_text = self::format_ymd_for_display((string) $submitted['fecha_inicio']);
            if (!empty($submitted['fecha_fin']) && (string) $submitted['fecha_fin'] !== (string) $submitted['fecha_inicio']) {
                $date_text .= ' al ' . self::format_ymd_for_display((string) $submitted['fecha_fin']);
            }
            $fact_lines[] = 'Fechas confirmadas: ' . $date_text . '.';
        }
        $place = array_values(array_filter([
            trim((string) ($submitted['ubicacion'] ?? '')),
            trim((string) ($submitted['ciudad'] ?? '')),
            trim((string) ($submitted['pais'] ?? '')),
        ]));
        if (!empty($place)) {
            $fact_lines[] = 'Lugar confirmado: ' . implode(', ', $place) . '.';
        }
        if ($official_url !== '') {
            $fact_lines[] = 'Fuente oficial: ' . $official_url . '.';
        }
        $brief_fact = implode(' ', $fact_lines);

        $material_parts = [];
        if (!empty($submitted['new_information'])) {
            $material_parts[] = "## Información manual de la nueva edición\n" . trim((string) $submitted['new_information']);
        }
        if (!empty($source_read['page_title']) || !empty($source_read['excerpt'])) {
            $material_parts[] = "## Lectura técnica de la fuente oficial\nTítulo detectado: " . trim((string) ($source_read['page_title'] ?? '')) . "\n\n" . trim((string) ($source_read['excerpt'] ?? ''));
        } elseif (!empty($source_read['message'])) {
            $material_parts[] = "## Estado de lectura de la fuente\n" . trim((string) $source_read['message']);
        }
        if ($post instanceof WP_Post && trim((string) $post->post_content) !== '') {
            $material_parts[] = "## Contenido de la edición anterior para depuración factual\n" . trim((string) $post->post_content);
        }

        $editorial_angle = 'Redactar la nueva edición como pieza de Agenda para ideasDi. Priorizar datos útiles y verificados —fechas, ciudad, sede, formato y enlace oficial— y después explicar qué vale la pena mirar y por qué importa para la cultura del diseño. Eliminar referencias obsoletas de ediciones anteriores, no inventar agenda ni participantes y mantener tono editorial no comercial. La salida debe reemplazar el contenido del evento existente, no crear una entrada nueva.';
        $recipe = 'Leer ' . ($title !== '' ? $title : 'el evento') . ' desde su utilidad como agenda de diseño, observando contexto, programación y conversación cultural, sin convertir el texto en promoción ni conservar datos obsoletos.';

        $data = [
            'workflow_origin' => 'recurring_update',
            'recurring_analysis_id' => (string) ($analysis['analysis_id'] ?? ''),
            'recurring_target_post_id' => $post_id,
            'recurring_selected_post_id' => (int) ($analysis['selected_post_id'] ?? $post_id),
            'recurring_target_post_type' => 'evento',
            'recurring_target_content_type' => 'event',
            'recurring_target_signature' => self::source_signature($source),
            'recurring_target_identity_fingerprint' => self::target_post_fingerprint($post_id, 'event'),
            'recurring_target_immutable_fingerprint' => self::immutable_post_fingerprint($post_id),
            'recurring_target_entity_key' => self::event_entity_key($title),
            'recurring_target_season' => self::event_season_key($title),
            'recurring_target_title' => $title,
            'recurring_target_status' => (string) ($source['post_status'] ?? ''),
            'recurring_target_edit_link' => get_edit_post_link($post_id, 'raw') ?: '',
            'recurring_structural_applied_at' => (string) ($analysis['application']['applied_at'] ?? ''),
            'keyword' => $title,
            'entity' => self::event_entity_name($title),
            'official_source' => $official_url,
            'source_information_url' => $official_url,
            'piece_type' => 'Agenda',
            'category_id' => 0,
            'tag_ids' => [],
            'editorial_context' => 'event_calendar',
            'editorial_context_name' => 'Calendario de eventos',
            'wordpress_content_type' => 'Evento',
            'event_taxonomy_context' => $event_taxonomies,
            'event_taxonomy_term_names' => $event_taxonomy_terms,
            'event_editorial_category' => $event_editorial_category,
            'event_editorial_category_source' => (string) ($event_category_details['source'] ?? ''),
            'event_start_date' => (string) ($submitted['fecha_inicio'] ?? ''),
            'event_end_date' => (string) ($submitted['fecha_fin'] ?? ''),
            'brief_fact' => $brief_fact,
            'editorial_angle' => $editorial_angle,
            'priority_readings' => $recipe,
            'editorial_recipe' => $recipe,
            'temp_material_text' => implode("\n\n", array_filter($material_parts)),
            'temp_material_filename' => 'actualizacion-recurrente-' . $post_id . '.txt',
            'temp_material_origin' => 'recurring_update_internal',
            'base_article' => '',
            'editorial_result' => '',
            'seo_result' => '',
            'last_error' => '',
        ];
        if (class_exists('IDG_Editorial_Recipe_Builder')) {
            $built_recipe = IDG_Editorial_Recipe_Builder::build($data);
            $data['priority_readings'] = (string) ($built_recipe['recipe'] ?? $data['priority_readings']);
            $data['editorial_recipe'] = (string) ($built_recipe['recipe'] ?? $data['editorial_recipe']);
            $data['recipe_base'] = (string) ($built_recipe['base_recipe'] ?? $data['editorial_recipe']);
            $data['recipe_base_structure'] = $built_recipe;
            $data['recipe_technical_summary'] = (string) ($built_recipe['technical_summary'] ?? '');
        }
        if (class_exists('IDG_Internal_Links')) {
            $data['internal_links_structured'] = IDG_Internal_Links::automatic($data);
        }
        return $data;
    }

    private static function build_contest_editorial_workflow_data(array $analysis, array $source, array $submitted, array $source_read): array {
        $post_id = (int) ($source['id'] ?? 0);
        $post = $post_id > 0 ? get_post($post_id) : null;
        $fields = isset($source['raw_fields']) && is_array($source['raw_fields']) ? $source['raw_fields'] : [];
        $title = trim((string) ($source['title'] ?? ''));
        $official_url = trim((string) ($submitted['new_source'] ?? $submitted['enlace_oficial_convocatoria'] ?? $fields['enlace_oficial_convocatoria'] ?? ''));
        $tag_ids = wp_get_post_tags($post_id, ['fields' => 'ids']);
        $tag_ids = is_wp_error($tag_ids) ? [] : array_values(array_map('intval', (array) $tag_ids));

        $fact_lines = [];
        $fact_lines[] = $title !== ''
            ? $title . ' corresponde a una convocatoria recurrente que debe actualizarse editorialmente sobre el mismo artículo.'
            : 'Concurso o convocatoria recurrente que debe actualizarse editorialmente sobre el mismo artículo.';
        if (!empty($submitted['fecha_inicio_convocatoria'])) {
            $fact_lines[] = 'Apertura confirmada: ' . self::format_ymd_for_display((string) $submitted['fecha_inicio_convocatoria']) . '.';
        }
        if (!empty($submitted['fecha_cierre_convocatoria'])) {
            $fact_lines[] = 'Cierre confirmado: ' . self::format_ymd_for_display((string) $submitted['fecha_cierre_convocatoria']) . '.';
        }
        if (!empty($submitted['fecha_premiacion_convocatoria'])) {
            $fact_lines[] = 'Premiación confirmada: ' . self::format_ymd_for_display((string) $submitted['fecha_premiacion_convocatoria']) . '.';
        }
        if ($official_url !== '') {
            $fact_lines[] = 'Fuente oficial: ' . $official_url . '.';
        }
        $brief_fact = implode(' ', $fact_lines);

        $material_parts = [];
        if (!empty($submitted['new_information'])) {
            $material_parts[] = "## Información manual de la nueva convocatoria
" . trim((string) $submitted['new_information']);
        }
        if (!empty($source_read['page_title']) || !empty($source_read['excerpt'])) {
            $material_parts[] = "## Lectura técnica de la fuente oficial
Título detectado: " . trim((string) ($source_read['page_title'] ?? '')) . "

" . trim((string) ($source_read['excerpt'] ?? ''));
        } elseif (!empty($source_read['message'])) {
            $material_parts[] = "## Estado de lectura de la fuente
" . trim((string) $source_read['message']);
        }
        if ($post instanceof WP_Post && trim((string) $post->post_content) !== '') {
            $material_parts[] = "## Contenido de la edición anterior para depuración factual
" . trim((string) $post->post_content);
        }

        $editorial_angle = 'Actualizar el concurso o convocatoria sobre el mismo artículo de ideasDi. Priorizar propósito, categorías, fechas y premios confirmados; excluir requisitos, entregables y criterios técnicos del cuerpo público, remitiendo esos detalles a la web oficial. Eliminar referencias obsoletas de ediciones anteriores, mantener tono editorial no comercial y no prometer resultados. La salida debe reemplazar el contenido del artículo existente, no crear una entrada nueva.';
        $recipe = 'Leer ' . ($title !== '' ? $title : 'la convocatoria') . ' desde su utilidad para orientar participación, verificando propósito, disciplinas, fechas y premios sin convertir las bases completas en artículo ni conservar datos obsoletos.';

        $data = [
            'workflow_origin' => 'recurring_update',
            'recurring_analysis_id' => (string) ($analysis['analysis_id'] ?? ''),
            'recurring_target_post_id' => $post_id,
            'recurring_selected_post_id' => (int) ($analysis['selected_post_id'] ?? $post_id),
            'recurring_target_post_type' => 'post',
            'recurring_target_content_type' => 'contest',
            'recurring_target_signature' => self::source_signature($source),
            'recurring_target_identity_fingerprint' => self::target_post_fingerprint($post_id, 'contest'),
            'recurring_target_entity_key' => self::event_entity_key($title),
            'recurring_target_title' => $title,
            'recurring_target_status' => (string) ($source['post_status'] ?? ''),
            'recurring_target_edit_link' => get_edit_post_link($post_id, 'raw') ?: '',
            'recurring_structural_applied_at' => (string) ($analysis['application']['applied_at'] ?? ''),
            'recurring_contest_start_date' => (string) ($submitted['fecha_inicio_convocatoria'] ?? ''),
            'recurring_contest_deadline' => (string) ($submitted['fecha_cierre_convocatoria'] ?? ''),
            'recurring_contest_award_date' => (string) ($submitted['fecha_premiacion_convocatoria'] ?? ''),
            'keyword' => $title,
            'entity' => '',
            'official_source' => $official_url,
            'source_information_url' => $official_url,
            'piece_type' => 'Agenda',
            'category_id' => self::CONTEST_CATEGORY_ID,
            'tag_ids' => $tag_ids,
            'editorial_context' => 'contest_call',
            'editorial_context_name' => 'Concursos y convocatorias',
            'wordpress_content_type' => 'Entrada de concurso',
            'brief_fact' => $brief_fact,
            'editorial_angle' => $editorial_angle,
            'priority_readings' => $recipe,
            'editorial_recipe' => $recipe,
            'temp_material_text' => implode("

", array_filter($material_parts)),
            'temp_material_filename' => 'actualizacion-concurso-' . $post_id . '.txt',
            'temp_material_origin' => 'recurring_update_internal',
            'base_article' => '',
            'editorial_result' => '',
            'seo_result' => '',
            'last_error' => '',
        ];
        if (class_exists('IDG_Editorial_Recipe_Builder')) {
            $built_recipe = IDG_Editorial_Recipe_Builder::build($data);
            $data['priority_readings'] = (string) ($built_recipe['recipe'] ?? $data['priority_readings']);
            $data['editorial_recipe'] = (string) ($built_recipe['recipe'] ?? $data['editorial_recipe']);
            $data['recipe_base'] = (string) ($built_recipe['base_recipe'] ?? $data['editorial_recipe']);
            $data['recipe_base_structure'] = $built_recipe;
            $data['recipe_technical_summary'] = (string) ($built_recipe['technical_summary'] ?? '');
        }
        if (class_exists('IDG_Internal_Links')) {
            $data['internal_links_structured'] = IDG_Internal_Links::automatic($data);
        }
        return $data;
    }

    public static function agenda_categories(): array {
        return [
            'Arquitectura e interiores',
            'Diseño digital y 3D',
            'Diseño interdisciplinar',
            'Moda',
            'Movilidad y transporte',
            'Semana de diseño',
        ];
    }

    public static function normalize_agenda_category(string $value): string {
        $plain = function_exists('remove_accents') ? remove_accents(trim($value)) : trim($value);
        $plain = mb_strtolower((string) $plain);
        $plain = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $plain));
        $aliases = [
            'arquitectura e interiores' => 'Arquitectura e interiores',
            'arquitectura interiores' => 'Arquitectura e interiores',
            'arquitectura y interiores' => 'Arquitectura e interiores',
            'interior arquitectura' => 'Arquitectura e interiores',
            'diseno digital y 3d' => 'Diseño digital y 3D',
            'diseno digital 3d' => 'Diseño digital y 3D',
            'digital y 3d' => 'Diseño digital y 3D',
            'diseno interdisciplinar' => 'Diseño interdisciplinar',
            'interdisciplinar' => 'Diseño interdisciplinar',
            'moda' => 'Moda',
            'fashion' => 'Moda',
            'movilidad y transporte' => 'Movilidad y transporte',
            'movilidad transporte' => 'Movilidad y transporte',
            'transporte' => 'Movilidad y transporte',
            'semana de diseno' => 'Semana de diseño',
            'design week' => 'Semana de diseño',
        ];
        return $aliases[$plain] ?? '';
    }

    public static function event_editorial_category_details(int $post_id, array $taxonomy_context = []): array {
        foreach ($taxonomy_context as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ((array) ($row['terms'] ?? []) as $term) {
                $name = is_array($term) ? (string) ($term['name'] ?? '') : '';
                $category = self::normalize_agenda_category($name);
                if ($category !== '') {
                    return ['category' => $category, 'source' => 'taxonomy:' . sanitize_key((string) ($row['taxonomy'] ?? 'evento'))];
                }
            }
        }

        if (function_exists('get_field_objects')) {
            $objects = get_field_objects($post_id, false, true);
            if (is_array($objects)) {
                foreach ($objects as $object) {
                    if (!is_array($object)) {
                        continue;
                    }
                    $name = (string) ($object['name'] ?? '');
                    $label = (string) ($object['label'] ?? '');
                    $field_key = self::country_key($name . ' ' . $label);
                    if (!str_contains($field_key, 'categoria') && !str_contains($field_key, 'disciplina') && !str_contains($field_key, 'tipo evento')) {
                        continue;
                    }
                    foreach (self::event_category_candidates($object['value'] ?? null, (string) ($object['taxonomy'] ?? '')) as $candidate) {
                        $category = self::normalize_agenda_category($candidate);
                        if ($category !== '') {
                            return ['category' => $category, 'source' => 'acf:' . sanitize_key($name !== '' ? $name : $label)];
                        }
                    }
                }
            }
        }

        $common_fields = ['categoria_evento', 'categoria_eventos', 'categoria_de_evento', 'event_category', 'disciplina_evento', 'tipo_evento', 'categoria'];
        foreach ($common_fields as $field_name) {
            $values = [];
            if (function_exists('get_field')) {
                $values[] = get_field($field_name, $post_id);
            }
            $values[] = get_post_meta($post_id, $field_name, true);
            foreach ($values as $value) {
                foreach (self::event_category_candidates($value) as $candidate) {
                    $category = self::normalize_agenda_category($candidate);
                    if ($category !== '') {
                        return ['category' => $category, 'source' => 'field:' . $field_name];
                    }
                }
            }
        }
        return ['category' => '', 'source' => ''];
    }

    private static function event_category_candidates($value, string $taxonomy = ''): array {
        if ($value instanceof WP_Term) {
            return [(string) $value->name];
        }
        if (is_object($value) && isset($value->name)) {
            return [(string) $value->name];
        }
        if (is_numeric($value) && (int) $value > 0 && $taxonomy !== '' && taxonomy_exists($taxonomy)) {
            $term = get_term((int) $value, $taxonomy);
            return $term && !is_wp_error($term) ? [(string) $term->name] : [];
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                $out = array_merge($out, self::event_category_candidates($item, $taxonomy));
            }
            return array_values(array_unique(array_filter(array_map('strval', $out))));
        }
        return is_scalar($value) && trim((string) $value) !== '' ? [trim((string) $value)] : [];
    }

    private static function append_event_editorial_category_context(array $context, string $category, string $source): array {
        foreach ($context as $row) {
            foreach ((array) ($row['terms'] ?? []) as $term) {
                if (is_array($term) && self::normalize_agenda_category((string) ($term['name'] ?? '')) === $category) {
                    return $context;
                }
            }
        }
        $context[] = [
            'taxonomy' => 'idg_event_editorial_category',
            'label' => 'Categoría del evento',
            'source' => $source,
            'terms' => [[
                'term_id' => 0,
                'name' => $category,
                'slug' => sanitize_title($category),
            ]],
        ];
        return $context;
    }

    public static function event_taxonomy_context(int $post_id): array {
        if ($post_id <= 0 || !function_exists('get_object_taxonomies') || !function_exists('wp_get_object_terms')) {
            return [];
        }
        $objects = get_object_taxonomies('evento', 'objects');
        if (!is_array($objects)) {
            return [];
        }
        $context = [];
        foreach ($objects as $taxonomy => $object) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if ($taxonomy === '' || in_array($taxonomy, ['category', 'post_tag'], true)) {
                continue;
            }
            if (is_object($object) && isset($object->show_ui) && !$object->show_ui && isset($object->public) && !$object->public) {
                continue;
            }
            $terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'all']);
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            $term_rows = [];
            foreach ((array) $terms as $term) {
                if (!is_object($term)) {
                    continue;
                }
                $term_rows[] = [
                    'term_id' => isset($term->term_id) ? (int) $term->term_id : 0,
                    'name' => sanitize_text_field((string) ($term->name ?? '')),
                    'slug' => sanitize_title((string) ($term->slug ?? '')),
                ];
            }
            if (empty($term_rows)) {
                continue;
            }
            $label = $taxonomy;
            if (is_object($object) && !empty($object->labels->singular_name)) {
                $label = (string) $object->labels->singular_name;
            } elseif (is_object($object) && !empty($object->label)) {
                $label = (string) $object->label;
            }
            $context[] = [
                'taxonomy' => $taxonomy,
                'label' => sanitize_text_field($label),
                'terms' => $term_rows,
            ];
        }
        return $context;
    }

    private static function event_taxonomy_term_names(array $context): array {
        $names = [];
        foreach ($context as $row) {
            if (!is_array($row) || empty($row['terms']) || !is_array($row['terms'])) {
                continue;
            }
            foreach ($row['terms'] as $term) {
                $name = is_array($term) ? trim((string) ($term['name'] ?? '')) : '';
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }
        return array_values(array_unique($names));
    }

    private static function event_entity_name(string $title): string {
        $name = preg_replace('/\b(?:19|20)\d{2}\b/u', '', $title);
        $name = preg_replace('/\b(?:primavera[\s\/-]*verano|otoño[\s\/-]*invierno|otono[\s\/-]*invierno|spring[\s\/-]*summer|fall[\s\/-]*winter|autumn[\s\/-]*winter)\b/iu', '', (string) $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name), " -–—");
        return $name !== '' ? $name : trim($title);
    }

    private static function source_signature(array $source): string {
        $payload = [
            'id' => (int) ($source['id'] ?? 0),
            'title' => (string) ($source['title'] ?? ''),
            'slug' => (string) ($source['slug'] ?? ''),
            'post_status' => (string) ($source['post_status'] ?? ''),
            'post_date_gmt' => (string) ($source['post_date_gmt'] ?? ''),
            'post_modified_gmt' => (string) ($source['post_modified_gmt'] ?? ''),
            'guid' => (string) ($source['guid'] ?? ''),
            'raw_fields' => isset($source['raw_fields']) && is_array($source['raw_fields']) ? $source['raw_fields'] : [],
        ];
        return hash('sha256', wp_json_encode($payload));
    }

    public static function immutable_post_fingerprint(int $post_id): string {
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post instanceof WP_Post || (string) $post->post_type !== 'evento') {
            return '';
        }
        $payload = [
            'id' => (int) $post->ID,
            'post_type' => (string) $post->post_type,
        ];
        return hash('sha256', wp_json_encode($payload));
    }

    public static function target_post_fingerprint(int $post_id, string $content_type = ''): string {
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post instanceof WP_Post) {
            return '';
        }
        $resolved_content_type = $content_type !== ''
            ? self::content_type_from_value($content_type)
            : self::content_type_for_post_type((string) $post->post_type);
        if ($resolved_content_type === 'event' && (string) $post->post_type !== 'evento') {
            return '';
        }
        if ($resolved_content_type === 'contest' && ((string) $post->post_type !== 'post' || !has_category(self::CONTEST_CATEGORY_ID, $post))) {
            return '';
        }
        $payload = [
            'id' => (int) $post->ID,
            'post_type' => (string) $post->post_type,
            'content_type' => $resolved_content_type,
        ];
        return hash('sha256', wp_json_encode($payload));
    }

    /**
     * Huella mínima para confirmar la selección inicial de cualquier tipo admitido.
     *
     * Esta huella confirma que el formulario conserva el mismo ID y post_type
     * seleccionado. La aplicación y el workflow añaden después la huella protegida
     * específica del destino, incluida la pertenencia a la categoría de concursos.
     */
    private static function selected_source_fingerprint(array $source): string {
        $post_id = (int) ($source['id'] ?? 0);
        $post_type = (string) ($source['post_type'] ?? '');
        if ($post_id <= 0 || !in_array($post_type, ['evento', 'post'], true)) {
            return '';
        }
        $payload = [
            'id' => $post_id,
            'post_type' => $post_type,
        ];
        return hash('sha256', wp_json_encode($payload));
    }

    private static function selection_token(array $source): string {
        $post_id = (int) ($source['id'] ?? 0);
        $fingerprint = self::selected_source_fingerprint($source);
        if ($post_id <= 0 || $fingerprint === '') {
            return '';
        }
        $payload = get_current_user_id() . '|' . $post_id . '|' . $fingerprint;
        $secret = function_exists('wp_salt') ? wp_salt('nonce') : (defined('AUTH_SALT') ? AUTH_SALT : 'idg-recurring-selection');
        return hash_hmac('sha256', $payload, $secret);
    }

    private static function event_result_summary(int $post_id): string {
        $fields = self::event_fields($post_id);
        $parts = [];
        $start = self::format_ymd_for_display((string) ($fields['fecha_inicio'] ?? ''));
        $end = self::format_ymd_for_display((string) ($fields['fecha_fin'] ?? ''));
        if ($start !== '' || $end !== '') {
            $parts[] = 'Fechas: ' . ($start !== '' ? $start : '—') . ' a ' . ($end !== '' ? $end : '—');
        }
        $place = array_values(array_filter([
            trim((string) ($fields['ciudad'] ?? '')),
            trim((string) ($fields['pais'] ?? '')),
        ]));
        if (!empty($place)) {
            $parts[] = implode(', ', $place);
        }
        return implode(' · ', $parts);
    }

    private static function event_season_key(string $value): string {
        $plain = function_exists('remove_accents') ? remove_accents($value) : $value;
        $plain = mb_strtolower((string) $plain);
        if (preg_match('/\b(?:primavera[\s\/-]*verano|spring[\s\/-]*summer|ss)\b/u', $plain)) {
            return 'spring_summer';
        }
        if (preg_match('/\b(?:otono[\s\/-]*invierno|fall[\s\/-]*winter|autumn[\s\/-]*winter|aw|fw)\b/u', $plain)) {
            return 'autumn_winter';
        }
        return '';
    }

    private static function event_entity_key(string $value): string {
        $value = preg_replace('/\b(?:19|20)\d{2}(?:\s*[\/-]\s*\d{2,4})?\b/u', ' ', $value);
        $value = preg_replace('/\b(?:primavera[\s\/-]*verano|otono[\s\/-]*invierno|otoño[\s\/-]*invierno|spring[\s\/-]*summer|fall[\s\/-]*winter|autumn[\s\/-]*winter|ss|aw|fw)\b/iu', ' ', (string) $value);
        $plain = function_exists('remove_accents') ? remove_accents((string) $value) : (string) $value;
        $plain = mb_strtolower($plain);
        $plain = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $plain);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $plain));
    }

    public static function validate_editorial_identity(int $post_id, array $workflow, string $generated_title): array {
        $post = $post_id > 0 ? get_post($post_id) : null;
        $target_post_type = (string) ($workflow['recurring_target_post_type'] ?? '');
        $content_type = self::content_type_for_post_type($target_post_type);
        $is_valid_target = $post instanceof WP_Post
            && $content_type !== ''
            && (string) $post->post_type === $target_post_type
            && ($content_type !== 'contest' || has_category(self::CONTEST_CATEGORY_ID, $post));
        if (!$is_valid_target) {
            return ['ok' => false, 'message' => 'La publicación de destino no existe, cambió de tipo o dejó de pertenecer a la categoría esperada.'];
        }
        $selected_id = (int) ($workflow['recurring_selected_post_id'] ?? $workflow['recurring_target_post_id'] ?? 0);
        if ($selected_id !== $post_id) {
            return ['ok' => false, 'message' => 'El ID seleccionado originalmente no coincide con la publicación de destino. La operación fue bloqueada.'];
        }
        $expected_fingerprint = (string) ($workflow['recurring_target_identity_fingerprint'] ?? '');
        if ($expected_fingerprint === '' && $content_type === 'event') {
            $expected_fingerprint = (string) ($workflow['recurring_target_immutable_fingerprint'] ?? '');
        }
        $current_fingerprint = self::target_post_fingerprint($post_id, $content_type);
        if ($expected_fingerprint === '' || $current_fingerprint === '' || !hash_equals($expected_fingerprint, $current_fingerprint)) {
            return ['ok' => false, 'message' => 'La identidad de la publicación no coincide con el encargo. Vuelve a seleccionarla por ID exacto.'];
        }

        $target_title = (string) $post->post_title;
        $keyword = (string) ($workflow['keyword'] ?? '');
        $warnings = [];
        $target_entity = self::event_entity_key($target_title);
        $keyword_entity = self::event_entity_key($keyword);
        $generated_entity = self::event_entity_key($generated_title);
        if ($target_entity !== '' && $keyword_entity !== '' && $target_entity !== $keyword_entity) {
            $warnings[] = 'La keyword usa una formulación distinta al título almacenado; se conserva porque el destino está protegido por ID y el H1 se valida contra la keyword del workflow.';
        }
        if ($target_entity !== '' && $generated_entity !== '' && $target_entity !== $generated_entity) {
            $warnings[] = 'El H1 usa una formulación distinta al título almacenado; si supera las validaciones editoriales y de año, se aplicará como título final de la misma publicación sin cambiar su ID.';
        }
        if ($content_type === 'event') {
            $target_season = self::event_season_key($target_title);
            foreach (['keyword' => $keyword, 'H1' => $generated_title] as $label => $candidate) {
                $candidate_season = self::event_season_key((string) $candidate);
                if ($target_season !== '' && $candidate_season !== '' && $target_season !== $candidate_season) {
                    $warnings[] = 'La temporada del ' . $label . ' difiere del título almacenado. Revisar antes de publicar; la fecha y el nombre editorial pueden pertenecer a años distintos.';
                }
            }
        }
        $label = $content_type === 'contest' ? 'concurso o convocatoria' : 'evento';
        return [
            'ok' => true,
            'message' => 'Destino editorial confirmado para el ' . $label . ' ID ' . $post_id . '.',
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private static function content_type_from_value(string $value): string {
        return sanitize_key($value) === 'contest' ? 'contest' : 'event';
    }

    private static function content_type_for_post_type(string $post_type): string {
        if ($post_type === 'evento') {
            return 'event';
        }
        if ($post_type === 'post') {
            return 'contest';
        }
        return '';
    }

    private static function search_publications(string $content_type, string $search): array {
        $search = trim($search);
        $args = self::base_search_args($content_type);

        if (ctype_digit($search)) {
            $args['p'] = absint($search);
            return self::query_items($args);
        }

        $slug = sanitize_title($search);
        if ($slug !== '') {
            $exact_args = $args;
            $exact_args['name'] = $slug;
            $exact_args['posts_per_page'] = 20;
            $exact = self::query_items($exact_args);
            if (!empty($exact)) {
                return $exact;
            }
        }

        $args['s'] = $search;
        return self::query_items($args);
    }

    private static function base_search_args(string $content_type): array {
        $args = [
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'suppress_filters' => false,
        ];
        if ($content_type === 'event') {
            $args['post_type'] = 'evento';
        } else {
            $args['post_type'] = 'post';
            $args['cat'] = self::CONTEST_CATEGORY_ID;
        }
        return $args;
    }

    private static function query_items(array $args): array {
        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'slug' => (string) $post->post_name,
                'date' => get_the_date('d/m/Y', $post),
                'modified' => get_the_modified_date('d/m/Y H:i', $post),
                'status_label' => self::status_label((string) $post->post_status),
                'event_summary' => (string) (($post->post_type === 'evento') ? self::event_result_summary((int) $post->ID) : ''),
            ];
        }
        wp_reset_postdata();
        return $items;
    }

    private static function load_source(string $content_type, int $post_id): ?array {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || !current_user_can('edit_post', $post_id)) {
            return null;
        }
        if ($content_type === 'event' && $post->post_type !== 'evento') {
            return null;
        }
        if ($content_type === 'contest' && ($post->post_type !== 'post' || !has_category(self::CONTEST_CATEGORY_ID, $post))) {
            return null;
        }

        $raw_fields = $content_type === 'event' ? self::event_fields($post_id) : self::contest_fields($post_id);
        if ($content_type === 'event') {
            $display_fields = [
                'Fecha de inicio' => self::format_ymd_for_display((string) ($raw_fields['fecha_inicio'] ?? '')),
                'Fecha de fin' => self::format_ymd_for_display((string) ($raw_fields['fecha_fin'] ?? '')),
                'Ciudad' => (string) ($raw_fields['ciudad'] ?? ''),
                'País' => (string) ($raw_fields['pais'] ?? ''),
                'Ubicación' => (string) ($raw_fields['ubicacion'] ?? ''),
                'Enlace oficial' => (string) ($raw_fields['enlace_oficial'] ?? ''),
                'Resumen editorial' => (string) ($raw_fields['resumen_editorial'] ?? ''),
            ];
        } else {
            $display_fields = [
                'Fecha de inicio' => self::format_ymd_for_display((string) ($raw_fields['fecha_inicio_convocatoria'] ?? '')),
                'Fecha de cierre' => self::format_ymd_for_display((string) ($raw_fields['fecha_cierre_convocatoria'] ?? '')),
                'Fecha de premiación' => self::format_ymd_for_display((string) ($raw_fields['fecha_premiacion_convocatoria'] ?? '')),
                'Enlace oficial' => (string) ($raw_fields['enlace_oficial_convocatoria'] ?? ''),
                'Etiquetas' => implode(', ', wp_get_post_tags($post_id, ['fields' => 'names'])),
            ];
        }

        return [
            'id' => $post_id,
            'title' => (string) $post->post_title,
            'slug' => (string) $post->post_name,
            'post_type' => (string) $post->post_type,
            'post_status' => (string) $post->post_status,
            'post_date_gmt' => (string) $post->post_date_gmt,
            'post_modified_gmt' => (string) $post->post_modified_gmt,
            'guid' => (string) $post->guid,
            'status_label' => self::status_label((string) $post->post_status),
            'edit_url' => get_edit_post_link($post_id, 'raw') ?: '',
            'permalink' => get_permalink($post_id) ?: '',
            'fields' => $display_fields,
            'raw_fields' => $raw_fields,
        ];
    }

    private static function source_snapshot(array $source): array {
        return [
            'id' => (int) ($source['id'] ?? 0),
            'title' => (string) ($source['title'] ?? ''),
            'slug' => (string) ($source['slug'] ?? ''),
            'post_type' => (string) ($source['post_type'] ?? ''),
            'post_status' => (string) ($source['post_status'] ?? ''),
            'post_modified_gmt' => (string) ($source['post_modified_gmt'] ?? ''),
            'status_label' => (string) ($source['status_label'] ?? ''),
            'permalink' => (string) ($source['permalink'] ?? ''),
            'fields' => isset($source['fields']) && is_array($source['fields']) ? $source['fields'] : [],
            'raw_fields' => isset($source['raw_fields']) && is_array($source['raw_fields']) ? $source['raw_fields'] : [],
        ];
    }

    private static function event_fields(int $post_id): array {
        $keys = ['fecha_inicio', 'fecha_fin', 'ciudad', 'pais', 'ubicacion', 'enlace_oficial', 'destacado_home', 'resumen_editorial'];
        $fields = [];
        foreach ($keys as $key) {
            $raw = get_post_meta($post_id, $key, true);
            if (in_array($key, ['fecha_inicio', 'fecha_fin'], true) && is_scalar($raw) && (string) $raw !== '') {
                $value = (string) $raw;
            } elseif ($key === 'pais') {
                $value = self::event_field_formatted_value($post_id, $key);
            } else {
                $value = function_exists('get_field') ? get_field($key, $post_id) : $raw;
            }
            if (is_array($value)) {
                $value = (string) ($value['label'] ?? $value['value'] ?? $value['url'] ?? '');
            }
            if (($value === '' || $value === null) && is_scalar($raw)) {
                $value = (string) $raw;
            }
            $fields[$key] = is_scalar($value) ? (string) $value : '';
        }
        return $fields;
    }

    private static function contest_fields(int $post_id): array {
        $keys = ['fecha_inicio_convocatoria', 'fecha_cierre_convocatoria', 'fecha_premiacion_convocatoria', 'enlace_oficial_convocatoria'];
        $fields = [];
        foreach ($keys as $key) {
            $value = function_exists('get_field') ? get_field($key, $post_id) : get_post_meta($post_id, $key, true);
            $fields[$key] = is_scalar($value) ? (string) $value : '';
        }
        return $fields;
    }

    private static function collect_submitted_data(string $content_type): array {
        $mode = isset($_POST['update_mode']) ? sanitize_key((string) wp_unslash($_POST['update_mode'])) : '';
        $data = [
            'update_mode' => in_array($mode, ['update_existing', 'create_new'], true) ? $mode : '',
            'new_source' => isset($_POST['new_source']) ? esc_url_raw((string) wp_unslash($_POST['new_source'])) : '',
            'new_information' => isset($_POST['new_information']) ? sanitize_textarea_field((string) wp_unslash($_POST['new_information'])) : '',
        ];

        if ($content_type === 'event') {
            $data = array_merge($data, [
                'fecha_inicio' => self::posted_text('recurring_fecha_inicio'),
                'fecha_fin' => self::posted_text('recurring_fecha_fin'),
                'ciudad' => self::canonical_city(self::posted_text('recurring_ciudad')),
                'pais' => self::posted_text('recurring_pais'),
                'ubicacion' => self::posted_text('recurring_ubicacion'),
                'enlace_oficial' => self::posted_url('recurring_enlace_oficial'),
                'resumen_editorial' => self::posted_textarea('recurring_resumen_editorial'),
                'register_new_city' => !empty($_POST['register_new_city']) ? '1' : '0',
                'register_new_country' => !empty($_POST['register_new_country']) ? '1' : '0',
            ]);
        } else {
            $data = array_merge($data, [
                'fecha_inicio_convocatoria' => self::posted_text('recurring_fecha_inicio_convocatoria'),
                'fecha_cierre_convocatoria' => self::posted_text('recurring_fecha_cierre_convocatoria'),
                'fecha_premiacion_convocatoria' => self::posted_text('recurring_fecha_premiacion_convocatoria'),
                'enlace_oficial_convocatoria' => self::posted_url('recurring_enlace_oficial_convocatoria'),
            ]);
        }
        return $data;
    }

    private static function posted_text(string $key): string {
        return isset($_POST[$key]) ? sanitize_text_field((string) wp_unslash($_POST[$key])) : '';
    }

    private static function posted_textarea(string $key): string {
        return isset($_POST[$key]) ? sanitize_textarea_field((string) wp_unslash($_POST[$key])) : '';
    }

    private static function posted_url(string $key): string {
        return isset($_POST[$key]) ? esc_url_raw((string) wp_unslash($_POST[$key])) : '';
    }

    private static function build_comparison(string $content_type, array $current, array $submitted): array {
        $map = $content_type === 'event'
            ? [
                'fecha_inicio' => 'Fecha de inicio',
                'fecha_fin' => 'Fecha de fin',
                'ciudad' => 'Ciudad',
                'pais' => 'País',
                'ubicacion' => 'Ubicación',
                'enlace_oficial' => 'Enlace oficial',
                'resumen_editorial' => 'Resumen editorial',
            ]
            : [
                'fecha_inicio_convocatoria' => 'Fecha de inicio',
                'fecha_cierre_convocatoria' => 'Fecha de cierre',
                'fecha_premiacion_convocatoria' => 'Fecha de premiación',
                'enlace_oficial_convocatoria' => 'Enlace oficial de la convocatoria',
            ];

        $rows = [];
        foreach ($map as $key => $label) {
            $current_value = (string) ($current[$key] ?? '');
            $proposed_value = (string) ($submitted[$key] ?? '');
            if ($key === 'ciudad') {
                $current_value = self::canonical_city($current_value);
                $proposed_value = self::canonical_city($proposed_value);
            }
            if ($key === 'pais') {
                $current_value = self::canonical_country($current_value);
                $proposed_value = self::canonical_country($proposed_value);
            }
            if ($key === 'destacado_home') {
                $current_value = !empty($current_value) ? '1' : '0';
                $proposed_value = !empty($proposed_value) ? '1' : '0';
            }
            $status = 'not_proposed';
            if ($proposed_value !== '') {
                $status = self::comparable_value($key, $current_value) === self::comparable_value($key, $proposed_value) ? 'same' : 'change';
            }
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'current' => $current_value,
                'proposed' => $proposed_value,
                'current_display' => self::display_comparison_value($key, $current_value, 'Sin información'),
                'proposed_display' => self::display_comparison_value($key, $proposed_value, 'Sin propuesta'),
                'status' => $status,
                'status_label' => $status === 'change' ? 'Cambio propuesto' : ($status === 'same' ? 'Sin cambios' : 'Sin propuesta'),
            ];
        }
        return $rows;
    }

    private static function comparable_value(string $key, string $value): string {
        if (str_contains($key, 'fecha_')) {
            return self::date_to_input($value);
        }
        if ($key === 'ciudad') {
            return self::city_key($value);
        }
        if ($key === 'pais') {
            return self::country_key(self::canonical_country($value));
        }
        return trim(mb_strtolower(function_exists('remove_accents') ? remove_accents($value) : $value));
    }

    private static function display_comparison_value(string $key, string $value, string $empty_label): string {
        if ($value === '') {
            return $empty_label;
        }
        if ($key === 'destacado_home') {
            return !empty($value) ? 'Sí' : 'No';
        }
        if (str_contains($key, 'fecha_')) {
            return self::format_ymd_for_display($value);
        }
        return $value;
    }

    private static function read_external_source(string $url): array {
        if ($url === '') {
            return self::empty_source_read('No se indicó una URL nueva. El análisis se realizó únicamente con la información manual.');
        }

        $response = wp_safe_remote_get($url, [
            'timeout' => 12,
            'redirection' => 3,
            'limit_response_size' => 350000,
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.5',
                'User-Agent' => 'ideasDi-Gerizim/' . IDG_VERSION . '; ' . home_url('/'),
            ],
        ]);

        if (is_wp_error($response)) {
            return [
                'status' => 'request_error',
                'label' => 'Error de conexión',
                'http_code' => 0,
                'url' => $url,
                'message' => 'No se pudo leer la fuente externa: ' . $response->get_error_message() . ' Se conservó la información manual.',
                'page_title' => '',
                'excerpt' => '',
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 403) {
            return [
                'status' => 'http_403',
                'label' => 'Acceso restringido por la fuente',
                'http_code' => 403,
                'url' => $url,
                'message' => 'La fuente respondió HTTP 403. El análisis continuó con la información manual sin mostrar una página Forbidden.',
                'page_title' => '',
                'excerpt' => '',
            ];
        }

        if ($code < 200 || $code >= 300) {
            return [
                'status' => 'http_error',
                'label' => 'Respuesta HTTP no utilizable',
                'http_code' => $code,
                'url' => $url,
                'message' => 'La fuente respondió HTTP ' . $code . '. El análisis continuó con la información manual.',
                'page_title' => '',
                'excerpt' => '',
            ];
        }

        $body = (string) wp_remote_retrieve_body($response);
        $title = '';
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/isu', $body, $match)) {
            $title = trim(wp_strip_all_tags(html_entity_decode((string) $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
        $clean = preg_replace('/<(script|style|noscript|svg)\b[^>]*>.*?<\/\1>/isu', ' ', $body);
        $clean = html_entity_decode(wp_strip_all_tags((string) $clean), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $clean = trim((string) preg_replace('/\s+/u', ' ', $clean));

        return [
            'status' => 'read_ok',
            'label' => 'Fuente leída',
            'http_code' => $code,
            'url' => $url,
            'message' => 'La fuente respondió correctamente. La lectura se adjuntó como apoyo técnico al expediente; no se generó contenido ni se llamó OpenAI.',
            'page_title' => mb_substr($title, 0, 300),
            'excerpt' => mb_substr($clean, 0, 3500),
        ];
    }

    private static function empty_source_read(string $message): array {
        return [
            'status' => 'manual_only',
            'label' => 'Solo información manual',
            'http_code' => 0,
            'url' => '',
            'message' => $message,
            'page_title' => '',
            'excerpt' => '',
        ];
    }

    private static function existing_cities(): array {
        $cities = [];
        $post_ids = get_posts([
            'post_type' => 'evento',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);
        foreach ((array) $post_ids as $post_id) {
            $value = function_exists('get_field') ? get_field('ciudad', (int) $post_id) : get_post_meta((int) $post_id, 'ciudad', true);
            if (is_array($value)) {
                $value = (string) ($value['label'] ?? $value['value'] ?? '');
            }
            if (is_scalar($value)) {
                $cities[] = (string) $value;
            }
        }
        $registered = get_option(self::CITY_REGISTRY_OPTION, []);
        if (is_array($registered)) {
            $cities = array_merge($cities, $registered);
        }
        return self::deduplicate_cities($cities);
    }

    private static function deduplicate_cities(array $cities): array {
        $unique = [];
        foreach ($cities as $city) {
            $canonical = self::canonical_city((string) $city);
            $key = self::city_key($canonical);
            if ($canonical === '' || $key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $canonical;
        }
        uasort($unique, static function (string $a, string $b): int {
            $a_sort = function_exists('remove_accents') ? remove_accents($a) : $a;
            $b_sort = function_exists('remove_accents') ? remove_accents($b) : $b;
            return strcasecmp($a_sort, $b_sort);
        });
        return array_values($unique);
    }

    private static function canonical_city(string $city): string {
        $city = sanitize_text_field(trim((string) preg_replace('/\s+/u', ' ', $city)));
        $key = self::city_key($city);
        if (in_array($key, ['milan', 'milano'], true)) {
            return 'Milán';
        }
        return $city;
    }

    private static function city_key(string $city): string {
        $city = trim($city);
        $plain = function_exists('remove_accents') ? remove_accents($city) : $city;
        $plain = mb_strtolower((string) $plain);
        $plain = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $plain);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $plain));
    }

    private static function register_city(string $city): array {
        $canonical = self::canonical_city($city);
        $known = self::existing_cities();
        $known_keys = array_map([self::class, 'city_key'], $known);
        $key = self::city_key($canonical);
        if ($canonical === '' || $key === '') {
            return ['city' => '', 'registered' => false, 'already_known' => false];
        }
        if (in_array($key, $known_keys, true)) {
            return ['city' => $canonical, 'registered' => false, 'already_known' => true];
        }
        $registered = get_option(self::CITY_REGISTRY_OPTION, []);
        $registered = is_array($registered) ? $registered : [];
        $registered[] = $canonical;
        update_option(self::CITY_REGISTRY_OPTION, self::deduplicate_cities($registered), false);
        return ['city' => $canonical, 'registered' => true, 'already_known' => false];
    }

    private static function existing_countries(int $post_id = 0): array {
        $countries = [];
        $post_ids = get_posts([
            'post_type' => 'evento',
            'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);
        foreach ((array) $post_ids as $event_id) {
            $value = self::event_field_formatted_value((int) $event_id, 'pais');
            if (is_array($value)) {
                $value = (string) ($value['label'] ?? $value['value'] ?? '');
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $countries[] = (string) $value;
            }
        }
        foreach (self::acf_country_choices($post_id) as $key => $label) {
            $countries[] = (string) $label;
        }
        return self::deduplicate_countries($countries, $post_id);
    }

    private static function deduplicate_countries(array $countries, int $post_id = 0): array {
        $unique = [];
        foreach ($countries as $country) {
            $canonical = self::canonical_country((string) $country, $post_id);
            $key = self::country_key($canonical);
            if ($canonical === '' || $key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $canonical;
        }
        uasort($unique, static function (string $a, string $b): int {
            $a_sort = function_exists('remove_accents') ? remove_accents($a) : $a;
            $b_sort = function_exists('remove_accents') ? remove_accents($b) : $b;
            return strcasecmp($a_sort, $b_sort);
        });
        return array_values($unique);
    }

    private static function canonical_country(string $country, int $post_id = 0): string {
        $country = sanitize_text_field(trim((string) preg_replace('/\s+/u', ' ', $country)));
        if ($country === '') {
            return '';
        }
        $key = self::country_key($country);
        foreach (self::acf_country_choices($post_id) as $choice_key => $label) {
            if ($key === self::country_key((string) $choice_key) || $key === self::country_key((string) $label)) {
                return (string) $label;
            }
        }
        $aliases = [
            'spain' => 'España', 'espana' => 'España',
            'denmark' => 'Dinamarca', 'danmark' => 'Dinamarca', 'dinamarca' => 'Dinamarca',
            'italy' => 'Italia', 'italia' => 'Italia',
            'france' => 'Francia', 'francia' => 'Francia',
            'germany' => 'Alemania', 'deutschland' => 'Alemania', 'alemania' => 'Alemania',
            'united states' => 'Estados Unidos', 'united states of america' => 'Estados Unidos', 'usa' => 'Estados Unidos', 'us' => 'Estados Unidos', 'ee uu' => 'Estados Unidos', 'estados unidos' => 'Estados Unidos',
            'united kingdom' => 'Reino Unido', 'uk' => 'Reino Unido', 'great britain' => 'Reino Unido', 'reino unido' => 'Reino Unido',
            'brazil' => 'Brasil', 'brasil' => 'Brasil',
            'mexico' => 'México',
            'colombia' => 'Colombia', 'argentina' => 'Argentina', 'chile' => 'Chile', 'peru' => 'Perú',
            'japan' => 'Japón', 'japon' => 'Japón', 'china' => 'China',
            'south korea' => 'Corea del Sur', 'korea' => 'Corea del Sur', 'corea del sur' => 'Corea del Sur',
        ];
        if (isset($aliases[$key])) {
            return $aliases[$key];
        }
        return $country;
    }

    private static function country_key(string $country): string {
        $plain = function_exists('remove_accents') ? remove_accents(trim($country)) : trim($country);
        $plain = mb_strtolower((string) $plain);
        $plain = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $plain);
        return trim((string) preg_replace('/\s+/u', ' ', (string) $plain));
    }

    private static function acf_country_choices(int $post_id): array {
        $object = self::acf_field_object($post_id, 'pais');
        $choices = [];
        if (!empty($object['choices']) && is_array($object['choices'])) {
            foreach ($object['choices'] as $key => $label) {
                $choices[(string) $key] = (string) $label;
            }
        }
        foreach (self::registered_country_choices() as $key => $label) {
            if (!array_key_exists($key, $choices)) {
                $choices[$key] = $label;
            }
        }
        return $choices;
    }

    private static function registered_country_choices(): array {
        $registered = get_option(self::COUNTRY_REGISTRY_OPTION, []);
        if (!is_array($registered)) {
            return [];
        }
        $choices = [];
        foreach ($registered as $key => $label) {
            $key = sanitize_key((string) $key);
            $label = sanitize_text_field((string) $label);
            if ($key !== '' && $label !== '') {
                $choices[$key] = $label;
            }
        }
        return $choices;
    }

    private static function register_country(int $post_id, string $country): array {
        $canonical = self::canonical_country($country, $post_id);
        if ($canonical === '') {
            return ['country' => '', 'storage_key' => '', 'registered' => false, 'already_known' => false];
        }
        if (self::country_is_valid_for_field($post_id, $canonical)) {
            $existing_value = self::country_storage_value($post_id, $canonical);
            return ['country' => $canonical, 'storage_key' => is_scalar($existing_value) ? (string) $existing_value : '', 'registered' => false, 'already_known' => true];
        }
        $registered = self::registered_country_choices();
        $storage_key = self::new_country_storage_key($canonical, array_merge(self::acf_country_choices($post_id), $registered));
        if ($storage_key === '') {
            return ['country' => $canonical, 'storage_key' => '', 'registered' => false, 'already_known' => false];
        }
        $registered[$storage_key] = $canonical;
        update_option(self::COUNTRY_REGISTRY_OPTION, $registered, false);
        return ['country' => $canonical, 'storage_key' => $storage_key, 'registered' => true, 'already_known' => false];
    }

    private static function new_country_storage_key(string $country, array $existing): string {
        $iso = [
            'dinamarca' => 'dk', 'denmark' => 'dk', 'espana' => 'es', 'spain' => 'es',
            'estados unidos' => 'us', 'united states' => 'us', 'italia' => 'it', 'italy' => 'it',
            'francia' => 'fr', 'france' => 'fr', 'alemania' => 'de', 'germany' => 'de',
            'reino unido' => 'gb', 'united kingdom' => 'gb', 'colombia' => 'co', 'argentina' => 'ar',
            'mexico' => 'mx', 'brasil' => 'br', 'brazil' => 'br', 'chile' => 'cl', 'peru' => 'pe',
            'japon' => 'jp', 'japan' => 'jp', 'china' => 'cn', 'corea del sur' => 'kr',
            'suecia' => 'se', 'sweden' => 'se', 'noruega' => 'no', 'norway' => 'no',
            'finlandia' => 'fi', 'finland' => 'fi', 'paises bajos' => 'nl', 'netherlands' => 'nl',
            'belgica' => 'be', 'belgium' => 'be', 'suiza' => 'ch', 'switzerland' => 'ch',
            'austria' => 'at', 'portugal' => 'pt', 'grecia' => 'gr', 'greece' => 'gr',
            'australia' => 'au', 'canada' => 'ca', 'nueva zelanda' => 'nz', 'new zealand' => 'nz',
        ];
        $country_key = self::country_key($country);
        $candidate = isset($iso[$country_key]) ? $iso[$country_key] : sanitize_key(sanitize_title($country));
        if ($candidate === '') {
            return '';
        }
        $base = $candidate;
        $i = 2;
        while (isset($existing[$candidate]) && self::country_key((string) $existing[$candidate]) !== $country_key) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    private static function country_is_valid_for_field(int $post_id, string $country): bool {
        $object = self::acf_field_object($post_id, 'pais');
        $choices = self::acf_country_choices($post_id);
        if (empty($object)) {
            return true;
        }
        $controlled_types = ['select', 'radio', 'checkbox', 'button_group'];
        if (in_array((string) ($object['type'] ?? ''), $controlled_types, true) && empty($choices)) {
            return false;
        }
        if (empty($choices)) {
            return true;
        }
        return self::country_storage_value($post_id, $country) !== '';
    }

    private static function country_storage_value(int $post_id, string $country) {
        $canonical = self::canonical_country($country, $post_id);
        $object = self::acf_field_object($post_id, 'pais');
        $choices = self::acf_country_choices($post_id);
        foreach ($choices as $key => $label) {
            if (self::country_key($canonical) === self::country_key((string) $label) || self::country_key($canonical) === self::country_key((string) $key)) {
                return (string) $key;
            }
        }
        if (!empty($choices)) {
            return '';
        }
        if ((string) ($object['type'] ?? '') === 'taxonomy' && !empty($object['taxonomy']) && taxonomy_exists((string) $object['taxonomy'])) {
            $taxonomy = (string) $object['taxonomy'];
            $term = get_term_by('name', $canonical, $taxonomy);
            if (!$term || is_wp_error($term)) {
                $term = get_term_by('slug', sanitize_title($canonical), $taxonomy);
            }
            if ($term && !is_wp_error($term)) {
                return !empty($object['multiple']) ? [(int) $term->term_id] : (int) $term->term_id;
            }
            return '';
        }
        return $canonical;
    }

    private static function apply_identity_overrides_from_request(array $analysis): array {
        $content_type = self::content_type_from_value((string) ($analysis['content_type'] ?? 'event'));
        $source = isset($analysis['source']) && is_array($analysis['source']) ? $analysis['source'] : [];
        $submitted = isset($analysis['submitted']) && is_array($analysis['submitted']) ? $analysis['submitted'] : [];
        $title = isset($_POST['proposed_title']) ? sanitize_text_field((string) wp_unslash($_POST['proposed_title'])) : '';
        $slug = isset($_POST['proposed_slug']) ? sanitize_title((string) wp_unslash($_POST['proposed_slug'])) : '';
        $errors = [];
        if ($title === '') {
            $errors[] = 'El título propuesto no puede quedar vacío.';
        }
        $start_date = $content_type === 'contest'
            ? (string) ($submitted['fecha_inicio_convocatoria'] ?? '')
            : (string) ($submitted['fecha_inicio'] ?? '');
        $end_date = $content_type === 'contest'
            ? (string) ($submitted['fecha_cierre_convocatoria'] ?? '')
            : (string) ($submitted['fecha_fin'] ?? '');
        $title_validation = self::validate_event_title_years($title, $start_date, $end_date);
        $errors = array_merge($errors, (array) ($title_validation['errors'] ?? []));
        if (!empty($title_validation['warnings'])) {
            $analysis['warnings'] = array_values(array_unique(array_merge((array) ($analysis['warnings'] ?? []), (array) $title_validation['warnings'])));
        }
        if ($slug === '') {
            $slug = sanitize_title($title);
        }
        $post_id = (int) ($analysis['source_post_id'] ?? 0);
        $post_type = $content_type === 'contest' ? 'post' : 'evento';
        if ($slug !== '') {
            $existing = get_page_by_path($slug, OBJECT, $post_type);
            if ($existing instanceof WP_Post && (int) $existing->ID !== $post_id) {
                $errors[] = 'El slug propuesto ya pertenece a otra publicación (ID ' . (int) $existing->ID . ').';
            }
        }
        $submitted['proposed_title'] = $title;
        $submitted['proposed_slug'] = $slug;
        $analysis['submitted'] = $submitted;
        if (!empty($source)) {
            $analysis['comparison'] = array_merge(
                self::build_identity_comparison($source, $submitted),
                self::build_comparison($content_type, (array) ($source['raw_fields'] ?? []), $submitted)
            );
        }
        if (!empty($errors)) {
            $analysis['errors'] = array_values(array_unique(array_merge((array) ($analysis['errors'] ?? []), $errors)));
        }
        return $analysis;
    }

    public static function validate_event_title_years(string $title, string $start = '', string $end = ''): array {
        $errors = [];
        $warnings = [];
        $title = trim($title);
        if ($title === '') {
            return ['errors' => $errors, 'warnings' => $warnings];
        }
        if (preg_match('/\b(?:19|20)\d{3,}\b/u', $title, $malformed)) {
            $errors[] = 'El título propuesto contiene un año anómalo (' . (string) $malformed[0] . '). Revisa el reemplazo antes de aplicar la actualización.';
        }
        preg_match_all('/\b(?:19|20)\d{2}\b/u', $title, $matches);
        $title_years = array_values(array_unique(array_map('intval', (array) ($matches[0] ?? []))));
        $start_year = self::year_from_date($start);
        $end_year = self::year_from_date($end);
        $expected = array_values(array_unique(array_filter([$start_year, $end_year])));
        if (!empty($title_years) && !empty($expected) && empty(array_intersect($title_years, $expected))) {
            $errors[] = 'El año del título (' . implode(', ', $title_years) . ') no coincide con las fechas propuestas (' . implode(', ', $expected) . ').';
        }
        if (count($title_years) > 2) {
            $warnings[] = 'El título contiene varios años. Confirma que todos sean necesarios antes de aplicar.';
        }
        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    private static function year_from_date(string $value): int {
        $value = trim($value);
        if (preg_match('/\b((?:19|20)\d{2})\b/u', $value, $match)) {
            return (int) $match[1];
        }
        return 0;
    }

    private static function validate_event_dates(string $start, string $end): array {
        $errors = [];
        if ($start !== '' && !self::is_valid_iso_date($start)) {
            $errors[] = 'La Fecha de inicio no tiene un formato válido.';
        }
        if ($end !== '' && !self::is_valid_iso_date($end)) {
            $errors[] = 'La Fecha de fin no tiene un formato válido.';
        }
        if (self::is_valid_iso_date($start) && self::is_valid_iso_date($end) && $end < $start) {
            $errors[] = 'La Fecha de fin debe ser igual o posterior a la Fecha de inicio. Los eventos de un día sí están permitidos.';
        }
        return $errors;
    }

    private static function is_valid_iso_date(string $value): bool {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        if (!$date) {
            return false;
        }
        if (is_array($errors) && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0)) {
            return false;
        }
        return $date->format('Y-m-d') === $value;
    }

    private static function date_to_input(string $value): string {
        $value = trim($value);
        if (self::is_valid_iso_date($value)) {
            return $value;
        }
        if (preg_match('/^(\d{2})[\/.-](\d{2})[\/.-](\d{4})$/', $value, $match)) {
            $candidate = $match[3] . '-' . $match[2] . '-' . $match[1];
            return self::is_valid_iso_date($candidate) ? $candidate : '';
        }
        $digits = preg_replace('/\D+/', '', $value);
        if (is_string($digits) && strlen($digits) === 8) {
            $candidate_ymd = substr($digits, 0, 4) . '-' . substr($digits, 4, 2) . '-' . substr($digits, 6, 2);
            if (self::is_valid_iso_date($candidate_ymd)) {
                return $candidate_ymd;
            }
            $candidate_dmy = substr($digits, 4, 4) . '-' . substr($digits, 2, 2) . '-' . substr($digits, 0, 2);
            return self::is_valid_iso_date($candidate_dmy) ? $candidate_dmy : '';
        }
        return '';
    }

    private static function format_ymd_for_display(string $value): string {
        $input = self::date_to_input($value);
        if ($input === '') {
            return $value;
        }
        return substr($input, 8, 2) . '/' . substr($input, 5, 2) . '/' . substr($input, 0, 4);
    }

    private static function get_analysis(string $analysis_id): array {
        $analysis = get_transient(self::ANALYSIS_TRANSIENT_PREFIX . $analysis_id);
        if (!is_array($analysis) || (int) ($analysis['user_id'] ?? 0) !== get_current_user_id()) {
            return [];
        }
        return $analysis;
    }

    private static function build_report(array $analysis): string {
        $source = isset($analysis['source']) && is_array($analysis['source']) ? $analysis['source'] : [];
        $submitted = isset($analysis['submitted']) && is_array($analysis['submitted']) ? $analysis['submitted'] : [];
        $source_read = isset($analysis['source_read']) && is_array($analysis['source_read']) ? $analysis['source_read'] : [];
        $comparison = isset($analysis['comparison']) && is_array($analysis['comparison']) ? $analysis['comparison'] : [];
        $errors = isset($analysis['errors']) && is_array($analysis['errors']) ? $analysis['errors'] : [];
        $warnings = isset($analysis['warnings']) && is_array($analysis['warnings']) ? $analysis['warnings'] : [];
        $city = isset($analysis['city_registration']) && is_array($analysis['city_registration']) ? $analysis['city_registration'] : [];
        $country_registration = isset($analysis['country_registration']) && is_array($analysis['country_registration']) ? $analysis['country_registration'] : [];
        $application = isset($analysis['application']) && is_array($analysis['application']) ? $analysis['application'] : [];
        $country = isset($analysis['country_normalization']) && is_array($analysis['country_normalization']) ? $analysis['country_normalization'] : [];
        $bridge = isset($analysis['editorial_bridge']) && is_array($analysis['editorial_bridge']) ? $analysis['editorial_bridge'] : [];

        $lines = [];
        $lines[] = '# Reporte completo · Gerizim Actualizaciones recurrentes';
        $lines[] = '';
        $lines[] = '## 1. Datos generales';
        $lines[] = '- **Versión plugin:** ' . self::report_value((string) ($analysis['plugin_version'] ?? IDG_VERSION));
        $lines[] = '- **Fecha del análisis:** ' . self::report_value((string) ($analysis['created_at'] ?? ''));
        $lines[] = '- **Fecha de aplicación:** ' . self::report_value((string) ($application['applied_at'] ?? ''));
        $lines[] = '- **Tipo de contenido:** ' . ((string) ($analysis['content_type'] ?? '') === 'contest' ? 'Concurso o convocatoria' : 'Evento');
        $mode = (string) ($submitted['update_mode'] ?? '');
        $lines[] = '- **Modo solicitado:** ' . ($mode === 'create_new' ? 'Crear nueva edición desde anterior' : ($mode === 'update_existing' ? 'Actualizar publicación vigente' : 'Sin seleccionar'));
        $lines[] = '';
        $lines[] = '## 2. Publicación de referencia';
        $lines[] = '- **ID seleccionado originalmente:** ' . self::report_value((string) ($analysis['selected_post_id'] ?? $source['id'] ?? ''));
        $lines[] = '- **ID del expediente:** ' . self::report_value((string) ($source['id'] ?? ''));
        $lines[] = '- **Identidad inmutable verificada:** ' . (!empty($analysis['selection_token_verified']) ? 'Sí' : 'No');
        $lines[] = '- **Título:** ' . self::report_value((string) ($source['title'] ?? ''));
        $lines[] = '- **Slug:** ' . self::report_value((string) ($source['slug'] ?? ''));
        $lines[] = '- **Tipo:** ' . self::report_value((string) ($source['post_type'] ?? ''));
        $lines[] = '- **Estado:** ' . self::report_value((string) ($source['status_label'] ?? ''));
        $lines[] = '- **URL:** ' . self::report_value((string) ($source['permalink'] ?? ''));
        $lines[] = '';
        $lines[] = '## 3. Fuente nueva';
        $lines[] = '- **URL solicitada:** ' . self::report_value((string) ($submitted['new_source'] ?? ''));
        $lines[] = '- **Estado de lectura:** ' . self::report_value((string) ($source_read['label'] ?? $source_read['status'] ?? ''));
        $lines[] = '- **Código HTTP:** ' . (!empty($source_read['http_code']) ? (string) $source_read['http_code'] : '_No aplica._');
        $lines[] = '- **Detalle:** ' . self::report_value((string) ($source_read['message'] ?? ''));
        $lines[] = '- **Título detectado:** ' . self::report_value((string) ($source_read['page_title'] ?? ''));
        if (!empty($source_read['excerpt'])) {
            $lines[] = '';
            $lines[] = '### Extracto técnico de la fuente';
            $lines[] = (string) $source_read['excerpt'];
        }
        $lines[] = '';
        $lines[] = '## 4. Información manual';
        $lines[] = self::report_value((string) ($submitted['new_information'] ?? ''));
        $lines[] = '';
        $lines[] = '## 5. Comparación de campos';
        if (empty($comparison)) {
            $lines[] = '_No fue posible construir la comparación._';
        } else {
            $lines[] = '| Campo | Actual | Propuesto | Estado |';
            $lines[] = '|---|---|---|---|';
            foreach ($comparison as $row) {
                $lines[] = '| ' . self::table_value((string) ($row['label'] ?? '')) . ' | ' . self::table_value((string) ($row['current_display'] ?? '')) . ' | ' . self::table_value((string) ($row['proposed_display'] ?? '')) . ' | ' . self::table_value((string) ($row['status_label'] ?? '')) . ' |';
            }
        }
        $lines[] = '';
        $lines[] = '## 6. Ciudad, país y normalización';
        if (!empty($city['requested']) && !empty($city['city'])) {
            $lines[] = '- **Ciudad normalizada:** ' . self::report_value((string) $city['city']);
            $lines[] = '- **Resultado:** ' . (!empty($city['registered']) ? 'Registrada en el listado auxiliar.' : (!empty($city['already_known']) ? 'Ya existía en el autocompletado.' : 'No registrada.'));
        } else {
            $lines[] = '_No se solicitó registrar una ciudad nueva._';
        }
        if (!empty($country['canonical'])) {
            $lines[] = '- **País normalizado:** ' . self::report_value((string) $country['canonical']);
        }
        if (!empty($country_registration['country'])) {
            $lines[] = '- **Registro ACF País:** ' . (!empty($country_registration['registered']) ? 'Nuevo país registrado.' : (!empty($country_registration['already_known']) ? 'País ya disponible.' : 'No registrado.'));
            $lines[] = '- **Clave interna País:** ' . self::report_value((string) ($country_registration['storage_key'] ?? ''));
        }

        $lines[] = '';
        $lines[] = '## 7. Resultado de la aplicación';
        $application_status = (string) ($application['status'] ?? 'not_applied');
        if ($application_status === 'not_applied' || $application_status === '') {
            $lines[] = '_La actualización todavía no fue aplicada._';
        } else {
            $lines[] = '- **Estado:** ' . self::report_value($application_status === 'success' ? 'Actualización completa' : ($application_status === 'partial' ? 'Actualización parcial' : 'No aplicada'));
            $lines[] = '- **Publicación actualizada:** ' . (!empty($application['publication_updated']) ? 'Sí' : 'No');
            $lines[] = '- **ID solicitado:** ' . self::report_value((string) ($application['selected_post_id'] ?? $analysis['selected_post_id'] ?? ''));
            $lines[] = '- **ID actualizado realmente:** ' . self::report_value((string) ($application['actual_updated_post_id'] ?? $application['post_id'] ?? ''));
            $lines[] = '- **Mismo ID de principio a fin:** ' . (!empty($application['same_post_id']) ? 'Sí' : 'No / bloqueado');
            $lines[] = '- **Estado WordPress antes:** ' . self::report_value((string) ($application['post_status_before'] ?? ''));
            $lines[] = '- **Estado WordPress después:** ' . self::report_value((string) ($application['post_status_after'] ?? ''));
            $lines[] = '- **Título antes:** ' . self::report_value((string) ($application['title_before'] ?? ''));
            $lines[] = '- **Título después:** ' . self::report_value((string) ($application['title_after'] ?? ''));
            $lines[] = '- **Slug antes:** ' . self::report_value((string) ($application['slug_before'] ?? ''));
            $lines[] = '- **Slug después:** ' . self::report_value((string) ($application['slug_after'] ?? ''));
            $lines[] = '- **Contenido y extracto conservados:** ' . (!empty($application['content_preserved']) ? 'Sí' : 'No / revisar');
            $lines[] = '- **URL de edición:** ' . self::report_value((string) ($application['edit_url'] ?? ''));
            $updated_fields = isset($application['updated_fields']) && is_array($application['updated_fields']) ? $application['updated_fields'] : [];
            if (!empty($updated_fields)) {
                $lines[] = '';
                $lines[] = '### Campos escritos y verificados';
                foreach ($updated_fields as $field) {
                    $detail = self::report_value((string) ($field['stored_display'] ?? ''));
                    if ((string) ($field['key'] ?? '') === 'pais') {
                        $detail .= ' · método: ' . self::report_value((string) ($field['method'] ?? ''));
                        $detail .= ' · selector: ' . self::report_value((string) ($field['selector'] ?? ''));
                        $detail .= ' · valor interno: ' . self::report_value((string) ($field['stored'] ?? ''));
                        $detail .= ' · valor visible ACF: ' . self::report_value((string) ($field['formatted'] ?? ''));
                    }
                    $lines[] = '- **' . self::report_value((string) ($field['label'] ?? $field['key'] ?? 'Campo')) . ':** ' . (!empty($field['success']) ? 'Sí' : 'No') . ' · ' . $detail;
                }
            }
        }

        $lines[] = '';
        $lines[] = '## 8. Puente hacia Flujo editorial';
        if (!empty($bridge['workflow_id'])) {
            $lines[] = '- **Estado:** Workflow editorial preparado.';
            $lines[] = '- **Workflow ID:** ' . self::report_value((string) $bridge['workflow_id']);
            $lines[] = '- **Fecha:** ' . self::report_value((string) ($bridge['created_at'] ?? ''));
            $lines[] = '- **Publicación de destino:** ' . self::report_value((string) ($bridge['target_post_id'] ?? $analysis['source_post_id'] ?? ''));
        } else {
            $lines[] = '_Todavía no se preparó una redacción en el Flujo editorial._';
        }

        $application_errors = isset($application['errors']) && is_array($application['errors']) ? $application['errors'] : [];
        $application_warnings = isset($application['warnings']) && is_array($application['warnings']) ? $application['warnings'] : [];
        $lines[] = '';
        $lines[] = '## 9. Errores y avisos';
        if (empty($errors) && empty($warnings) && empty($application_errors) && empty($application_warnings)) {
            $lines[] = '_Sin errores ni avisos._';
        } else {
            foreach ($errors as $error) {
                $lines[] = '- **Error de análisis:** ' . self::report_value((string) $error);
            }
            foreach ($warnings as $warning) {
                $lines[] = '- **Aviso de análisis:** ' . self::report_value((string) $warning);
            }
            foreach ($application_errors as $error) {
                $lines[] = '- **Error de aplicación:** ' . self::report_value((string) $error);
            }
            foreach ($application_warnings as $warning) {
                $lines[] = '- **Aviso de aplicación:** ' . self::report_value((string) $warning);
            }
        }
        $lines[] = '';
        $lines[] = '## 10. Protección de alcance';
        $lines[] = '- Publicación actualizada: **' . (!empty($application['publication_updated']) ? 'Sí' : 'No') . '**.';
        $lines[] = '- Nueva edición creada: **No**.';
        $lines[] = '- Concurso o convocatoria modificado: **' . (((string) ($analysis['content_type'] ?? '') === 'contest' && !empty($application['publication_updated'])) ? 'Sí' : 'No') . '**.';
        $lines[] = '- Estado WordPress modificado deliberadamente: **No**.';
        $lines[] = '- Destacar en home modificado: **No**; se conserva el valor existente.';
        $lines[] = '- Contenido del artículo modificado por el módulo recurrente: **No**.';
        $lines[] = '- OpenAI ejecutado durante análisis/aplicación estructural: **No**.';
        $lines[] = '- Workflow editorial preparado: **' . (!empty($bridge['workflow_id']) ? 'Sí' : 'No') . '**.';
        $lines[] = '- El workflow reutiliza receta compacta, prompts, validaciones, enlaces y Gutenberg existentes: **Sí**.';
        $lines[] = '';
        return implode("\n", $lines);
    }

    private static function report_value(string $value): string {
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        return $value === '' || $value === '0' ? '_Sin información._' : $value;
    }

    private static function table_value(string $value): string {
        $value = trim(str_replace(["\r\n", "\r", "\n", '|'], [' ', ' ', ' ', '\\|'], $value));
        return $value === '' ? '—' : $value;
    }

    private static function status_label(string $status): string {
        $object = get_post_status_object($status);
        return $object ? (string) $object->label : $status;
    }
}
