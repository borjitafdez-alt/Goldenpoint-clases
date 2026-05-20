# GoldenPoint Class Manager

## Fase 5: Usuarios WordPress + Inscripciones

### Vinculación con usuarios WordPress
- El sistema usa `wp_users` como fuente única de identidad.
- Tabla interna `gpcm_students` guarda ficha operativa vinculada por `wp_user_id` (sin usuarios duplicados).
- Pantalla **Alumnos WP** permite buscar por nombre, email o teléfono y convertir usuario existente en alumno.

### Ficha interna del alumno
Campos gestionados en `gpcm_students`:
- `wp_user_id`, teléfono, DNI, fecha nacimiento
- nivel preferente, sede preferente, tipo clase preferente
- estado (`active`, `pending`, `baja`, `waitlist`)
- observaciones

La pantalla **Ficha alumno** muestra datos básicos del usuario vinculado.

### Inscripciones
Pantalla **Inscripciones**:
- Lista de registros pendientes detectados desde formularios.
- Conversión de inscripción a alumno sin duplicar por email/wp_user_id.
- Permite asignar nivel/sede/grupo y estado destino (activo, pendiente, lista de espera).
- Si el alumno queda en espera, se inserta en `gpcm_waitlist`.

### Lista de espera
Pantalla **Lista de espera**:
- Muestra solicitudes pendientes.
- Permite asignar a grupo con un botón de conversión rápida.
- Al asignar, cambia estado de espera a `assigned` y alumno a `active`.

### Compatibilidad con formularios
Se añadió `GPCM_Form_Integration` con capa flexible:
- Preparado para Forminator con hook `forminator_custom_form_after_handle_submit`.
- Mapeo configurable por pantalla **Mapeo formularios**.
- Hooks de extensión:
  - `gpcm_forminator_submission_data`
  - `gpcm_mapped_inscription_data`
  - `gpcm_before_store_inscription`

### Mapeo de campos configurable
Pantalla **Mapeo formularios** permite definir claves para:
- nombre, apellidos, email, teléfono, DNI, fecha nacimiento
- nivel, sede, tipo clase
- disponibilidad, días
- observaciones, temporada, estado inscripción
- ID de formulario

### Flujo inscripción -> alumno
1. Llega inscripción y se guarda en `gpcm_inscriptions` como pendiente.
2. Admin revisa en **Inscripciones**.
3. Si existe usuario WP por email, se vincula automáticamente.
4. Se crea/actualiza ficha en `gpcm_students` evitando duplicados.
5. Si hay grupo, se asigna en `gpcm_group_students`.
6. Si no hay plaza, se marca `waitlist`.

### Seguridad
- Conversión de usuarios/inscripciones y mapeo solo para admins (`gpcm_manage_all`).
- Sanitización de entradas y escape de salida en listados.
- Nonces en acciones administrativas.
- Control de duplicados por `wp_user_id` y email.

### Tablas nuevas fase 5
- `gpcm_students`
- `gpcm_inscriptions`
- `gpcm_waitlist`
