from pathlib import Path

root = Path(__file__).resolve().parents[1]

bootstrap = (
    root / "ideasdi-redaccion-gerizim.php"
).read_text(encoding="utf-8")

post = (
    root / "includes/class-post-creator.php"
).read_text(encoding="utf-8")

guard = (
    root / "includes/class-final-guard.php"
).read_text(encoding="utf-8")

admin = (
    root / "includes/class-admin-page.php"
).read_text(encoding="utf-8")

prompt = (
    root / "includes/class-prompt-library.php"
).read_text(encoding="utf-8")

rules = (
    root / "includes/class-editorial-rules.php"
).read_text(encoding="utf-8")

contract = (
    root / "includes/class-reel-contract.php"
).read_text(encoding="utf-8")


def check(value, label):
    if not value:
        raise RuntimeError(label)

    print("OK", label)


check(
    "class-reel-contract.php" in bootstrap,
    "bootstrap carga contrato Reel",
)

check(
    bootstrap.index("class-reel-contract.php")
    < bootstrap.index("class-post-creator.php"),
    "contrato carga antes de Post Creator",
)

check(
    "normalize_reel_package" in post,
    "Post Creator usa normalización Reel",
)

check(
    "IDG_Reel_Contract::normalize" in post,
    "Post Creator consume contrato compartido",
)

for forbidden in [
    "build_deterministic_reel_package",
    "reel_context(",
    "reel_overlays_for_scene",
    "fit_words(",
]:
    check(
        forbidden not in post,
        "eliminado generador determinista: "
        + forbidden,
    )

check(
    "IDG_Reel_Contract::inspect" in guard,
    "Final Guard consume contrato compartido",
)

check(
    "IDG_Reel_Contract::inspect" in admin,
    "Admin consume contrato compartido",
)

check(
    "reel_package_postprocessed" in admin,
    "Admin prioriza Reel postprocesado",
)

check(
    "$rules = class_exists('IDG_Editorial_Rules')" in admin,
    "Admin inicializa reglas antes del informe Reel",
)

check(
    "'válido'" in admin
    and "'requiere revisión'" in admin,
    "Admin distingue válido / requiere revisión",
)

check(
    "Overlay n.1:" in prompt
    and "Overlay n.3:" in prompt,
    "prompt define overlays por escena",
)

check(
    "No añadas escenas, VO, overlays" in prompt,
    "prompt impide estructura extra",
)

check(
    "Overlay n.1, n.2 y n.3" in rules,
    "regla editorial refleja estructura Reel",
)

check(
    "cta_in_final_vo" in contract,
    "contrato valida CTA en VO final",
)

check(
    "overlay_counts" in contract,
    "contrato valida overlays por escena",
)

check(
    "expected_positions" in contract,
    "contrato valida posiciones overlay por escena",
)

check(
    "overlay_max_chars" in contract,
    "contrato aplica máximo de caracteres",
)

check(
    "text_length" in contract,
    "contrato tiene fallback de longitud Unicode",
)

check(
    "Version: 0.4.0-RC1.7.5"
    in bootstrap,
    "sin version bump funcional",
)

check(
    "define('IDG_VERSION', '0.4.0-RC1.7.5');"
    in bootstrap,
    "IDG_VERSION permanece RC1.7.5",
)

check(
    "serialize_blocks($blocks)" in post,
    "RC1.7.5 Gutenberg preservado",
)

check(
    "parse_blocks($post_content)" in guard,
    "Final Guard Gutenberg preservado",
)

print("RC176_REEL_STATIC_OK")
