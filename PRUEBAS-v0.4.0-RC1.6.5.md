# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.5

Estado: validada funcionalmente mediante prueba humana en WordPress.

- `./scripts/test.sh`: `VALIDACION_GERIZIM_OK`.
- 92 archivos PHP revisados sin errores de sintaxis.
- 41 pruebas PHP aprobadas.
- Prueba específica: `tests/rc165-legacy-cleanup-equivalence.php`.
- Prueba específica de importación Radar: `tests/radar-category-mapping-mock.php`.
- Mapeos verificados: Arquitectura e interiores, Diseño de producto, Diseño digital y 3D, Movilidad y transporte, Concursos y convocatorias y Moda.
- Regresión editorial RC1.6.5: `REGRESION_EDITORIAL_OK`.
- Exactamente ocho llamadas `->complete(` preservadas.
- `IDG_Workflow_Admin_Support` eliminado sin referencias runtime.
- Las tres delegaciones privadas de snapshot Radar permanecen en `IDG_Admin_Page`.
- Formato `legacy-array-v1` preservado.
- Adaptadores Admin, Radar, Actualizaciones recurrentes y Trazabilidad preservados.
- `git diff --check` sin errores.
- RC1.6.5 queda funcionalmente aprobada; la trazabilidad Git separa el commit funcional probado del cierre documental de la release.

- Prueba humana WordPress: aprobada.
- Commit probado: `d3a0bc3abb701456af2679190cf410803ed66d12`.
- ZIP probado SHA-256: `22ff48663ca8f9fa657901db00a6a22bec4e2f19927c587c53508abb221b80c0`.

## Cierre Git

- Commit funcional probado: `d3a0bc3abb701456af2679190cf410803ed66d12`.
- ZIP probado SHA-256: `22ff48663ca8f9fa657901db00a6a22bec4e2f19927c587c53508abb221b80c0`.
- Head final de `rc/0.4.0-RC1.6.5`: `02db8d7419e8a4e2b03dd089d28a9242cd304088`.
- Merge aprobado y publicado en `main`: `2b3eb6f35796499d4b49caa3092e57fab172e956`.
- Tag `v0.4.0-RC1.6.5` permanece asociado al commit funcional probado.
