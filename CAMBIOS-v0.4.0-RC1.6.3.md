# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.3

## Objetivo

Continuar la migración progresiva separando técnicamente la planificación documental/editorial de la redacción, sin modificar el comportamiento editorial ni el formato histórico `legacy-array-v1`.

## Arquitectura incorporada

### Contratos compatibles

- `IDG_Workflow_Planning_Pipeline_Contract` define la preparación documental y del plan editorial.
- `IDG_Workflow_Redaction_Pipeline_Contract` define las fases `generate`, `editorial` y `seo`.
- `IDG_Workflow_Stage_Input_Adapter_Contract` define fronteras internas transparentes.
- `IDG_Workflow_Stage_Orchestrator_Contract` expone una fachada estable entre el runner y las nuevas etapas.

### Adaptadores transparentes

- `IDG_Planning_Workflow_Stage_Input_Adapter`.
- `IDG_Redaction_Workflow_Stage_Input_Adapter`.

Ambos inspeccionan y devuelven el mismo array, sin añadir, eliminar, renombrar ni reordenar campos.

### Planificación desacoplada

`IDG_Workflow_Planning_Pipeline` concentra, en el mismo orden histórico:

1. investigación web controlada;
2. ficha documental temporal;
3. unificación de ficha cuando hay varios fragmentos;
4. receta base;
5. plan editorial aplicado.

### Redacción desacoplada

`IDG_Workflow_Redaction_Pipeline` concentra:

- generación del artículo base;
- revisión editorial;
- revisión SEO.

El pipeline no crea ni actualiza publicaciones WordPress.

### Orquestador delgado

`IDG_Workflow_Stage_Orchestrator`:

- adapta la entrada de cada frontera;
- crea una sola instancia del cliente OpenAI por fase;
- ejecuta primero planificación y después redacción;
- no contiene prompts, reglas editoriales, validaciones ni publicación.

## Compatibilidad preservada

- `IDG_Job_Runner` conserva su API pública y la persistencia de workflows.
- El centro de estrategias conserva las siete acciones y su enrutamiento.
- Las políticas centralizadas conservan estados, bloqueos y transiciones.
- No se añaden claves obligatorias ni envolturas al workflow.
- No hay migración de base de datos.
- Radar → Gerizim continúa en contrato 1.1.
- Las actualizaciones recurrentes siguen escribiendo sobre el mismo ID.
- La creación normal permanece en estado `pending`.

## Sin cambios editoriales intencionales

Permanecen sin cambios byte a byte:

- prompts;
- interfaz administrativa;
- cliente OpenAI;
- reglas editoriales;
- plan y receta editorial;
- validador y guard final;
- creación y actualización de publicaciones;
- módulo de Actualizaciones recurrentes.

El número total de llamadas a OpenAI permanece en ocho y los seis puntos de construcción de prompts conservan el mismo orden lógico.

## Fuera de alcance

No se incorporan ajustes de redacción, enlaces, H3, metadescripciones, reels ni experiencia visual. Tampoco se elimina código legacy ajeno a la separación de planificación y redacción.
