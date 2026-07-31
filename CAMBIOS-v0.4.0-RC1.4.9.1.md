# ideasDi Redacción Gerizim v0.4.0-RC1.4.9.1

Base verificada: `ideasdi-redaccion-gerizim-v0.4.0-RC1.4.9.0`.

## Alcance aplicado · Eventos existentes

- El análisis y la escritura quedan separados en dos acciones:
  1. **Analizar cambios**.
  2. **Aplicar actualización al evento**, con POST, nonce propio, permiso `edit_posts`, permiso específico `edit_post` y confirmación explícita.
- La aplicación real se habilita únicamente para el modo **Actualizar publicación vigente** sobre el CPT `evento`.
- El año propuesto actualiza de forma determinista el año del título y reconstruye el slug antes de mostrar la comparación.
- **Destacar en home** se presenta deseleccionado por defecto, mostrando el valor actual para decisión manual.
- Se escriben únicamente campos marcados como **Cambio propuesto**:
  - `fecha_inicio`
  - `fecha_fin`
  - `ciudad`
  - `pais`
  - `ubicacion`
  - `enlace_oficial`
  - `destacado_home`
  - `resumen_editorial`
- Las fechas se guardan en formato interno ACF `Ymd` y se leen también desde `Y-m-d`, `Ymd` o `d/m/Y`.
- Cuando ACF está disponible, Gerizim localiza y utiliza la `field key`; mantiene un respaldo con metadatos nativos si la verificación no coincide.
- Después de escribir, cada campo se vuelve a leer y verificar.
- Se preservan deliberadamente:
  - estado WordPress;
  - contenido y extracto;
  - autor;
  - taxonomías;
  - imagen destacada;
  - plantilla y resto de propiedades del evento.
- Se genera una firma del evento durante el análisis. Si el evento cambia antes de aplicar, la operación se bloquea y exige un análisis nuevo.
- El reporte completo registra resultado, campos escritos, título y slug antes/después, estado antes/después y conservación del contenido.

## Fuera de alcance

- No se crean nuevas ediciones.
- No se escriben concursos o convocatorias.
- No se genera ni reescribe el contenido editorial del evento.
- No se cambia automáticamente un borrador a publicado ni un publicado a borrador.
- No se ejecuta OpenAI.
- No se modifican receta editorial compacta, Radar, flujo editorial, prompts, validaciones, enlaces ni borrador Gutenberg.
