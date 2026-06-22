# Cambios v0.4.0-RC1.4.7.2

Versión de estabilización editorial-operativa para la integración manual Radar editorial → Gerizim.

## Entra en RC1.4.7.2

- Soporte para JSON Radar `version_exportacion: 1.1`.
- Botón independiente **Validar JSON** sin importar ni modificar la ficha.
- Importación de `tag_principal`, `tags_secundarios` y `clasificacion_editorial`.
- Evergreen se conserva como clasificación editorial secundaria; no se convierte en tipo de pieza.
- `contenido_editorial.lecturas_prioritarias` deja de usarse como receta operativa. En JSON v1.1 se considera inválido; en compatibilidad antigua queda solo como contexto interno.
- Nueva clase `IDG_Editorial_Recipe_Builder` para calcular una **receta editorial compacta** desde categoría, tag principal, tags secundarios y ángulo editorial.
- Nuevo archivo `includes/data/editorial-recipes.php` como fuente interna de ingredientes editoriales.
- Separación entre receta editorial compacta y reglas técnicas de enlace interno.
- Tratamiento de URLs de `ideasdi.com` como internas; no se exigen como enlace externo.
- Distribución protegida de enlaces: un enlace temprano en los dos primeros párrafos y el segundo después de la caja editorial, nunca dentro de la caja.
- Refuerzo para insertar el enlace interno calculado por matriz cuando exista.

## No entra en RC1.4.7.2

- Lectura de `index.json`.
- Bandeja de briefs.
- Lectura remota por URL.
- REST API.
- Automatización Radar → WordPress.
- Creación automática de artículo al importar.
- Cambios en reel.
- Rediseño del flujo principal.
