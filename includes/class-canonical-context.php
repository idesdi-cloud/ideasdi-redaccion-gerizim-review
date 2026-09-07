<?php
/** Local read-only consumption of the canonical adapter; layers are never merged into policy. */
final class IDG_Canonical_Context {
    public static function resolve(array $workflow): array {
        $resolved = IDG_Canonical_Adapter::resolve($workflow);
        $projection = $resolved['projection'];
        unset($resolved['projection']);
        $definitions = $projection['lenses']['definitions'];
        $secondary = [];
        foreach ($resolved['secondary_lenses'] as $id) {
            $secondary[$id] = $definitions[$id];
        }
        $resolved['resolution'] = $projection['resolution'];
        $resolved['layers'] = [
            'global' => $projection['global'],
            'surface' => $projection['surfaces'][$resolved['surface']],
            'category' => $projection['categories'][$resolved['category']] ?? [],
            'primary_lens' => $definitions[$resolved['primary_lens']] ?? [],
            'secondary_lenses' => $secondary,
            'piece_context' => $resolved['piece_context'],
        ];
        return $resolved;
    }

    /** Suggestions only: no taxonomy writes or factual assertions. */
    public static function lens_axes(array $workflow): array {
        $layers = self::resolve($workflow)['layers'];
        $axes = $layers['primary_lens']['axes'] ?? [];
        foreach ($layers['secondary_lenses'] as $lens) {
            $axes = array_merge($axes, $lens['axes']);
        }
        return array_values(array_unique($axes));
    }

    public static function prompt_block(array $workflow): string {
        $context = self::resolve($workflow);
        $global = $context['layers']['global'];
        $lines = [
            'Canonical ' . $context['canonical_version'] . ' SHA-256 ' . $context['canonical_sha256'],
            'Superficie: ' . $context['surface'] . '; categoría: ' . ($context['category'] ?? 'sin resolver') . '; lente primaria: ' . ($context['primary_lens'] ?? 'sin resolver'),
            'La fuente verifica, no narra. Distinguir ' . implode(', ', $global['evidence']['levels']) . '.',
            'Prohibidas las afirmaciones sin respaldo. La evidencia prevalece sobre taxonomía, receta y lente. Brief, categoría y tags no son hechos verificados.',
            'Mostrar antes de explicar la importancia; lenguaje disciplinar abierto y no promocional, sin voz de comunicado ni catálogo.',
            'Las restricciones particulares no pueden anular las guardas globales. Las sugerencias no crean hechos ni fuerzan el ángulo.',
        ];
        $identity = $context['layers']['category']['identity'] ?? [];
        if ($identity) {
            $lines[] = 'Identidad de autor o marca opcional, únicamente con evidencia verificable.';
        }
        if (isset($context['layers']['surface']['title'])) {
            $title = $context['layers']['surface']['title'];
            $lines[] = 'H1: preferido hasta ' . $title['preferred_max_chars'] . ' caracteres como calidad; máximo obligatorio ' . $title['hard_max_chars'] . '. De 61 a 68 no es fallo de validación.';
        }
        $axes = self::lens_axes($workflow);
        if ($axes) {
            $lines[] = 'Ejes de lentes canónicas, solo como sugerencias sujetas a evidencia: ' . implode('; ', $axes) . '.';
        }
        return implode("\n", $lines);
    }
}
