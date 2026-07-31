# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.0.1

## Prueba focalizada

`tests/recurring-contest-selection-token-mock.php` verifica que:

- una entrada normal de WordPress usada como Concurso genera un token válido;
- el CPT Evento conserva su token válido;
- los tokens distinguen ID y tipo de contenido;
- tipos no permitidos siguen bloqueados;
- la huella inmutable de aplicación permanece exclusiva del CPT Evento.

## Regresión

También deben aprobarse:

- aceptación RC1.6.0 actualizada para la versión hotfix;
- equivalencia de adaptadores y orquestador;
- regresión RC1.5.6 y RC1.5.7;
- mocks de trazabilidad;
- hashes editoriales protegidos;
- PHP lint completo.

El análisis de concursos sigue siendo informativo: esta corrección no habilita escritura ni preparación editorial para concursos.
