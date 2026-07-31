# Resumen de pruebas · v0.4.0-RC1.4.9.1

## Validación sintáctica

- Todos los archivos PHP del plugin se validaron con `php -l`.
- `assets/admin.js` se validó con `node --check`.

## Caso funcional simulado: MBFWMadrid 2023 → 2026

Se reprodujo un evento legado en estado Borrador, con contenido y extracto existentes y campos del evento vacíos.

Resultados comprobados:

1. El título cambia de `Semana de la Moda de Madrid Otoño Invierno 2023` a `Semana de la Moda de Madrid Otoño Invierno 2026`.
2. El slug cambia al equivalente terminado en `-2026`.
3. El estado WordPress permanece como `draft`.
4. El contenido del evento permanece idéntico.
5. El extracto permanece idéntico.
6. Fecha de inicio se guarda como `20260914`.
7. Fecha de fin se guarda como `20260919`.
8. Ciudad, país, ubicación y enlace oficial se escriben y verifican.
9. `destacado_home` puede pasar de activo a inactivo y el control se entrega deseleccionado por defecto.
10. El reporte confirma que la publicación fue actualizada.
11. El reporte confirma que el contenido y extracto se conservaron.
12. El reporte confirma que no se creó una nueva edición.
13. El reporte confirma que no se modificaron concursos.

## Compatibilidad ACF

Se ejecutó un segundo arnés con funciones ACF simuladas:

- La escritura utiliza la `field key` detectada para fechas y ciudad.
- Las fechas se almacenan en el formato interno `Ymd`.
- La verificación posterior lee el valor persistido.

## Guardas de seguridad comprobadas

- Una firma antigua bloquea una reaplicación después de que el evento cambió.
- La escritura sobre concursos o convocatorias permanece bloqueada.
- El modo Crear nueva edición permanece bloqueado.
- La fecha `d/m/Y`, el formato ACF `Ymd` y el formato ISO `Y-m-d` se normalizan correctamente.
- Una respuesta HTTP 403 continúa como aviso recuperable y conserva el flujo manual.
- Un escenario adicional con estado `publish` conserva el evento publicado y su contenido después de actualizarlo.
- El código no contiene `wp_insert_post` ni una ruta de creación de publicaciones.

## Prueba pendiente en WordPress

Instalar la versión y repetir el caso del evento ID 32393. Después de **Analizar cambios**, revisar la comparación y ejecutar **Aplicar actualización al evento**. Confirmar visualmente en ACF que los campos aparecen poblados y que el evento continúa como Borrador.
