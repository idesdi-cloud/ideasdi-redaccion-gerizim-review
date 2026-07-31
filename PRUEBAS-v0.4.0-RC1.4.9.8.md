# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.8

## Sintaxis

- 22 archivos PHP aprobados con `php -l`.
- `assets/admin.js` aprobado con `node --check`.

## Interfaz

- Categoría WordPress aparece antes de Categoría editorial del evento.
- El campo editorial permanece dentro de la cuadrícula del perfil recurrente.
- En escritorio se ubica en la segunda columna, debajo de Categoría WordPress.
- Texto de ayuda compacto presente.

## Prompts

- Eliminada la obligación de crear “Datos clave del evento”.
- Eliminada la obligación de presentar datos prácticos en bullets.
- Generación, Revisión editorial y Revisión SEO contienen reglas narrativas específicas para Eventos.
- Concursos y convocatorias conservan su bloque práctico propio.

## Guardia funcional aislada

- Caso inválido: H3 `Datos clave del evento` + lista → bloqueado con 2 errores.
- Caso válido: H3 editorial + dos párrafos narrativos → aprobado.

## Regresión

- El perfil sigue siendo `Calendario de eventos`.
- Tipo de pieza sigue siendo `Agenda`.
- Categoría WordPress sigue siendo `No aplica`.
- La categoría propia continúa como lente editorial y no se asigna a `category` ni `post_tag`.
- No se incorporó creación de Eventos.
- El mismo ID continúa siendo el destino de la aplicación editorial.
