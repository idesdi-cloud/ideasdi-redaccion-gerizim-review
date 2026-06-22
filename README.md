# ideasDi Redacción Gerizim v0.4.0-RC1.4

Plugin interno para el flujo editorial de ideasDi: brief editorial, investigación controlada, ficha documental, redacción, revisión editorial, revisión SEO, validación real y creación de borradores Gutenberg en estado Pendiente de revisión.

## Objetivo de esta versión

v0.4.0-RC1.4 estabiliza el núcleo funcional y agrega una capa editable de reglas editoriales desde WordPress. La idea es que los ajustes continuos de redacción no obliguen a reconstruir el plugin completo.

## Cambios principales

- Nueva pantalla **Gerizim → Reglas editoriales**.
- Reglas guardadas en la base de datos de WordPress; no sobrescriben archivos del plugin.
- Campos medibles para H1, H2, caja editorial, H3 y paquete reel.
- Campos de texto para copiar/pegar reglas editoriales.
- Historial reciente, exportación JSON e importación JSON de reglas.
- Reparación previa del paquete reel antes del borrador.
- Validación real bloqueante si la reparación no resuelve errores formales.
- Reporte con versión de reglas activas y conteo de VO.
- Refuerzo de enlaces contextuales y reducción de enlaces sueltos.

## Instalación

1. Subir el ZIP desde WordPress → Plugins → Añadir nuevo → Subir plugin.
2. Activar o reemplazar la versión anterior.
3. Verificar que la versión del plugin sea **0.4.0-RC1.4**.
4. Entrar en **Gerizim → Reglas editoriales** y revisar las reglas activas.
5. Probar con un flujo nuevo antes de usarlo en publicación real.

## Uso recomendado

- Ajustes editoriales menores: modificar desde **Gerizim → Reglas editoriales**.
- Cambios técnicos del flujo: actualizar plugin con nueva versión.
- Si una regla empeora los resultados, restaurar reglas base o importar el JSON anterior.

## Nota de seguridad

La publicación automática sigue desactivada. El plugin crea entradas como **Pendiente de revisión** para revisión humana final.

## v0.4.0-RC1.4.4

- Versión visible en la interfaz.
- Tipo de pieza automático y campos base bloqueados tras generar artículo.
- Botón de ignorar validación solo con bloqueo real.
- Reel como módulo preliminar no bloqueante.

## v0.4.0-RC1.4.3

Parche acotado sobre RC1.4.2: limpieza del metabox editorial, investigación aplicada cerrada por defecto, selector de etiquetas por categoría, simplificación de tipo de pieza y correcciones puntuales de validación/postprocesado detectadas en pruebas.



## v0.4.0-RC1.4.7.2

- Importador Radar actualizado para JSON v1.1.
- Receta editorial compacta calculada dentro de Gerizim.
- Separación entre receta editorial y reglas técnicas de enlace.
- URLs de ideasDi tratadas como internas.
- Distribución protegida de enlaces fuera de caja editorial.

## v0.4.0-RC1.4.7

Integración inicial manual con Radar editorial ideasDi:

- Permite pegar un JSON individual exportado por Radar.
- Precarga la ficha de encargo de Gerizim.
- No genera artículo ni ejecuta OpenAI automáticamente.
- No crea borrador WordPress automáticamente.
- Valida sistema, destino, keyword principal y hecho base.
- Busca categorías y etiquetas por nombre sin crearlas.
- Guarda IDs internos `radar_brief_id` y `radar_hallazgo_id` cuando están disponibles.
