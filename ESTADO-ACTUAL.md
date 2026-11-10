# Estado actual · ideasDi Redacción Gerizim

Actualizado: 2026-11-09 · instalación manual y diagnóstico del brief 249

## Situación operativa al 9 de noviembre de 2026

- **WordPress:** el editor instaló y activó `ideasDi Redacción Gerizim 0.4.0-RC1.7.8.5.3`. Lead confirmó en vivo el plugin activo y 253 archivos instalados. La comparación con el Git local antiguo `051f0881ed0b` detectó 5 cambios y un archivo nuevo, exactamente las 6 rutas esperadas respecto del commit funcional aprobado.
- **Fuente funcional aprobada e instalada:** GitHub `5fe107634e7377cad35aec8f6b5269edf1e38ad3`, PR #4 (funcional), #5 (manifiesto), #6 (regresión harness). ZIP reproducible, suite `VALIDACION_GERIZIM_OK`, SHA-256 `5b3e6b2b14218d2ba7081c169e2d6977f48e55d53e1d9322af09b3c8bf8d4e44`. La igualdad de hashes 253/253 en WordPress **no ha sido demostrada**, y no se declara aceptación funcional completa.
- **Incidente de producción abierto:** brief Radar **249**, workflow `idg_0ef5a9f8-fd93-4307-bc53-f668245d4694`. Generar y Revisión editorial correctos; SEO falló al reconciliar enlaces autorizados, sin preflight final, sin V3 aprobada, sin entrada WordPress y sin botón de excepción. Lead confirmó `authorized_links.status=incident`, respuesta SEO recibida, 5 componentes presentes pero ninguno validado.
- **Criterio editorial adoptado:** la categoría define territorio; las etiquetas aportan lentes y campo semántico, independientemente de `Index/No Index`; los enlaces deben conectar argumentos pertinentes con URLs reales de ideasDi. Las selecciones finales del editor en Gerizim prevalecen sobre las sugerencias heredadas de Radar; no imponer literalidad de etiquetas ni enlazar solo porque una URL sea indexable.
- **Causa comprobada:** `complete_missing_authorized_links` falla y corta `run_seo` antes de guardar una validación estructurada que pueda evaluar el botón `Ignorar validación y crear entrada`. **Subtipo exacto de reconciliación no expuesto**: no presentar la falta de la palabra Render como causa confirmada.
- **Plan breve de solución:** P0 recuperar el flujo preservando componentes SEO, diagnóstico y `pending` seguro si elegible; P1 alinear taxonomías reales, roles semánticos y selección de destinos; P2 anticipar enlace contextual a generación/plan y conservarlo en SEO. Evitar reintentos SEO sin cambios. Aún **sin autorización de cambios funcionales, Codex, RELEASE o DEPLOY**.
- **Continuidad detallada:** [`docs/gerizim-brief249-taxonomias-y-recuperacion-20261109.md`](docs/gerizim-brief249-taxonomias-y-recuperacion-20261109.md). Documento de diagnóstico, límites, verificaciones y siguiente implementación propuesta; no es código ni despliegue.
- **Git local del VPS:** `/opt/gerizim-ideasdi` permanece limpio en `051f0881ed0b`, sin fetch del nuevo remoto; `upstream_relation=ALIGNED` es solo tracking local. La actualización documental de GitHub posterior al ZIP tampoco está instalada en WordPress.
- **Change Record histórico:** `chg-20261109T201020Z-0a83ef17` sigue `NEEDS_ATTENTION` / HOLD. No se modifica ni se considera liberado por los PR excepcionales.

## Registro histórico (información anterior; no interpretar como estado actual)

Actualizado: 2026-11-07 — preparación aislada RC1.7.8.5.3

## Situación registrada entonces · RC1.7.8.5.3

PR #2 fusionado en GitHub main. Commit fuente:
`8135bf8bd18524c984eee46da5f861fbedd6ca6c`.
Commit de integración:
`97c39b432d9666b2d281b63d7314e19edd76c80e`.

El VPS quedó sincronizado con main y limpio. La versión y el manifiesto se prepararon en una rama de trabajo
aislada. Los cambios se conservan en el commit que incorpora este
documento; el ZIP, instalación y aceptación WordPress siguen pendientes.

La implementación conserva el documento editorial, recupera
componentes SEO independientes, diagnostica PATCH rechazados
y separa la reparación del Reel. Los briefs 231 y 247 cuentan
con regresiones locales; el 247 utiliza variantes sintéticas.
No existe aceptación funcional WordPress de esta versión.

El Change Record original
`chg-20261107T130152Z-485922bd` permanece en
NEEDS_ATTENTION. El merge excepcional no cambia ese estado.
La preparación no equivale a RELEASE ni a DEPLOY.

## Registro histórico anterior

Actualizado: 2026-10-27 (postmerge RC1.7.8, previo a ZIP oficial)

## Estado canónico observado e incorporación RC1.7.8

- Baseline de RC1.7.7: `a35beee6e8fab486fab492d446acfba85f887ef9`.
- RC1.7.8 pasó REVIEW y se integró en GitHub `main` mediante PR #1:
  commit fuente `bdb133d412d948734cf2fed3691c584e73bda4af`;
  merge `502ba021bda324fbb360046e3e705ecd8835a16a`;
  árbol Git de las 20 rutas `4296351340ee26847bae3c2138c9276b3cb13bfc`.
- Candidato corregido: 20 rutas identificadas por SHA-256
  `fcf5b8ce023fda13ef293ea9d8c9d1e97d9d54be877bff55be53551662b7d0b2`.
  Suite dirigida, histórica, lint y controles de integridad: PASS en clon aislado del VPS.
  GitHub no registró check-runs CI para dicho commit.
- Construcción reproducible desde commit limpio: fase RELEASE autorizada,
  pendiente de ejecución y de registrar ZIP + SHA-256; aún sin tag/release remoto.
- WordPress permanece en RC1.7.7 según comprobación de sincronización: 222 archivos;
  RC1.7.8 NO ha sido instalada ni aceptada funcionalmente.
- Origen `chg-20261027T213048Z-47bd9e74`: `NEEDS_ATTENTION`, sin gate de RELEASE;
  la incorporación excepcional se autorizó directamente por el editor y no
  modifica ni simula el estado del Controlled Change.
- Documentación: `INFORME_RC1.7.8.txt`, `CAMBIOS-v0.4.0-RC1.7.8.md`,
  `NOTAS-INSTALACION.md`. Despliegue y aceptación WordPress: autorización separada.

## Historial previo conservado (actualización del 2026-08-25)

### Versión aprobada en ese momento

- Versión activa y funcionalmente aprobada: `0.4.0-RC1.6.5`.
- RC1.6.4 fue aprobada funcionalmente mediante prueba humana. ZIP probado: `99a89843bd999769267e501a1d4d72aad8e4af63517105bef3fe4dd205d5c64e`.
- `0.4.0-RC1.6.3.1` está descartada y no es baseline.
- RC1.6.5 fue validada funcionalmente en WordPress mediante prueba humana.
- Commit funcional validado: `d3a0bc3abb701456af2679190cf410803ed66d12`.
- ZIP validado SHA-256: `22ff48663ca8f9fa657901db00a6a22bec4e2f19927c587c53508abb221b80c0`.
- Build reproducible confirmado antes de la instalación humana.
- Rama de release aprobada: `rc/0.4.0-RC1.6.5`.
- Head final de la rama de release: `02db8d7419e8a4e2b03dd089d28a9242cd304088`.
- Tag de release: `v0.4.0-RC1.6.5`, asociado al commit funcional validado indicado arriba.
- Merge aprobado y publicado en `main`: `2b3eb6f35796499d4b49caa3092e57fab172e956`.
- WordPress RC1.6.5: instalación, activación y funcionamiento validados mediante prueba humana el 2026-08-25.
- La validación editorial completa del 2026-08-11 corresponde al historial previo del flujo y no a la aceptación específica de RC1.6.5.

## Decisión sobre RC1.6.3.1

`0.4.0-RC1.6.3.1` está **no aprobada y descartada como baseline**. Introdujo background Responses, polling, Action Scheduler, reentrada y estados operativos que alteraron la UX síncrona. Se conserva solo como evidencia del intento descartado. El commit local de evidencia es `e0b557eb657cd77137f143bf3f620d8759283ec0`; no se publica como release ni se usa para continuar desarrollo.

## Incidente OpenAI cerrado

RC1.6.3 podía terminar mostrando `Error no identificado desde OpenAI API` aun cuando OpenAI Platform registraba la Response como completada. RC1.6.3.2 mantiene la arquitectura síncrona y el mismo número de llamadas, pero mejora el cliente para:

- aceptar una Response `completed` válida con texto utilizable aunque el status HTTP recibido sea anómalo;
- exponer status HTTP y referencias correlacionables cuando no existe contenido recuperable;
- distinguir fallos de transporte;
- no añadir retries, polling, GET, scheduler ni reentrada.

El análisis detallado está en `ANALISIS-RC1.6.3-vs-RC1.6.3.1.md`.

## Invariantes que permanecen protegidos

- prompts sin cambios intencionales;
- ocho puntos de llamada OpenAI;
- UX síncrona de RC1.6.3;
- Generar → V1 → Editorial → V2 → SEO → V3 sin recargas manuales obligatorias;
- validaciones, Gutenberg, publicación, Actualizaciones recurrentes y trazabilidad sin cambios funcionales intencionales;
- contrato Radar/Directus 1.1 y formato de workflow `legacy-array-v1` preservados.

## Límite conocido

Si un intermediario corta una respuesta antes de que el cuerpo llegue a PHP, no es posible recuperar ese cuerpo sin otra llamada o sin cambiar la arquitectura. RC1.6.3.2 deja ese caso diagnosticable, pero deliberadamente no añade reintentos ni procesamiento asíncrono.

## Candidato P1-FINAL-R2 (sin release)

El candidato local `chg-20261022T225545Z-3aa6c484`, sobre `c5e58527c45d638423d4e48e505f4e8f4eda4dc4`, conserva el parser Reel de GREEN1 y usa preflight común sin escrituras para creación y recurrencias. La caja editorial se conserva literal; Final Guard informa ausencias, enlaces, negritas y ubicación inválida. `--final` confirmó 31 escenarios GREEN y `--red` reprodujo los dos RED conocidos. La única ejecución de `./scripts/test.sh` falló por las cuatro clases deliberadamente fuera del manifiesto y por la invocación sin escenario de los nuevos ejecutables P1; esa entrada se corrigió después de dicha ejecución y no se afirma una segunda pasada. No existe release, ZIP, despliegue ni verificación WordPress de este candidato.

## Inicio recomendado para un nuevo chat o agente

Leer, en este orden:

1. `AGENTS.md`;
2. `ESTADO-ACTUAL.md`;
3. `CAMBIOS-v0.4.0-RC1.6.5.md`;
4. `PRUEBAS-v0.4.0-RC1.6.5.md`;
5. `ANALISIS-RC1.6.3-vs-RC1.6.3.1.md` solo si se necesita el historial del incidente OpenAI.

No usar RC1.6.3.1 como baseline. Para futuros cambios, partir de main/tag aprobado y mantener la prueba humana de WordPress como condición de aceptación de cambios que afecten el flujo editorial.
