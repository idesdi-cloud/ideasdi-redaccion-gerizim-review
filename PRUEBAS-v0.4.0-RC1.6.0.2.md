# Pruebas · ideasDi Redacción Gerizim v0.4.0-RC1.6.0.2

## Pruebas nuevas

### `tests/recurring-contest-structural-apply-mock.php`

Simula la entrada de concurso ID `11902` y verifica:

- carga válida como `post` de categoría `34`;
- aplicación sobre exactamente el mismo ID;
- actualización de título, slug, fechas y enlace oficial;
- almacenamiento de fechas en formato ACF compatible;
- preservación de contenido, extracto, estado, autor, categorías, etiquetas e imagen;
- continuidad de la huella protegida del destino.

### `tests/recurring-contest-editorial-routing-static.php`

Verifica:

- retirada del bloqueo de escritura de concursos;
- aplicación estructural genérica;
- construcción del workflow editorial de concurso;
- uso del orquestador recurrente;
- reconocimiento del destino `post` por la interfaz y el escritor final;
- restricción a la categoría `34`;
- conservación de taxonomías según el tipo real;
- ausencia de una ruta nueva de `wp_insert_post()` para Actualizaciones recurrentes;
- mantenimiento de ocho llamadas a OpenAI.

## Regresión completa

Deben aprobarse todas las pruebas PHP incluidas, entre ellas:

- equivalencia de adaptadores;
- equivalencia del orquestador;
- aceptación RC1.6.0.2;
- selección segura de concursos;
- aplicación estructural de concursos;
- enrutamiento editorial de concursos;
- regresión RC1.5.6 y RC1.5.7;
- trazabilidad, outbox, claims y publicación;
- carga del plugin;
- lint PHP completo.

## Equivalencia editorial

`REGRESION-EDITORIAL-RC1.6.0.2.sha256` fija los hashes de los archivos editoriales que no debían cambiar: interfaz estática, prompts, cliente OpenAI, validador, guard final, reglas editoriales, plan y receta. `class-post-creator.php` cambia intencionalmente para admitir el destino de concurso existente y queda cubierto por pruebas específicas de enrutamiento y preservación.
