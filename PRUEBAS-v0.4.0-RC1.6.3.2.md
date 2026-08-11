# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.3.2

## Gate técnico

- sintaxis de todos los PHP;
- suite heredada del ZIP;
- regresión focal `tests/openai-sync-http-recovery-mock.php`;
- manifiesto SHA-256 de activos editoriales protegidos;
- ausencia de polling/background/reentrada OpenAI en el runtime;
- conteo de ocho `->complete(` en `includes/`.

## Casos OpenAI focales

1. HTTP 200 + Response completed: éxito normal, un POST.
2. HTTP anómalo + Response completed válida y texto utilizable: se recupera el mismo contenido, sin llamada adicional.
3. HTTP 429 JSON: se conserva mensaje real, status y `x-request-id`.
4. HTTP 502 no JSON: error explícito, referencia cliente y diagnóstico de cuerpo por tamaño/hash.
5. `WP_Error`: error de transporte explícito y referencia cliente.
6. HTTP 2xx sin texto: conserva el fallo histórico específico.

La aceptación definitiva sigue siendo humana en WordPress: Generar artículo base → Versión 1 → Revisión editorial → Versión 2 → Revisión SEO → Versión 3, sin recargas manuales obligatorias.
