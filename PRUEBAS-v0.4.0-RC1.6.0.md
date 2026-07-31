# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.0

## Equivalencia de entradas

`tests/workflow-adapters-equivalence.php` verifica que cada adaptador devuelve el mismo array, con el mismo orden, tipos, claves y valores.

## Equivalencia del orquestador

`tests/workflow-orchestrator-equivalence.php` compara ejecución directa y orquestada para:

- creación y persistencia inicial;
- actualización de un workflow existente;
- sesión activa del usuario;
- acciones no conocidas;
- diagnóstico de workflow ausente.

## Protección editorial

`tests/rc160-acceptance.php` valida:

- versión y carga de contratos;
- integración de formulario, Radar y Actualizaciones recurrentes;
- delegación del orquestador al runner legado;
- permanencia de la API histórica;
- hashes SHA-256 de archivos protegidos;
- ocho puntos de llamada a OpenAI;
- seis referencias a la biblioteca de prompts dentro del runner;
- esquema de trazabilidad 1.2.0 sin migración.

Los hashes de referencia están en `REGRESION-EDITORIAL-RC1.6.0.sha256`.

## Regresión heredada

También deben pasar las pruebas RC1.5.6 y RC1.5.7, incluidos mocks de captura, outbox, recaptura, esquema, cliente, URL, logger y observabilidad.
