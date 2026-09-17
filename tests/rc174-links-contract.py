from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

guard = (ROOT / "includes/class-final-guard.php").read_text(encoding="utf-8")
post = (ROOT / "includes/class-post-creator.php").read_text(encoding="utf-8")
prompt = (ROOT / "includes/class-prompt-library.php").read_text(encoding="utf-8")
internal = (ROOT / "includes/class-internal-links.php").read_text(encoding="utf-8")

def check(value, label):
    if not value:
        raise RuntimeError(label)
    print("OK", label)

check("frase contextual de 3 a 8 palabras" in prompt, "prompt anchor 3-8")
check("frase contextual de 3 a 6 palabras" not in prompt, "sin contrato 3-6")

check(
    "El enlace externo obligatorio aparece más de una vez." in guard,
    "guard duplicación externa",
)

check(
    "El anchor del enlace interno debe tener entre 3 y 8 palabras." in guard,
    "guard longitud interna",
)

check(
    "El enlace interno no puede usar la keyword principal exacta como anchor." in guard,
    "guard keyword exacta",
)

check(
    "El enlace interno no puede usar el nombre literal del tag como anchor." in guard,
    "guard tag literal",
)

check("anchor_word_count" in guard, "helper conteo")

start = post.index("private static function ensure_internal_links")
end = post.index(
    "private static function deduplicate_configured_internal_links",
    start
)
ensure = post[start:end]

check(
    "if (!self::is_event_workflow($workflow))" in ensure,
    "article sin rescate PHP",
)

check(
    "contextual_internal_anchor" not in post,
    "sin helper semántico hardcodeado",
)

for phrase in [
    "mirada sobre el automóvil contemporáneo",
    "vestuario listo para moverse",
    "decisiones de diseño sostenible",
    "procesos de modelado tridimensional",
    "relación entre moda y rendimiento",
    "relación entre atmósfera y uso",
    "transformación de interiores académicos",
    "adaptación del espacio existente",
    "formas de habitar",
    "experiencia de audio",
    "mirada editorial de movilidad",
    "lectura editorial de la moda",
    "lectura espacial del diseño",
    "relación entre objeto y uso",
]:
    check(phrase not in post, "sin hardcode: " + phrase)

start = guard.index(
    "private static function resolved_official_source_url"
)
end = guard.index(
    "private static function event_presentation_status",
    start
)

check(
    "source_information_url" not in guard[start:end],
    "fuente documental no sustituye responsable",
)

check(
    "source_information_url es solo fuente documental/complementaria" in prompt,
    "separación documental",
)

check(
    "editorial_resolution_status" in internal
    and "return [];" in internal,
    "unresolved sin fallback",
)

check(
    "Las categorías son contexto; no son destinos alternativos." in internal,
    "categoría no fallback",
)

print("RC174_LINKS_CONTRACT_OK")
