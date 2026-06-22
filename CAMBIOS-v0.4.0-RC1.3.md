# Cambios v0.4.0-RC1.3

Corrección de arquitectura sobre v0.4.0-RC1.2.

## Cambio principal

La validación del paquete reel deja de ser solo un detector posterior y pasa a tener una capa de reparación determinista antes de crear el borrador.

## Ajustes

- Si el paquete reel viene incompleto, con VO 1–5 fuera de 14 palabras, sin CTA fijo o sin 18 overlays, el plugin genera un paquete reel de seguridad formalmente válido.
- El paquete reel de seguridad incluye:
  - VO 1–5 con exactamente 14 palabras según el mismo contador del validador.
  - VO 6 con el CTA fijo “Conoce más de este proyecto en ideasDi.com”.
  - 18 overlays en total, 6 escenas × 3 bloques.
- La validación final ya no depende únicamente de que el modelo obedezca el formato exacto.
- Se conserva el reinicio parcial de RC1.2.

## Motivo

Las pruebas mostraron que tres capas basadas en instrucciones y revisión podían detectar errores, pero no repararlos antes del borrador. Esta versión agrega reparación automática para evitar que el borrador llegue con fallos formales del paquete reel.
