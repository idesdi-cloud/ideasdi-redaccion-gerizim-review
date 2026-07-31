# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.4.9.5

## Sintaxis

- Todos los archivos PHP del plugin deben aprobar `php -l`.
- `assets/admin.js` debe aprobar `node --check`.
- El ZIP final debe aprobar `unzip -t` y una segunda validación después de extraerlo.

## Pruebas funcionales aisladas

Se validan, como mínimo:

1. Copenhague Otoño-Invierno 2026 puede proponerse como Primavera-Verano 2027.
2. El slug acompaña la nueva temporada.
3. Un título y slug manuales prevalecen sobre el cálculo automático.
4. Las fechas de agosto de 2026 mantienen año calendario 2026.
5. SS27 se interpreta como año editorial 2027.
6. AW26/27 no fuerza automáticamente un único año editorial.
7. Denmark se normaliza como Dinamarca.
8. Dinamarca se registra con clave interna estable.
9. El filtro ACF incorpora el país nuevo sin eliminar opciones existentes.
10. El valor almacenado coincide con la clave registrada.
11. El país registrado se considera válido para un selector ACF cerrado.
12. La confirmación de identidad no existe inicialmente.
13. La confirmación se conserva para el mismo usuario y huella.
14. Otro ID no reutiliza la confirmación del registro anterior.

## Aplicación editorial

- `wp_update_post()` debe devolver el mismo ID de destino.
- La operación no debe crear un Evento nuevo.
- El contenido almacenado debe coincidir con la Versión 3 convertida a Gutenberg.
- El extracto almacenado debe coincidir con la meta description prevista.
- Solo después de esas verificaciones se marca `Redacción aplicada: sí`.

## Regresión protegida

- Radar, receta editorial compacta, prompts, reglas editoriales, investigación y validadores generales permanecen sin cambios funcionales.
- El perfil virtual Calendario de eventos sigue usando el CPT `evento`, no una categoría WordPress ficticia.
- Se mantiene la identidad inmutable incorporada en RC1.4.9.4.
