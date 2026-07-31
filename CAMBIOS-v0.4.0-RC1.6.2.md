# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.2

## Objetivo

Continuar la migración progresiva con una única fuente técnica para las políticas operativas del workflow, sin introducir cambios editoriales intencionales ni alterar el formato histórico `legacy-array-v1`.

## Políticas centralizadas

Se incorpora `IDG_Workflow_Policies`, que concentra:

- estados `draft`, `processing`, `completed` y `failed`;
- catálogo de las siete acciones existentes;
- relación entre acciones normales y forzadas;
- pasos de validación equivalentes;
- transiciones documentadas entre estados;
- límite histórico de 20 eventos;
- bloqueo de mutaciones interactivas mientras el workflow está en `processing`;
- política de reintento editorial manual, sin reintentos automáticos nuevos;
- condiciones de avance y sus mismos códigos históricos de bloqueo.

## Integración

- `IDG_Workflow_Contract` consulta el catálogo central de acciones.
- El centro de estrategias consume las constantes y la política de override.
- `IDG_Job_Runner` delega inicialización, procesamiento, éxito, fallo, límite histórico y decisión de reintento.
- El panel administrativo consulta una sola política para acciones, bloqueos y requisitos previos.
- El orquestador mantiene su delegación al runner y declara la política de cola sin implementar lógica editorial.

## Compatibilidad preservada

- No se añaden claves al workflow persistido.
- No hay migración de base de datos.
- Las siete acciones mantienen nombres y orden.
- Las acciones forzadas mantienen sus eventos y mensajes.
- Las acciones desconocidas conservan el no-op histórico.
- Los errores continúan visibles para reintento manual.
- La creación normal permanece en `pending`.
- Actualizaciones recurrentes continúan escribiendo sobre el mismo ID protegido.

## Fuera de alcance

Los reportes entregados se conservan como evidencia para mejoras posteriores de redacción, enlaces, validación de H3, metadescripciones y paquete reel. Esta RC no cambia prompts, reglas editoriales, validadores, interfaz, Gutenberg ni publicación.
