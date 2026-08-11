# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.3.2

Corrección mínima basada en RC1.6.3. RC1.6.3.1 queda no aprobada y no es baseline de esta candidata.

## Cambio productivo

Solo cambia el cliente OpenAI, además del número de versión:

- se mantiene `POST /v1/responses` síncrono;
- no se activa `background`;
- no hay polling, GET de Response, reentrada ni estados nuevos del workflow;
- no se agregan reintentos ni llamadas extra;
- se añade `X-Client-Request-Id` único por llamada;
- los fallos HTTP muestran status y referencia correlacionable (`x-request-id` cuando está disponible);
- los fallos de transporte se distinguen explícitamente;
- si el cuerpo recibido es inequívocamente una Response OpenAI `completed`, con `response_id` y texto utilizable, el contenido no se descarta aunque el status HTTP recibido sea anómalo;
- cuando el cuerpo no es JSON utilizable, el diagnóstico seguro conserva tamaño y SHA-256 sin depender de exponerlo en el mensaje.

## Invariantes preservados

- prompts sin cambios;
- ocho puntos de llamada OpenAI;
- misma secuencia síncrona de RC1.6.3;
- misma UX y transiciones de generar → editorial → SEO;
- mismas validaciones, publicación, Gutenberg y trazabilidad;
- sin cambios en Radar ni en la publicación vinculada.
