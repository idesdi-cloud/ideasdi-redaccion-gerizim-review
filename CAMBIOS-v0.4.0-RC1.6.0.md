# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.0

## Alcance

Primera fase de migración progresiva hacia contratos y orquestación. La versión es estructural y deliberadamente conservadora.

## Implementado

- Contrato `IDG_Workflow_Input_Adapter_Contract` para entradas de workflow.
- Contrato `IDG_Workflow_Orchestrator_Contract` para creación, guardado, cola y ejecución.
- `IDG_Workflow_Contract` con formato compatible `legacy-array-v1`.
- Adaptadores transparentes para:
  - interfaz administrativa;
  - Radar;
  - Actualizaciones recurrentes;
  - trazabilidad;
  - compatibilidad legacy.
- Registro central de adaptadores por origen.
- `IDG_Workflow_Orchestrator` como fachada delgada sobre `IDG_Job_Runner`.
- Hook de Action Scheduler y ejecución inmediata conectados al orquestador.
- Entradas de formulario, Radar y Actualizaciones recurrentes conectadas a sus adaptadores.

## Compatibilidad

- No se cambia el array de workflow ni se añade una envoltura de persistencia.
- No se migran opciones existentes.
- `IDG_Job_Runner::new_workflow()`, `get_workflow()`, `save_workflow()` y `process_scheduled_action()` permanecen disponibles.
- Las acciones desconocidas siguen delegándose al runner y conservan su semántica histórica.
- La base de datos de trazabilidad permanece en `1.2.0`.
- El contrato Radar/Directus permanece en `1.1`.

## Sin cambios editoriales intencionales

Permanecen byte a byte los archivos protegidos de prompts, interfaz, cliente OpenAI, reglas editoriales, planificación, receta, validación, guard final y publicación. El total de puntos de llamada a OpenAI continúa en ocho.
