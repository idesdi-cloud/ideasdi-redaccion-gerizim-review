# Reconciliación Gerizim 0.4.0-RC1.7.8.1

**Estado:** candidato modificado y validado dentro del workspace aislado; sin commit, push, tag, ZIP, RELEASE, PROMOTE, despliegue ni instalación WordPress.

## Identidad y procedencia

- Autorización humana única: `MODIFY` para `chg-20261029T192043Z-3bd72f7c`.
- Baseline limpio: `main` en `9b629b13006f9a9dd0cbbcad6213f97a5a73c551` (RC1.7.8).
- Fuente técnica prioritaria declarada y verificada independientemente: `chg-20261029T181316Z-c1fe9e1b`, workspace `ideasdi-redaccion-gerizim-9b629b13006f-20261029T182004Z-918fce33`, `result_tree_oid fb189c743e0ddb34e61ac6a28ca67f865c492c14`, `patch_sha256 aa38f069267d484b12a79cb8acb2404512efcaacdd1e1cb6777d6c7dda23d37a`, `change_id sha256:236ab639a0b3433e09de02f74961ed0b80cba5178e06bf92037b6bca0c70662e`.
- Referencia limitada para texto, versiones y P2: `chg-20261029T165901Z-f5361f5e`, tree `84c2c4047651c5e3a510a42a81a4949437f38dbd`.
- Los archivos de esos workspaces fuente no estaban presentes dentro del repositorio suministrado. No se atravesó el sandbox ni se leyó ninguna ruta host: el candidato se reconstruyó desde el baseline y la evidencia autorizada.
- Por esa limitación de procedencia, **no se afirma equivalencia binaria** con el workspace fuente. La equivalencia documentada es funcional y ejecutable respecto de los contratos y casos suministrados.

## Reconciliación funcional

1. `IDG_Internal_Links` selecciona primero `primary_lens`, luego `secondary_lenses` en orden y finalmente el primer `post_tag` explícitamente seleccionado y elegible. La fase canónica devuelve `tag_principal`; SkyPaint/Render y cualquier fallback equivalente devuelven `tag_fallback_seleccionado` con `resolved_lens=''`.
2. Solo se consultan IDs de `workflow.tag_ids`, deduplicados en orden. No se usa `get_term_by`, Radar, nombres ni categoría para crear selecciones implícitas. La elegibilidad exige `WP_Term` `post_tag`, nombre y slug exactos, `category_slug` exacto, estado `Index` o `Index / Transversal` y URL HTTP(S) real.
3. Se validan simultáneamente las ternas WordPress autorizadas: 57/Diseño de Producto/`diseno-de-productos`, 1231/Arquitectura y diseño interior/`diseno-interior`, 1238/Moda/`diseno-de-indumentaria`, 1236/Movilidad/`diseno-transporte`, 1232/Diseño digital/`modelado-3d` y 34/Concursos y convocatorias/`concursos-de-diseno`.
4. `category_url` sigue siendo contexto: prioriza `category_curated_url()` cuando devuelve HTTP(S) y usa `get_category_link(ID)` solo para una categoría validada. Nunca se convierte en destino primario P2 ni en fallback de tag.
5. Los prompts system, SEO y Concursos, además de `library_summary()`, describen las dos fases, una sola URL, anchor contextual de 3–8 palabras, prohibición de anchor literal/keyword y revisión editorial cuando ambas fases quedan unresolved. El mensaje `UNRESOLVED` menciona ambas fases y prohíbe inferir o fabricar URL.
6. Las versiones exactas son `generate-v2.2.0-RC1.7.8.1`, `editorial-v2.2.0-RC1.7.8.1` y `seo-v2.2.0-RC1.7.8.1`; los demás IDs de prompt y `IDG_TRACEABILITY_DB_VERSION = 1.2.0` permanecen sin cambios.
7. El override editorial queda cerrado: `override_eligible()` devuelve siempre `false`, el wrapper privado delega en esa política y el admin consulta el `preflight_public()` actual con `force_validation_override=false`, sin inferir permiso desde `last_error` ni `history`.
8. La política separada de eventos conserva archivo CPT/taxonomía real. Una excepción de cuota no autoriza anchors internos extra.

## Pruebas y protecciones

- `php tests/rc1781-skypaint-regression.php`: **69/69**; fixtures WordPress reutilizables, cinco categorías más Concursos, selección canónica/fallback, URLs y auditoría HTML P2.
- `php tests/rc1781-p2-instruction-consistency.php`: **54/54**; ejecuta `IDG_Internal_Links::library()`, `normalize()`, `library_summary()` e `IDG_Prompt_Library::system_prompt()/seo_prompt()` reales sobre los mismos fixtures.
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
