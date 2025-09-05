<?php
// generate_pdf.php

require_once __DIR__ . '/vendor/autoload.php';
include_once 'db_config.php';
date_default_timezone_set('America/Asuncion');

// Lógica para formatear la fecha de manera consistente y robusta
if (extension_loaded('intl')) {
    $formatter = new IntlDateFormatter(
        'es_ES',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        'America/Asuncion',
        IntlDateFormatter::GREGORIAN
    );
    $fecha_actual_header = 'Luque, ' . $formatter->format(time());
} else {
    // Fallback: usar un array de meses para garantizar el español
    $meses = array("enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre");
    $dia = date('d');
    $mes = $meses[date('n') - 1];
    $anio = date('Y');
    $fecha_actual_header = "Luque, $dia de $mes del $anio";
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mantenimiento_id = $_GET['id'] ?? null;

if (!$mantenimiento_id) {
    die("Error: No se ha proporcionado un ID de mantenimiento para generar el PDF. Asegúrate de que el botón de descarga en conformidad_mantenimiento.php está pasando el ID.");
}

try {
    $stmt = $pdo->prepare("SELECT * FROM conformidades_digitales WHERE id = ?");
    $stmt->bindParam(1, $mantenimiento_id, PDO::PARAM_INT);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Error: No se encontraron datos para el ID de mantenimiento proporcionado (" . htmlspecialchars($mantenimiento_id, ENT_QUOTES, 'UTF-8') . "). Verifica que el ID exista en la tabla 'conformidades_digitales'.");
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font_size' => 10,
        'default_font' => 'dejavusans'
    ]);

    $logoPath = 'medsupar_logo.jpg';
    $logoHtml = '';
    if (file_exists($logoPath) && is_readable($logoPath)) {
        $type = pathinfo($logoPath, PATHINFO_EXTENSION);
        $imgData = file_get_contents($logoPath);
        if ($imgData !== false) {
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
            $logoHtml = '<img src="' . $base64 . '" style="width: 100px; height: auto;">';
        }
    } else {
        error_log("ADVERTENCIA: Archivo de logo no encontrado o no legible en: " . $logoPath);
    }

    $userSignatureImagePath = isset($data['ruta_firma_usuario']) ? $data['ruta_firma_usuario'] : '';
    $tiSignatureImagePath = isset($data['ruta_firma_ti']) ? $data['ruta_firma_ti'] : '';

    $userSignatureHtml = '';
    if (!empty($userSignatureImagePath) && file_exists($userSignatureImagePath)) {
        $type = pathinfo($userSignatureImagePath, PATHINFO_EXTENSION);
        $imgData = file_get_contents($userSignatureImagePath);
        if ($imgData !== false) {
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
            $userSignatureHtml = '<img src="' . $base64 . '" style="max-width: 150px; height: auto;">';
        }
    } else {
        $userSignatureHtml = '<span style="color: #666; font-style: italic;">Firma de usuario no disponible</span>';
        error_log("ADVERTENCIA: Firma de usuario no encontrada o no legible en: " . htmlspecialchars($userSignatureImagePath, ENT_QUOTES, 'UTF-8'));
    }

    $tiSignatureHtml = '';
    if (!empty($tiSignatureImagePath) && file_exists($tiSignatureImagePath)) {
        $type = pathinfo($tiSignatureImagePath, PATHINFO_EXTENSION);
        $imgData = file_get_contents($tiSignatureImagePath);
        if ($imgData !== false) {
            $base64 = 'data:image/' . $type . ';base64,' . base64_encode($imgData);
            $tiSignatureHtml = '<img src="' . $base64 . '" style="max-width: 150px; height: auto;">';
        }
    } else {
        $tiSignatureHtml = '<span style="color: #666; font-style: italic;">Firma de TI no disponible</span>';
        error_log("ADVERTENCIA: Firma de TI no encontrada o no legible en: " . htmlspecialchars($tiSignatureImagePath, ENT_QUOTES, 'UTF-8'));
    }

    $tareas_usuario_array = array_map('trim', explode(',', $data['tareas_usuario']));
    $componentes_ti_array = array_map('trim', explode(',', $data['componentes_ti']));
    $archivos_adjuntos = !empty($data['archivos']) ? explode(',', $data['archivos']) : [];

    function getCheckboxHtml($item, $dataArray)
    {
        $checked = in_array($item, $dataArray) ? '&#9745;' : '&#9744;';
        return '<span style="font-family: DejaVu Sans; font-size: 1.1em; vertical-align: middle;">' . $checked . '</span> <span style="vertical-align: middle;">' . htmlspecialchars($item, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $archivosHtml = '';
    if (!empty($archivos_adjuntos)) {
        $archivosHtml .= '<div style="margin-top: 20px;">';
        $archivosHtml .= '<p style="font-weight: bold; margin-bottom: 5px;">Archivos adjuntos:</p>';
        $archivosHtml .= '<ul>';
        foreach ($archivos_adjuntos as $filePath) {
            $fileName = basename($filePath);
            $fileUrl = str_replace('\\', '/', $filePath);
            // Solo permitir extensiones seguras
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','pdf'])) {
                $archivosHtml .= '<li><a href="' . htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') . '</a></li>';
            }
        }
        $archivosHtml .= '</ul>';
        $archivosHtml .= '</div>';
    }

    $html = '
    <div style="width: 100%; font-family: sans-serif; font-size: 10pt;">
        <table width="100%" style="border-collapse: collapse; margin-bottom: 20px;">
            <tr>
                <td style="width: 15%; vertical-align: middle; text-align: left; padding: 5px;">' . $logoHtml . '</td>
                <td style="width: 60%; vertical-align: middle; text-align: center; font-size: 1.4em; font-weight: bold; padding: 5px;">
                    Hoja de Conformidad de Mantenimiento
                </td>
                <td style="width: 25%; text-align: right; font-size: 8pt; vertical-align: middle; padding: 5px;">
                    Edición: 01 | Pág. 1 de 1<br>
                    Vigencia: 15/12/2021<br>
                    Código: R-01-01<br>
                    ' . $fecha_actual_header . '
                </td>
            </tr>
        </table>
        <hr style="border: 0; height: 1px; background: #ddd; margin: 10px 0;">

        <div style="background-color: #495057; color: white; padding: 8px 15px; border-radius: 5px; text-align: center; font-weight: bold; font-size: 1.2em; margin-bottom: 15px;">USO DEL USUARIO</div>

        <p style="margin-bottom: 20px;">Al firmar este documento doy mi conformidad plena de que se realizaron correctamente los trabajos de
        mantenimiento referentes a mi equipo <span style="font-weight: bold; text-decoration: underline;">' . htmlspecialchars($data['equipo_usuario']) . '</span> , según las tareas marcadas a continuación:</p>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; padding: 0; vertical-align: top;">
                    ' . getCheckboxHtml('Limpieza de Gabinete', $tareas_usuario_array) . '<br>
                    ' . getCheckboxHtml('Limpieza de Teclado', $tareas_usuario_array) . '<br>
                    ' . getCheckboxHtml('Eliminación de archivos temporales', $tareas_usuario_array) . '<br>
                    ' . getCheckboxHtml('Control de activación de Windows', $tareas_usuario_array) . '
                </td>
                <td style="width: 50%; padding: 0; vertical-align: top;">
                    ' . getCheckboxHtml('Control de activación de Office', $tareas_usuario_array) . '<br>
                    ' . getCheckboxHtml('Actualización del antivirus', $tareas_usuario_array) . '<br>
                    ' . getCheckboxHtml('Actualización del sistema', $tareas_usuario_array) . '<br>
                    ' . getCheckboxHtml('Instalación de programas permitidos', $tareas_usuario_array) . '
                </td>
            </tr>
        </table>

        <div style="background-color: #495057; color: white; padding: 8px 15px; border-radius: 5px; text-align: center; font-weight: bold; font-size: 1.2em; margin-bottom: 15px;">USO EXCLUSIVO DE TI</div>

        <p style="margin-bottom: 15px;">Según el mantenimiento realizado se verificaron los componentes del equipo y los ítems marcados a continuación coinciden con los detallados en la hoja de vida del equipo:</p>

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
            <tr>
                <td style="width: 50%; padding: 0; vertical-align: top;">
                    ' . getCheckboxHtml('Placa madre', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Procesador', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Fuente', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Memoria RAM', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Teclado', $componentes_ti_array) . '
                </td>
                <td style="width: 50%; padding: 0; vertical-align: top;">
                    ' . getCheckboxHtml('Mouse', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Monitor', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Cargador', $componentes_ti_array) . '<br>
                    ' . getCheckboxHtml('Batería', $componentes_ti_array) . '
                </td>
            </tr>
        </table>

        <p style="margin-bottom: 10px;"><strong>Referencia:</strong></p>
        <p style="margin-left: 20px;">
            <span style="font-family: DejaVu Sans; font-size: 1.1em;">' . ($data['estado_referencia'] == 'En existencia' ? '&#9745;' : '&#9744;') . '</span> En existencia &nbsp;&nbsp;&nbsp;&nbsp;
            <span style="font-family: DejaVu Sans; font-size: 1.1em;">' . ($data['estado_referencia'] == 'En falta' ? '&#9745;' : '&#9744;') . '</span> En falta
        </p>

        <p style="margin-bottom: 5px;"><strong>Observación:</strong></p>
        <div style="border: 1px solid #ccc; padding: 10px; min-height: 80px; background-color: #f9f9f9;">
            ' . nl2br(htmlspecialchars($data['observaciones'])) . '
        </div>

        ' . $archivosHtml . '

        <h2 style="background-color: #495057; color: white; padding: 8px 15px; border-radius: 5px; text-align: center; font-weight: bold; font-size: 1.2em; margin-top: 30px; margin-bottom: 20px;">Firmas</h2>

        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="width: 50%; text-align: center; padding: 10px;">
                    <p style="font-weight: bold; margin-bottom: 5px;">Firma usuario:</p>
                    <div style="border: 1px solid #ccc; min-height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: #f9f9f9;">
                        ' . $userSignatureHtml . '
                    </div>
                    <p style="font-weight: bold; margin-top: 15px; margin-bottom: 5px;">Aclaración Usuario:</p>
                    <div style="border: 1px solid #ccc; padding: 5px; min-height: 30px; background-color: #f9f9f9;">' . htmlspecialchars($data['aclaracion_usuario']) . '</div>
                </td>
                <td style="width: 50%; text-align: center; padding: 10px;">
                    <p style="font-weight: bold; margin-bottom: 5px;">Firma TI:</p>
                    <div style="border: 1px solid #ccc; min-height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: #f9f9f9;">
                        ' . $tiSignatureHtml . '
                    </div>
                    <p style="font-weight: bold; margin-top: 15px; margin-bottom: 5px;">Aclaración TI:</p>
                    <div style="border: 1px solid #ccc; padding: 5px; min-height: 30px; background-color: #f9f9f9;">' . htmlspecialchars($data['aclaracion_ti']) . '</div>
                </td>
            </tr>
        </table>
    </div>';

    $mpdf->WriteHTML($html);
    $mpdf->Output('conformidad_mantenimiento_' . $data['equipo_usuario'] . '_' . date('Ymd_His', strtotime($data['fecha_envio'])) . '.pdf', 'I');

} catch (PDOException $e) {
    error_log("Error fetching conformity data for PDF: " . $e->getMessage());
    die("Error al obtener datos de la conformidad para el PDF. Por favor, intente de nuevo más tarde.<br>Detalles de error: " . htmlspecialchars($e->getMessage()));
} catch (\Mpdf\MpdfException $e) {
    error_log("Error generating PDF: " . $e->getMessage());
    die("Error al generar el PDF (mPDF). Detalles: " . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    error_log("Ha ocurrido un error inesperado al generar el PDF: " . $e->getMessage());
    die("Ha ocurrido un error inesperado al generar el PDF. Detalles: " . htmlspecialchars($e->getMessage()));
}