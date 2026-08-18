# Estructura del proyecto

## Árbol simplificado

El siguiente árbol resume únicamente rutas observadas en el repositorio:

```text
iris/
├── README.md
├── bootstrap.php
├── ajax.php
├── api/
│   ├── http.php
│   └── api.inc.php
├── apps/
├── css/
├── custom/
│   └── material/
│       └── v1/
│           └── iris-material-custom.css
├── include/
│   ├── class.*.php
│   ├── api.*.php
│   ├── ajax.*.php
│   ├── client/
│   ├── staff/
│   ├── cli/
│   ├── i18n/
│   ├── upgrader/
│   ├── pear/
│   ├── mpdf/
│   ├── laminas-mail/
│   └── mysqli.php
├── js/
├── scp/
│   ├── ajax.php
│   ├── css/
│   └── js/
└── setup/
    └── inc/
        └── streams/
            └── core/
                └── install-mysql.sql
```

## Raíz

### `bootstrap.php`

Inicialización central. Define rutas internas (`ROOT_DIR`, `INCLUDE_DIR`, `SETUP_DIR`, etc.), versión (`1.18-git`), configuración de PHP, constantes de tablas, conexión a base de datos y carga de clases base.

### `ajax.php`

Punto de entrada AJAX del cliente. Carga `client.inc.php`, crea un dispatcher y expone funciones de configuración, borradores, formularios/carga de archivos e internacionalización.

### Entrypoints web

**Comprobado por la estructura del proyecto:** existen scripts PHP públicos en raíz propios de osTicket para la experiencia de usuario final. Para documentar contratos formales de API se toman únicamente los dispatchers explícitos, no se clasifican automáticamente todos los scripts de página como REST endpoints.

## `api/`

Contiene el punto de entrada de API HTTP y su bootstrap específico.

- `api/http.php`: registra rutas API.
- `api/api.inc.php`: inicialización y validaciones comunes de API.

Los controladores concretos viven bajo `include/api.*.php`.

## `scp/`

Interfaz administrativa / de agentes (“staff control panel”).

- `scp/ajax.php`: gran dispatcher AJAX para operaciones internas.
- `scp/css/`, `scp/js/`: recursos de interfaz de staff.

El archivo `scp/ajax.php` redefine el comportamiento de login para devolver HTTP 403 en solicitudes AJAX no autenticadas.

## `include/`

Es el núcleo principal del sistema.

### `include/class.*.php`

Contiene clases de modelo, dominio e infraestructura. Ejemplos verificados:

- `class.orm.php`: ORM.
- `class.ticket.php`: tickets.
- `class.api.php`: infraestructura de API.
- `class.auth.php`: autenticación.
- `class.http.php`: HTTP.
- `class.signal.php`: señales.
- `class.queue.php`: colas/vistas.
- `class.i18n.php`: internacionalización.
- `class.crypto.php`: criptografía.

### `include/api.*.php`

Controladores para API HTTP. `include/api.tickets.php` implementa la creación de tickets desde API.

### `include/ajax.*.php`

Controladores utilizados por `ajax.php` y `scp/ajax.php`.

### `include/client/`

Plantillas y componentes de interfaz del usuario/cliente.

### `include/staff/`

Plantillas y componentes de la consola de agentes/administradores.

### `include/cli/`

Código ejecutable por línea de comandos.

### `include/i18n/`

Recursos y soporte de internacionalización.

### `include/upgrader/`

Infraestructura de actualización del esquema/aplicación.

### Dependencias incluidas

- `include/pear/`
- `include/mpdf/`
- `include/laminas-mail/`

Estas rutas muestran que varias dependencias están vendorizadas dentro del repositorio.

## `setup/`

Instalador y esquema inicial. El archivo `setup/inc/streams/core/install-mysql.sql` constituye evidencia primaria de estructura relacional, claves e índices iniciales.

## `custom/`

Personalizaciones propias separadas del núcleo. Se verificó `custom/material/v1/iris-material-custom.css`.

**Inferencia:** esta ubicación es apropiada para mantener ajustes visuales institucionales separados del CSS base, aunque no se puede afirmar que todas las personalizaciones del proyecto estén exclusivamente allí.

## `css/`, `js/`

Recursos front-end compartidos por la interfaz pública.

## `apps/`

Directorio presente en el repositorio. Sin atribuirle una responsabilidad más específica de la que pueda comprobarse archivo por archivo, se trata como espacio modular/extensible del sistema.
