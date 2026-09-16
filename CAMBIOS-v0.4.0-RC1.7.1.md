# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.7.1

RC1.7.1 finaliza la integración determinista de `editorial.canonical` 1.1.0. La versión del plugin y `IDG_VERSION` pasan a `0.4.0-RC1.7.1`; `IDG_TRACEABILITY_DB_VERSION` permanece en `1.2.0`.

La proyección y sus consumidores liberados se conservan sin cambios funcionales adicionales. En particular, se mantienen SEO/Yoast, los enlaces interno y externo, Reel, Gutenberg, creación de entradas, trazabilidad, outbox, recaptura, reintentos y los contratos vigentes.

## Flexibilizaciones estructurales de canonical 1.1.0

Estas son exactamente las tres rigideces estructurales relajadas:

1. Los dos párrafos de introducción dejan de ser una obligación rígida: son una preferencia no rígida.
2. Se elimina la cuota fija de seis a siete encabezados H3.
3. Se elimina el mínimo universal de dos párrafos por cada H3.

La regla de keyword de la Caja es distinta y no forma parte de esas tres flexibilizaciones: no se exige ni se prohíbe comenzar la Caja con la keyword. La Caja continúa después de la introducción, tiene 40–55 palabras y conserva carácter primordialmente factual conforme a canonical 1.1.0; no repite la tesis editorial ni adelanta el análisis del cuerpo.

## Regresión y trazabilidad

- Se integran `tests/rc171-canonical-fidelity.py` y `tests/rc171-editorial-consumption.py` en `scripts/test.sh`.
- `tests/rc171-release-integration.py` comprueba el cableado RC1.7.1 sin invocar recursivamente `scripts/test.sh`.
- `REGRESION-EDITORIAL-RC1.7.1.sha256` es un manifiesto sin autorreferencia. Conserva como entrada el manifiesto RC1.7.0 y no modifica RC1.7.0 ni los manifiestos legacy.
- Se reconcilian solo expectativas de versión, pin canonical 1.1.0 y huellas de los consumidores ya liberados.
