# Integración Gerizim 0.4.0-RC1.7.8.2

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

Se corrige la visibilidad de la excepción usando salida SEO disponible, sin exigir validación aprobada. El informe verificado se limita a INFORME SEO INTERNO, preserva el artículo y distingue las declaraciones del modelo. Validación de estos ajustes: lint PHP y regresión dirigida aprobados en el host; pendiente contrastar la identidad final del candidato. El REVIEW_READY anterior corresponde a la ejecución previa a estos ajustes. Sin cambio de versión, commit, release ni WordPress.


### Preparación de entrega RC1.7.8.3

Esta sección actualiza el estado del candidato descrito arriba: versión RC1.7.8.3 preparada, sin liberar. Se actualiza la expectativa de versión y se verifica el snapshot histórico con su HEAD aislado, conservando sus seis pruebas y el commit histórico exacto. Nuevo manifiesto RC1.7.8.3; manifiestos anteriores intactos. Se ejecuta una comprobación completa porque cambiaron estos controles. Sin commit, push, ZIP ni cambios en WordPress.

Resultado de comprobación de entrega: código 1. Log: rc1783-delivery-validation.log en el workspace. Pendientes: revisar identidad final y autorizar liberación; si falló, diagnosticar solo las pruebas fallidas.


### Reconciliación de pruebas heredadas

Los nueve fallos de entrega se reprodujeron en el baseline RC1.7.8.2. Se fijan sus conteos comprobados: 9 puntos OpenAI y 7 puntos de construcción de prompts; no se permiten incrementos. La comprobación de no escritura permite exclusivamente preflight_public y prepared_payload_hash. El mock de estrategias incorpora save_workflow y comprueba rechazo, cero escrituras y trazabilidad del override recurrente. Las versiones de los tres prompts modificados pasan a RC1.7.8.3. Resultado dirigido: 9/9 aprobados. No se retiraron casos ni se repitió la suite completa. Pendientes: revisar diff final y autorizar liberación.
