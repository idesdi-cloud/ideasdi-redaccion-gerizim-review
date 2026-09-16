# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.7.1

## Alcance validado

- `./scripts/test.sh` ejecuta sintaxis PHP, la suite PHP, las dos comprobaciones Python de canonical 1.1.0, la integración RC1.7.1 no recursiva y la verificación SHA-256 del manifiesto de la release.
- `tests/rc171-canonical-fidelity.py` comprueba literalmente la proyección `editorial.canonical` 1.1.0 y su pin `0bc762b7666ffead0c54dbe83ecddc281ed4a9f2e7bdbaaf61406b3e921c9acf`.
- `tests/rc171-editorial-consumption.py` comprueba que los cuatro consumidores editoriales liberados expresan esa semántica.
- `tests/rc171-release-integration.py` confirma versión, DB version inalterada, inclusión de las pruebas y ausencia de autoejecución del runner.
- El manifiesto RC1.7.1 no se incluye a sí mismo y conserva bajo huella el manifiesto RC1.7.0 y los componentes regresivos del conjunto.

## Semántica editorial cubierta

Las comprobaciones distinguen expresamente las tres flexibilizaciones estructurales: introducción de dos párrafos como preferencia no rígida, ausencia de cuota fija de 6–7 H3 y ausencia de mínimo universal de dos párrafos por H3.

La keyword de la Caja no pertenece a esa lista: la Caja no exige ni prohíbe abrir con la keyword. Se verifica separadamente que conserva su posición después de la introducción y su función factual según canonical 1.1.0.

## Documentación afectada

Se crean este documento y `CAMBIOS-v0.4.0-RC1.7.1.md` porque cambian versión, circuito de pruebas y trazabilidad de release. No se actualizan contrato Radar, especificaciones de trazabilidad ni documentación operativa: no cambian sus contratos, esquema DB ni operación.
