# Pruebas · v0.4.0-RC1.4.9.4

## Sintaxis

- Todos los archivos PHP: `php -l`.
- JavaScript administrativo: `node --check`.

## Prueba funcional aislada

Se simuló un evento importado sin referencia ACF `_pais`, con un campo País tipo select cuyas opciones internas eran:

- `ES` → España
- `US` → Estados Unidos
- `AR` → Argentina

Resultados esperados y verificados:

- `Spain` se normaliza como España.
- La clave almacenada es `ES`.
- ACF devuelve visualmente `España`.
- Se crea la referencia `_pais = field_country`.
- La escritura solo se marca como correcta cuando coinciden valor crudo y etiqueta visible.

## Identidad del evento

- Mismo ID y misma temporada: permitido.
- ID distinto del seleccionado: bloqueado.
- Evento Otoño-Invierno con keyword/H1 Primavera-Verano: bloqueado.
- Aplicación editorial: contiene `wp_update_post()` y no contiene `wp_insert_post()`.
- Se exige que el ID devuelto por WordPress sea idéntico al seleccionado.

## Regresión

- Se mantiene el perfil editorial Calendario de eventos.
- Se mantiene Categoría WordPress: No aplica.
- Se mantienen investigación, receta compacta, revisión editorial, revisión SEO, enlaces, validación final y Gutenberg.
- Concursos y creación de nuevas ediciones permanecen desactivados.
