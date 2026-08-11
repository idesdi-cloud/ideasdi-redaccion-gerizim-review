# Contrato Radar/Directus · trazabilidad 1.1

Estado: congelado para Gerizim `0.4.0-RC1.6.3.2`. RC1.6.3.2 no modifica este contrato ni implementa cambios en Directus.

## Transporte

`POST` HTTPS, `Content-Type: application/json`, encabezado `X-Radar-Traceability-Token`.

HTTP se admite únicamente para localhost o un entorno de desarrollo autorizado explícitamente. El token no puede aparecer en respuestas locales, opciones, workflows, post meta, outbox, HTML, logs o excepciones.

## Eventos

Únicos tipos:

- `gerizim_imported`;
- `wordpress_post_created`;
- `wordpress_published`.

Orden causal obligatorio:

```text
gerizim_imported
        ↓
wordpress_post_created
        ↓
wordpress_published
```

## Payload

Campos comunes:

- `event_type`;
- `brief_id`;
- `occurred_at`;
- `observed_at`;
- `source_system`;
- `source_record_id`;
- `workflow_id`;
- `wordpress_post_id`, cuando aplica;
- `wordpress_status`, cuando aplica;
- `idempotency_key`;
- `evidence_payload.contract_version = 1.1`;
- `actor`.

`observed_at` no cambia la identidad. `occurred_at` sí forma parte del payload contractual inmutable.

## Claves

```text
gerizim_imported:{brief_id}:{workflow_id}
wordpress_post_created:{workflow_id}:{post_id}
wordpress_published:{workflow_id}:{post_id}
```

## Respuesta de evento nuevo

HTTP 201:

```json
{
  "ok": true,
  "result": "traceability_event_recorded",
  "event_type": "wordpress_post_created",
  "idempotency_key": "wordpress_post_created:idg_xxx:12345",
  "event_id": 789,
  "brief_id": 123,
  "already_existed": false
}
```

## Respuesta idempotente

HTTP 200:

```json
{
  "ok": true,
  "result": "traceability_event_already_recorded",
  "event_type": "wordpress_post_created",
  "idempotency_key": "wordpress_post_created:idg_xxx:12345",
  "event_id": 789,
  "brief_id": 123,
  "already_existed": true
}
```

`result` es el campo oficial. `code` se acepta temporalmente solo cuando `result` no existe. Ambos resultados anteriores son éxito.

La respuesta debe devolver exactamente la misma `idempotency_key`. Una clave ausente o distinta es `response_idempotency_key_mismatch` y nunca marca la fila como `sent`.

## Errores

- 400: validación, no reintentar.
- 401/403: autenticación o autorización, no reintentar.
- 409: contradicción contractual real, no reintentar automáticamente.
- 429: temporal, reintentar.
- 500–599: temporal, reintentar.
- timeout o red: temporal, reintentar.

Un 409 se reserva para la misma clave con payload distinto, identidad incompatible, post contradictorio, cambio de `occurred_at` u orden causal inválido. `traceability_event_already_recorded` nunca es conflicto.

## Estado WordPress

La entrada creada por Gerizim permanece en `pending`. El evento es `wordpress_post_created` con `wordpress_status = pending`. No existe `wordpress_draft_created` en el contrato 1.1.

## Exclusiones

No participan workflows manuales, posts normales, Actualizaciones recurrentes, eventos históricos ni las identidades excluidas documentadas en la especificación RC1.5.6 y preservadas en RC1.6.0.
