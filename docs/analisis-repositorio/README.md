# Análisis técnico del repositorio IRIS


## Alcance y criterio de evidencia

Esta documentación describe únicamente elementos comprobados en el repositorio. Se usan tres niveles:

- **Comprobado:** existe evidencia directa en archivos, rutas, configuración o esquema SQL del repositorio.
- **Inferencia:** interpretación técnica sustentada en varios elementos del código, pero no declarada explícitamente por el proyecto.
- **Recomendación:** propuesta de mejora; no describe el comportamiento actual.

Cuando una característica no pudo establecerse con certeza a partir del código inspeccionado, se indica expresamente.

## Descripción general

**Comprobado.** El `README.md` original identifica el proyecto como `ost_institucional` y lo describe como “Sistema institucional”. El núcleo de la aplicación corresponde a **osTicket 1.18-git**, versión declarada en `bootstrap.php` mediante `MAJOR_VERSION` y `THIS_VERSION`.

El sistema implementa una plataforma de gestión de solicitudes/tickets con interfaz pública, consola de agentes, usuarios, organizaciones, departamentos, equipos, tareas, hilos de conversación, formularios dinámicos, FAQ/base de conocimiento, SLA, filtros, plugins, correo y reportes. La presencia de estos conceptos se comprueba en las tablas registradas en `Bootstrap::defineTables()`, las clases de `include/`, los controladores AJAX y el esquema de instalación MySQL.

## Objetivo aparente

**Inferencia.** Por el nombre institucional, la personalización visual ubicada en `custom/material/` y el núcleo osTicket, IRIS funciona como una adaptación institucional de una mesa de ayuda / sistema de gestión de tickets.

## Tecnologías principales

| Tecnología | Evidencia | Uso identificado |
|---|---|---|
| PHP | `*.php`, `bootstrap.php` | Aplicación servidor y vistas |
| osTicket | `bootstrap.php` | Núcleo funcional, versión `1.18-git` |
| MySQL/MariaDB compatible | `setup/inc/streams/core/install-mysql.sql`, `include/mysqli.php` | Persistencia relacional |
| ORM propio de osTicket | `include/class.orm.php` | Mapeo objeto-relacional y relaciones |
| JavaScript/AJAX | `ajax.php`, `scp/ajax.php`, `js/`, `scp/js/` | Interacciones asíncronas |
| HTML/CSS | `include/client/`, `include/staff/`, `css/`, `custom/material/` | Interfaz web |
| mPDF | `include/mpdf/` y referencias desde `bootstrap.php` | Generación de PDF |
| Laminas Mail | `include/laminas-mail/` | Infraestructura de correo |
| PEAR / librerías incluidas | `include/pear/` | Utilidades heredadas |

La vigencia actual del soporte de las dependencias no se determina solo con el repositorio; requiere contrastar cada versión con sus fuentes oficiales.

## Arquitectura identificada

**Inferencia sustentada.** Es un **monolito PHP modular**, basado en el núcleo osTicket, con:

1. scripts de entrada HTTP en la raíz y `scp/`;
2. bootstrap compartido y configuración global;
3. despachadores de rutas para API/AJAX;
4. clases de dominio/servicio/infraestructura en `include/`;
5. ORM propio que accede a MySQL;
6. plantillas para cliente y personal;
7. librerías externas distribuidas dentro del árbol del repositorio.

No se observa una separación estricta Controller → Service → Repository. Varias clases del núcleo combinan comportamiento de dominio con persistencia mediante el ORM.

## Índice

- [Vista unificada de expedientes](../expedientes-vista-unificada.md)
- [Arquitectura](arquitectura.md)
- [Estructura del proyecto](estructura-proyecto.md)
- [Modelos y datos](modelos-datos.md)
- [APIs y endpoints](apis-endpoints.md)
- [Flujos de aplicación](flujo-aplicacion.md)
- [Dependencias](dependencias.md)
- [Hallazgos y recomendaciones](hallazgos-recomendaciones.md)

## Hallazgos principales

1. **Comprobado:** el núcleo es osTicket `1.18-git` (`bootstrap.php`).
2. **Comprobado:** la persistencia se apoya en un ORM propio inspirado en Django (`include/class.orm.php`) y en MySQL (`setup/inc/streams/core/install-mysql.sql`).
3. **Comprobado:** existe una API HTTP explícita para creación de tickets y ejecución cron (`api/http.php`) y dos superficies AJAX diferenciadas: cliente (`ajax.php`) y staff (`scp/ajax.php`).
4. **Comprobado:** `bootstrap.php` activa `display_errors` y `display_startup_errors`; esto merece revisión para producción.
5. **Comprobado:** existen personalizaciones propias bajo `custom/material/`, lo que permite aislar al menos parte del código visual institucional del núcleo upstream.
6. **Inferencia:** el alto acoplamiento al bootstrap, constantes globales y clases de dominio con persistencia hace que cambios transversales requieran pruebas de regresión amplias.
7. **Comprobado:** el repositorio contiene dependencias vendorizadas dentro de `include/` (`mpdf`, `laminas-mail`, `pear`, entre otras). Esto aumenta la importancia de mantener un inventario explícito de versiones.
8. **No determinado:** no se afirma cobertura de pruebas, CI, ni estado de soporte de librerías sin evidencia suficiente en los archivos inspeccionados.

## Archivos de evidencia principales

- `README.md`
- `bootstrap.php`
- `ajax.php`
- `scp/ajax.php`
- `api/http.php`
- `api/api.inc.php`
- `include/class.api.php`
- `include/api.tickets.php`
- `include/class.orm.php`
- `include/class.ticket.php`
- `include/mysqli.php`
- `setup/inc/streams/core/install-mysql.sql`
- `custom/material/`
