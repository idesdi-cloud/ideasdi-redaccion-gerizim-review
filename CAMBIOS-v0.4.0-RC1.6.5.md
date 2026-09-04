# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.5

RC1.6.5 cierra la migración progresiva mediante eliminación controlada de infraestructura temporal sin consumidores funcionales.

- Se retira `IDG_Workflow_Admin_Support`, wrapper introducido durante RC1.6.4 que no tenía consumidores runtime.
- El plugin principal deja de cargar por segunda vez la vista y la fachada administrativas; `class-admin-page.php` conserva su carga autónoma para compatibilidad con pruebas y consumidores aislados.
- Se eliminan del orquestador consultas a `is_known_action()` y `automatic_retry_limit()` cuyos resultados se descartaban y no producían efectos.
- `IDG_Admin_Page` permanece como fachada compatible.
- Se preservan las delegaciones privadas usadas por las pruebas de snapshot del Reinicio parcial Radar.
- Se preservan contratos, adaptadores, estrategias, políticas, pipelines y formato `legacy-array-v1`.
- No cambian prompts, interfaz, ocho llamadas OpenAI, validaciones, publicación, Actualizaciones recurrentes ni trazabilidad.
- Se corrige de forma aditiva el mapeo de importación Radar para que `Arquitectura e interiores` resuelva la categoría WordPress `Arquitectura y diseño interior`, conservando los aliases existentes.
- Se añade regresión específica de las seis categorías editoriales principales importadas desde Radar.

RC1.6.5 es la versión productiva aprobada y validada en WordPress; esta corrección mantiene la misma versión y ajusta únicamente el mapeo de categorías importadas desde Radar.
