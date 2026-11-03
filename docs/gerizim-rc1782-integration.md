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
