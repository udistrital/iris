# Vista unificada de expedientes

## Decisión vigente

IRIS presenta todos los objetos del modelo `Ticket` como **expedientes**, sin
clasificarlos ni excluirlos por su canal de origen. Esta decisión es de
aplicación e interfaz; no modifica el esquema de base de datos ni renombra las
clases, rutas o tablas heredadas de osTicket.

En el código interno continúan existiendo nombres como `Ticket`,
`tickets.php`, `ticket_id` y `Ticket::create()`. Se mantienen deliberadamente
para preservar compatibilidad con el núcleo, el ORM, plugins, API y futuras
actualizaciones. En la interfaz institucional, el nombre funcional es
"Expediente".

## Alcance de la restauración

Se eliminó la separación que construía dos pestañas a partir del parámetro
`source`:

- `source=web` para supuestas tareas externas;
- `source=!web` para supuestos expedientes.

La lista vuelve a usar las colas nativas de osTicket, incluyendo:

- colas jerárquicas y subcolas;
- restricciones de visibilidad del agente;
- colas privadas;
- búsquedas guardadas y búsquedas ad hoc;
- conteo y paginación propios de la cola;
- ordenamiento por relevancia en búsquedas;
- eliminación de filas duplicadas por `ticket_id`.

No se filtra por origen en `scp/tickets.php` ni en
`include/staff/templates/queue-tickets.tmpl.php`.

## Datos que permanecen disponibles

La tabla de tickets conserva intactos los campos existentes:

- `number`: identificador visible del expediente;
- `source`: canal técnico (`Web`, `Email`, `Phone`, `API` u `Other`);
- `source_extra`: metadato adicional opcional;
- `user_id`, `dept_id`, `topic_id`, `staff_id` y `team_id`;
- estados, fechas, banderas y relaciones del núcleo.

`source` no representa necesariamente el tipo funcional ni la identidad del
actor creador. Un correo puede provenir de un usuario externo y un agente
puede seleccionar un canal al registrar un expediente. Por esta razón, el
canal no debe reutilizarse como clasificación de negocio sin una especificación
posterior.

## Flujo actual

1. El portal, un agente, el correo o una API crean un `Ticket` mediante los
   mecanismos existentes.
2. El origen técnico se almacena en `source`.
3. El registro se incorpora a las colas según sus criterios, permisos,
   departamento, estado y asignación.
4. Todas las vistas lo denominan expediente; el origen no crea una vista ni un
   proceso paralelo.

## Restricción por dependencias

Un agente solamente puede ver o abrir un expediente cuando al menos una de las
dependencias a las que tiene acceso es creadora o participante del expediente.
La regla se aplica en `Staff::getExpedientesDepartmentVisibility()` y se
reutiliza desde listas, búsquedas y `Ticket::checkStaffPerm()`.

La aplicación reconoce como participación departamental cualquiera de estas
relaciones existentes:

- dependencia actual del expediente (`ticket.dept_id`);
- dependencia del agente asignado directamente;
- dependencia de un agente que publicó una entrada o nota en el hilo;
- dependencia de un agente registrado en un evento del ciclo de vida;
- dependencia destinataria o agente asignado registrado en un evento;
- referencia explícita a una dependencia;
- referencia a un agente de la dependencia;
- referencia departamental o a un agente registrada en un hilo hijo.

El evento `created` permite reconocer la dependencia creadora cuando el
expediente fue abierto por un agente. Cuando el creador es un usuario del
portal, correo o API y no tiene dependencia de personal, la dependencia a la
que se enruta inicialmente el expediente constituye su primera dependencia
participante.

La validación no depende de que el agente conozca la URL: el detalle vuelve a
evaluar la misma regla contra el expediente solicitado. La configuración
"acceso solo a asignados" se conserva como una restricción adicional; nunca
amplía el acceso departamental.

### Alcance de permisos

La pertenencia a una dependencia creadora o participante concede visibilidad,
pero no sustituye los permisos del rol. Editar, responder, transferir, asignar
o eliminar continúa sujeto al rol efectivo que osTicket determine para la
dependencia actual y a las comprobaciones específicas de cada acción.

### Consideración histórica

La participación se deriva de relaciones y eventos existentes, no de una tabla
nueva. Si los datos históricos carecen del evento de creación o de referencias
anteriores, la dependencia actual seguirá teniendo acceso, pero una dependencia
creadora antigua que ya no sea la actual solo podrá reconocerse si quedó
registrada mediante un agente, entrada, evento o referencia.

## Reglas para cambios futuros

Una futura incorporación de recursos externos debe evitar condiciones de
origen dispersas en controladores y plantillas. Antes de implementarla se debe
definir un clasificador central que pueda ser utilizado por consultas,
permisos, procesos, contadores, reportes y vistas.

Si otra aplicación identifica registros mediante un formato como `OAS-000001`,
se recomienda:

1. documentar formalmente el patrón, longitud y responsable del consecutivo;
2. validar unicidad y evitar colisiones con la numeración de IRIS;
3. centralizar la interpretación del prefijo en una clase o método;
4. no ejecutar comparaciones del prefijo directamente en plantillas;
5. conservar `source` como canal técnico;
6. evaluar `source_extra` como respaldo de clasificación sin cambiar el
   esquema;
7. crear el registro con el flujo completo de osTicket o una integración que
   reproduzca transaccionalmente usuario, formulario, hilo, primera entrada,
   estado, departamento y eventos.

Una inserción aislada en la tabla principal de tickets no constituye un
expediente válido: el modelo depende de varias relaciones del núcleo.

## Archivos relevantes

- `scp/tickets.php`: selección de cola, acciones y navegación del personal.
- `include/staff/templates/queue-tickets.tmpl.php`: lista de expedientes.
- `include/staff/templates/navigation.tmpl.php`: render genérico de pestañas.
- `include/class.queue.php`: construcción de consultas de colas.
- `include/class.ticket.php`: modelo y creación del registro.
- `include/class.nav.php`: nombres de navegación para personal y usuarios.
- `setup/inc/streams/core/install-mysql.sql`: esquema de referencia.

## Verificación de regresión recomendada

Antes de desplegar se deben comprobar al menos estos recorridos:

1. abrir la pestaña Expedientes sin parámetros;
2. navegar por colas y subcolas;
3. abrir una búsqueda guardada;
4. buscar por número, correo y texto;
5. ordenar y paginar resultados;
6. crear un expediente desde el panel de agentes;
7. crear un expediente desde el portal;
8. abrir, responder, transferir, cerrar y reabrir;
9. exportar una cola y comprobar que no haya duplicados;
10. verificar que registros de todos los valores de `source` sean visibles
    cuando cumplan los criterios y permisos de la cola.
11. transferir un expediente y verificar acceso de la dependencia creadora y
    de la dependencia receptora;
12. agregar una nota desde una tercera dependencia autorizada y comprobar que
    esta quede reconocida como participante;
13. intentar abrir por URL directa el expediente desde una dependencia ajena y
    comprobar que se rechace el acceso;
14. repetir listado, búsqueda, vista previa, exportación y detalle con agentes
    de dependencias creadoras, participantes y ajenas;
15. validar por separado un agente configurado con acceso solo a asignados.
