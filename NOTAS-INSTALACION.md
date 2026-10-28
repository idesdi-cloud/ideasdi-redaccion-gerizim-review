# Instalación · ideasDi Redacción Gerizim

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
