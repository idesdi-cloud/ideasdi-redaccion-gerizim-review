# Gerizim 0.4.0-RC1.7.8 — cambios incorporados en Git

**Estado:** revisión y merge en Git completados; fase de construcción reproducible autorizada, aún sin ZIP oficial, tag ni despliegue WordPress.
**Commit fuente del cambio:** `bdb133d412d948734cf2fed3691c584e73bda4af`.
**Merge en `main`:** `502ba021bda324fbb360046e3e705ecd8835a16a` (PR #1).
**Árbol funcional de 20 rutas:** `4296351340ee26847bae3c2138c9276b3cb13bfc`.
**Identidad congelada de 20 rutas:** `fcf5b8ce023fda13ef293ea9d8c9d1e97d9d54be877bff55be53551662b7d0b2`.
**Baseline:** RC1.7.7, commit `a35beee6e8fab486fab492d446acfba85f887ef9`.
**Procedencia:** `chg-20261027T213048Z-47bd9e74` en `NEEDS_ATTENTION`,
no es `REVIEW_READY` ni autoriza RELEASE.

## Cambios funcionales

- H1 único y coherencia de título WordPress con Yoast `%%title%%`; soporte de
  lectura de `TÍTULO SEO` anterior sin producir nuevas secciones de ese tipo.
- Caja editorial factual de 40–55 palabras, bloque Gutenberg y validaciones duras.
- Redacción ideasDi Diseñador Traductor y apertura natural con variantes de keyword.
- Enlaces internos P2: una selección canónica resuelta en artículos normales;
  nunca aceptar URL internas inventadas/duplicadas. Excepciones justificadas
  solo afectan obligatoriedad de enlace, no control de destinos.
- Compatibilidad preservada: los campos históricos `sponsor_client` y
  `sponsored_required_link` mantienen la detección de patrocinados y el aviso
  condicionado por `sponsored_visible_label`; NO conceden privilegios a P2.
- Reel: 6 escenas, VO 1–5 de 14 palabras, 3 overlays de máximo 40 caracteres y
  VO 6 con CTA literal `Conoce más de este proyecto en ideasDi.com`.

## Compatibilidad, pruebas y control

- Sin migración prevista de base de datos; `IDG_TRACEABILITY_DB_VERSION = 1.2.0`.
- Runner de pruebas actualizado a RC1.7.8; fixture Reel y composición P3
  reconciliados; manifiesto de protección RC1.7.8 para 51 archivos de base.
- Prueba dirigida ampliada para verificar tanto aviso de patrocinados históricos
  como ausencia de excepción P2 o permiso para destinos internos no autorizados.
- Antes del cierre deben pasar PHP lint, `php tests/rc178-targeted-regression.php`,
  `./scripts/test.sh`, `git diff --check` y revisión de identidad de rutas/tree.
- Validación CLI en copia aislada no reemplaza la prueba humana WordPress.
- Existe commit y merge Git exactos; aún no existe tag ni ZIP oficial construido.
  El ZIP oficial se construirá exclusivamente desde un commit limpio validado;
  su SHA-256 y commit de build se registrarán como evidencia externa al árbol fuente.
  La fase RELEASE no autoriza instalación ni aceptación funcional WordPress.
- Guía futura de instalación/reversión: `NOTAS-INSTALACION.md`.
