# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.5.7

## Alcance

Pruebas locales y aisladas. No se usaron WordPress, MySQL, Radar ni Directus de producción y no se realizaron conexiones HTTP reales.

## Cobertura nueva

`tests/traceability-observability-mock.php` verifica:

- las ocho ramas de `early_exit_reason`;
- selección y conteo de candidatos;
- claim correcto;
- `claim_update_failed`;
- `claimed_row_not_found` después de un UPDATE correcto;
- `lock_token_mismatch`;
- sanitización de `sql_error`;
- redacción del token;
- ausencia de `payload_json` en el resultado;
- preservación de la transición normal hasta `sent` en el fixture simulado.

`tests/traceability-admin-observability-mock.php` verifica:

- transient asociado al usuario;
- expiración breve;
- recuperación y eliminación después de mostrarlo;
- presencia permanente de los nueve campos del aviso;
- visualización de los valores reales;
- ausencia de error SQL, payloads y cabeceras en el aviso.

`tests/rc157-acceptance.php` verifica versionado, contrato, esquema, consulta de elegibilidad, campos diagnósticos, transient y componentes protegidos.

## Ejecución

```bash
for file in tests/*.php; do
  php "$file"
done
```

La validación final también debe incluir:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
unzip -t ideasdi-redaccion-gerizim-v0.4.0-RC1.5.7.zip
```

## Interpretación productiva

Una ejecución controlada del botón mostrará evidencia, no una conclusión anticipada:

- `no_candidates`: la consulta válida no seleccionó filas;
- `candidates_not_claimed`: hubo candidatos, pero ningún UPDATE de claim terminó verificado;
- `claim_verification_failed`: al menos un UPDATE funcionó, pero la fila no pudo recuperarse o el lock no coincidió;
- `completed`: el worker terminó el recorrido normal de los candidatos seleccionados.

## Resultado local consolidado

- scripts de prueba ejecutados: 22;
- scripts aprobados: 22;
- scripts fallidos: 0;
- scripts omitidos: 0;
- verificaciones explícitas con marcador `OK`: 385;
- errores de sintaxis PHP: 0;
- consulta de elegibilidad comparada con RC1.5.6: idéntica;
- archivos eliminados respecto a RC1.5.6: 0.
