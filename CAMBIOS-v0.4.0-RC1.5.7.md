# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.7

## Alcance

RC1.5.7 es una actualización exclusiva de observabilidad del procesador de trazabilidad. No se presenta como corrección demostrada de la causa raíz del caso productivo.

## Procesador de cola

`IDG_Traceability_Outbox::process_queue()` conserva los contadores anteriores y añade:

- IDs y cantidad de candidatos seleccionados;
- IDs reclamados y no reclamados;
- motivo individual del fallo de claim;
- motivo inequívoco de salida;
- error SQL de selección sanitizado.

Los motivos posibles son:

- `schema_not_ready`;
- `delivery_disabled`;
- `delivery_configuration_invalid`;
- `sql_selection_error`;
- `no_candidates`;
- `candidates_not_claimed`;
- `claim_verification_failed`;
- `completed`.

Un claim solo se considera correcto después de que el `UPDATE` afecte la fila, `get_by_id()` la recupere y el `lock_token` coincida.

## Administración

El botón **Procesar cola ahora** guarda temporalmente el resultado para el usuario que ejecutó la acción, conserva la redirección existente y muestra siempre:

- Procesados;
- Enviados;
- Reintentos;
- Fallidos;
- Bloqueados;
- Candidatos;
- Reclamos correctos;
- Reclamos fallidos;
- Motivo.

El transient se elimina después de mostrarlo. El aviso no muestra `sql_error`, tokens, payloads ni cabeceras HTTP.

## Elementos preservados

- consulta de elegibilidad;
- contrato Radar/Directus 1.1;
- esquema de base de datos 1.2.0;
- cliente HTTP;
- claves idempotentes;
- reconciliación;
- máquina de estados;
- creación de posts en estado `pending`;
- exclusiones históricas existentes.

## Limitación

RC1.5.7 permite saber si el worker se detiene por configuración, selección SQL, ausencia de candidatos, fallo del `UPDATE` de claim o fallo al verificar la fila y el lock. La causa raíz productiva solo podrá afirmarse después de observar el resultado real de una ejecución controlada.
