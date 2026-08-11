# AGENTS.md — ideasDi Redacción Gerizim

## Alcance

Este repositorio contiene el código fuente canónico del plugin WordPress
ideasDi Redacción Gerizim.

- Repositorio: idesdi-cloud/ideasdi-redaccion-gerizim-review
- Rama canónica: main
- Espacio de trabajo VPS: /opt/gerizim-ideasdi
- Versión productiva de referencia: 0.4.0-RC1.6.3.2
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

## Mantenimiento documental y trazabilidad

- Todo cambio funcional, arquitectónico, contractual, de versión, pruebas, release o circuito operativo debe actualizar en la misma intervención la documentación canónica realmente afectada.
- Antes del cierre, identificar explícitamente qué documentos requieren actualización y cuáles no; la ausencia de cambios documentales debe ser una decisión consciente.
- Una tarea no está completa si código, pruebas y documentación describen estados diferentes.
- Priorizar la actualización de documentos canónicos existentes. No crear handoffs, README, notas u otros archivos redundantes cuando ya exista un documento adecuado.
- Crear o actualizar un handoff solo si aporta continuidad: trabajo pausado, incidencias abiertas, cambio de arquitectura o circuito, decisión no deducible del código o cierre de una fase relevante. No crear handoffs burocráticos para cambios menores completamente cerrados.
- Para cada RC/release relevante, mantener coherentes, cuando existan, la versión del plugin, rama de desarrollo, commit exacto, tag, pruebas, artefacto ZIP y su SHA-256.
- El código fuente registrado en Git es la fuente de desarrollo (Git-first). Los ZIP son artefactos generados desde Git y no vuelven a ser fuente primaria salvo reconciliación explícita de un artefacto legado.
- Los ZIP instalables deben construirse de forma reproducible desde un commit limpio mediante `./scripts/build-zip.sh`, conservando la trazabilidad con el commit o tag correspondiente.
- Antes de solicitar un commit, mostrar junto al diff de código el diff documental pertinente y confirmar la coherencia entre versión, pruebas y documentación.

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

- No hacer commit, hacer push, construir ZIP, instalar o actualizar WordPress, actuar en producción ni modificar otros repositorios sin la autorización correspondiente.
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
