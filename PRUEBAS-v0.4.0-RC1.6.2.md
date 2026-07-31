# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.2

## Pruebas nuevas

### `tests/workflow-policies-equivalence.php`

Comprueba:

- nombres y orden de las siete acciones;
- mapeo de acciones forzadas y pasos de validación;
- estados y transiciones equivalentes;
- conservación exacta de los datos al cambiar estado;
- bloqueo exclusivo en `processing`;
- límite histórico de 20;
- ausencia de reintentos automáticos;
- códigos históricos de elegibilidad para generación, revisión, SEO, borrador y actualizaciones recurrentes.

### `tests/workflow-policies-routing-static.php`

Comprueba que contrato, estrategias, runner, panel y orquestador consumen la política central y que ya no duplican estados ni catálogo de acciones en sus puntos anteriores.

### `tests/rc162-acceptance.php`

Verifica versión, arquitectura, ausencia de migración, hashes editoriales protegidos, ocho llamadas OpenAI y presencia de la regresión funcional heredada.

## Regresión completa

Se ejecutan todas las pruebas PHP incluidas en el paquete, cubriendo:

- contratos, adaptadores y orquestación RC1.6.0;
- centro de estrategias RC1.6.1;
- políticas centralizadas RC1.6.2;
- flujos normales y acciones forzadas;
- actualización recurrente de Eventos y Concursos;
- reinicio parcial e importación Radar;
- trazabilidad, recaptura, outbox, claims y observabilidad;
- carga integral del plugin.

## Equivalencia editorial

`REGRESION-EDITORIAL-RC1.6.2.sha256` conserva los hashes de interfaz, prompts, cliente OpenAI, validador, guard final, reglas, plan y receta. El número de llamadas a OpenAI permanece en ocho.
