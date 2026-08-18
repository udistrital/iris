# Dependencias

## 1. Plataforma

### PHP

La aplicación está implementada en PHP. `bootstrap.php` contiene compatibilidad heredada con distintas versiones y extensiones.

**No determinado:** el repositorio por sí solo no establece con suficiente precisión la versión de PHP desplegada actualmente en producción.

### MySQL / MySQLi

- `include/mysqli.php`
- `setup/inc/streams/core/install-mysql.sql`
- `Bootstrap::connect()`

Uso: persistencia principal.

## 2. Núcleo osTicket

`bootstrap.php` declara:

```text
MAJOR_VERSION = 1.18
THIS_VERSION = 1.18-git
```

Esto identifica la base del producto, pero no se debe asumir que el repositorio coincide exactamente con una release upstream porque contiene personalizaciones.

## 3. ORM propio

**Archivo:** `include/class.orm.php`.

No es una dependencia externa; forma parte del núcleo. Gestiona metadata de modelos, joins, claves externas lógicas y consultas.

## 4. Librerías vendorizadas verificadas

### mPDF

**Ruta:** `include/mpdf/`  
**Propósito:** generación/renderizado de PDF. `bootstrap.php` contiene compatibilidad específica porque mPDF requiere funciones `mbstring`.

### Laminas Mail

**Ruta:** `include/laminas-mail/`  
**Propósito:** infraestructura de correo.

El árbol incluye paquetes Laminas relacionados, por ejemplo `laminas-stdlib` y `laminas-validator` dentro de la distribución vendorizada.

### PEAR

**Ruta:** `include/pear/`  
**Propósito:** utilidades heredadas. Se verificó, entre otros, `include/pear/Math/BigInteger.php`.

### TNEF decoder

**Ruta:** `include/tnef_decoder.php`  
**Propósito aparente:** procesamiento de adjuntos/correo en formato TNEF.

## 5. Extensiones PHP observadas o contempladas

A partir de `bootstrap.php`:

- `mbstring` — preferida para procesamiento multibyte;
- `iconv` — fallback de conversión;
- `APCu` — cache opcional de metadata ORM (`class.orm.php`);
- `exif` / `getimagesize` — compatibilidad para tipo de imagen;
- MySQLi — persistencia.

## 6. Dependencias front-end

El repositorio contiene JavaScript y CSS propios/vendor en:

- `js/`;
- `css/`;
- `scp/js/`;
- `scp/css/`;
- `custom/material/`.

No se documenta un gestor Node/npm como requisito sin un manifiesto verificado que lo respalde.

## 7. Gestión de dependencias

**Comprobado:** existen bibliotecas con sus árboles `vendor` dentro de `include/`.

**Inferencia:** al menos parte de las dependencias se distribuye junto con el código de aplicación en vez de resolverse exclusivamente durante build/deploy.

### Riesgos

- inventario de versiones menos visible;
- actualizaciones de seguridad más difíciles de automatizar;
- posibilidad de modificar accidentalmente código vendorizado;
- mayor tamaño del repositorio.

## 8. Dependencias potencialmente obsoletas

No se etiqueta una librería como “obsoleta” únicamente por antigüedad aparente. Para determinar soporte vigente debe contrastarse la **versión exacta** incluida con fuentes oficiales y avisos de seguridad actuales.

Sí se considera **riesgo de mantenibilidad** la coexistencia de componentes heredados (PEAR, compatibilidad con PHP antiguo y código vendorizado) porque aumenta la superficie que debe probarse al actualizar runtime.

## 9. Dependencias duplicadas

No se afirma duplicación de paquetes sin un inventario de manifests/lockfiles completo y comparación de versiones. La presencia de subárboles `vendor` anidados puede incorporar dependencias transitivas repetidas; debe confirmarse antes de eliminar cualquier archivo.

## 10. SDKs y cloud

En el árbol del repositorio se observó configuración de despliegue asociada a AWS Elastic Beanstalk. Esto demuestra intención/soporte de despliegue en AWS, pero no permite afirmar la topología real del ambiente productivo actual.

No se reproducen secretos ni valores sensibles de infraestructura.

## 11. Recomendación de inventario

Generar posteriormente un SBOM o inventario automático de librerías, pero hacerlo como actividad separada de este análisis y sin modificar funcionalidad. Debe incluir nombre, versión, licencia, ruta, CVEs aplicables y origen upstream.
