<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Primera fase del módulo de actualizaciones recurrentes.
 *
 * Alcance protegido:
 * - Buscar y seleccionar Eventos o Concursos existentes.
 * - Cargar sus campos actuales.
 * - Elegir modo de actualización y preparar datos de nueva edición.
 * - No modifica publicaciones, no ejecuta OpenAI y no altera el flujo editorial.
 */
final class IDG_Recurring_Updates {
    private const CONTEST_CATEGORY_ID = 34;

    public static function render_page(): void {
        if (!current_user_can('edit_posts')) {
            wp_die('No tienes permisos suficientes.');
        }

        $content_type = self::content_type_from_request();
        $search = isset($_GET['recurring_search']) ? sanitize_text_field((string) wp_unslash($_GET['recurring_search'])) : '';
        $source_post_id = isset($_GET['source_post_id']) ? absint($_GET['source_post_id']) : 0;
        $results = $search !== '' ? self::search_publications($content_type, $search) : [];
        $source = $source_post_id > 0 ? self::load_source($content_type, $source_post_id) : null;
        ?>
        <div class="wrap idg-wrap idg-recurring-wrap">
            <h1>Gerizim · Actualizaciones recurrentes</h1>
            <p class="idg-version-badge">Versión plugin: <?php echo esc_html(IDG_VERSION); ?></p>

            <div class="idg-card">
                <h2>Módulo independiente</h2>
                <p>Esta pantalla prepara actualizaciones de ediciones recurrentes sin modificar el <strong>Flujo editorial</strong>. En esta primera fase solo busca, selecciona y carga la publicación existente.</p>
                <p class="description">No genera contenido, no llama OpenAI, no crea borradores y no actualiza WordPress.</p>
            </div>

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
                            <p class="description">Eventos: busca en el CPT <code>evento</code>. Concursos: busca entradas de la categoría existente ID 34.</p>
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
                                        <p class="description">
                                            ID <?php echo esc_html((string) $item['id']); ?> · <?php echo esc_html((string) $item['status_label']); ?> · <?php echo esc_html((string) $item['date']); ?>
                                        </p>
                                        <code><?php echo esc_html((string) $item['slug']); ?></code>
                                    </div>
                                    <p><a class="button button-primary" href="<?php echo esc_url($select_url); ?>">Seleccionar</a></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (is_array($source)) : ?>
                <?php self::render_selected_source($source, $content_type); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_selected_source(array $source, string $content_type): void {
        $is_event = $content_type === 'event';
        ?>
        <div class="idg-card">
            <h2>2. Publicación seleccionada</h2>
            <div class="idg-recurring-source-header">
                <div>
                    <h3><?php echo esc_html((string) $source['title']); ?></h3>
                    <p class="description">ID <?php echo esc_html((string) $source['id']); ?> · <?php echo esc_html((string) $source['post_type']); ?> · <?php echo esc_html((string) $source['status_label']); ?></p>
                    <p><a href="<?php echo esc_url((string) $source['edit_url']); ?>" target="_blank" rel="noopener noreferrer">Abrir publicación en una pestaña nueva</a></p>
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

        <form class="idg-card idg-recurring-preparation" onsubmit="return false;">
            <h2>3. Preparar nueva edición</h2>
            <p>
                <label><input type="radio" name="update_mode" value="update_existing" checked> <strong>Actualizar publicación vigente</strong></label><br>
                <label><input type="radio" name="update_mode" value="create_new"> Crear nueva edición desde anterior</label>
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="recurring_edition_year">Nueva edición / año</label></th>
                    <td><input type="number" min="2000" max="2100" id="recurring_edition_year" class="small-text" placeholder="2027"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="recurring_new_source">URL oficial o fuente nueva</label></th>
                    <td><input type="url" id="recurring_new_source" class="large-text" placeholder="https://..."></td>
                </tr>
                <tr>
                    <th scope="row"><label for="recurring_new_information">Información nueva</label></th>
                    <td><textarea id="recurring_new_information" rows="6" class="large-text" placeholder="Pega aquí información de la nueva edición, nota de prensa o datos confirmados."></textarea></td>
                </tr>
            </table>

            <h3><?php echo $is_event ? 'Datos del evento' : 'Datos de la convocatoria'; ?></h3>
            <p class="description">Los valores actuales se muestran como referencia. La extracción y comparación automática se implementarán en la siguiente fase.</p>
            <table class="form-table" role="presentation">
                <?php if ($is_event) : ?>
                    <?php self::render_event_preparation_fields($source); ?>
                <?php else : ?>
                    <?php self::render_contest_preparation_fields($source); ?>
                <?php endif; ?>
            </table>
            <p><button type="button" class="button button-primary" disabled title="Disponible en la siguiente fase">Analizar nueva edición</button></p>
        </form>
        <?php
    }

    private static function render_event_preparation_fields(array $source): void {
        $fields = (array) ($source['raw_fields'] ?? []);
        $rows = [
            'fecha_inicio' => ['Fecha de inicio', 'date'],
            'fecha_fin' => ['Fecha de fin', 'date'],
            'ciudad' => ['Ciudad', 'text'],
            'pais' => ['País', 'text'],
            'ubicacion' => ['Ubicación', 'text'],
            'enlace_oficial' => ['Enlace oficial', 'url'],
            'resumen_editorial' => ['Resumen editorial', 'textarea'],
        ];
        foreach ($rows as $key => [$label, $type]) {
            self::render_preparation_row($key, $label, $type, (string) ($fields[$key] ?? ''));
        }
        ?>
        <tr><th scope="row">Destacar en home</th><td><label><input type="checkbox" <?php checked(!empty($fields['destacado_home'])); ?>> Mantener o activar destacado</label></td></tr>
        <?php
    }

    private static function render_contest_preparation_fields(array $source): void {
        $fields = (array) ($source['raw_fields'] ?? []);
        $rows = [
            'fecha_inicio_convocatoria' => ['Fecha de inicio', 'date'],
            'fecha_cierre_convocatoria' => ['Fecha de cierre', 'date'],
            'fecha_premiacion_convocatoria' => ['Fecha de premiación', 'date'],
            'enlace_oficial_convocatoria' => ['Enlace oficial de la convocatoria', 'url'],
        ];
        foreach ($rows as $key => [$label, $type]) {
            self::render_preparation_row($key, $label, $type, (string) ($fields[$key] ?? ''));
        }
    }

    private static function render_preparation_row(string $key, string $label, string $type, string $current_value): void {
        $display_current = str_contains($key, 'fecha_') ? self::format_ymd_for_display($current_value) : $current_value;
        ?>
        <tr>
            <th scope="row"><label for="recurring_<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <?php if ($type === 'textarea') : ?>
                    <textarea id="recurring_<?php echo esc_attr($key); ?>" rows="4" class="large-text" placeholder="Valor nuevo"></textarea>
                <?php else : ?>
                    <input type="<?php echo esc_attr($type); ?>" id="recurring_<?php echo esc_attr($key); ?>" class="regular-text" placeholder="Valor nuevo">
                <?php endif; ?>
                <p class="description">Valor actual: <?php echo $display_current !== '' ? esc_html($display_current) : 'Sin información'; ?></p>
            </td>
        </tr>
        <?php
    }

    private static function content_type_from_request(): string {
        $value = isset($_GET['content_type']) ? sanitize_key((string) $_GET['content_type']) : 'event';
        return $value === 'contest' ? 'contest' : 'event';
    }

    private static function search_publications(string $content_type, string $search): array {
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

        if (ctype_digit($search)) {
            $args['p'] = absint($search);
        } else {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $items[] = [
                'id' => (int) $post->ID,
                'title' => get_the_title($post),
                'slug' => (string) $post->post_name,
                'date' => get_the_date('d/m/Y', $post),
                'status_label' => self::status_label((string) $post->post_status),
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
        $display_fields = [];
        if ($content_type === 'event') {
            $display_fields = [
                'Fecha de inicio' => self::format_ymd_for_display((string) ($raw_fields['fecha_inicio'] ?? '')),
                'Fecha de fin' => self::format_ymd_for_display((string) ($raw_fields['fecha_fin'] ?? '')),
                'Ciudad' => (string) ($raw_fields['ciudad'] ?? ''),
                'País' => (string) ($raw_fields['pais'] ?? ''),
                'Ubicación' => (string) ($raw_fields['ubicacion'] ?? ''),
                'Enlace oficial' => (string) ($raw_fields['enlace_oficial'] ?? ''),
                'Destacar en home' => !empty($raw_fields['destacado_home']) ? 'Sí' : 'No',
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
            'title' => get_the_title($post),
            'post_type' => (string) $post->post_type,
            'status_label' => self::status_label((string) $post->post_status),
            'edit_url' => get_edit_post_link($post_id, 'raw') ?: '',
            'permalink' => get_permalink($post_id) ?: '',
            'fields' => $display_fields,
            'raw_fields' => $raw_fields,
        ];
    }

    private static function event_fields(int $post_id): array {
        $keys = ['fecha_inicio', 'fecha_fin', 'ciudad', 'pais', 'ubicacion', 'enlace_oficial', 'destacado_home', 'resumen_editorial'];
        $fields = [];
        foreach ($keys as $key) {
            $value = function_exists('get_field') ? get_field($key, $post_id) : get_post_meta($post_id, $key, true);
            if (is_array($value)) {
                $value = (string) ($value['label'] ?? $value['value'] ?? $value['url'] ?? '');
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

    private static function format_ymd_for_display(string $value): string {
        $digits = preg_replace('/\D+/', '', $value);
        if (!is_string($digits) || strlen($digits) !== 8) {
            return $value;
        }
        return substr($digits, 6, 2) . '/' . substr($digits, 4, 2) . '/' . substr($digits, 0, 4);
    }

    private static function status_label(string $status): string {
        $object = get_post_status_object($status);
        return $object ? (string) $object->label : $status;
    }
}
