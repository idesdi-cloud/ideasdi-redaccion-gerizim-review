# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.7

## Sintaxis

- Todos los archivos PHP deben aprobar `php -l`.
- `assets/admin.js` debe aprobar `node --check`.
- El ZIP final debe aprobar `unzip -t` y una segunda validación después de extraerlo.

## Pruebas funcionales aisladas

- Normalización `Fashion` → `Moda`.
- Detección de `Moda` desde una taxonomía propia del Evento.
- Detección de `Moda` desde un campo ACF cuando no existe término de taxonomía.
- Receta de Moda con calendario, colecciones, pasarela, industria, ciudad y cultura del vestir.
- Riesgo editorial que evita una agenda genérica.
- Un H1 con formulación distinta no bloquea si el mismo ID y huella permanecen válidos.
- Un ID distinto continúa bloqueado.
- Un post inexistente o de otro tipo continúa bloqueado.

## Comprobaciones estáticas

- Versión RC1.4.9.7 visible y constante interna actualizada.
- Keyword y responsable editables antes de generar contenido.
- Se mantienen bloqueados cuando el brief ya tiene contenido generado.
- Existen exactamente las seis categorías editoriales de Agenda acordadas.
- `Agenda` y `Categoría WordPress: No aplica` permanecen separados de la categoría temática.
- `category_id = 0` y `tag_ids = []` continúan forzados en workflows de Evento.
- Los prompts incluyen la categoría editorial del Evento.
- Los prompts de Eventos no usan la regla de convocatoria por ser Agenda.
- Se incluye “Datos clave del evento” o equivalente.
- La cadena de error por H1 de otro evento ya no se usa para diferencias de formulación.
- Persisten comprobaciones de ID, post type y huella inmutable.
- La acción final sigue usando `wp_update_post()` sobre el mismo ID y no crea Eventos nuevos.

## Prueba recomendada en WordPress

1. Iniciar un expediente nuevo desde Actualizaciones recurrentes.
2. Aplicar los campos estructurados y preparar la redacción.
3. Comprobar que Keyword y Responsable sean editables.
4. Verificar que Categoría editorial del evento detecte la categoría propia, por ejemplo Moda.
5. Confirmar que Tipo de pieza sea Agenda y Categoría WordPress sea No aplica.
6. Generar el artículo base y comprobar que no aparezca “Lo esencial de la convocatoria”.
7. Completar Revisión editorial y Revisión SEO.
8. Aplicar la Versión 3 con un H1 válido aunque no sea literalmente igual al título anterior.
9. Confirmar que se actualice el mismo ID de Evento.
