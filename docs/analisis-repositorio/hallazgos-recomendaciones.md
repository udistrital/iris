# Hallazgos y recomendaciones

Los hallazgos se basan en el estado observado del repositorio. Las recomendaciones no implican que deban aplicarse todas de inmediato.

## Arquitectura

### A-01 — Acoplamiento elevado al bootstrap global

**Hallazgo:** gran parte del sistema depende de constantes, includes y configuración cargados globalmente.  
**Evidencia:** `bootstrap.php` define rutas, versión, tablas, conexión y carga de clases; `api/http.php`, `ajax.php` y `scp/ajax.php` dependen del bootstrap/includes comunes.  
**Archivo(s):** `bootstrap.php`, `api/api.inc.php`, `ajax.php`, `scp/ajax.php`.  
**Impacto:** dificulta pruebas aisladas y aumenta el alcance de regresión de cambios transversales.  
**Recomendación:** para funcionalidades nuevas, introducir seams/adaptadores pequeños y testeables alrededor de integraciones, sin reescribir el núcleo de una sola vez.  
**Prioridad:** Media.

### A-02 — No existe separación estricta Controller/Service/Repository

**Hallazgo:** el proyecto usa controladores y clases de dominio integradas al ORM, no una capa repository/service formal independiente.  
**Evidencia:** `include/class.orm.php`, `include/class.ticket.php`, `include/api.tickets.php`.  
**Archivo(s):** los anteriores.  
**Impacto:** reglas, persistencia y orquestación pueden quedar estrechamente relacionadas.  
**Recomendación:** mantener el diseño existente para correcciones puntuales y aislar nueva lógica compleja en servicios propios cuando aporte valor; evitar una migración arquitectónica masiva sin pruebas.  
**Prioridad:** Media.

## Código

### C-01 — Compatibilidad heredada aumenta complejidad

**Hallazgo:** `bootstrap.php` contiene polyfills/fallbacks para funciones/extensiones y lógica para versiones antiguas de PHP.  
**Evidencia:** fallback de `mbstring`, `iconv`, `random_int` para PHP < 7 y compatibilidad de configuración antigua.  
**Archivo(s):** `bootstrap.php`.  
**Impacto:** mayor superficie de código y dificultad para conocer qué ramas siguen siendo necesarias en el runtime actual.  
**Recomendación:** primero documentar la versión PHP realmente soportada/desplegada; después retirar compatibilidad solo mediante una actualización controlada del núcleo y pruebas de regresión.  
**Prioridad:** Media.

### C-02 — Código upstream y personalización deben distinguirse explícitamente

**Hallazgo:** el repositorio es una adaptación de osTicket y contiene personalización propia, incluyendo `custom/material/v1/iris-material-custom.css`.  
**Evidencia:** versión/núcleo en `bootstrap.php`; carpeta `custom/`; README `ost_institucional`.  
**Archivo(s):** `README.md`, `bootstrap.php`, `custom/material/v1/iris-material-custom.css`.  
**Impacto:** modificar directamente archivos upstream puede complicar upgrades y resolución de conflictos.  
**Recomendación:** inventariar todas las diferencias institucionales frente al upstream y preferir `custom/`, plugins, señales o extensiones cuando el núcleo lo permita.  
**Prioridad:** Alta.

## APIs

### API-01 — API pública y AJAX interno tienen contratos diferentes

**Hallazgo:** `api/http.php` expone API externa, mientras `ajax.php` y `scp/ajax.php` exponen endpoints internos ligados a sesión/interfaz.  
**Evidencia:** dispatchers y includes de autenticación distintos.  
**Archivo(s):** `api/http.php`, `ajax.php`, `scp/ajax.php`.  
**Impacto:** consumir AJAX interno como si fuera API estable crea fuerte acoplamiento con la UI y autenticación de osTicket.  
**Recomendación:** nuevas integraciones externas deben usar/crear contratos formales en la superficie API o un adaptador dedicado, no depender de rutas AJAX de staff.  
**Prioridad:** Alta.

### API-02 — Superficie AJAX de staff es extensa

**Hallazgo:** `scp/ajax.php` registra muchas operaciones sensibles: usuarios, organizaciones, tickets, plugins, configuración, reportes y tareas.  
**Evidencia:** árbol de rutas del dispatcher.  
**Archivo(s):** `scp/ajax.php`.  
**Impacto:** un error de autorización o proxy puede tener un alcance funcional alto.  
**Recomendación:** conservar la autenticación de `staff.inc.php`, revisar permisos por operación y añadir pruebas de autorización a rutas críticas al modificar esa superficie.  
**Prioridad:** Alta.

## Datos

### D-01 — Relaciones dependen en parte de metadata ORM

**Hallazgo:** `class.orm.php` procesa `joins` y `foreign_keys` lógicas definidas por modelos.  
**Evidencia:** `ModelMeta::$base`, `processJoin()`.  
**Archivo(s):** `include/class.orm.php`, clases de modelo, `setup/inc/streams/core/install-mysql.sql`.  
**Impacto:** analizar solo DDL puede omitir relaciones utilizadas por el código.  
**Recomendación:** cualquier cambio de modelo debe revisar simultáneamente DDL, metadata ORM y código que usa la relación.  
**Prioridad:** Alta.

### D-02 — Esquema dinámico mediante formularios y `__cdata`

**Hallazgo:** existen tablas de formularios/valores y tablas `ticket__cdata`, `user__cdata`, `organization__cdata`, `task__cdata`.  
**Evidencia:** `Bootstrap::defineTables()` y esquema SQL.  
**Archivo(s):** `bootstrap.php`, `setup/inc/streams/core/install-mysql.sql`.  
**Impacto:** consultas directas que ignoren campos dinámicos pueden producir una visión incompleta del dominio.  
**Recomendación:** evitar asumir que todos los datos funcionales viven como columnas estáticas de la tabla principal.  
**Prioridad:** Media.

## Seguridad

### S-01 — `display_errors` se activa en bootstrap

**Hallazgo:** el bootstrap ejecuta `ini_set('display_errors', 1)` y `ini_set('display_startup_errors', 1)`.  
**Evidencia:** código directo.  
**Archivo(s):** `bootstrap.php`.  
**Impacto:** dependiendo de configuración/runtime, errores no controlados podrían revelar rutas, detalles internos o información de diagnóstico.  
**Recomendación:** confirmar comportamiento del ambiente productivo y desactivar exposición al cliente, enviando errores a logging seguro.  
**Prioridad:** Alta.

### S-02 — API depende de configuración de claves/permisos

**Hallazgo:** existe tabla/modelo de `api_key` e infraestructura específica de API.  
**Evidencia:** `Bootstrap::defineTables()`, `include/class.api.php`, `api/api.inc.php`.  
**Archivo(s):** los anteriores.  
**Impacto:** configuraciones débiles o claves excesivamente privilegiadas aumentarían riesgo de uso no autorizado.  
**Recomendación:** rotación, mínimo privilegio, restricción por origen cuando aplique y auditoría de uso. No almacenar claves en documentación.  
**Prioridad:** Alta.

### S-03 — Superficies AJAX responden 403 cuando falta login

**Hallazgo:** tanto cliente como staff tienen funciones de login AJAX que responden HTTP 403.  
**Evidencia:** `clientLoginPage()` y `staffLoginPage()`.  
**Archivo(s):** `ajax.php`, `scp/ajax.php`.  
**Impacto:** es un control positivo, pero debe preservarse al cambiar proxies, rewrites o entrypoints.  
**Recomendación:** añadir pruebas de acceso anónimo a acciones críticas y verificar que no existan rutas alternativas que eviten estos includes.  
**Prioridad:** Media.

## Mantenibilidad

### M-01 — README insuficiente para la complejidad del sistema

**Hallazgo:** el README original contiene únicamente el título `ost_institucional` y “Sistema institucional”.  
**Evidencia:** `README.md`.  
**Archivo(s):** `README.md`.  
**Impacto:** onboarding lento y alto conocimiento tácito.  
**Recomendación:** enlazar desde el README principal esta documentación técnica y añadir instrucciones de entorno/despliegue verificadas.  
**Prioridad:** Alta.

### M-02 — Dependencias vendorizadas dentro del repositorio

**Hallazgo:** se verifican árboles de `mpdf`, `laminas-mail` y `pear` bajo `include/`.  
**Evidencia:** estructura del repositorio.  
**Archivo(s):** `include/mpdf/`, `include/laminas-mail/`, `include/pear/`.  
**Impacto:** seguimiento manual de versiones y vulnerabilidades; riesgo de cambios accidentales en vendor.  
**Recomendación:** construir un inventario/SBOM antes de actualizar o eliminar cualquier dependencia.  
**Prioridad:** Media.

### M-03 — Estado de pruebas y cobertura no determinado en este análisis

**Hallazgo:** no existe evidencia suficiente en los archivos ya inspeccionados para declarar un porcentaje o estrategia de cobertura.  
**Evidencia:** no se encontró una fuente canónica de cobertura durante este análisis.  
**Archivo(s):** N/A.  
**Impacto:** no se puede estimar con rigor el riesgo de regresión solo desde documentación.  
**Recomendación:** inventariar suites automatizadas y, si son insuficientes, priorizar pruebas de caracterización sobre creación de tickets, autenticación, correo y permisos antes de refactors.  
**Prioridad:** Alta.

## Priorización sugerida

1. Revisar exposición de errores en producción (`S-01`).
2. Inventariar y aislar personalizaciones respecto de osTicket upstream (`C-02`).
3. Formalizar/documentar contratos de integraciones y no usar AJAX interno como API (`API-01`).
4. Añadir pruebas de autorización y caracterización de flujos críticos (`API-02`, `S-03`, `M-03`).
5. Generar inventario de dependencias/SBOM (`M-02`).
6. Solo después considerar refactors arquitectónicos graduales (`A-01`, `A-02`).
