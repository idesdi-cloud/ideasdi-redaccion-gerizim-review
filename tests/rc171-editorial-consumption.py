#!/usr/bin/env python3
"""RC1.7.1: editorial consumers faithfully express canonical 1.1.0 semantics."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
FILES = {name: (ROOT / 'includes' / name).read_text(encoding='utf-8') for name in ('class-canonical-context.php', 'class-editorial-rules.php', 'class-prompt-library.php', 'class-final-guard.php')}

def require(text, fragment, label):
    if fragment not in text:
        raise AssertionError(f'missing {label}: {fragment!r}')

def forbid(text, fragment, label):
    if fragment in text:
        raise AssertionError(f'legacy requirement remains in {label}: {fragment!r}')

for fragment in ('decisiones de diseño concretas', 'explica esas decisiones, no solo sus prestaciones', 'consecuencia relevante y detente', 'cualidades y la abstracción para después de la evidencia concreta', 'intención de autor exige documentación', 'metalenguaje editorial cuando sustituya la observación', 'autoría es transversal y se vincula a decisiones concretas', 'vocabulario de experiencia y percepción adecuado a la categoría', 'No sobreexplique: interpreta solo cuando aporte una relación nueva', 'preferencia, no una cuota rígida', 'qué es, quién es responsable, para quién cuando sea relevante, origen o estado actual cuando sea relevante y rasgos objetivos esenciales', 'no repite la tesis ni adelanta el análisis', 'Encabezados jerárquicos, descriptivos y concisos', 'Transiciones flexibles', 'observación abierta antes que un veredicto definitivo', 'Progresión preferida de categoría (no rígida)'):
    require(FILES['class-canonical-context.php'], fragment, 'canonical context')

for fragment in ("'min_paragraphs_per_h3' => 0", 'introducción preferentemente de dos párrafos sin convertirla en cuota rígida', 'sin imponer un mínimo universal de párrafos', 'para quién cuando sea relevante', 'origen o estado actual cuando sea relevante', 'rasgos objetivos esenciales', 'sin requisito de apertura por keyword'):
    require(FILES['class-editorial-rules.php'], fragment, 'editorial rules')
forbid(FILES['class-editorial-rules.php'], 'Mínimo de párrafos por H3:', 'editorial rules prompt')

for fragment in ('Empieza por decisiones de diseño concretas', 'La intención de autor exige documentación', 'Introducción: dos párrafos es la preferencia no rígida', 'origen o estado actual cuando sea relevante', 'No exige abrir con la keyword', 'sin cuota fija de H3 ni mínimo universal de párrafos', 'Transiciones flexibles', 'observación abierta antes que un veredicto definitivo'):
    require(FILES['class-prompt-library.php'], fragment, 'prompt library')
for fragment in ('Debe empezar con la keyword principal', 'Desarrollo mínimo: 6 subtítulos H3', 'entre 6 y 7 subtítulos H3', 'Cada H3 de desarrollo debe tener 2 párrafos breves como mínimo', 'qué es, quién lo impulsa y qué aporta'):
    forbid(FILES['class-prompt-library.php'], fragment, 'prompt library')

for fragment in ('guía no rígida', 'no existe un mínimo universal de párrafos'):
    require(FILES['class-final-guard.php'], fragment, 'final guard')
for fragment in ('El artículo debe tener al menos', 'El rango editorial recomendado es de 6 a 7', 'Cada H3 debe tener mínimo dos párrafos', 'después de los dos párrafos de introducción'):
    forbid(FILES['class-final-guard.php'], fragment, 'final guard')

print('RC171_EDITORIAL_CONSUMPTION_OK')
