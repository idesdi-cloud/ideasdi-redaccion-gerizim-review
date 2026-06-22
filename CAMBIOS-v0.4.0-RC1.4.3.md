# Cambios v0.4.0-RC1.4.3

Base segura: v0.4.0-RC1.4.2.

Alcance acotado de esta versión:

- La sección **Investigación aplicada** mantiene el resumen visible, pero **Aplicación en el artículo** queda cerrada por defecto para no alargar la barra de Estado.
- Se confirma y conserva el **Reinicio parcial mínimo seguro** sin cambios de lógica.
- El metabox del borrador queda más limpio: se ocultan de la entrega editorial interna los campos técnicos que ya se revisan antes del borrador.
- Se retira de la interfaz el botón **Guardar flujo** y la opción manual asociada; el guardado interno del workflow se conserva.
- El selector **Etiquetas WordPress existentes** muestra sugerencias filtradas por categoría y mantiene búsqueda textual.
- El campo **Tipo de pieza** se simplifica: valores antiguos como Evergreen se normalizan a Actualidad; Agenda / concursos y Calendario de eventos se normalizan a Agenda.
- Correcciones puntuales heredadas de pruebas RC1.4.2:
  - Enlace externo obligatorio cuando existe URL del responsable.
  - Autorreparación acotada de H1 cercano al límite de caracteres.
  - Mayor tolerancia de coherencia externa para entidades locales/dealer/contexto.
  - Reel contextual más limpio, evitando muletillas como “actual actual” o frases cortadas.
  - Detección más útil de aplicación de investigación en el artículo.

No se modificó la lógica validada del reinicio parcial mínimo.
