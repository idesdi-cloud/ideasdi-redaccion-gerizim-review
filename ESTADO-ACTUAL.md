# Estado actual · ideasDi Redacción Gerizim

Actualizado: 2026-08-11

## Versión aprobada

- Versión activa y funcionalmente aprobada: `0.4.0-RC1.6.4`.
- RC1.6.4 fue aprobada funcionalmente mediante prueba humana. ZIP probado: `99a89843bd999769267e501a1d4d72aad8e4af63517105bef3fe4dd205d5c64e`.
- `0.4.0-RC1.6.3.1` está descartada y no es baseline.
- Rama de release: `rc/0.4.0-RC1.6.3.2`.
- Commit de release: `a2a4639fe6f44b64e4b8558ac7962442239e6524`.
- Tag: `v0.4.0-RC1.6.3.2`.
- ZIP probado por humano en WordPress SHA-256: `12cc036ec4adcae528eb40a3c7db72544e870d1c056cc9deb8680855be3962c6`.
- ZIP reproducible generado desde el commit SHA-256: `3a9a52089335b4e4c70d05ccff078bcabe7f3883483d0e6bf7f00eb28011f9c8`.
- Los contenidos internos de ambos ZIP fueron comparados y son idénticos.
- WordPress: instalación, activación, Generar artículo base → Versión 1 → Revisión editorial → Versión 2 → Revisión SEO → Versión 3 y publicación real verificadas correctamente el 2026-08-11.

## Decisión sobre RC1.6.3.1

`0.4.0-RC1.6.3.1` está **no aprobada y descartada como baseline**. Introdujo background Responses, polling, Action Scheduler, reentrada y estados operativos que alteraron la UX síncrona. Se conserva solo como evidencia del intento descartado. El commit local de evidencia es `e0b557eb657cd77137f143bf3f620d8759283ec0`; no se publica como release ni se usa para continuar desarrollo.

## Incidente OpenAI cerrado

RC1.6.3 podía terminar mostrando `Error no identificado desde OpenAI API` aun cuando OpenAI Platform registraba la Response como completada. RC1.6.3.2 mantiene la arquitectura síncrona y el mismo número de llamadas, pero mejora el cliente para:

- aceptar una Response `completed` válida con texto utilizable aunque el status HTTP recibido sea anómalo;
- exponer status HTTP y referencias correlacionables cuando no existe contenido recuperable;
- distinguir fallos de transporte;
- no añadir retries, polling, GET, scheduler ni reentrada.

El análisis detallado está en `ANALISIS-RC1.6.3-vs-RC1.6.3.1.md`.

## Invariantes que permanecen protegidos

- prompts sin cambios intencionales;
- ocho puntos de llamada OpenAI;
- UX síncrona de RC1.6.3;
- Generar → V1 → Editorial → V2 → SEO → V3 sin recargas manuales obligatorias;
- validaciones, Gutenberg, publicación, Actualizaciones recurrentes y trazabilidad sin cambios funcionales intencionales;
- contrato Radar/Directus 1.1 y formato de workflow `legacy-array-v1` preservados.

## Límite conocido

Si un intermediario corta una respuesta antes de que el cuerpo llegue a PHP, no es posible recuperar ese cuerpo sin otra llamada o sin cambiar la arquitectura. RC1.6.3.2 deja ese caso diagnosticable, pero deliberadamente no añade reintentos ni procesamiento asíncrono.

## Inicio recomendado para un nuevo chat o agente

Leer, en este orden:

1. `AGENTS.md`;
2. `ESTADO-ACTUAL.md`;
3. `CAMBIOS-v0.4.0-RC1.6.3.2.md`;
4. `PRUEBAS-v0.4.0-RC1.6.3.2.md`;
5. `ANALISIS-RC1.6.3-vs-RC1.6.3.1.md` solo si se necesita el historial del incidente.

No usar RC1.6.3.1 como baseline. Para futuros cambios, partir de main/tag aprobado y mantener la prueba humana de WordPress como condición de aceptación de cambios que afecten el flujo editorial.
