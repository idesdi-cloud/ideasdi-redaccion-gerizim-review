# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.7.2

## Alcance validado

- `./scripts/test.sh` ejecuta sintaxis PHP, suite PHP, comprobaciones canonical RC1.7.1, las comprobaciones RC1.7.2 y el manifiesto SHA-256 de la release.
- `tests/rc172-seo-alignment.py` verifica la semántica SEO liberada: keyword o variante suficientemente reconocible, y preservación de tesis editorial, Caja, H1, H2 y estructura. No la sustituye por comprobaciones SHA.
- `tests/rc172-seo-alignment.py` y `tests/rc172-release-integration.py` comparan los siete archivos de producción protegidos con el baseline `72d659acc9e9079d9112a1f04405a5525591fe3d`.
- `tests/rc172-release-integration.py` comprueba versión, DB version inalterada, cableado no recursivo, manifiesto sin autorreferencia y su cobertura RC1.7.2.

## Documentación afectada

Se crean este documento y `CAMBIOS-v0.4.0-RC1.7.2.md` por el cambio de versión, trazabilidad y circuito de pruebas. No se actualizan contrato Radar, documentación de trazabilidad ni documentación operativa: sus contratos, esquema y operación no cambian.
