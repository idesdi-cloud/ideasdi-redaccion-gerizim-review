# ideasDi Redacción Gerizim v0.4.0-RC1.4.9.0

Base verificada: `ideasdi-redaccion-gerizim-v0.4.0-RC1.4.8`.

## Alcance aplicado · Gerizim / Actualizaciones recurrentes

- Campo **Ciudad** con autocompletado desde valores existentes del CPT `evento`.
- Deduplicación de ciudades por forma normalizada y registro auxiliar de ciudades nuevas sin modificar publicaciones.
- Alias inicial: `Milan`, `Milano` y `Milán` se consolidan como **Milán**.
- Botón **Analizar nueva edición** activo mediante `admin-post.php`, método POST, nonce `idg_analyze_recurring_update` y permiso `edit_posts`.
- Lectura segura de URL mediante `wp_safe_remote_get`.
- Respuesta HTTP 403 tratada como aviso recuperable: no muestra una página Forbidden y continúa con la información manual.
- Expediente temporal con comparación entre campos actuales y valores propuestos.
- Botón **Descargar reporte completo** disponible después del análisis, incluso cuando la fuente respondió HTTP 403.
- Fecha de fin del evento con mínimo dinámico igual a Fecha de inicio.
- Validación PHP: la fecha final puede ser igual a la inicial y no puede ser anterior.
- Búsqueda exacta por slug prioritaria mediante el argumento `name` de `WP_Query`, con búsqueda textual como respaldo.

## Protección de alcance

- No se llama `wp_update_post`, `wp_insert_post`, `update_post_meta`, ACF `update_field` ni operaciones equivalentes.
- No se actualizan publicaciones vigentes.
- No se crean nuevas ediciones.
- No se ejecuta OpenAI.
- No se modifican receta editorial compacta, Radar, flujo editorial, prompts, validaciones editoriales, enlaces ni borrador Gutenberg.
- La única escritura auxiliar es la opción `idg_recurring_city_registry`, usada exclusivamente para recordar ciudades nuevas del autocompletado.
