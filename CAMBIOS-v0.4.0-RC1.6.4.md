# Cambios · ideasDi Redacción Gerizim v0.4.0-RC1.6.4

RC1.6.4 separa la capa administrativa del Flujo Editorial manteniendo la lógica histórica y sus contratos.

- `IDG_Workflow_Admin_Controller` conserva el procesamiento del formulario y las acciones.
- `IDG_Workflow_Admin_View` delimita el renderizado de la pantalla, estados, V1/V2/V3 e historial.
- `IDG_Workflow_Admin_Support` concentra helpers administrativos compartidos.
- `IDG_Admin_Page` permanece como fachada compatible para hooks y firmas públicas.
- No cambian prompts, cliente OpenAI, ocho llamadas, flujo síncrono, `admin.js` ni `admin.css`.

La versión productiva de referencia es `0.4.0-RC1.6.3.2`; RC1.6.3.1 permanece descartada. ZIP probado: `99a89843bd999769267e501a1d4d72aad8e4af63517105bef3fe4dd205d5c64e`.
