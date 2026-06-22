# Cambios v0.4.0-RC1.4.7

## Objetivo
Agregar una integración inicial y manual entre Radar editorial ideasDi y Redacción Gerizim mediante importación de un JSON individual.

## Alcance aplicado
- Nuevo bloque en la pantalla principal: **Importar brief desde Radar editorial**.
- Textarea para pegar JSON individual exportado por Radar.
- Botón **Importar a ficha de encargo**.
- Clase aislada `IDG_Radar_Importer` en `includes/class-radar-importer.php`.
- Validación básica del JSON:
  - `sistema = radar-editorial-ideasdi`.
  - `destino = gerizim-wp`.
  - `brief.keyword_principal` obligatorio.
  - `contenido_editorial.hecho_base` obligatorio.
- Mapeo de campos hacia la ficha editorial existente.
- Búsqueda de categoría y etiquetas por nombre, sin crear términos.
- Avisos claros si faltan categorías o etiquetas no críticas.
- Guardado del workflow activo con datos importados.
- Registro en historial del flujo con IDs de Radar cuando están disponibles.
- Bloqueo de importación si el flujo ya tiene artículo base, revisión, SEO o borrador.

## Fuera de alcance
- No se implementó lectura remota por URL.
- No se implementó lectura de `index.json`.
- No se implementó bandeja/lista de briefs.
- No se implementó REST API.
- No se implementó sincronización automática.
- No se ejecuta OpenAI automáticamente.
- No se genera artículo ni borrador automáticamente.
- No se modificaron prompts, generación, revisión editorial, revisión SEO, creación de borradores ni validaciones existentes.

## Validación técnica
- PHP validado con `php -l`.
- JavaScript validado con `node --check`.
