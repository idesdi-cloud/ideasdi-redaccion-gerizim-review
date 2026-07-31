# Instalación · ideasDi Redacción Gerizim v0.4.0-RC1.6.2

## Actualización controlada

1. Respaldar el directorio completo del plugin activo y la base de datos.
2. Instalar el ZIP RC1.6.2 reemplazando únicamente el plugin.
3. Confirmar versión **0.4.0-RC1.6.2**.
4. No editar `wp-config.php` ni modificar las constantes de trazabilidad existentes.
5. No ejecutar SQL manual ni alterar filas de la outbox.
6. Abrir un workflow existente y confirmar que conserva campos, historial y resultados.
7. Crear un workflow manual de prueba y recorrer Guardar → Generar → Revisión editorial → SEO.
8. Importar un JSON Radar de prueba y confirmar que mantiene el flujo y la trazabilidad actuales.
9. Confirmar que la creación ordinaria de entradas WordPress continúa usando estado `pending`.
10. Revisar Gerizim → Ajustes → Trazabilidad Radar y confirmar esquema 1.2.0 y contrato 1.1.

RC1.6.2 no migra datos ni cambia el formato persistido. Los adaptadores siguen transparentes, el orquestador permanece delgado, el centro de estrategias conserva la selección de etapas y la nueva política central solo reúne reglas operativas ya existentes.

## Prueba manual de Concurso o convocatoria

1. Abrir `Gerizim → Actualizaciones recurrentes`.
2. Elegir **Concurso o convocatoria**.
3. Buscar y seleccionar la entrada por su ID exacto.
4. Introducir la fuente oficial y los datos confirmados de la nueva edición.
5. Pulsar **Analizar**.
6. Revisar la comparación y pulsar la acción confirmada para aplicar título, slug, fechas y enlace oficial.
7. Confirmar que WordPress conserva el mismo ID, estado, autor, categoría, etiquetas, imagen, contenido y extracto.
8. Pulsar **Preparar redacción en Flujo editorial**.
9. Recorrer Generar → Revisión editorial → SEO → Aplicar versión final.
10. Verificar que la redacción se escribió sobre la misma entrada y no se creó una publicación nueva.

La actualización estructural no modifica el cuerpo del artículo. La aplicación editorial final sí reemplaza título, contenido y extracto después de las validaciones y de la confirmación humana.

## Validación técnica incluida

```bash
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

1. No borrar ni editar workflows, posts ni filas de `wp_idg_traceability_outbox`.
2. Restaurar el respaldo completo del plugin anterior.
3. Confirmar la versión restaurada en la pantalla de plugins.
4. No revertir el esquema: RC1.6.0 conserva la versión de base de datos `1.2.0` y no ejecuta migraciones nuevas.
5. Los workflows creados con RC1.6.2 siguen usando el formato histórico.
6. Un concurso ya actualizado conserva sus cambios en WordPress; restaurar el plugin no revierte datos. Para deshacer contenido real debe usarse una revisión de WordPress o el respaldo correspondiente.
