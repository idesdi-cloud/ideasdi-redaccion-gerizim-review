# Gerizim · continuidad editorial y solución del bloqueo SEO del brief 249

**Fecha:** 2026-11-09 (Colombia). **Estado:** diagnóstico sustentado; diseño propuesto, sin implementación ni autorización para cambiar código.  
**Proyecto:** Editorial ideasDi · WordPress / Radar / Gerizim.  
**Caso de regresión:** Radar brief 249, workflow `idg_0ef5a9f8-fd93-4307-bc53-f668245d4694`, EOV Car Simulation (Eugene Melnikov).  
**Código instalado, fuente funcional:** GitHub `5fe107634e7377cad35aec8f6b5269edf1e38ad3` (PR #4 funcional, PR #5 manifiesto, PR #6 harness).  
**Paquete instalado manualmente por el editor:** `0.4.0-RC1.7.8.5.3`; ZIP SHA-256 `5b3e6b2b14218d2ba7081c169e2d6977f48e55d53e1d9322af09b3c8bf8d4e44`, validación local `VALIDACION_GERIZIM_OK`, 253 archivos. Lead observó el plugin WordPress activo; **sin aceptación funcional total**.

## 1. Resultado funcional y evidencia

- Brief 249: Generar artículo base **correcto**; Revisión editorial **correcta**; Revisión SEO **bloqueada**; no se creó entrada WordPress.
- Historial de reporte completo: `seo_validation_failed` con «SEO: no fue posible completar inequívocamente los enlaces autorizados; se conserva el documento previo».
- Lead `gerizim_workflow_diagnostics` (consulta live de solo lectura): `execution.status=failed`, `last_action=editorial`, `editorial.ok=true`; `seo_diagnostics.response_received=true`; componentes recibidos `meta_description`, `seo_report`, `social_copy`, `reel_package`, `feedback_notes`; `validated_components=[]`, `final_preflight=not_run`, `authorized_links.status=incident`. El diagnóstico no expone el subtipo exacto del fallo.
- El informe muestra destino interno elegido `https://ideasdi.com/tag/render/` (Render / Index), lente principal Software 3D y etiqueta Render secundaria. No se detectaron enlaces finales; V3 y entregables SEO persistidos no quedaron aprobados. El texto trata de simulación física y animación en Maya, no principalmente de renderizado.
- El mensaje de UI «Revisión SEO pendiente de corrección: la salida existe, pero el preflight no está aprobado y no admite excepción editorial» **no es la causa primaria**; es consecuencia de que no se ha registrado una validación de SEO recuperable.
- El código de `IDG_Workflow_Redaction_Pipeline::run_seo` sale antes del preflight cuando `IDG_Editorial_Document::complete_missing_authorized_links()` retorna `ok=false`. `IDG_Post_Creator::override_workflow_eligible` exige errores y códigos de validación que esa salida temprana no proporciona; así el botón «Ignorar validación y crear entrada» no aparece. El servidor no debe saltarse comprobaciones de seguridad.
- `complete_missing_authorized_links` distingue `authorized_links_unresolved`, `duplicate_authorized_link`, `missing_unique_authorized_anchor`; el subtipo del caso 249 **no está demostrado** con la evidencia sanitaria disponible. Es hipótesis, no hecho confirmado, que la falta de ancla ligada a Render explique el incidente.
- La matriz `IDG_Priority_Readings::tag_matrix()` contiene Render (Index) en Diseño digital, pero no incorpora Software 3D como fila de esa categoría. Eso **no prueba** que Software 3D sea noindex en WordPress; requiere verificar estado real y correspondencia.

## 2. Decisión editorial que debe gobernar el correctivo

> **La categoría establece el territorio; las etiquetas aportan perspectivas y campos semánticos; el artículo desarrolla ideas de diseño; los enlaces conectan esas ideas con páginas reales y pertinentes de ideasDi.**

- Las etiquetas llegan desde Radar y el editor puede modificarlas en Gerizim. Las etiquetas finales decididas por el editor tienen prioridad; ninguna etapa puede usar silenciosamente selecciones obsoletas de Radar.
- Distinguir **valor semántico** de **elegibilidad como destino SEO**. Una etiqueta `No Index` puede orientar vocabulario y análisis; esa condición no autoriza enlazar su página, pero tampoco la elimina automáticamente del contexto editorial.
- No confundir etiqueta indexable con destino pertinente. En el caso 249, Render era indexable pero su contexto resulta secundario; Software 3D y Animación son perspectivas más relacionadas con el tema, aunque Animación no estaba seleccionada y **no puede agregarse automáticamente**.
- La orientación semántica debe ser **abierta y basada en evidencia**, no una lista de términos obligatorios ni obligación de mencionar literalmente el nombre del tag. Evitar insertos artificiales de enlaces y párrafos de rescate.
- El enlazado se planifica durante la preparación/redacción; SEO verifica, integra de forma localizada o propone resolución, en lugar de descubrir tarde qué idea debería haberse trabajado.
- El mecanismo de excepción ayuda a crear **solo un `pending` nuevo con confirmación y revisión manual**, cuando la causa sea efectivamente recuperable, sin fingir aprobación del preflight ni permitir enlaces ajenos, duplicados, identidad no verificada, contenido peligroso o destinos recurrentes.

## 3. Solución propuesta, priorizada y acotada

**P0 · Recuperación del brief 249.** Conservar respuesta SEO y componentes válidos; persistir `seo_validation` con código de error, etapa, destino requerido y subtipo real cuando falle reconciliación de enlaces; exponer un diagnóstico inteligible en UI. Evaluar una reparación local de links desde el documento guardado (sin nueva llamada al modelo) cuando sea inequívoca, segura y respete contenido/autorización. Si la reparación no es posible, habilitar opción `pending` solo tras reevaluar en servidor la elegibilidad por errores **recuperables**, el hash, nonce, permiso, idempotencia y exclusiones existentes. Una ausencia de enlaces autorizados **no equivale** automáticamente a riesgo de URL falsa, pero no permite saltar la integridad de links ni declarar SEO aprobado.

**P1 · Contexto taxonómico único.** Inspeccionar tags/slug/id/indexabilidad **reales** de WordPress (no inferirlos solo de la matriz), ajustar correspondencias de `IDG_Priority_Readings`, `IDG_Disciplinary_Library` e `IDG_Internal_Links`. Calcular una selección efectiva de etiquetas con precedencia explícita editor Gerizim > importación Radar; exponer roles semánticos y candidatos de enlace independientes. Registrar por qué se elige un destino; descartar candidatos semánticamente impertinentes aunque sean Index. Cuando el editor cambia tags, invalidar solo decisiones derivadas afectadas, sin perder la investigación/artículo.

**P2 · Enlace contextual desde el origen.** Preparar un plan de enlace en la generación, apoyado en el tema y en el vocabulario disciplinar, sin forzar nombres literales del tag. Integrar enlace en la prosa cuando proceda; SEO debe conservar la URL exacta, proponer una edición mínima verificable o devolver una decisión editorial concreta. No reescribir secciones ni inventar hechos para satisfacer cuota de enlaces.

**P3 · Menos controles estériles.** Clasificar incidentes como fallos recuperables de contenido, no-recuperables de integridad, o carencias del harness. No repetir llamadas SEO a ciegas; no perder entregables que puedan conservarse en estado no aprobado; evitar ciclos de RELEASE/DEPLOY hasta prueba dirigida.

## 4. Validación exigida antes de liberar otra versión

1. Reproducir con fixture derivada y **sanitizada** del brief 249: prueba negativa de la reconciliación actual y prueba positiva tras reparación; identificar subtipo exacto sin inventarlo.
2. Verificar selección de tags editor/Radar, estado real WP, etiqueta de alto valor semántico noindex, y rechazo de un link indexable pero semánticamente impropio.
3. Probar ancla natural que no repite nombre del tag, y ausencia de ancla inequívoca sin inventar prosa ni URL.
4. SEO con `final_preflight` válido y Reel validado, o recuperación a `pending` manual sin declarar aprobada la revisión; conservar metadatos válidos, trazabilidad y cero duplicados.
5. Negativos: URL extra/no autorizada, cambio de href o anchor protegido, identidad responsable errónea, documento obsoleto, CSRF/nonce/hash, edición recurrente o entrada existente.
6. Suite histórica completa y verificación de hashes/manifiesto; luego QA manual en WordPress. No tomar los mocks como aceptación humana de producción.

## 5. Continuidad operativa y autorizaciones

- GitHub `main` con el plugin instalado: `5fe107634e7377cad35aec8f6b5269edf1e38ad3`. **La presente actualización documental posterior no está instalada en WordPress** y no es un cambio funcional.
- VPS canónico `/opt/gerizim-ideasdi` observado limpio pero rezagado en `051f0881ed0beb0cbe22cf39b76717b7c055d6dc`. Nunca interpretar `upstream_relation=ALIGNED` de Lead como fetch remoto verificado.
- Change Record anterior `chg-20261109T201020Z-0a83ef17`: `NEEDS_ATTENTION` / continuidad `HOLD` histórico. No reabrirlo ni presentarlo como liberado por los PR #4–#6.
- No se ha autorizado en este encargo ninguna modificación funcional, Codex, RELEASE, DEPLOY ni edición del WordPress activo.
- **Siguiente acción:** congelar un correctivo P0/P1 mínimo con archivos permitidos, pruebas y prohibiciones; consultar a Lead/WordPress en solo lectura si se requiere una evidencia faltante; solicitar autorización explícita de `MODIFY` antes de cambiar PHP. Priorizar una única implementación bien definida y recuperación del mismo workflow 249, no otra investigación general.
- Referencia de conversación: evaluación editorial de etiquetas y enlace contextual, 2026-11-09. El reporte completo original del brief 249 quedó aportado por el editor; no incluir respuestas completas de modelos ni datos privados en repositorio.

## 6. Criterio de éxito editorial

Un artículo sobre animación en Maya puede enlazar naturalmente un concepto pertinente a una página real seleccionada; si la etiqueta Render no aporta a ese argumento, el sistema explica la tensión y propone una opción válida **sin imponerla ni paralizar la operación**. La protección de URLs sigue siendo estricta, la experiencia editorial no.
