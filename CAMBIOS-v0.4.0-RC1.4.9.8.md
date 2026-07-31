# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.8

## Alcance

Corrección incremental sobre RC1.4.9.7, limitada a la presentación editorial de Eventos recurrentes y al orden visual del Brief.

## 1. Campo editorial reubicado

- **Categoría editorial del evento** se ubica dentro del bloque del perfil recurrente, inmediatamente debajo de **Categoría WordPress**.
- En escritorio ocupa la misma columna de Categoría WordPress.
- En móvil vuelve a una sola columna.
- Texto de ayuda reducido a: **“Lente de redacción del evento; no asigna una categoría de WordPress.”**
- Los reportes muestran primero Categoría WordPress y después Categoría editorial del evento.

## 2. Eventos sin ficha ni listado práctico

Se retiraron de los prompts las instrucciones que obligaban a crear:

- `Datos clave del evento`;
- títulos equivalentes;
- bullets con organizador, fechas, ciudad, sede, formato, acceso o enlace oficial.

Las nuevas instrucciones exigen integrar esa información en la introducción y el desarrollo mediante párrafos naturales, conversacionales y amenos.

## 3. Revisión editorial y SEO

- Revisión editorial elimina apartados de ficha y transforma listas prácticas en prosa.
- Revisión SEO no puede recrear esos apartados ni resumir datos del evento en listas o tablas.
- La categoría editorial del evento continúa definiendo la lente temática: Moda, Arquitectura e interiores, Diseño digital y 3D, Diseño interdisciplinar, Movilidad y transporte o Semana de diseño.

## 4. Guardia final

Para workflows vinculados al CPT `evento`, la aplicación se bloquea si el artículo público contiene:

- H3 “Datos clave del evento”, “Información del evento”, “Ficha del evento” o equivalentes directos;
- listas `<ul>` o `<ol>`.

El mensaje indica que la información debe integrarse en prosa conversacional.

## 5. Sin cambios

No se modificaron:

- Actualizaciones recurrentes y su escritura estructural;
- identidad inmutable e ID de destino;
- País ampliable y ACF;
- Radar;
- investigación web;
- biblioteca de enlaces;
- creación Gutenberg;
- reglas globales para artículos normales y Concursos.
