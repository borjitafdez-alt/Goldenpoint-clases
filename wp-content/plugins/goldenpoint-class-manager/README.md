# GoldenPoint Class Manager (Diseño Inicial)

## 1) Estructura de base de datos

Se propone una arquitectura con tablas propias (`wp_gpcm_*`) para evitar acoplar lógica de negocio compleja a `wp_posts` y `wp_postmeta`.

- **WordPress users** sigue siendo la fuente de identidad (login/registro).
- **Tablas gpcm** contienen la operación académica/financiera.
- Relación por `student_user_id` y `monitor_user_id` hacia `wp_users.ID`.
- Históricos inmutables de asistencia, pagos y recuperaciones.

## 2) Tablas necesarias

- `gpcm_venues`: sedes.
- `gpcm_levels`: niveles.
- `gpcm_class_types`: modalidades y aforo.
- `gpcm_groups`: grupo activo con monitor/sede/pista/día/hora.
- `gpcm_group_students`: pertenencia de alumnos a grupos + histórico altas/bajas/cambios.
- `gpcm_classes`: instancias de clases por fecha.
- `gpcm_attendance`: resultado por alumno y clase.
- `gpcm_recoveries`: bolsa de recuperaciones (origen, estado y consumo).
- `gpcm_student_plans`: suscripciones y condiciones por modalidad.
- `gpcm_wallets`: bonos de clases.
- `gpcm_payments`: cobros, estado y trazabilidad.

## 3) Roles de usuario

- `administrator` + capacidades `gpcm_manage_*` (control total).
- `gpcm_monitor`: asistencia, agenda propia, observaciones y evolución.
- `gpcm_student`: agenda propia, pagos, bonos, cancelaciones y recuperaciones.

## 4) Pantallas del panel interno

### Administrador
- Dashboard de KPIs (plazas libres, pagos pendientes, recuperaciones abiertas).
- Alumnos (listado, estado, baja, movimientos de grupo).
- Grupos y horarios (CRUD + asignaciones).
- Asistencias (filtros por sede/monitor/semana/estado).
- Recuperaciones (saldo, disponibilidad y consumo).
- Pagos (mensualidades, bonos, extras, devoluciones, parciales).
- Exportación a Excel (alumnos, caja, asistencia).

### Monitor
- Mi agenda (día/semana/mes).
- Detalle grupo y lista de alumnos.
- Pase de asistencia + observaciones.

### Alumno (área privada)
- Mis clases asignadas.
- Mis pagos/pendientes.
- Mis bonos (disponible/consumido).
- Mis recuperaciones y plazas libres.
- Cancelación con regla 24h.

## 5) Flujo de asistencias

1. Sistema crea clase programada en `gpcm_classes`.
2. Monitor abre clase y marca estado por alumno:
   - `attended`
   - `missed_notified`
   - `missed_unnotified`
   - `recovered`
3. Si `missed_notified` con >24h: `recovery_eligible=1` y se crea registro en `gpcm_recoveries`.
4. Si <24h: no hay recuperación y la clase se considera consumida.
5. Observaciones se guardan por asistencia para evolución.

## 6) Flujo de pagos

1. Generación mensual automática según plan (`gpcm_student_plans`).
2. Ajuste proporcional por semanas del mes (`monthly_classes` decimal).
3. Registro de clase extra fuera de plan como `extra_pending`.
4. Estados de pago:
   - `paid`
   - `pending`
   - `partial`
   - `refunded`
   - `extra_pending`
5. Historial completo por alumno para conciliación.

## 7) Flujo de recuperación de clases

1. Se origina por falta avisada >24h.
2. Alumno consulta plazas libres compatibles con modalidad/nivel.
3. Alumno reserva plaza de recuperación.
4. Sistema vincula `recovery_class_id` y cambia estado a `booked`.
5. Al asistir, estado pasa a `consumed`.
6. Si vence plazo, estado pasa a `expired`.

## Integración WordPress recomendada

- Plugin personalizado (este repositorio) + tablas propias.
- Menú interno en `wp-admin` para staff.
- Área privada para monitor/alumno mediante shortcodes o plantillas de cuenta.
- Exportaciones con `PhpSpreadsheet` (fase siguiente).
- API REST interna para futuro app móvil.
