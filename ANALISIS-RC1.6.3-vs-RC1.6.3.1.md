# Análisis RC1.6.3 → RC1.6.3.1

## Baseline

RC1.6.3 funcional reconciliada desde el artefacto histórico validado:

- commit/tag canónico: `f587772c02ff820825c49ee006f94726f5e0dc4c` / `v0.4.0-RC1.6.3`;
- SHA-256 del ZIP histórico: `23857d910e8a28e84a14cd8ffd46fcaadc7a90e7dbfcfc39a225f650c0d60644`.

RC1.6.3.1 se considera **no aprobada** y solo sirve como evidencia técnica.

## Qué amplió RC1.6.3.1

RC1.6.3.1 cambió seis archivos productivos: bootstrap, cliente OpenAI, investigación web, pipeline de planificación, pipeline de redacción y orquestador de etapas.

El cambio dejó de ser una corrección HTTP acotada porque introdujo para las generaciones de texto:

- `background=true` por defecto;
- persistencia de `response_id` y resultados completados dentro del workflow;
- polling por GET `/v1/responses/{id}`;
- Action Scheduler / eventos de polling;
- reentrada de acciones editoriales;
- slots para material fragmentado;
- nuevos marcadores `planning_pending`, estados de background y marcadores de reanudación;
- retornos `pending` y nuevas ramas en planificación/redacción/orquestación.

Esto cambió la semántica operativa que en RC1.6.3 era síncrona y visible tras cada clic. La prueba humana mostró precisamente la consecuencia: backend completado mientras la pantalla permanecía en la etapa anterior hasta recargar.

## Qué sí era útil de RC1.6.3.1

La parte estrictamente reutilizable es la observabilidad del cliente:

- `X-Client-Request-Id` único;
- status HTTP explícito;
- `x-request-id` de OpenAI cuando existe;
- distinción transporte vs. HTTP;
- metadata segura de error, sin necesitar prompts ni artículo en el diagnóstico.

## Corrección mínima elegida

RC1.6.3.2 vuelve a la semántica de RC1.6.3 y modifica únicamente `IDG_OpenAI_Client`:

1. sigue haciendo el mismo POST síncrono;
2. si recibe un cuerpo que es una Response `completed` válida y tiene texto, lo acepta aunque el status HTTP sea anómalo;
3. si no hay contenido recuperable, expone HTTP + referencias precisas en lugar de `Error no identificado desde OpenAI API`;
4. no hace GET, polling, retry, scheduler ni reentrada.

## Límite conocido

El incidente RC1.6.3 demostró en OpenAI Platform que la generación SEO sí se completó, pero RC1.6.3 no persistió el status, headers ni cuerpo HTTP recibido por WordPress. Por eso no puede probarse retrospectivamente si WordPress recibió el cuerpo completed con un status anómalo o si un intermediario sustituyó completamente la respuesta. Esta candidata resuelve el primer caso sin llamadas extra y vuelve diagnosticable el segundo. Recuperar un cuerpo que nunca llegó a PHP exigiría otra llamada o una arquitectura distinta, lo cual queda deliberadamente fuera de esta corrección.
