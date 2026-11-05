# Integración Gerizim 0.4.0-RC1.7.8.2

## Candidato RC1.7.8.5.1 — estabilización Honda RN0, no liberado ni instalado

Sobre el baseline autorizado `fa4b3ce4488cc4c24459e6f907b726c27af5a34d`, este candidato reconcilia de forma determinista una respuesta editorial completa con el documento por bloques existente: conserva forma, orden, IDs y enlaces Markdown exactos; ante estructura ambigua, URL o anchor alterados conserva el documento previo y registra la respuesta cruda con diagnóstico específico. Los parches siguen siendo alternativa válida. El panel permite continuar SEO desde ese documento seguro, pero exhibe la revisión editorial como rechazada y disponible para revisión manual, no como aprobada.

SEO solo puede reconciliar un artículo completo cuando inserta una única URL responsable canónica faltante dentro de prosa existente, sin modificar P2 ni otros anchors. La prioridad es `canonical_context.responsible_official_url`, luego `responsible_official_url` y finalmente `official_source`. Metadatos, informe, social, Reel y proyecciones siguen en `editorial_document`; H1 continúa siendo únicamente el título WordPress.

La regresión `tests/rc17851-honda-regression.php` usa el texto base inmutable del caso Honda RN0/brief 230 y respuestas expresamente simuladas (no la salida histórica del modelo). Verifica revisión completa y localizada, sin cambios, URL insegura rechazada sin overwrite, navegación SEO desde base segura, P2 conservado, metadata/Reel, Gutenberg y un único `pending` mediante mock. También verifica el circuito excepcional sin SEO: nonce, permiso y hash actual del documento; una confirmación obsoleta no escribe y el único `pending` permitido conserva la marca de revisión manual. Se añade una vez a `scripts/test.sh`. Ejecución local final: `./scripts/test.sh` terminó `VALIDACION_GERIZIM_OK`, incluidos contrato de versión, regresión Honda, regresión histórica protegida y manifiesto SHA-256. QA WordPress real, ZIP, release, instalación y aceptación humana siguen pendientes.

Documentos actualizados: este registro y `NOTAS-INSTALACION.md`, pues cambian contrato, versión, pruebas y candidatura. No se actualizan manifiestos RC1.7.8.5 históricos, informes de release ni handoff: no hubo release, artefacto, producción ni cambio de contrato Radar.

Fecha de modificación: 2026-11-03. Baseline aislado: `6599ef23a5bff85f7c62159335cef6b7e5dc7ccc`. Esta intervención no afirma herencia del workspace anterior ni un estado formal de revisión. No hubo red, IA real, WordPress real, ZIP, commit, tag, push, release ni despliegue.

## Comportamiento integrado

- Generación y revisión editorial validan estructura de fase antes de declararse completas. La validación no afirma una comprobación factual exhaustiva. Editorial registra `changed` o `unchanged` por comparación real y no adjudica cambios inexistentes.
- Los tres prompts consumen el mismo contexto canónico, ficha documental y de encargo, plan aplicado, recetas/biblioteca, reglas, resolución P2, caja y Reel. El contrato Diseñador Traductor se reafirma después de instrucciones editables.
- SEO conserva por separado la respuesta del modelo, ejecuta como máximo una reparación localizada por parches identificados, contabiliza su uso y valida el Gutenberg real mediante `preflight_public()` antes de `seo_completed`. Guarda hash y contenido exacto del payload; la escritura normal rechaza ausencia o divergencia del sello.
- Reel se inspecciona con el contrato real: seis escenas, tres overlays por escena, VO 1–5 de 14 palabras y CTA VO6 literal. La reparación admite únicamente VO inválidas y conserva overlays y VO correctas byte a byte.
- La excepción editorial no convierte `validation.ok=false` en aprobación. Solo `write_allowed=true` para un pending nuevo, con errores exclusivamente omitibles, confirmación explícita, nonce, permiso, usuario, workflow y hash actual. Se consume en un intento, nunca modifica destinos existentes o recurrentes y guarda errores, usuario, hashes, sello y revisión manual en metadatos e historial.
- P2 unresolved sin URL inventada puede ser omitible. URL interna no autorizada, URL externa incoherente, entidad/destino sustituido, integridad Gutenberg, guard ausente y error desconocido siguen cerrados.
- Para Diseño digital se exige un solo enlace externo contextual a la URL responsable autorizada, con atribución correcta y anchor distinto de la keyword; P2 usa solo el tag real resuelto.

## Estados y trazabilidad

`model_response` significa únicamente texto devuelto por el cliente. `validation` es el resultado de validadores y preflight. `write` solo existe después de `wp_insert_post`; una excepción mantiene `validation_ok=false`, `overridden=true` y `manual_review_required=true`. Una salida inválida se conserva editable con diagnóstico y estado `failed`/`requires_intervention`, nunca `processing` ni `seo_completed`.

## Pruebas y comandos

El harness funcional dirigido es `tests/rc1782-targeted-regression.php`. Usa exclusivamente variantes de SkyPaint199 y ActionForge217, mocks WordPress y transporte local, y ejecuta métodos reales de pipeline, guard, creator y estrategias. No usa búsquedas estáticas del código como aceptación ni imprime un estado formal de revisión.

Comandos usados en el cierre de esta modificación:

```text
php tests/rc1782-targeted-regression.php
php -l <archivos PHP afectados>
git diff --check
git diff --stat
git status --short --branch
```

Resultado del candidato fuente: runner SUCCESS y aceptación externa 1/1, confirmados por workspace.json y coincidencia del árbol dcb8322bff94329275f99feadcf11dd0d32489ad. El informe previo fue escrito antes del cierre externo; los fallos iniciales de helpers no son el resultado final.

Revisión final acotada: Diseño digital no recibe autolinks técnicos; ninguna categoría enlaza automáticamente la keyword exacta. El guard audita todos los anchors externos mediante IDG_Internal_Links y conserva el enlace patrocinado explícitamente configurado como contrato separado. Un externo adicional no autorizado bloquea la excepción. El estado del artículo se calcula sin los errores Reel; la aprobación conjunta no se relaja. No se solicita reparación sin segmentos permitidos. El fixture SkyPaint199 conserva caja factual39 y P2 unresolved, con datos Casey_Sheep/Superhive del brief existente. Los nuevos negativos se derivan únicamente de ActionForge217.

VALIDACION_DIRIGIDA_FINAL_OK: harness funcional y lint PHP aprobados por el cierre externo.

La comprobación externa final corre una sola vez el harness y lint apropiado. No se ejecuta suite histórica/general ni generaciones completas, web o WordPress de producción. No hay aceptación humana Gutenberg/Yoast.

## Documentación afectada

Se actualizan este documento y `INFORME_RC1.7.8.2.txt`, que son los documentos canónicos expresamente requeridos para el correctivo. No se modifican `editorial.canonical`, Lead, manifiestos SHA-256, suites históricas, handoffs, notas de instalación ni documentos de release: no hubo cambio canónico editorial, instalación, empaquetado, promoción o aceptación humana.

## Candidato RC1.7.8.3 — preventivo correctivo, aún no liberado

Baseline: `37da08bb394bc787e9f18f2caa7afcaf4774de93`. Este candidato sustituye el intento rechazado `chg-20261103T182815Z-4029f000`; no reutiliza su workspace. El estado instalado permanece en RC1.7.8.2. No hay cambio de versión, ZIP, commit, tag, despliegue, WordPress ni aprobación humana.

Objetivo y causa: prevenir que generación pidiera una caja de 40–55 palabras sin exigir el rótulo literal que valida la fase; evitar que Prompt_Data redujera el workflow antes de resolver P2 y perdiera `primary_lens`; y evitar que la UI presentara una salida existente como una fase aprobada. La prevención conserva la recuperación localizada existente: máximo un intento, parches restringidos, VO correctas y overlays intactos, y un parche inválido no aprueba.

Cambios del candidato: el contexto compartido exige en las tres fases `Caja editorial` literal, párrafo factual 40–55 después de la introducción y antes de H3, sin enlaces/negritas; preserva caja válida y limita la corrección al defecto de fase. Prompt_Data resuelve `IDG_Internal_Links::normalize()` una vez sobre el workflow completo y transmite el array exacto al formateador, con unresolved explícito y sin fallback de categoría. El contrato Reel instruye el mismo conteo Unicode real del validador. SEO reemplaza en el informe la afirmación del modelo sobre validaciones por los resultados verificados de preflight y Reel. La interfaz distingue salida disponible de validada, bloquea la continuación SEO de editorial inválida y muestra diagnóstico concreto cuando SEO existente no puede crear entrada ni usar excepción.

Pruebas verificadas en este candidato: `php tests/rc1782-targeted-regression.php` pasó; conserva SkyPaint199 y ActionForge217, incluida la reparación Reel inválida y la reparación localizada única; añade resolución P2 completa, informe verificado y recorrido generate/editorial/SEO válido con mocks, una llamada por fase, caja/Gutenberg/URLs/tesis preservados. `php -l` de los PHP modificados y `git diff --check` también pasaron. Pendiente: revisión humana del diff y QA manual WordPress; no se ejecutará la suite completa por sus desajustes históricos RC1.7.8.1/HEAD ya documentados.

Límite de fixtures: SkyPaint199 y ActionForge217 son mocks disponibles. XREAL219 y BorisFX221 no tienen fixtures reales confirmados; no se declara procedencia ni se añade investigación. Siguiente acción: ejecutar la validación dirigida local, revisar diff funcional y documental y, si resulta limpia, dejar el candidato para revisión humana; no declarar QA WordPress ni liberación.


### Ajustes de revisión del candidato

## RC1.7.8.4 — correctivo focalizado en workspace aislado

Sin cambio de versión, commit, ZIP, release, despliegue ni WordPress real. `preflight_public()` selecciona SEO y, solo para una excepción confirmada, revisión editorial o artículo base si SEO falta; deja el faltante expresamente en el guard y no genera ni persiste una salida SEO, Reel, meta ni sello ficticios. La excepción conserva `pending`, revisión manual, errores ignorados, usuario, identidad de workflow, hash/payload y fase fuente. Un candado atómico de opción por workflow evita doble inserción; recurrentes, destinos existentes, permisos/nonce inválidos e integridad Gutenberg continúan cerrados.

Los faltantes de enlace/anchor autorizado se distinguen de URLs internas o externas no autorizadas: solo el faltante recuperable puede acompañar la excepción; URL, identidad o escritura no se omiten. La caja normaliza de forma determinista el rótulo `**Caja editorial**` con espacios Markdown y negritas tipográficas del cuerpo, sin cambiar palabras, enlaces ni número de párrafos; el guard y Gutenberg reciben la misma representación. No hubo IA real ni reparación adicional.

Prueba dirigida nueva: `php tests/rc1784-targeted-regression.php`, reutilizando SkyPaint199 y ActionForge217 del harness RC1.7.8.2 y añadiendo Brief222/224 locales: excepción hasta `wp_insert_post` y metadatos, ausencia de SEO, caja Markdown, permiso/nonce, URLs no autorizadas, Gutenberg e idempotencia. También se ejecutan lint de PHP afectado y `git diff --check`. Pendiente: QA humano y WordPress/Gutenberg real; no se declara aceptación ni release. No requieren cambio otros documentos canónicos: no hubo contrato Radar, prompt, versión, artefacto ni circuito de producción modificado.

### Segunda revisión RC1.7.8.4

La salida de generación y editorial pasa por una normalización reutilizable antes de `Final_Guard`: convierte únicamente el rótulo Markdown `**Caja editorial**  ` en `Caja editorial` y elimina negrita tipográfica dentro de su único párrafo. No reescribe palabras, enlaces ni límites de párrafo, por lo que el guard conserva la decisión de 40–55 palabras, posición, unicidad, ausencia de enlaces/HTML y Gutenberg. En SEO esa operación queda limitada a `ARTÍCULO FINAL`; los informes y Reel no se transforman.

Antes de validar editorial y SEO se comparan los enlaces Markdown realmente presentes en el artículo fuente contra la URL oficial autorizada y el P2 tag resuelto. Una ausencia solo se restaura si conserva exactamente el anchor y existe una única aparición en un párrafo normal fuera de la caja y sin enlaces; de otro modo se conserva el contenido y se falla cerrado con diagnóstico para revisión humana. El SEO vuelve a pasar por el mismo preflight HTML/Gutenberg después de esa restauración. No se solicita reparación IA para formato ya normalizado ni para una pérdida de enlace sin contexto seguro.

Resultados locales verificables: `php tests/rc1784-targeted-regression.php` y `php tests/rc1782-targeted-regression.php` pasan con mocks. RC1.7.8.4 cubre dos espacios Markdown tras la etiqueta, prosa de caja inalterada, cadena base→editorial→SEO de URL oficial/P2, fallo sin recuperación inequívoca, Brief222 rechazado por SEO con excepción controlada, Brief224 sin SEO, nonce/permisos, idempotencia, destino recurrente y escritura `pending`/metadatos por estrategia. Límite: es integración de rutas con mocks WordPress; no constituye UI renderizada completa ni E2E en WordPress/Gutenberg real. No se requirieron otros documentos: no cambiaron versión, contrato Radar, prompt, release, ZIP ni operación productiva.

Se corrige la visibilidad de la excepción usando salida SEO disponible, sin exigir validación aprobada. El informe verificado se limita a INFORME SEO INTERNO, preserva el artículo y distingue las declaraciones del modelo. Validación de estos ajustes: lint PHP y regresión dirigida aprobados en el host; pendiente contrastar la identidad final del candidato. El REVIEW_READY anterior corresponde a la ejecución previa a estos ajustes. Sin cambio de versión, commit, release ni WordPress.


### Preparación de entrega RC1.7.8.3

Esta sección actualiza el estado del candidato descrito arriba: versión RC1.7.8.3 preparada, sin liberar. Se actualiza la expectativa de versión y se verifica el snapshot histórico con su HEAD aislado, conservando sus seis pruebas y el commit histórico exacto. Nuevo manifiesto RC1.7.8.3; manifiestos anteriores intactos. Se ejecuta una comprobación completa porque cambiaron estos controles. Sin commit, push, ZIP ni cambios en WordPress.

Resultado de comprobación de entrega: código 1. Log: rc1783-delivery-validation.log en el workspace. Pendientes: revisar identidad final y autorizar liberación; si falló, diagnosticar solo las pruebas fallidas.


### Reconciliación de pruebas heredadas

### Preparación de versión RC1.7.8.4 — 2026-11-04

El RELEASE funcional previo está comprometido en `18f0a1435814262df3c43d22c121ea1b072bd493`.
Esta intervención propone únicamente la nueva versión `0.4.0-RC1.7.8.4` en este
workspace aislado; todavía no es un release. Se actualizaron de forma coherente el
header, `IDG_VERSION` y el literal esperado por `plugin-version-contract.py`, y se
generó un manifiesto editorial nuevo con hashes de los archivos candidatos finales.

Se validaron el contrato de versión, el manifiesto con `sha256sum -c`, lint del
bootstrap, las regresiones RC1.7.8.4 y RC1.7.8.2, y `scripts/test.sh` una sola vez.
Antes de construir cualquier ZIP aún faltan revisión del resultado completo y una
fase autorizada sobre un commit limpio y conocido; no hubo commit, ZIP, instalación,
WordPress, despliegue ni release.

### Revisión final de enlaces RC1.7.8.4

### Incidente administrativo y correctivo RC1.7.8.4.1 — 2026-11-04

Se observó inestabilidad administrativa y una UI alterada el 4 de noviembre; el plugin quedó desactivado. El código instalado coincidía con Git. Las advertencias PHP de WP-Optimize no se vinculan causalmente al incidente. Sí está probado un defecto local: el render GET podía ejecutar dos veces `should_show_force_draft()` y, por tanto, `preflight_public()` en workflows con salida SEO (una vez en los demás casos). El vínculo causal entre ese coste y la caída no está probado.

RC1.7.8.4.1 corrige el render para usar solo elegibilidad estructural O(1): fuente base/editorial/SEO, sin draft existente, sin recurrente y sin SEO validado. No ejecuta preflight, Gutenberg, escrituras ni mutaciones en GET; el POST conserva la validación estricta y las protecciones existentes. El aviso aclara que es un intento sujeto a validación del servidor. Se añadió prueba funcional por ReflectionMethod, sin afirmar E2E WordPress.

## Candidato RC1.7.8.5 — editorial_document v1, no liberado ni instalado

El workflow nuevo conserva un único `editorial_document` autoritativo dentro del array histórico ya guardado en `wp_options`; no crea tablas ni migraciones. Su `schema_version=1` contiene bloques ordenados con IDs estables, hash, revisión, SEO (`%%title%%`), enlaces autorizados, copy social, Reel, origen y estado de revisión. `base_article`, `editorial_result` y `seo_result` continúan como proyecciones de compatibilidad y no sustituyen al documento.

Los workflows sin documento siguen el recorrido legacy sin mutación. Generación crea el documento; Editorial y SEO admiten eco idéntico o parches explícitos con ID y hash, conservan IDs y registran cambios reales; una respuesta inesperada falla cerrada. El serializer tipado entrega el H1 solo como título de WordPress, H2/H3 en cuerpo y una sola caja `featured-snippet-box`, sin postprocesar o reparar prosa. GET administrativo no recibe nuevas rutas ni trabajo de preflight.

La prueba local `tests/rc1785-integration.php` reutiliza el scaffolding aislado RC1782 y ejecuta Generate → Editorial → SEO → Pending con `IDG_Workflow_Redaction_Pipeline`, `IDG_Job_Runner`, cliente Responses en cola y escrituras WP en memoria. Usa fixtures sintéticos derivados de los briefs Radar 227 (AccuFACE 2/Reallusion), 224 (Nike x LEGO) y 222 (Character Creator 5.2/Reallusion), sin llamarlos exportaciones reales ni evaluación de IA. Verifica el manifiesto actual ID/hash en los prompts, parches, sello SEO, guard final, payload byte-idéntico, caja/H1/Reel, rechazo fail-closed y legado. Limitaciones: no sustituye QA WordPress/Gutenberg real, no demuestra calidad factual ni cubre administración E2E; 199/217/219/221 permanecen en sus suites históricas existentes.

Este correctivo no tuvo QA WordPress, ZIP, release ni despliegue; no se realizaron operaciones en WordPress.

### Corrección controlada RC1.7.8.5

La continuación expone un manifiesto compacto y determinista solo en Editorial/SEO: ID, tipo, nivel, ordinal, SHA-256 exacto del texto actual (listas unidas por `|`) y vista truncada. El pipeline falla cerrado antes de llamar al modelo si el documento está malformado, duplica IDs o el artículo está desfasado. SEO marca H1/H2/H3/caja como protegidos. El único cambio de prosa permitido es un parche textual explícito con ID existente y `EXPECTED_SHA256`; se rechazan ID inexistente, hash obsoleto, URL/anchor distinto, encabezados y caja en SEO. Un eco editorial idéntico y SEO sin prosa nueva siguen siendo válidos. La proyección conserva la retroalimentación Gerizim además de metadatos y Reel para que el guard final valide la salida completa. El serializer excluye el único H1 de `post_content` y lo entrega como título. El sello de preflight incorpora `document_hash`, por lo que cualquier cambio posterior invalida la escritura. Reel persiste como texto normalizado e inspeccionado, sin reducir VO u overlays. La integración local RC1785 valida estas reglas con mock de serialización WordPress; no prueba WordPress/Gutenberg real, administración E2E, producción ni una liberación.

Se corrigieron dos defectos: editorial y SEO vuelven a preservar enlaces aprobados después de cada parche localizado y recalculan la validación sobre el contenido exacto; el comparador URL solo ignora mayúsculas en esquema/host y conserva sensibles a mayúsculas ruta, query, fragmento y slash final, fallando cerrado ante URLs ambiguas. Las regresiones dirigidas cubren ambos casos, restauración segura y rechazo sin contexto; RC1782 continúa incluido. La integridad manual y las pruebas del candidato previo fueron verificadas antes de este tercer modify. Cobertura restante: mocks locales, sin UI/E2E real de WordPress/Gutenberg. RELEASE/DEPLOY no realizados.

### Cuarto correctivo controlado RC1.7.8.5 — integridad URL+anchor

La huella de enlaces de los parches Editorial y SEO conserva, en orden y sin normalización, el texto visible Markdown y la URL completa. Las regresiones locales comprueban el rechazo de cambio de anchor con el mismo href, cambio de href con el mismo anchor y altas/bajas de enlaces, además de permitir sólo cambios de prosa con pares intactos y eco idéntico. La cobertura sigue siendo mock local, no QA ni instalación WordPress/Gutenberg vivo; no hubo release, ZIP ni despliegue.

Los nueve fallos de entrega se reprodujeron en el baseline RC1.7.8.2. Se fijan sus conteos comprobados: 9 puntos OpenAI y 7 puntos de construcción de prompts; no se permiten incrementos. La comprobación de no escritura permite exclusivamente preflight_public y prepared_payload_hash. El mock de estrategias incorpora save_workflow y comprueba rechazo, cero escrituras y trazabilidad del override recurrente. Las versiones de los tres prompts modificados pasan a RC1.7.8.3. Resultado dirigido: 9/9 aprobados. No se retiraron casos ni se repitió la suite completa. Pendientes: revisar diff final y autorizar liberación.
