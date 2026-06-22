# Cambios v0.4.0-RC1.4

Versión puente para estabilizar el núcleo funcional y separar los ajustes editoriales continuos del código del plugin.

## Núcleo protegido
- Se mantiene el flujo: brief → investigación → ficha documental → redacción → revisión editorial → SEO → validación → borrador Gutenberg.
- Se conserva el reinicio parcial para retomar sin recargar toda la información.
- Se conserva la matriz de enlaces internos por tag/categoría.

## Reglas editoriales editables desde WordPress
- Nueva pantalla: Gerizim → Reglas editoriales.
- Las reglas se guardan en la base de datos de WordPress, no sobrescriben archivos del plugin.
- Campos estructurados: H1, H2, caja editorial, H3, reel, overlays y CTA fijo.
- Campos de texto para copiar/pegar reglas de estructura, enlaces, caja editorial, reel, frases prohibidas y reglas por categoría.
- Historial reciente de reglas, exportación JSON e importación JSON.

## Validación y reparación
- El paquete reel se repara antes del borrador si no cumple formato.
- El validador vuelve a tratar los errores formales del reel como bloqueantes si la reparación no los resolvió.
- El reporte muestra conteo VO 1–5 contra el objetivo configurado.
- El reporte incluye la versión de reglas editoriales activa.
- El workflow guarda la salida SEO postprocesada para que reporte y borrador no diverjan.

## Enlaces
- Refuerzo de enlaces internos contextuales para evitar enlaces sueltos tipo “ver más”.
- Nuevos anchors contextuales para Modelado 3D y Reforma.
- Contextualización de enlaces internos existentes cuando aparecen como párrafo aislado.
