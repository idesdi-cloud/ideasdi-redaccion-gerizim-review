# Cambios técnicos · v0.4.0-RC1.2

Corrección sobre v0.4.0-RC1 a partir de las pruebas Brabus BODO y Moncler Grenoble Primavera-Verano 2026.

## Correcciones principales

- Caja editorial reubicada por postprocesado para quedar después de los dos párrafos de introducción.
- Prompts reforzados para evitar artículos con un solo párrafo por H3.
- Validación real de profundidad H3: si la mayoría de bloques H3 tiene menos de dos párrafos, se bloquea el borrador.
- Normalización de alias de Movilidad: Diseño automotriz / Automotriz / Automóvil / automovil apuntan a Automotriz con slug `automovil`.
- Autoinserción del enlace interno calculado cuando el modelo no lo integra en la revisión SEO.
- Enlace externo suelto convertido a frase contextual cuando aparece como párrafo aislado.
- Detección corregida del CTA fijo del paquete reel en el reporte.
- Validación completa del paquete reel: VO 1–5 de 14 palabras, VO 6 con CTA fijo y 18 overlays.
- Reporte final ampliado con estado de caja editorial, profundidad H3 y conteo de overlays.

## Base protegida

La versión sigue partiendo de v0.3.5 como base protegida y mantiene la lógica RC1 de ficha de encargo, validación previa, matriz tag/categoría y borrador Gutenberg.
