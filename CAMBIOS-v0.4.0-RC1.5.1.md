# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.5.1

## Alcance

RC1.5.1 es una actualización menor de pulido editorial y experiencia de uso sobre RC1.5.0. No cambia la arquitectura de Recetas v2, la investigación, Radar, Eventos recurrentes, ACF ni Gutenberg.

## Redacción y estructura

- El plan aplicado selecciona 3 o 4 ejes editoriales centrales.
- El artículo conserva entre 6 y 7 H3 para ritmo, lectura móvil e inserción de imágenes.
- Los hallazgos secundarios deben funcionar como evidencia, contexto o sección práctica; ya no se interpretan como H3 automáticos.
- Generación y Revisión editorial deben variar la estructura de los párrafos y evitar la fórmula repetida `Eso/Esa/Ahí/Desde ahí + explicación + minicierre`.
- Se evita anunciar una interpretación y volver a resumirla al final del mismo párrafo.
- Se bloquea o advierte sobre metalenguaje público como `densidad editorial`, `interés editorial`, `alcance editorial`, `marco documental` y `las fuentes consultadas`.
- El cierre ya no está obligado a terminar en pregunta retórica.

## Concursos y convocatorias

- La receta prioriza propósito, reto creativo, perfil, categorías, fechas, premios confirmados y alcance disciplinar.
- Se permiten listas únicamente para categorías, fechas principales y premios confirmados.
- Se permiten como máximo dos bloques de lista por artículo.
- Cada lista debe tener un párrafo introductorio y otro de cierre o transición.
- Se excluyen del desarrollo público requisitos técnicos, formatos, entregables, elegibilidad detallada y criterios de evaluación.
- El cierre oficial remite a la web del concurso para consultar las bases completas.
- El tono debe ser claro, cordial, cercano e inspirador, sin convertir fechas, instituciones o ciudades en reflexiones abstractas.
- No se fuerza un H3 dedicado al organizador; se integra en la introducción o en contexto cuando aporte valor.

## Enlaces internos

- La Revisión SEO recibe una instrucción explícita para usar una sola vez cada URL interna.
- La capa técnica conserva el primer enlace y desenvuelve apariciones posteriores de la misma URL sin cambiar las palabras del artículo.
- La Guardia final bloquea un enlace interno principal repetido si la deduplicación no pudo resolverlo.

## Material temporal

- Se mantiene el límite de 6 MB.
- El navegador valida tamaño y extensión antes de permitir Generar artículo base.
- El servidor valida tamaño, formato y texto extraíble antes de iniciar llamadas a OpenAI.
- Un DOCX sin texto o un PDF con menos de 250 caracteres extraíbles bloquea la generación.
- El aviso aclara que la generación no comenzó.
- El editor puede reemplazar el archivo o descartarlo y continuar con el texto pegado sin perder el brief.
- Reemplazar un archivo elimina el bloque adjunto anterior y conserva el texto manual.
- El reporte registra estado, nombre, caracteres extraídos y error del archivo.

## Sistemas preservados

Permanecen sin cambios funcionales:

- Motor editorial de Recetas v2.
- Investigación web controlada.
- Ficha documental y plan aplicado.
- Radar editorial.
- Actualizaciones recurrentes y protección del ID de Evento.
- País ampliable y ACF.
- CPT Eventos.
- Metaboxes, logging y cliente OpenAI.
- Conversión a Gutenberg y publicación como Pendiente de revisión.
