# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.5.1

## Sintaxis

- 22 archivos PHP de `includes/` aprobados con `php -l`.
- Archivo principal aprobado con `php -l`.
- `assets/admin.js` aprobado con `node --check`.

## Comprobaciones funcionales aisladas

Aprobadas:

- detección de estado bloqueante del archivo temporal;
- rechazo previo de archivo superior a 6 MB;
- eliminación del archivo adjunto conservando el texto pegado;
- deduplicación de la URL interna conservando el texto del segundo anchor;
- aceptación de una lista de concurso acompañada por introducción y cierre;
- rechazo de más de dos listas y listas sin prosa de apoyo.

## Comprobaciones estáticas

20 de 20 aprobadas:

- versión RC1.5.1;
- plan limitado a 3–4 ejes centrales;
- conservación de 6–7 H3;
- hallazgos secundarios no convertidos automáticamente en H3;
- control de metalenguaje;
- variación de estructura de párrafos;
- listas de Concursos limitadas a categorías, fechas y premios;
- máximo de dos listas;
- exclusión de requisitos, entregables y criterios;
- organizador sin H3 obligatorio;
- bloqueo de `las fuentes consultadas`;
- receta de Concursos actualizada;
- deduplicación técnica de enlaces;
- Guardia final contra enlaces internos repetidos;
- límite de 6 MB conservado;
- preflight de servidor;
- reemplazo o descarte del archivo;
- preflight del navegador;
- historial de generación detenida antes de OpenAI;
- fallback del plan limitado a cuatro ejes.

## Regresión

La comparación byte a byte frente a RC1.5.0 confirmó que solo cambiaron los archivos previstos:

- `ideasdi-redaccion-gerizim.php`;
- `includes/class-prompt-library.php`;
- `includes/data/editorial-recipes.php`;
- `includes/class-editorial-plan.php`;
- `includes/class-final-guard.php`;
- `includes/class-post-creator.php`;
- `includes/class-temporary-material.php`;
- `includes/class-admin-page.php`;
- `assets/admin.js`;
- `assets/admin.css`;
- documentación de RC1.5.1.

Todos los demás archivos funcionales permanecieron idénticos a RC1.5.0.

## Limitación

No se ejecutó una generación real contra OpenAI ni una instalación dentro del WordPress de producción. La validación editorial final debe realizarse con las pruebas habituales de ideasDi.
