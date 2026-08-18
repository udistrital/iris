# Flujos de aplicación

## 1. Flujo general

```mermaid
sequenceDiagram
    participant U as Usuario/Integración
    participant E as Entrypoint PHP
    participant B as Bootstrap/Include de sesión
    participant D as Dispatcher/Controller
    participant M as Dominio osTicket
    participant O as ORM
    participant DB as MySQL

    U->>E: Request HTTP
    E->>B: Inicialización
    B->>DB: Carga configuración/sesión según flujo
    E->>D: Resolver ruta o acción
    D->>M: Validar y ejecutar operación
    M->>O: Consultar/guardar modelos
    O->>DB: SQL
    DB-->>O: Resultado
    O-->>M: Modelos/datos
    M-->>D: Resultado
    D-->>U: HTML/JSON/XML/HTTP response
```

Este diagrama representa el patrón predominante; las páginas heredadas pueden combinar pasos en un mismo script.

## 2. Creación de ticket mediante API

### Punto de entrada

`api/http.php` registra:

`POST /tickets.{xml|json|email}` → `TicketApiController::create`.

### Flujo

```mermaid
sequenceDiagram
    participant X as Sistema externo
    participant H as api/http.php
    participant A as api/api.inc.php
    participant C as TicketApiController
    participant T as Dominio Ticket
    participant O as ORM
    participant DB as MySQL

    X->>H: POST ticket (xml/json/email)
    H->>A: Inicialización API
    A->>A: Validar contexto/API
    H->>C: create(request)
    C->>C: Parsear y validar entrada
    C->>T: Crear ticket
    T->>O: Persistir entidades relacionadas
    O->>DB: INSERT/UPDATE
    DB-->>O: Resultado
    O-->>T: Estado persistido
    T-->>C: Ticket creado / error
    C-->>X: Respuesta HTTP
```

### Validaciones

**Comprobado:** el controlador de tickets y la infraestructura API realizan validación antes de crear el ticket. Los campos concretos dependen del formato y de formularios/configuración activa.

### Persistencia

Involucra al menos la entidad ticket y estructuras relacionadas de formulario/thread conforme a la lógica osTicket. No se fija aquí una lista inmutable de INSERTs porque puede variar con configuración, formularios y plugins.

## 3. Operación AJAX del cliente

Ejemplo: obtención de formularios para un tema de ayuda.

```mermaid
sequenceDiagram
    participant B as Browser
    participant A as ajax.php
    participant S as client.inc.php
    participant D as Dispatcher
    participant F as DynamicFormsAjaxAPI
    participant M as Modelos de formularios
    participant DB as MySQL

    B->>A: GET /form/help-topic/{id}
    A->>S: Inicializar cliente/sesión
    A->>D: resolve(path_info)
    D->>F: getClientFormsForHelpTopic(id)
    F->>M: Consultar formulario/tema
    M->>DB: Query vía ORM
    DB-->>M: Datos
    M-->>F: Formularios
    F-->>B: Respuesta AJAX
```

## 4. Operación AJAX de staff sobre tickets

`scp/ajax.php` registra numerosas operaciones sobre `/tickets/`, incluyendo preview, status, tareas, búsqueda, transferencia, asignación, referidos, campos y exportación.

```mermaid
sequenceDiagram
    participant S as Staff browser
    participant A as scp/ajax.php
    participant Auth as staff.inc.php
    participant C as TicketsAjaxAPI
    participant T as Ticket/domain
    participant DB as MySQL

    S->>A: Request /tickets/...
    A->>Auth: Validar sesión staff
    alt No autorizado
        Auth-->>S: HTTP 403
    else Autorizado
        A->>C: Acción resuelta
        C->>T: Validar permiso/estado y ejecutar
        T->>DB: Persistencia vía ORM
        DB-->>T: Resultado
        T-->>C: Resultado
        C-->>S: Respuesta AJAX
    end
```

## 5. Cron vía API

`POST /tasks/cron` → `CronApiController::execute`.

**Inferencia sustentada:** permite disparar procesamiento periódico desde una llamada HTTP autenticada/configurada, en lugar de depender exclusivamente de ejecución local.

## 6. Inicialización de aplicación

`bootstrap.php` realiza, entre otras, las siguientes tareas comprobadas:

1. ajusta opciones de PHP;
2. configura timezone UTC;
3. define rutas internas;
4. declara versión `1.18-git`;
5. carga configuración local;
6. define constantes de tablas según prefijo;
7. conecta a BD;
8. carga clases base;
9. prepara i18n.

El orden preciso completo debe consultarse en `Bootstrap::init()`.

## 7. Errores fatales

`Bootstrap::croak()`:

1. construye un mensaje con la página actual;
2. intenta enviar alerta mediante `osTicket\Mail\Mailer::sendmail`;
3. devuelve HTTP 500 con mensaje genérico al usuario.

Esto evita exponer el detalle del error en esa ruta concreta, aunque el estado global de `display_errors` se analiza como hallazgo separado.
