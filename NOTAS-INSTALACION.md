# Instalación · ideasDi Redacción Gerizim

## Candidato RC1.7.8.5 — NO INSTALAR

RC1.7.8.5 es un candidato no liberado ni instalado. Introduce `editorial_document` schema v1 en el array existente del workflow, sin tabla ni migración: los expedientes legacy se leen sin mutación y las proyecciones históricas siguen disponibles. Editorial y SEO reciben un manifiesto actual de IDs y hashes de bloque; si está desfasado, malformado o contiene IDs duplicados, el pipeline no llama al modelo. La transición solo ocurre al ejecutar una etapa de un documento nuevo; no hay ZIP ni operación WordPress autorizada.

La regresión local RC1.7.8.5 también verifica que cada parche conserve exactamente, en orden, URL y anchor visible Markdown: rechaza cambios de anchor/href y enlaces añadidos o eliminados, y permite prosa no relacionada con el par intacto. Es cobertura con mocks; no acredita QA WordPress/Gutenberg vivo, instalación, release ni despliegue.

El siguiente gate es revisión humana del diff, las pruebas locales y QA manual autorizada de Gutenberg/administración. Riesgos abiertos: la integración usa mocks y no acredita la fidelidad factual de la prosa ni una instalación. La nota temporal siguiente se conserva: el 4 de noviembre se observó RC1.7.8.4.1 activo y 239/239 archivos coincidentes; esa coincidencia no prueba causalidad con el incidente.

## Incidente administrativo RC1.7.8.4.1 — 2026-11-04

Se observó inestabilidad y una UI alterada; el plugin quedó desactivado y el código instalado coincidía con Git. Las advertencias PHP de WP-Optimize no están vinculadas causalmente. El defecto confirmado era trabajo duplicado de preflight durante el render administrativo; no se ha probado que causara la caída. El correctivo usa elegibilidad estructural barata en GET y conserva la validación POST.

RC1.7.8.4.1 no tiene QA WordPress, ZIP, release ni despliegue. No instalar.

## RC1.7.8.4 — propuesta de cambio de versión; NO INSTALAR

El código fuente funcional ya está incorporado; en esta intervención solo se
propone el bump de versión a RC1.7.8.4. No se ha construido ZIP, instalado en
WordPress ni autorizado DEPLOY. La reversión deberá usar un respaldo real del
plugin efectivamente instalado, observado en WordPress; no debe suponerse que
RC1.7.8.3 sea la versión activa.

## RC1.7.8 — incorporación Git completada; NO INSTALAR SIN DEPLOY AUTORIZADO

RC1.7.8 ya está fusionada en Git `main` por PR #1: merge
`502ba021bda324fbb360046e3e705ecd8835a16a`, con commit funcional
`bdb133d412d948734cf2fed3691c584e73bda4af` y 20 rutas verificadas.
La construcción del ZIP oficial desde Git limpio está autorizada, pero aún no
se ha ejecutado ni registrado su SHA-256. No hay tag ni instalación RC1.7.8.
Los ZIP `PRE_RELEASE_NO_INSTALAR` y los bundles de REVIEW no pueden instalarse.
El RELEASE y el DEPLOY son fases separadas.

**Procedimiento futuro sujeto a autorización DEPLOY independiente:**

1. REVIEW, commit, push y merge excepcionales ya completados y verificados
   (PR #1, árbol `4296351340ee26847bae3c2138c9276b3cb13bfc`).
   Controlled Change `chg-20261027T213048Z-47bd9e74` sigue en NEEDS_ATTENTION:
   esta incorporación no lo libera ni implica autorización de despliegue.
2. Desde commit aprobado, limpio y conocido, ejecutar `./scripts/test.sh` y
   `./scripts/build-zip.sh`; validar ZIP oficial, versión interna y SHA-256.
3. Antes de instalar: comprobar la versión **realmente activa** en WordPress,
   exportar ZIP/directorio completo del plugin vigente y respaldar base de datos.
   No usar RC1.6.2 o RC1.6.5 como reversión por defecto; confirmar el ZIP previo.
4. Instalar manualmente el ZIP aprobado mediante el mecanismo autorizado de WordPress;
   no desplegar directamente desde el VPS ni alterar configuración de trazabilidad.
5. Verificar versión RC1.7.8, acceso admin, workflows existentes, importación Radar,
   reinicio parcial y recorridos ordinarios sin degradación de datos.
6. Validar creación de borrador `pending`, título H1, Gutenberg, meta Yoast `%%title%%`,
   caja editorial 40–55 y enlaces P2 únicamente canónicos; ensayar rechazos de enlaces
   extra en artículos, Eventos, concursos recurrentes y patrocinados.
7. Verificar visibilidad del aviso patrocinado en expedientes antiguos con
   `sponsor_client` o `sponsored_required_link`, `sponsored_visible_label` activo,
   incluso si falta `piece_type`. Confirmar P2 sin permisos implícitos por estos campos.
8. Confirmar Reel 6 escenas / 3 overlays / VO6 CTA fijo y conservación del ID
   al actualizar eventos recurrentes; evitar publicar contenido de prueba accidentalmente.
9. En caso de fallo, suspender generación, restaurar copia exacta del plugin que estaba
   instalado previamente y verificar integridad y estados. Conservar backup de la BD;
   cualquier reversión de esquema requerirá análisis y autorización específicos.
10. Registrar fecha, persona que verificó, commit, tag si existe, hashes ZIP,
    resultados de pruebas y decisión de aceptación. No declarar aprobado antes.

---

## Historial de instalación RC1.6.3.2 (solo referencia; no ejecutar para RC1.7.8)

## Actualización controlada

1. Respaldar el directorio completo del plugin activo y la base de datos.
2. Instalar el ZIP RC1.6.3 reemplazando únicamente el plugin.
3. Confirmar versión **0.4.0-RC1.6.3.2**.
4. No modificar `wp-config.php`, constantes de trazabilidad ni datos reales.
5. Abrir un workflow existente y confirmar que conserva campos, historial y resultados.
6. Crear un workflow de prueba y recorrer Guardar → Generar → Revisión editorial → SEO → Borrador.
7. Confirmar que el borrador ordinario continúa en estado `pending`.
8. Probar una Actualización recurrente y confirmar que conserva el mismo ID.
9. Verificar Reinicio parcial e importación Radar sin modificar producción fuera de la prueba autorizada.

RC1.6.3.2 no migra datos ni cambia el formato persistido. La separación es interna: planificación y redacción usan contratos y adaptadores transparentes, mientras publicación, validaciones y workflows continúan con el comportamiento anterior.

## Validación técnica incluida

### Candidato RC1.7.8.5 (continuación controlada)

No instalar todavía. El documento editorial autoritativo ahora separa el H1 para `post_title`, deja el cuerpo Gutenberg sin H1 y sella el hash documental dentro del payload aprobado. Editorial y SEO aceptan eco idéntico o parches explícitos por ID y SHA-256; SEO rechaza reemplazos completos, mutaciones de URL y cambios en encabezados o caja. El Reel conserva la normalización completa de `IDG_Reel_Contract` y su validez. `tests/rc1785-integration.php` es un mock local (no QA WordPress vivo) de Generate → Editorial → SEO → Pending con Responses en cola y writes en memoria; cubre los fixtures sintéticos 227, 224 y 222, manifiesto de prompt, serialización, sellos, parches y legado. No hubo ZIP, instalación, despliegue ni release.

```bash
php tests/workflow-planning-redaction-equivalence.php
php tests/workflow-planning-redaction-routing-static.php
php tests/rc163-acceptance.php
php tests/workflow-adapters-equivalence.php
php tests/workflow-orchestrator-equivalence.php
php tests/workflow-action-strategy-center-equivalence.php
php tests/workflow-policies-equivalence.php
php tests/workflow-policies-routing-static.php
php tests/workflow-strategy-runner-routing-static.php
php tests/rc162-acceptance.php
php tests/rc161-acceptance.php
php tests/rc160-acceptance.php
php tests/recurring-contest-selection-token-mock.php
php tests/recurring-contest-structural-apply-mock.php
php tests/recurring-contest-editorial-routing-static.php
php tests/plugin-load-smoke.php
php tests/traceability-static.php
php tests/rc157-acceptance.php
```

## Reversión

1. No borrar workflows, entradas ni filas de trazabilidad.
2. Restaurar el respaldo completo de RC1.6.2.
3. Confirmar la versión restaurada.
4. No revertir el esquema de base de datos: RC1.6.3 no introduce migraciones.
5. Los workflows creados o procesados en RC1.6.3 siguen usando `legacy-array-v1` y permanecen compatibles.
