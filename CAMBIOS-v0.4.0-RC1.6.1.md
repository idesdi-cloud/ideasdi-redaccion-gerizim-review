# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.1

## Objetivo

Continuar la migración progresiva mediante un **centro de estrategias de acciones**, retirando del `IDG_Job_Runner` la selección directa de etapas sin cambiar el comportamiento editorial ni el formato histórico de workflows.

## Arquitectura incorporada

- Contrato `IDG_Workflow_Action_Strategy_Contract`.
- Centro `IDG_Workflow_Action_Strategy_Center` como registro único de acciones.
- Estrategias separadas para:
  - generación del artículo base;
  - revisión editorial;
  - revisión SEO;
  - creación de borrador normal o forzada;
  - aplicación recurrente normal o forzada.
- Entradas públicas mínimas del runner que delegan en las funciones históricas privadas de cada etapa.

## Compatibilidad

- Las acciones conservan exactamente sus nombres actuales.
- `draft_force` y `recurring_event_content_force` mantienen las mismas banderas, eventos de historial y mensajes.
- Una acción desconocida sigue siendo un no-op después de marcar el workflow como `processing`, igual que en RC1.6.0.2.
- El orquestador continúa siendo una fachada delgada.
- No se modifica el array persistido en `wp_options`.
- No hay migración de esquema ni de datos.

## Alcance funcional preservado

- Actualizaciones recurrentes de Eventos y Concursos permanecen habilitadas.
- La creación normal de entradas mantiene estado `pending`.
- La aplicación recurrente continúa escribiendo sobre la publicación seleccionada.
- Trazabilidad, outbox, reintentos, contratos Radar y publicación se conservan.

## Fuera de alcance

Los reportes de prueba muestran oportunidades posteriores sobre enlaces, H3, metadescripciones, validadores y paquete reel. RC1.6.1 no cambia redacción, prompts ni validaciones: esas observaciones se reservan para una fase editorial específica.
