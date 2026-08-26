# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.5

RC1.6.5 cierra la migración progresiva mediante eliminación controlada de infraestructura temporal sin consumidores funcionales.

- Se retira `IDG_Workflow_Admin_Support`, wrapper introducido durante RC1.6.4 que no tenía consumidores runtime.
- El plugin principal deja de cargar por segunda vez la vista y la fachada administrativas; `class-admin-page.php` conserva su carga autónoma para compatibilidad con pruebas y consumidores aislados.
- Se eliminan del orquestador consultas a `is_known_action()` y `automatic_retry_limit()` cuyos resultados se descartaban y no producían efectos.
- `IDG_Admin_Page` permanece como fachada compatible.
- Se preservan las delegaciones privadas usadas por las pruebas de snapshot del Reinicio parcial Radar.
- Se preservan contratos, adaptadores, estrategias, políticas, pipelines y formato `legacy-array-v1`.
- No cambian prompts, interfaz, ocho llamadas OpenAI, validaciones, publicación, Actualizaciones recurrentes ni trazabilidad.

RC1.6.4 continúa siendo la versión productiva aprobada mientras RC1.6.5 completa sus pruebas y validación humana.
