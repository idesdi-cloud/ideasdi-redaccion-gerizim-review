# Reconciliación Gerizim 0.4.0-RC1.7.8.1

**Estado:** candidato modificado y validado dentro del workspace aislado; sin commit, push, tag, ZIP, RELEASE, PROMOTE, despliegue ni instalación WordPress.

## Identidad y procedencia

- Autorización humana única: `MODIFY MEDIANTE CONTINUAR` para `chg-20261102T212918Z-59c754e8`.
- Baseline limpio: `main` en `9b629b13006f9a9dd0cbbcad6213f97a5a73c551` (RC1.7.8).
- Fuente heredada exclusivamente por continuidad nativa: candidato `REVIEW_READY chg-20261029T192043Z-3bd72f7c`, workspace `ideasdi-redaccion-gerizim-9b629b13006f-20261029T192318Z-75878abb`, con `expected_source_tree_oid a5024197009c2d8e07cb8ecd23aa6e4048a5117f` y patch SHA-256 `90bcf66be244e49f57d9ee60402104c846d840f2581bac86fbad1ffca74ed1a1`.
- Comparación directa por blob contra `a502419…`: los once archivos heredados coincidían byte a byte antes de la corrección. Tras ella solo cambian las cinco rutas autorizadas: este documento, el manifiesto, `includes/class-internal-links.php` y los dos tests RC1.7.8.1. Los otros seis archivos heredados siguen idénticos a la fuente.

## Reconciliación funcional

1. `IDG_Internal_Links` normaliza individualmente solo los valores explícitos de `workflow.primary_lens` y `workflow.secondary_lenses`, en ese orden, con `IDG_Canonical_Adapter::resolve(['primary_lens' => valor])`; no llama a `IDG_Canonical_Context::resolve()` para P2. Después selecciona el primer `post_tag` explícitamente seleccionado y elegible. La fase canónica devuelve `tag_principal`; SkyPaint/Render y cualquier fallback equivalente devuelven `tag_fallback_seleccionado` con `resolved_lens=''`.
2. `radar_lente_sugerida`, `radar_tag_principal`, `tag_names`, `radar_tags_secundarios` y `lens_suggested` no intervienen en la prioridad P2. Caso Radar reproducido: antes de la corrección, `category_id=1231`, `tag_ids=[206,202]` y sugerencia Radar `Materialidad` resolvía indebidamente `Materialidad` (**FAIL**); después resuelve el primer seleccionado elegible, `Iluminación natural`, como `tag_fallback_seleccionado` y sin lente (**PASS**).
3. Solo se consultan IDs de `workflow.tag_ids`, deduplicados en orden. No se usa `get_term_by`, Radar, nombres ni categoría para crear selecciones implícitas. La elegibilidad exige `WP_Term` `post_tag`, nombre y slug exactos, `category_slug` exacto, estado `Index` o `Index / Transversal` y URL HTTP(S) real.
4. Se validan simultáneamente las ternas WordPress autorizadas: 57/Diseño de Producto/`diseno-de-productos`, 1231/Arquitectura y diseño interior/`diseno-interior`, 1238/Moda/`diseno-de-indumentaria`, 1236/Movilidad/`diseno-transporte`, 1232/Diseño digital/`modelado-3d` y 34/Concursos y convocatorias/`concursos-de-diseno`.
5. `category_url` sigue siendo contexto: prioriza `category_curated_url()` cuando devuelve HTTP(S) y usa `get_category_link(ID)` solo para una categoría validada. Nunca se convierte en destino primario P2 ni en fallback de tag.
6. Los prompts system, SEO y Concursos, además de `library_summary()`, describen las dos fases, una sola URL, anchor contextual de 3–8 palabras, prohibición de anchor literal/keyword y revisión editorial cuando ambas fases quedan unresolved. El mensaje `UNRESOLVED` menciona ambas fases y prohíbe inferir o fabricar URL.
7. Las versiones exactas son `generate-v2.2.0-RC1.7.8.1`, `editorial-v2.2.0-RC1.7.8.1` y `seo-v2.2.0-RC1.7.8.1`; los demás IDs de prompt y `IDG_TRACEABILITY_DB_VERSION = 1.2.0` permanecen sin cambios.
8. El override editorial queda cerrado: `override_eligible()` devuelve siempre `false`, el wrapper privado delega en esa política y el admin consulta el `preflight_public()` actual con `force_validation_override=false`, sin inferir permiso desde `last_error` ni `history`.
9. La política separada de eventos conserva archivo CPT/taxonomía real. Una excepción de cuota no autoriza anchors internos extra.

## Pruebas y protecciones

- `php tests/rc1781-skypaint-regression.php`: **69/69**; conserva la cobertura original y añade la regresión Radar real: sugerencias Radar y nombres heredados no desplazan el fallback de `Iluminación natural`.
- `php tests/rc1781-p2-instruction-consistency.php`: **54/54**; conserva las integraciones fuente y añade sobre el resolver y prompts reales los casos Radar negativo y las tres prioridades explícitas: primaria Materialidad, secundaria tras primaria inválida y primaria Iluminación sobre secundaria Materialidad.
- `python3 tests/rc1781-historical-suite.py`: exporta el baseline exacto RC1.7.8, recupera su `scripts/test.sh` excluido por `.gitattributes`, ejecuta la suite histórica real y confirma los seis casos dirigidos protegidos: **6/6**. Las consultas Git internas son de solo lectura; no hay checkout ni cambios de refs.
- `bash scripts/test.sh`: **114** archivos PHP sin errores y **58** pruebas ejecutadas, incluida la historia protegida; resultado `VALIDACION_GERIZIM_OK`.
- `python3 tests/plugin-version-contract.py 0.4.0-RC1.7.8.1`: `PLUGIN_VERSION_CONTRACT_OK`.
- `sha256sum -c REGRESION-EDITORIAL-RC1.7.8.1.sha256`: **10/10**. El manifiesto no se incluye a sí mismo y no modifica manifiestos históricos.
- `git diff --check`: sin errores.

Permanecen intactos `includes/class-final-guard.php`, `includes/class-reel-contract.php`, `includes/class-canonical-adapter.php`, `includes/class-priority-readings.php`, los tests congelados RC1.7.0/RC1.7.8 y `tests/support/canonical-regression.php`. La suite histórica conserva caja 39/56 rechazada y 40/55 admitida, Reel de seis escenas, VO 1–5 de 14 palabras, VO6 literal y tres overlays por escena de máximo 40 caracteres.

## Rutas del candidato y decisión documental

Las once rutas modificadas o creadas son:

1. `ideasdi-redaccion-gerizim.php`
2. `includes/class-admin-page.php`
3. `includes/class-internal-links.php`
4. `includes/class-post-creator.php`
5. `includes/class-prompt-library.php`
6. `scripts/test.sh`
7. `tests/rc1781-historical-suite.py`
8. `tests/rc1781-p2-instruction-consistency.php`
9. `tests/rc1781-skypaint-regression.php`
10. `docs/gerizim-rc1781-reconciliation.md`
11. `REGRESION-EDITORIAL-RC1.7.8.1.sha256`

Este documento es la trazabilidad canónica afectada por la reconciliación. No se actualizan `CAMBIOS-v0.4.0-RC1.7.8.md`, `INFORME_RC1.7.8.txt`, `ESTADO-ACTUAL.md`, `NOTAS-INSTALACION.md` ni manifiestos históricos porque RC1.7.8.1 no ha sido liberada, promovida, empaquetada, instalada ni aceptada humanamente. No se crea handoff adicional: la fase MODIFY queda cerrada y sus limitaciones están registradas aquí.

## Limitaciones y siguiente puerta

- No hubo prueba humana en WordPress/Gutenberg/Yoast ni staging; no existe staging.
- No se construyó ZIP y no existe SHA-256 de artefacto, commit candidato, tag, despliegue ni aceptación humana RC1.7.8.1.
- Este estado no concede RELEASE, PROMOTE ni DEPLOY. Cualquier commit, ZIP o instalación requiere una autorización humana separada.
