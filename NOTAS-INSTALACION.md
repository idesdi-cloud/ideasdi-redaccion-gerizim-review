# Instalación · ideasDi Redacción Gerizim v0.4.0-RC1.6.3.2

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
