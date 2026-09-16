# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.7.2

RC1.7.2 es un candidato determinista de empaquetado sobre el baseline `72d659acc9e9079d9112a1f04405a5525591fe3d`. La cabecera del plugin e `IDG_VERSION` pasan a `0.4.0-RC1.7.2`; `IDG_TRACEABILITY_DB_VERSION` permanece en `1.2.0`.

No introduce lógica de producción. En particular, permanecen byte-idénticos al baseline los prompts, el validador, el final guard, creación de entradas/Yoast/Reel, enlaces, contexto y proyección canonical. Se conservan Gutenberg, trazabilidad, outbox, recaptura, reintentos y el contrato Radar–Gerizim 1.1.

## Regresión y empaquetado

- Se integran `tests/rc172-seo-alignment.py` y `tests/rc172-release-integration.py` en `scripts/test.sh`.
- La prueba SEO conserva las aserciones semánticas de keyword flexible/variante reconocible y de preservación de tesis, Caja, H1, H2 y estructura; el límite de empaquetado contrasta los archivos protegidos contra el baseline.
- `REGRESION-EDITORIAL-RC1.7.2.sha256` continúa la cadena desde RC1.7.1, sin autorreferencia.
- La reconciliación histórica queda cerrada en siete rutas; solo se actualizan huellas y expectativas de versión necesarias para RC1.7.2.
