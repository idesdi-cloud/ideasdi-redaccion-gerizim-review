# Cambios técnicos · v0.4.0-RC1

Base: `ideasdi-redaccion-gerizim-v0.3.5-base-protegida`.

## Implementado

- `IDG_VERSION` y cabecera del plugin actualizados a `0.4.0-RC1`.
- Nueva clase `IDG_Assignment_Card` para crear y registrar la ficha de encargo editorial.
- Nueva clase `IDG_Final_Guard` para validación real previa al borrador.
- Reporte completo ampliado con ficha de encargo y resumen de validación final.
- Metabox ampliado con ficha de encargo editorial y validación final real.
- `IDG_Post_Creator` ahora bloquea la creación del borrador si fallan reglas duras.
- `IDG_Post_Creator` conserva comentarios de bloque Gutenberg en `post_content`.
- Caja editorial se limpia al convertir a HTML para evitar enlaces y negritas.
- Enlace externo obligatorio y control básico de coherencia responsable/URL.
- Prompt versions actualizados a RC1.
- Matriz de concursos ajustada a tags autorizados: Producto, Arquitectura e interiores, Moda, Movilidad y Diseño digital.

## Validaciones ejecutadas

- `php -l` aprobado en todos los archivos PHP.
- Revisión de matriz de concursos sin `Estudiantes` ni `Profesionales`.
- Documentación actualizada: README, notas de instalación y protección de regresiones.
