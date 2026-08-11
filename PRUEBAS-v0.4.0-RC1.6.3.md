# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.3

## Pruebas nuevas

### `tests/workflow-planning-redaction-equivalence.php`

Comprueba:

- conservación exacta de claves, orden, tipos y valores en los adaptadores de planificación y redacción;
- una sola ejecución de planificación por fase;
- una sola ejecución de redacción;
- uso de la misma instancia del cliente OpenAI entre ambas etapas;
- conservación de la fase solicitada;
- equivalencia del sanitizador, separación de revisión editorial y extracción de retroalimentación.

### `tests/workflow-planning-redaction-routing-static.php`

Comprueba:

- carga de contratos, adaptadores, pipelines y orquestador;
- ausencia de prompts y llamadas al modelo dentro de `IDG_Job_Runner`;
- ausencia de planificación dentro del runner;
- conservación de los prompts de ficha, plan, generación, revisión y SEO;
- ausencia de publicación dentro de planificación y redacción;
- permanencia de la publicación en el runner histórico;
- ocho llamadas OpenAI y seis puntos de construcción de prompts.

### `tests/rc163-acceptance.php`

Verifica versión, contrato `legacy-array-v1`, contrato Radar/Directus 1.1, arquitectura RC1.6.3, hashes protegidos, ausencia de migración, número de llamadas y regresiones heredadas.

## Regresión completa

Se ejecutan todas las pruebas PHP incluidas en el paquete, cubriendo:

- contratos, adaptadores y orquestación RC1.6.0;
- centro de estrategias RC1.6.1;
- políticas centralizadas RC1.6.2;
- separación planificación/redacción RC1.6.3;
- flujos normales y acciones forzadas;
- Eventos y Concursos recurrentes;
- Radar, reinicio parcial y snapshots;
- trazabilidad, outbox, recaptura y observabilidad;
- carga integral del plugin.

## Equivalencia editorial y de publicación

`REGRESION-EDITORIAL-RC1.6.3.sha256` protege interfaz, prompts, cliente OpenAI, reglas, plan, receta, validación, guard final, publicación, panel y Actualizaciones recurrentes.
