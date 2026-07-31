# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.1

## Pruebas nuevas

### `tests/workflow-action-strategy-center-equivalence.php`

Verifica para las siete acciones actuales:

- selección de la estrategia correcta;
- una sola ejecución de etapa;
- conservación de `workflow_id`, datos anidados, claves y tipos;
- limpieza del override en acciones normales;
- activación del override y evento histórico exacto en acciones forzadas;
- no-op para acciones desconocidas.

### `tests/workflow-strategy-runner-routing-static.php`

Verifica que:

- el dispatcher del runner delega en el centro;
- ya no contiene la cadena `if/elseif` que seleccionaba etapas;
- existen las cinco familias de estrategias;
- cada familia delega una vez en el motor histórico;
- el plugin carga contrato y centro en el orden requerido.

### `tests/rc161-acceptance.php`

Comprueba versión, arquitectura, mapeos de acciones, compatibilidad de overrides, ocho llamadas OpenAI y hashes de los archivos editoriales protegidos.

## Regresión completa

Se ejecutan todas las pruebas PHP incluidas en el paquete, cubriendo:

- contratos, adaptadores y orquestador RC1.6.0;
- centro de estrategias RC1.6.1;
- actualización recurrente de concursos;
- reinicio parcial e importación Radar;
- trazabilidad, recaptura, outbox, claims y observabilidad;
- creación normal de borradores y actualización de publicaciones existentes;
- carga completa del plugin.

## Equivalencia editorial

`REGRESION-EDITORIAL-RC1.6.1.sha256` conserva los hashes de interfaz, prompts, cliente OpenAI, validador, guard final, reglas, plan y receta. El número de llamadas a OpenAI permanece en ocho.
