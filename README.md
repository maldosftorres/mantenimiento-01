# Hoja de Conformidad de Mantenimiento - MEDSUPAR

Este proyecto es una aplicación web en PHP para la gestión digital de hojas de conformidad de mantenimiento de equipos informáticos. Permite registrar, firmar digitalmente y almacenar información sobre tareas de mantenimiento, generando comprobantes en PDF y almacenando los datos y archivos asociados en una base de datos.

## Características principales

- Registro de conformidades de mantenimiento mediante formulario web.
- Firma digital del usuario y del personal de TI (SignaturePad).
- Almacenamiento seguro de firmas y archivos adjuntos.
- Generación de comprobante PDF con todos los datos y firmas.
- Listado y descarga de archivos adjuntos.
- Seguridad reforzada: validación de entradas, control de archivos, logs y protección de carpetas.

## Estructura del proyecto

```
├── index.php                  # Formulario principal y lógica de guardado
├── conformidad_mantenimiento.php # Variante del formulario
├── generate_pdf.php           # Generación y descarga del PDF
├── db_config.php              # Configuración de la base de datos
├── test_db_connection.php     # Script para probar la conexión a la base de datos
├── test_temp.php              # Script para probar permisos de la carpeta temp
├── signatures/                # Almacenamiento de firmas digitales (PNG)
├── uploads/                   # Almacenamiento de archivos adjuntos
├── vendor/                    # Dependencias de Composer (incluye mPDF)
├── temp/                      # Carpeta temporal para mPDF
├── medsupar_logo.jpg          # Logo institucional
├── .gitignore                 # Exclusión de archivos sensibles y temporales
```

## Requisitos

- PHP >= 7.4
- MySQL/MariaDB
- Composer
- Servidor web (XAMPP recomendado para desarrollo)

## Instalación y configuración

1. **Clona el repositorio:**
   ```bash
   git clone https://github.com/maldosftorres/mantenimiento-01.git
   cd mantenimiento-01
   ```

2. **Instala dependencias:**
   ```bash
   composer install
   ```

3. **Configura la base de datos:**
   - Crea una base de datos llamada `mantenimiento_db` en MySQL.
   - Importa la estructura de la tabla principal si es quela tienes.
   - Edita `db_config.php` con tus credenciales de MySQL si es necesario.

4. **Permisos de carpetas:**
   - Asegúrate de que las carpetas `uploads/`, `signatures/` y `temp/` sean escribibles por el servidor web.
   - Los archivos `.htaccess` en `uploads/` y `signatures/` protegen contra la ejecución de scripts.

5. **Configuración recomendada de PHP:**
   - `file_uploads = On`
   - `upload_max_filesize = 2M`
   - `post_max_size = 8M`
   - `extension=gd` (para imágenes)
   - `extension=intl` (opcional, para mejor formato de fechas)

6. **Acceso a la aplicación:**
   - Abre `index.php` o `conformidad_mantenimiento.php` en tu navegador a través de tu servidor local (ej: http://localhost/mantenimiento_db/index.php)

## Seguridad

- Todas las entradas de usuario son validadas y sanitizadas.
- Solo se permiten archivos adjuntos de tipo JPG, PNG y PDF (máx. 2MB).
- Las firmas y archivos adjuntos se almacenan fuera del alcance público directo y protegidos por `.htaccess`.
- Los errores críticos se registran en logs, pero no se muestran detalles al usuario final.

## Dependencias principales

- [mPDF](https://mpdf.github.io/) (para generación de PDFs)
- [SignaturePad](https://github.com/szimek/signature_pad) (para firmas digitales)
- Bootstrap (CDN)

## Scripts útiles

- `test_db_connection.php`: Verifica la conexión a la base de datos y existencia de la tabla.
- `test_temp.php`: Verifica permisos de escritura en la carpeta `temp/`.

## Personalización

- Puedes modificar el logo cambiando `medsupar_logo.jpg`.
- Para agregar campos al formulario, edita `index.php` y la tabla en la base de datos.

## Mejoras recomendadas

- Agregar autenticación y roles de usuario.
- Panel de administración para listar y buscar conformidades.
- Notificaciones por email.
- Exportación de registros a CSV/Excel.
- Pruebas automatizadas y documentación adicional.

## Autor

- Maldosftorres

---

¿Dudas o problemas? Revisa los scripts de prueba y los logs, o contacta al administrador del sistema.
