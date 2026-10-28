# Gerizim 0.4.0-RC1.7.8 — cambios previstos (candidato aislado)

**Estado:** no liberado; revisión aislada y cambio excepcional en preparación.
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
- No existe commit ni tag de RC1.7.8; el ZIP aislado es de revisión, NO instalable.
  El ZIP oficial se construirá solo desde commit limpio después de autorizaciones.
- Guía futura de instalación/reversión: `NOTAS-INSTALACION.md`.
