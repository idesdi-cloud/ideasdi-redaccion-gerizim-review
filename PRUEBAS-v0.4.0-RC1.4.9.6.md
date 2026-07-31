# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.6

## Casos cubiertos

1. El formulario nuevo no preselecciona una operación.
2. El análisis de eventos exige seleccionar Actualizar publicación vigente.
3. Crear nueva edición sigue desactivado.
4. El checkbox repetido de confirmación del ID ya no existe.
5. Al seleccionar Actualizar publicación vigente aparece la advertencia de sobrescritura.
6. Temporada editorial, año editorial y etiqueta oficial no aparecen en el formulario ni en el reporte.
7. Destacar en home no aparece en preparación, comparación ni escritura.
8. Título y slug propuestos continúan editables antes de aplicar.
9. La huella del evento no cambia al modificar título, slug, fecha interna del borrador o metadatos.
10. La huella cambia si cambia el ID o el post type.
11. Después de la aplicación estructural se renueva la huella guardada del mismo ID.
12. Preparar redacción puede continuar después de actualizar título y slug.
13. País ampliable y verificación ACF permanecen activos.
14. El botón Analizar conserva el estado visual completado y se reinicia al editar el formulario.
15. La aplicación de Versión 3 continúa restringida al mismo evento vinculado.

## Validaciones técnicas

- Todos los PHP se validan con `php -l`.
- JavaScript se valida con `node --check`.
- El ZIP final se extrae y se valida nuevamente.
