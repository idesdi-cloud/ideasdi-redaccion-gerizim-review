# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.0.1

## Objetivo

Corrección focalizada del análisis de **Concursos o convocatorias** en `Gerizim · Actualizaciones recurrentes`.

## Problema corregido

La selección de concursos encontraba y cargaba correctamente la entrada normal de WordPress, pero el token de confirmación reutilizaba una huella que aceptaba exclusivamente el CPT `evento`. Como consecuencia, todo concurso producía una huella vacía y el análisis terminaba con el error de identidad aunque el ID seleccionado fuera correcto.

## Cambio

- Se añadió una huella mínima de selección compatible con los dos tipos admitidos por la pantalla:
  - `evento` para Eventos;
  - `post` para Concursos o convocatorias.
- El token sigue ligado al usuario actual, ID y tipo de publicación.
- La huella inmutable usada para aplicar cambios y preparar redacción continúa limitada a Eventos.
- El mensaje de validación se hizo neutral para hablar de “publicación seleccionada”.

## Alcance preservado

- Concursos o convocatorias continúan en modo análisis.
- No se habilita escritura sobre concursos.
- No se crean nuevas ediciones.
- No se llama OpenAI durante el análisis estructural.
- No se modifican prompts, interfaz general, receta, número de llamadas, validaciones editoriales ni publicación.
- Contratos, adaptadores y orquestación de RC1.6.0 permanecen sin cambios funcionales.
