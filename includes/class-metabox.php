<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Metabox {
    public static function register(): void {
        add_meta_box(
            'idg_internal_info',
            'Gerizim · Información editorial interna',
            [self::class, 'render'],
            'post',
            'normal',
            'default'
        );
    }

    public static function render(WP_Post $post): void {
        if (!current_user_can('edit_post', $post->ID)) {
            return;
        }
        wp_nonce_field('idg_save_metabox', 'idg_metabox_nonce');

        // RC1.4.3: el borrador queda más limpio. La ficha, validación,
        // fuente oficial y brief completo siguen disponibles en el reporte descargable,
        // pero ya no se muestran en el metabox editorial del borrador.
        $fields = [
            '_idg_seo_report' => ['Informe SEO interno', 'textarea'],
            '_idg_social_copy' => ['Copy para redes', 'textarea'],
            '_idg_reel_package' => ['Paquete reel', 'textarea'],
            '_idg_feedback_notes' => ['Retroalimentación Gerizim', 'textarea'],
        ];
        $is_sponsored = (stripos((string) get_post_meta($post->ID, '_idg_piece_type', true), 'patrocinado') !== false)
            || get_post_meta($post->ID, '_idg_sponsor_client', true)
            || get_post_meta($post->ID, '_idg_sponsored_link', true);
        if ($is_sponsored) {
            $fields['_idg_sponsor_client'] = ['Cliente / marca', 'text'];
            $fields['_idg_sponsored_link'] = ['Enlace patrocinado', 'url'];
            $fields['_idg_sponsored_anchor'] = ['Anchor solicitado', 'text'];
            $fields['_idg_sponsored_rel'] = ['Tipo de enlace', 'text'];
            $fields['_idg_sponsored_notes'] = ['Notas internas patrocinado', 'textarea'];
            $fields['_idg_sponsored_visible_label'] = ['Aviso visible patrocinado', 'text'];
        }
        ?>
        <div class="idg-metabox">
            <p class="description">Estos datos son internos del equipo editorial y no aparecen en el frontend del artículo.</p>
            <?php foreach ($fields as $key => [$label, $type]) : ?>
                <?php $value = (string) get_post_meta($post->ID, $key, true); ?>
                <p>
                    <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
                    <?php if ($type === 'textarea') : ?>
                        <textarea id="<?php echo esc_attr($key); ?>" name="idg_meta[<?php echo esc_attr($key); ?>]" rows="5" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
                    <?php else : ?>
                        <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($key); ?>" name="idg_meta[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>" class="large-text">
                    <?php endif; ?>
                </p>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public static function save(int $post_id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['idg_metabox_nonce']) || !wp_verify_nonce((string) $_POST['idg_metabox_nonce'], 'idg_save_metabox')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (!isset($_POST['idg_meta']) || !is_array($_POST['idg_meta'])) {
            return;
        }

        $allowed = [
            '_idg_keyword',
            '_idg_entity',
            '_idg_piece_type',
            '_idg_brief_summary',
            '_idg_official_source',
            '_idg_internal_links',
            '_idg_assignment_card',
            '_idg_final_validation',
            '_idg_meta_description',
            '_idg_seo_report',
            '_idg_social_copy',
            '_idg_reel_package',
            '_idg_feedback_notes',
            '_idg_prompt_versions',
            '_idg_execution_history',
            '_idg_sponsor_client',
            '_idg_sponsored_link',
            '_idg_sponsored_anchor',
            '_idg_sponsored_rel',
            '_idg_sponsored_notes',
            '_idg_sponsored_visible_label',
        ];

        $meta = wp_unslash($_POST['idg_meta']);
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $meta)) {
                continue;
            }
            $value = is_string($meta[$key]) ? $meta[$key] : '';
            if (in_array($key, ['_idg_official_source', '_idg_sponsored_link'], true)) {
                update_post_meta($post_id, $key, esc_url_raw($value));
            } elseif (in_array($key, ['_idg_keyword', '_idg_entity', '_idg_piece_type', '_idg_sponsor_client', '_idg_sponsored_anchor', '_idg_sponsored_rel', '_idg_sponsored_visible_label'], true)) {
                update_post_meta($post_id, $key, sanitize_text_field($value));
            } else {
                update_post_meta($post_id, $key, wp_kses_post($value));
            }
        }
    }
}
