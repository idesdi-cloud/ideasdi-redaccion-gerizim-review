# AGENTS.md — ideasDi Redacción Gerizim

## Alcance

Este repositorio contiene el código fuente canónico del plugin WordPress
ideasDi Redacción Gerizim.

- Repositorio: idesdi-cloud/ideasdi-redaccion-gerizim-review
- Rama canónica: main
- Espacio de trabajo VPS: /opt/gerizim-ideasdi
- Versión productiva de referencia: 0.4.0-RC1.6.2
- WordPress se actualiza manualmente mediante un ZIP validado.
- No existe un entorno WordPress de staging.

## Flujo obligatorio

1. Revisar estado Git, versión y alcance solicitado.
2. Modificar únicamente archivos relacionados con la tarea.
3. No introducir secretos, tokens, credenciales ni datos productivos.
4. Ejecutar ./scripts/test.sh.
5. Revisar git diff, git diff --check y git status.
6. Crear commits acotados y descriptivos.
7. No realizar push sin autorización explícita del usuario.
8. Construir el ZIP solo desde un commit conocido y limpio.
9. Instalar el ZIP manualmente en WordPress producción.
10. Verificar funcionamiento y conservar una opción de reversión.

## Comportamientos protegidos

No deben alterarse accidentalmente:

- contrato Radar–Gerizim 1.1;
- identidad y procedencia de workflows importados;
- trazabilidad, outbox, recaptura y reintentos;
- orden causal de eventos;
- reinicio parcial e importación Radar;
- recetas y reglas editoriales;
- actualizaciones recurrentes;
- concursos y convocatorias;
- manifiestos de regresión editorial SHA-256.

## Reglas Git y publicación

- No cambiar la versión sin una fase de release autorizada.
- No crear ni mover etiquetas sin autorización.
- No usar force push.
- No desplegar directamente desde el VPS a WordPress.
- No incluir .git, temporales ni ZIP anteriores en el paquete.
- Conservar el ZIP estable anterior antes de actualizar producción.

## Comandos operativos

- Validación: ./scripts/test.sh
- Revisión: git diff --check
- Estado: git status --short --branch
- Construcción: ./scripts/build-zip.sh
- Salida temporal de builds: /tmp/gerizim-builds
