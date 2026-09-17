<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IDG_Reel_Contract {
    public static function normalize(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));

        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\n/', $text);

        if (!is_array($lines)) {
            return trim($text);
        }

        $out = [];
        $current_scene = 0;
        $overlay_index = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            if (
                preg_match(
                    '/^\s*(?:#{1,6}\s*)?PAQUETE\s+REEL\s*:?\s*$/iu',
                    $line
                )
            ) {
                continue;
            }

            if (
                preg_match(
                    '/^\s*(?:#{1,6}\s*)?(?:Escena|Scene)\s+\d+\s*:?\s*$/iu',
                    $line
                )
            ) {
                continue;
            }

            if (
                preg_match(
                    '/^\s*(?:[-*]\s*)?VO\s*(?:[—\-]\s*Bloque\s*)?(\d+)\s*:\s*(.+)$/iu',
                    $line,
                    $m
                )
            ) {
                $current_scene = (int) $m[1];
                $out[] = 'VO ' . $current_scene . ': ' . trim((string) $m[2]);
                continue;
            }

            if (
                preg_match(
                    '/^\s*(?:[-*]\s*)?(?:Overlay|Subt[ií]tulo|Texto en pantalla)(?:\s+(?:[—\-]\s*)?(\d+)(?:[.\-](\d+))?)?\s*:\s*(.+)$/iu',
                    $line,
                    $m
                )
            ) {
                $first = isset($m[1]) && $m[1] !== ''
                    ? (int) $m[1]
                    : 0;

                $second = isset($m[2]) && $m[2] !== ''
                    ? (int) $m[2]
                    : 0;

                $content = trim((string) ($m[3] ?? ''));

                if ($second > 0) {
                    // Numeración explícita escena.posición:
                    // se conserva para detectar errores reales.
                    $scene = $first;
                    $position = $second;

                    $out[] =
                        'Overlay '
                        . $scene
                        . '.'
                        . $position
                        . ': '
                        . $content;

                    if ($scene === $current_scene) {
                        $overlay_index[$scene] = max(
                            (int) ($overlay_index[$scene] ?? 0),
                            $position
                        );
                    }

                    continue;
                }

                if ($current_scene > 0) {
                    // Formato legacy "Overlay 1:":
                    // el número representa posición dentro del VO actual.
                    if ($first > 0) {
                        $position = $first;
                    } else {
                        $position =
                            (int) (
                                $overlay_index[$current_scene]
                                ?? 0
                            ) + 1;
                    }

                    $overlay_index[$current_scene] = max(
                        (int) (
                            $overlay_index[$current_scene]
                            ?? 0
                        ),
                        $position
                    );

                    $out[] =
                        'Overlay '
                        . $current_scene
                        . '.'
                        . $position
                        . ': '
                        . $content;

                    continue;
                }

                // Sin VO previo no se inventa asociación de escena.
                $out[] = $line;
                continue;
            }

            // Conserva cualquier contenido no reconocido para revisión humana.
            $out[] = $line;
        }

        return trim(implode("\n", $out));
    }

    public static function inspect(string $text): array {
        $normalized = self::normalize($text);

        $rules = class_exists('IDG_Editorial_Rules')
            ? IDG_Editorial_Rules::get()
            : [];

        $target_scenes = max(
            1,
            (int) ($rules['reel_scenes'] ?? 6)
        );

        $target_words = max(
            1,
            (int) ($rules['reel_vo_words'] ?? 14)
        );

        $overlays_per_scene = max(
            1,
            (int) ($rules['reel_overlays_per_scene'] ?? 3)
        );

        $overlay_max = max(
            1,
            (int) ($rules['reel_overlay_max_chars'] ?? 40)
        );

        $cta = trim(
            (string) (
                $rules['reel_cta']
                ?? 'Conoce más de este proyecto en ideasDi.com'
            )
        );

        $issues = [];
        $warnings = [];
        $vo = [];
        $vo_counts = [];
        $overlays = [];
        $overlay_counts = [];
        $seen_vo_order = [];

        for ($i = 1; $i <= $target_scenes; $i++) {
            $overlays[$i] = [];
            $overlay_counts[$i] = 0;
        }

        if ($normalized === '') {
            $issues[] = 'Falta paquete reel en la salida final.';

            return [
                'valid' => false,
                'status' => 'requiere revisión',
                'normalized' => '',
                'issues' => $issues,
                'warnings' => [],
                'vo' => [],
                'vo_counts' => [],
                'overlays' => $overlays,
                'overlay_counts' => $overlay_counts,
                'total_overlays' => 0,
                'cta_in_final_vo' => false,
                'targets' => [
                    'scenes' => $target_scenes,
                    'words' => $target_words,
                    'overlays_per_scene' => $overlays_per_scene,
                    'overlay_max_chars' => $overlay_max,
                    'total_overlays' =>
                        $target_scenes * $overlays_per_scene,
                ],
            ];
        }

        foreach (preg_split('/\n/', $normalized) as $line) {
            $line = trim((string) $line);

            if ($line === '') {
                continue;
            }

            if (
                preg_match(
                    '/^VO\s+(\d+)\s*:\s*(.+)$/iu',
                    $line,
                    $m
                )
            ) {
                $scene = (int) $m[1];
                $content = trim((string) $m[2]);

                $seen_vo_order[] = $scene;

                if ($scene < 1 || $scene > $target_scenes) {
                    $issues[] = 'VO fuera de rango: VO ' . $scene . '.';
                    continue;
                }

                if (array_key_exists($scene, $vo)) {
                    $issues[] =
                        'VO ' . $scene . ' aparece más de una vez.';
                }

                $vo[$scene] = $content;
                $vo_counts[$scene] = self::word_count($content);

                continue;
            }

            if (
                preg_match(
                    '/^Overlay\s+(\d+)\.(\d+)\s*:\s*(.+)$/iu',
                    $line,
                    $m
                )
            ) {
                $scene = (int) $m[1];
                $position = (int) $m[2];
                $content = trim((string) $m[3]);

                if ($scene < 1 || $scene > $target_scenes) {
                    $issues[] =
                        'Overlay fuera de rango: escena '
                        . $scene
                        . '.';
                    continue;
                }

                $overlays[$scene][] = [
                    'position' => $position,
                    'text' => $content,
                ];

                $overlay_counts[$scene]++;

                if (self::text_length($content) > $overlay_max) {
                    $issues[] =
                        'Overlay '
                        . $scene
                        . '.'
                        . $position
                        . ' supera '
                        . $overlay_max
                        . ' caracteres.';
                }

                continue;
            }

            $issues[] =
                'Línea no reconocida en paquete reel: '
                . $line;
        }

        $expected_order = range(1, $target_scenes);

        if ($seen_vo_order !== $expected_order) {
            $issues[] =
                'Las escenas del paquete reel deben aparecer en orden VO 1 a VO '
                . $target_scenes
                . '.';
        }

        for ($i = 1; $i <= $target_scenes; $i++) {
            if (
                !isset($vo[$i])
                || trim((string) $vo[$i]) === ''
            ) {
                $issues[] = 'Falta VO ' . $i . '.';
            }
        }

        for ($i = 1; $i < $target_scenes; $i++) {
            if (!isset($vo[$i])) {
                continue;
            }

            $count = (int) ($vo_counts[$i] ?? 0);

            if ($count !== $target_words) {
                $issues[] =
                    'VO '
                    . $i
                    . ' debe tener exactamente '
                    . $target_words
                    . ' palabras. Detectadas: '
                    . $count
                    . '.';
            }
        }

        $final_vo = (string) ($vo[$target_scenes] ?? '');

        $cta_in_final_vo =
            $cta !== ''
            && stripos($final_vo, $cta) !== false;

        if (!$cta_in_final_vo) {
            $issues[] =
                'VO '
                . $target_scenes
                . ' debe incluir el CTA fijo obligatorio.';
        }

        for ($i = 1; $i <= $target_scenes; $i++) {
            $count = (int) ($overlay_counts[$i] ?? 0);

            if ($count !== $overlays_per_scene) {
                $issues[] =
                    'Escena '
                    . $i
                    . ' debe incluir exactamente '
                    . $overlays_per_scene
                    . ' overlays. Detectados: '
                    . $count
                    . '.';

                continue;
            }

            $positions = array_map(
                static function (array $overlay): int {
                    return (int) ($overlay['position'] ?? 0);
                },
                (array) ($overlays[$i] ?? [])
            );

            sort($positions);

            $expected_positions =
                range(1, $overlays_per_scene);

            if ($positions !== $expected_positions) {
                $labels = [];

                foreach ($expected_positions as $position) {
                    $labels[] =
                        $i . '.' . $position;
                }

                $issues[] =
                    'Escena '
                    . $i
                    . ' debe usar exactamente Overlay '
                    . implode(', ', $labels)
                    . '.';
            }
        }

        $total_overlays = array_sum($overlay_counts);
        $issues = array_values(array_unique($issues));

        return [
            'valid' => empty($issues),
            'status' =>
                empty($issues)
                    ? 'válido'
                    : 'requiere revisión',
            'normalized' => $normalized,
            'issues' => $issues,
            'warnings' => $warnings,
            'vo' => $vo,
            'vo_counts' => $vo_counts,
            'overlays' => $overlays,
            'overlay_counts' => $overlay_counts,
            'total_overlays' => $total_overlays,
            'cta_in_final_vo' => $cta_in_final_vo,
            'targets' => [
                'scenes' => $target_scenes,
                'words' => $target_words,
                'overlays_per_scene' => $overlays_per_scene,
                'overlay_max_chars' => $overlay_max,
                'total_overlays' =>
                    $target_scenes * $overlays_per_scene,
            ],
        ];
    }

    private static function text_length(string $text): int {
        if (function_exists('mb_strlen')) {
            return mb_strlen($text);
        }

        $count = preg_match_all('/./us', $text, $matches);

        if ($count !== false) {
            return $count;
        }

        return strlen($text);
    }

    private static function word_count(string $text): int {
        $plain = trim(strip_tags($text));

        preg_match_all(
            '/\b[\p{L}\p{N}][\p{L}\p{N}\-]*\b/u',
            $plain,
            $m
        );

        return count($m[0] ?? []);
    }
}
