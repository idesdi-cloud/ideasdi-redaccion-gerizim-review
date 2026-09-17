from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

post = (
    ROOT / "includes/class-post-creator.php"
).read_text(encoding="utf-8")

guard = (
    ROOT / "includes/class-final-guard.php"
).read_text(encoding="utf-8")

card = (
    ROOT / "includes/class-assignment-card.php"
).read_text(encoding="utf-8")


def check(value, label):
    if not value:
        raise RuntimeError(label)
    print("OK", label)


check(
    "return serialize_blocks($blocks);" in post,
    "serialización nativa serialize_blocks",
)

check(
    "'core/list-item'" in post,
    "listas usan core/list-item",
)

check(
    '<ul class="wp-block-list">' in post,
    "lista usa markup wp-block-list",
)

check(
    "'core/html'" in post,
    "fallback desconocido queda identificable como core/html",
)

check(
    "parse_blocks($post_content)" in guard,
    "Final Guard usa parse_blocks",
)

check(
    "serialize_blocks($blocks)" in guard,
    "Final Guard verifica round-trip",
)

allowed_start = guard.index("$allowed = [")
allowed_end = guard.index("];", allowed_start) + 2

check(
    "'core/html'" not in guard[allowed_start:allowed_end],
    "core/html no está permitido",
)

check(
    "Existe contenido suelto fuera de bloques Gutenberg." in guard,
    "bloquea freeform top-level",
)

check(
    "Gutenberg contiene un heading fuera de la jerarquía H2/H3."
    in guard,
    "bloquea H1/H4+ dentro de post_content",
)

check(
    "core/list contiene un hijo distinto de core/list-item."
    in guard,
    "valida estructura de listas",
)

check(
    "assignment-card-v2-links-no-category-fallback" in card,
    "Assignment Card tiene versión de contrato",
)

check(
    "self::CONTRACT_VERSION" in card,
    "versión de contrato participa en hash",
)

check(
    "tag No Index → categoría" not in card,
    "eliminada regla antigua de fallback",
)

check(
    "la categoría es contexto, nunca fallback" in card,
    "Assignment Card alineada con RC1.7.4",
)

check(
    "'post_status' => 'pending'" in post,
    "estado pending preservado",
)

check(
    "hash_equals(hash('sha256', $post_content)" in post,
    "verificación de contenido almacenado preservada",
)

print("RC175_GUTENBERG_CONTRACT_OK")
