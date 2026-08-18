# APIs y endpoints

## 1. Superficies HTTP encontradas

El repositorio tiene tres superficies de enrutamiento explícito:

1. API externa: `api/http.php`.
2. AJAX cliente: `ajax.php`.
3. AJAX staff: `scp/ajax.php`.

No se clasifica cada página PHP del sistema como REST API.

## 2. API externa

### Endpoints registrados directamente en `api/http.php`

| Método | Patrón de ruta | Controlador | Acción | Propósito |
|---|---|---|---|---|
| POST | `/tickets.{xml|json|email}` | `include/api.tickets.php:TicketApiController` | `create` | Crear un ticket desde integración externa |
| POST | `/tasks/cron` | `include/api.cron.php:CronApiController` | `execute` | Ejecutar tareas cron mediante API |

El prefijo efectivo depende de la instalación/web server; el archivo controlador es `api/http.php` y resuelve `Osticket::get_path_info()`.

### Creación de ticket

**Entrada:** soporta formatos `xml`, `json` y `email` según la ruta.  
**Controlador:** `TicketApiController::create`.  
**Archivo:** `include/api.tickets.php`.

**Comprobado:** el controlador interpreta la solicitud, valida datos y delega en la lógica del sistema para crear el ticket.

**Autenticación:** `include/class.api.php` y `api/api.inc.php` conforman la infraestructura de autenticación/validación de API. El esquema contiene `api_key`.

**Precaución:** la combinación exacta de API key, IP permitida y permisos depende de la configuración almacenada; no se documentan valores de despliegue ni secretos.

### Cron

`POST /tasks/cron` delega en `CronApiController::execute`.

Por tratarse de una operación administrativa/automatizada, debe conservar la validación de API configurada; no debe exponerse como ruta anónima por cambios de proxy.

## 3. AJAX de cliente

`ajax.php` carga `client.inc.php`. Si una operación requiere autenticación y ésta falta, `clientLoginPage()` responde HTTP 403.

Rutas comprobadas:

| Método | Ruta relativa | Controlador | Acción |
|---|---|---|---|
| GET | `/config/client` | `ConfigAjaxAPI` | `client` |
| POST | `/draft/{id}` | `DraftAjaxAPI` | `updateDraftClient` |
| DELETE | `/draft/{id}` | `DraftAjaxAPI` | `deleteDraftClient` |
| POST | `/draft/{id}/attach` | `DraftAjaxAPI` | `uploadInlineImageClient` |
| POST | `/draft/{namespace}/attach` | `DraftAjaxAPI` | `uploadInlineImageEarlyClient` |
| GET | `/draft/{namespace}` | `DraftAjaxAPI` | `getDraftClient` |
| POST | `/draft/{namespace}` | `DraftAjaxAPI` | `createDraftClient` |
| GET | `/form/help-topic/{id}` | `DynamicFormsAjaxAPI` | `getClientFormsForHelpTopic` |
| POST | `/form/upload/{...}` | `DynamicFormsAjaxAPI` | `upload` / `attach` |
| GET | `/i18n/{lang}/{tag}` | `i18nAjaxAPI` | `getLanguageFile` |

## 4. AJAX de staff

`scp/ajax.php` carga `staff.inc.php` y redefine `staffLoginPage()` para responder 403 cuando el acceso AJAX no está autenticado.

La superficie es amplia. Los grupos comprobados incluyen:

| Grupo | Ejemplos de rutas | Controlador |
|---|---|---|
| Knowledge base | `/kb/canned-response/{id}.{json|txt}`, `/kb/faq/{id}` | `KbaseAjaxAPI` |
| Contenido | `/content/log/{id}`, `/content/context`, `/content/signature/...` | `ContentAjaxAPI` |
| Configuración | `/config/scp`, `/config/links`, `/config/date-format` | `ConfigAjaxAPI` |
| Formularios | `/form/help-topic/{id}`, `/form/field-config/{id}`, `/form/upload/...` | `DynamicFormsAjaxAPI` |
| Filtros | `/filter/action/{type}/config` | `FilterAjaxAPI` |
| Schedules | `/schedule/add`, `/{id}/clone`, `/{id}/diagnostic` | `ScheduleAjaxAPI` |
| Listas | `/list/{list}/items`, búsqueda, importación, enable/disable/delete | `DynamicFormsAjaxAPI` |
| Plugins | `/plugins/{id}/instances...` | `PluginsAjaxAPI` |
| Reportes | `/report/overview/graph`, `/table`, `/table/export` | `OverviewReportAjaxAPI` |
| Usuarios | `/users`, `/users/{id}`, lookup, register, notes, forms, export | `UsersAjaxAPI` |
| Organizaciones | `/orgs`, `/orgs/{id}`, lookup, add-user, import, notes | `OrgsAjaxAPI` |
| Locks | `/lock/ticket/{tid}`, renovación y release | `TicketsAjaxAPI` |
| Tickets | cambio usuario, preview, merge, status, tasks, search, transfer, fields, assign, refer, claim, export | `TicketsAjaxAPI` |
| Tareas | grupo `/tasks/` | `TasksAjaxAPI` |

El archivo contiene más rutas que las resumidas aquí. La fuente canónica de contratos AJAX es `scp/ajax.php`; para cambios de API se debe revisar el patrón exacto y el método HTTP antes de modificar clientes.

## 5. Headers y contenido

### API externa

La infraestructura de `api/` maneja solicitudes según formato (`xml`, `json`, `email`). Los headers exactos aceptados y la forma de la API key deben validarse contra `api/api.inc.php` y `include/class.api.php` al integrar un consumidor.

### AJAX

Son endpoints internos de la aplicación web, ligados a las sesiones/autenticación del cliente o staff. No deben tratarse como API pública estable.

## 6. Autenticación y autorización

### Cliente

`ajax.php` usa `client.inc.php` y devuelve 403 a través de `clientLoginPage()` en accesos no autorizados.

### Staff

`scp/ajax.php` usa `staff.inc.php` y devuelve 403 mediante `staffLoginPage()`.

### API

Existe modelo `api_key` y clases de infraestructura específicas de API. Los permisos efectivos son configurables.

## 7. Middlewares, guards, interceptores

No se observó un framework moderno con conceptos denominados formalmente middleware/guard/interceptor. Sus equivalentes funcionales son:

- includes de inicialización (`client.inc.php`, `staff.inc.php`, `api/api.inc.php`);
- autenticación/autorización de clases del núcleo;
- dispatcher;
- validaciones en controladores;
- señales/plugins.

## 8. Manejo de errores

- AJAX cliente/staff: usa `Http::response(...)`, por ejemplo 403 para login requerido.
- `Bootstrap::croak()` responde 500 con un mensaje genérico y envía una alerta de error por correo.
- El bootstrap controla configuración de reporting de PHP.

## 9. APIs externas consumidas

**No determinado de forma exhaustiva:** el proyecto incluye infraestructura de correo y plugins capaces de integrarse con sistemas externos, pero no se documenta aquí una API externa concreta sin una llamada/configuración específica verificada.

Por seguridad, esta documentación tampoco reproduce credenciales, claves o destinos de despliegue.
