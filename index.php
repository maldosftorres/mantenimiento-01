<?php
session_start();
require_once 'db_config.php';

$feedbackMessage = '';
$feedbackType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();

        // Sanitización y validación de entradas
        $userEquipment = htmlspecialchars(trim($_POST['userEquipment'] ?? ''), ENT_QUOTES, 'UTF-8');
        $userTasksString = implode(', ', array_map(function($v){return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');}, $_POST['userTasks'] ?? []));
        $tiComponentsString = implode(', ', array_map(function($v){return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');}, $_POST['tiComponents'] ?? []));
        $referenceStatus = htmlspecialchars(trim($_POST['referenceStatus'] ?? ''), ENT_QUOTES, 'UTF-8');
        $observaciones = htmlspecialchars(trim($_POST['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8');

        $userSignatureData = $_POST['userSignatureData'] ?? '';
        $tiSignatureData = $_POST['tiSignatureData'] ?? '';
        $userAclaracion = htmlspecialchars(trim($_POST['userAclaracion'] ?? ''), ENT_QUOTES, 'UTF-8');
        $tiAclaracion = htmlspecialchars(trim($_POST['tiAclaracion'] ?? ''), ENT_QUOTES, 'UTF-8');

        $submissionDateDB = date('Y-m-d H:i:s');

        // Directorios para guardar las firmas y los archivos
        $signatureDir = 'signatures/';
        if (!is_dir($signatureDir)) {
            if (!mkdir($signatureDir, 0755, true)) {
                throw new Exception("Error: No se pudo crear el directorio de firmas: " . $signatureDir);
            }
        }
        $filesDir = 'uploads/';
        if (!is_dir($filesDir)) {
            if (!mkdir($filesDir, 0755, true)) {
                throw new Exception("Error: No se pudo crear el directorio de archivos: " . $filesDir);
            }
        }

        // Validación y guardado de firmas
        $userSignatureFileName = '';
        if (!empty($userSignatureData)) {
            $userSignatureData = str_replace('data:image/png;base64,', '', $userSignatureData);
            $userSignatureData = base64_decode($userSignatureData);
            $userSignatureFileName = $signatureDir . 'user_signature_' . uniqid() . '.png';
            if (!file_put_contents($userSignatureFileName, $userSignatureData)) {
                throw new Exception("Error: Falló la escritura del archivo de firma del usuario: " . $userSignatureFileName);
            }
        } else {
            throw new Exception("Falta la firma del usuario.");
        }

        $tiSignatureFileName = '';
        if (!empty($tiSignatureData)) {
            $tiSignatureData = str_replace('data:image/png;base64,', '', $tiSignatureData);
            $tiSignatureData = base64_decode($tiSignatureData);
            $tiSignatureFileName = $signatureDir . 'ti_signature_' . uniqid() . '.png';
            if (!file_put_contents($tiSignatureFileName, $tiSignatureData)) {
                throw new Exception("Error: Falló la escritura del archivo de firma de TI: " . $tiSignatureFileName);
            }
        } else {
            throw new Exception("Falta la firma de TI.");
        }

        // --- PROCESAMIENTO DE ARCHIVOS ADJUNTOS ---
        $uploadedFilePaths = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        if (!empty($_FILES['archivos']['name'][0])) {
            $totalFiles = count($_FILES['archivos']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                $fileName = basename($_FILES['archivos']['name'][$i]);
                $fileTmpName = $_FILES['archivos']['tmp_name'][$i];
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $fileMime = mime_content_type($fileTmpName);
                if (!in_array($fileExt, $allowedExtensions) || !in_array($fileMime, $allowedMimeTypes)) {
                    throw new Exception("Tipo de archivo no permitido: " . $fileName);
                }
                if ($_FILES['archivos']['size'][$i] > $maxFileSize) {
                    throw new Exception("El archivo es demasiado grande: " . $fileName);
                }
                $safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
                $filePath = $filesDir . uniqid() . '_' . $safeFileName;
                if (move_uploaded_file($fileTmpName, $filePath)) {
                    $uploadedFilePaths[] = $filePath;
                } else {
                    throw new Exception("Error al subir el archivo: " . $fileName);
                }
            }
        }
        $archivosString = implode(',', $uploadedFilePaths);
        // --- FIN PROCESAMIENTO DE ARCHIVOS ADJUNTOS ---

        $sql = "INSERT INTO conformidades_digitales (fecha_envio, equipo_usuario, tareas_usuario, componentes_ti, estado_referencia, observaciones, ruta_firma_usuario, ruta_firma_ti, aclaracion_usuario, aclaracion_ti, archivos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $submissionDateDB,
            $userEquipment,
            $userTasksString,
            $tiComponentsString,
            $referenceStatus,
            $observaciones,
            $userSignatureFileName,
            $tiSignatureFileName,
            $userAclaracion,
            $tiAclaracion,
            $archivosString
        ]);

        $last_inserted_id = $pdo->lastInsertId();

        $pdo->commit();
        $feedbackMessage = "Formulario enviado y firmas guardadas correctamente en la base de datos.";
        $feedbackType = 'success';

        if ($feedbackType === 'success' && $last_inserted_id) {
            $feedbackMessage .= ' <a href="generate_pdf.php?id=' . $last_inserted_id . '" class="btn btn-sm btn-info" target="_blank">Descargar PDF</a>';
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $feedbackMessage = "Error al procesar el formulario. Por favor, intente nuevamente o contacte al administrador.";
        $feedbackType = 'danger';
        error_log("Error al procesar el formulario de conformidad: " . $e->getMessage());
    }
}

if (extension_loaded('intl')) {
    $formatter = new IntlDateFormatter(
        'es_ES',
        IntlDateFormatter::LONG,
        IntlDateFormatter::NONE,
        'America/Asuncion',
        IntlDateFormatter::GREGORIAN
    );
    $fecha_actual_formato = $formatter->format(time());
} else {
    // strftime está deprecada, usamos date() y un array de meses en español
    $meses = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    $dia = date('d');
    $mes = $meses[(int)date('n')];
    $anio = date('Y');
    $fecha_actual_formato = "$dia de $mes del $anio";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoja de Conformidad de Mantenimiento - MEDSUPAR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 900px;
            margin-top: 50px;
            margin-bottom: 50px;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            background-color: #fff;
        }
        .header-section {
            background-color: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-section img {
            max-height: 80px;
            width: auto;
        }
        .header-info {
            text-align: right;
            font-size: 0.9em;
        }
        .section-title {
            background-color: #495057;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .form-check-label {
            margin-left: 5px;
        }
        .signature-pad-container {
            border: 1px solid #ced4da;
            border-radius: 5px;
            background-color: #fcfcfc;
            margin-top: 10px;
            margin-bottom: 10px;
            position: relative;
        }
        .signature-pad-container canvas {
            display: block;
            background-color: white;
            width: 100%;
            height: 150px;
        }
        .signature-label {
            text-align: center;
            font-size: 0.9em;
        }
        .form-control-plaintext.d-inline-block {
            border-bottom: 1px solid #dee2e6;
            padding: 0;
            vertical-align: baseline;
            line-height: normal;
        }
        /* Estilos para el botón de enviar */
        .btn-primary {
            background-color: #495057;
            border-color: #495057;
        }
        .btn-primary:hover {
            background-color: #343a40;
            border-color: #343a40;
        }
        .btn-primary:focus,
        .btn-primary:active {
            background-color: #343a40 !important;
            border-color: #343a40 !important;
            box-shadow: 0 0 0 0.25rem rgba(73, 80, 87, 0.5) !important;
        }
        /* Estilos para los botones de radio y checkboxes seleccionados */
        .form-check-input:checked {
            background-color: #495057;
            border-color: #495057;
        }
        .form-check-input:checked:focus {
            box-shadow: 0 0 0 0.25rem rgba(73, 80, 87, 0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div>
                <img src="medsupar_logo.jpg" alt="Logo MEDSUPAR">
            </div>
            <div>
                <h4 class="mb-0">Hoja de Conformidad de Mantenimiento</h4>
            </div>
            <div class="header-info">
                <p class="mb-0">Edición: 01 | Pág: 1 de 1</p>
                <p class="mb-0">Vigencia: 15/12/2021</p>
                <p class="mb-0">Código: R-01-01</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12 text-end">
                <p class="mb-0">Luque, <?php echo $fecha_actual_formato; ?></p>
            </div>
        </div>

        <?php if (!empty($feedbackMessage)): ?>
            <div class="alert alert-<?php echo $feedbackType; ?> alert-dismissible fade show" role="alert">
                <?php echo $feedbackMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="" method="POST" id="conformityForm" enctype="multipart/form-data">
            <div class="section-title">USO DEL USUARIO</div>
            <div class="mb-3">
                <label for="userEquipment" class="form-label">Al firmar este documento doy mi conformidad plena de que se realizaron correctamente los trabajos de mantenimiento referentes a mi equipo <input type="text" class="form-control-plaintext d-inline-block w-auto" id="userEquipment" name="userEquipment" placeholder="[equipo]" required>, según las tareas marcadas a continuación:</label>
            </div>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Limpieza de Gabinete" id="limpiezaGabinete">
                        <label class="form-check-label" for="limpiezaGabinete">Limpieza de Gabinete.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Limpieza de Teclado" id="limpiezaTeclado">
                        <label class="form-check-label" for="limpiezaTeclado">Limpieza de Teclado.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Eliminación de archivos temporales" id="eliminacionArchivos">
                        <label class="form-check-label" for="eliminacionArchivos">Eliminación de archivos temporales.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Control de activación de Windows" id="controlWindows">
                        <label class="form-check-label" for="controlWindows">Control de activación de Windows.</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Control de activación de Office" id="controlOffice">
                        <label class="form-check-label" for="controlOffice">Control de activación de Office.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Actualización del antivirus" id="actualizacionAntivirus">
                        <label class="form-check-label" for="actualizacionAntivirus">Actualización del antivirus.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="userTasks[]" value="Actualización del sistema" id="actualizacionSistema">
                        <label class="form-check-label" for="actualizacionSistema">Actualización del sistema.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Instalación de programas permitidos" id="instalacionProgramas">
                        <label class="form-check-label" for="instalacionProgramas">Instalación de programas permitidos.</label>
                    </div>
                </div>
            </div>

            <div class="section-title">USO EXCLUSIVO DE TI</div>
            <p class="mb-3">Según el mantenimiento realizado se verificaron los componentes del equipo y los ítems marcados a continuación coinciden con los detallados en la hoja de vida del equipo:</p>

            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Placa madre" id="placaMadre">
                        <label class="form-check-label" for="placaMadre">Placa madre.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Procesador" id="procesador">
                        <label class="form-check-label" for="procesador">Procesador.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Fuente" id="fuente">
                        <label class="form-check-label" for="fuente">Fuente.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Memoria RAM" id="memoriaRam">
                        <label class="form-check-label" for="memoriaRam">Memoria RAM.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Teclado" id="teclado">
                        <label class="form-check-label" for="teclado">Teclado.</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Mouse" id="mouse">
                        <label class="form-check-label" for="mouse">Mouse.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Monitor" id="monitor">
                        <label class="form-check-label" for="monitor">Monitor.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Cargador" id="cargador">
                        <label class="form-check-label" for="cargador">Cargador.</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Batería" id="bateria">
                        <label class="form-check-label" for="bateria">Batería.</label>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label d-block">Referencia:</label>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="referenceStatus" id="enExistencia" value="En existencia" checked>
                    <label class="form-check-label" for="enExistencia">En existencia</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="referenceStatus" id="enFalta" value="En falta">
                    <label class="form-check-label" for="enFalta">En falta</label>
                </div>
            </div>

            <div class="mb-4">
                <label for="observaciones" class="form-label">Observación:</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
            </div>

            <div class="mb-4">
                <label for="fileUploadsContainer" class="form-label">Adjuntar archivos (Ej. fotos):</label>
                <div id="fileUploadsContainer" class="d-grid gap-2">
                    <input type="file" class="form-control" name="archivos[]">
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="addFileInput()">Agregar otro archivo</button>
            </div>
            <div class="signature-section row mt-5">
                <div class="col-md-6 text-center">
                    <label class="form-label d-block mb-2">Firma usuario:</label>
                    <div class="signature-pad-container">
                        <canvas id="userSignaturePad"></canvas>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="clearSignature('userSignaturePad')">Limpiar Firma</button>
                    <input type="hidden" name="userSignatureData" id="userSignatureData">
                    <div class="mt-4">
                        <input type="text" class="form-control text-center d-inline-block w-auto border-bottom" placeholder="Aclaración" name="userAclaracion">
                        <p class="signature-label mt-1">Aclaración</p>
                    </div>
                </div>
                <div class="col-md-6 text-center">
                    <label class="form-label d-block mb-2">Firma TI:</label>
                    <div class="signature-pad-container">
                        <canvas id="tiSignaturePad"></canvas>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="clearSignature('tiSignaturePad')">Limpiar Firma</button>
                    <input type="hidden" name="tiSignatureData" id="tiSignatureData">
                    <div class="mt-4">
                        <input type="text" class="form-control text-center d-inline-block w-auto border-bottom" placeholder="Aclaración" name="tiAclaracion">
                        <p class="signature-label mt-1">Aclaración</p>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary" id="submitFormBtn">Enviar Conformidad</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>
    <script>
        const userCanvas = document.getElementById('userSignaturePad');
        const userSignaturePad = new SignaturePad(userCanvas, {
            backgroundColor: 'rgb(255, 255, 255)'
        });

        const tiCanvas = document.getElementById('tiSignaturePad');
        const tiSignaturePad = new SignaturePad(tiCanvas, {
            backgroundColor: 'rgb(255, 255, 255)'
        });

        function clearSignature(canvasId) {
            if (canvasId === 'userSignaturePad') {
                userSignaturePad.clear();
                document.getElementById('userSignatureData').value = '';
            } else if (canvasId === 'tiSignaturePad') {
                tiSignaturePad.clear();
                document.getElementById('tiSignatureData').value = '';
            }
        }

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            userCanvas.width = userCanvas.offsetWidth * ratio;
            userCanvas.height = userCanvas.offsetHeight * ratio;
            userCanvas.getContext('2d').scale(ratio, ratio);
            userSignaturePad.clear();

            tiCanvas.width = tiCanvas.offsetWidth * ratio;
            tiCanvas.height = tiCanvas.offsetHeight * ratio;
            tiCanvas.getContext('2d').scale(ratio, ratio);
            tiSignaturePad.clear();
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        document.getElementById('conformityForm').addEventListener('submit', function(event) {
            if (userSignaturePad.isEmpty()) {
                alert('Por favor, firme el campo "Firma usuario" para continuar.');
                event.preventDefault();
                return;
            }
            if (tiSignaturePad.isEmpty()) {
                alert('Por favor, firme el campo "Firma TI" para continuar.');
                event.preventDefault();
                return;
            }

            document.getElementById('userSignatureData').value = userSignaturePad.toDataURL('image/png');
            document.getElementById('tiSignatureData').value = tiSignaturePad.toDataURL('image/png');
        });

        // Función para agregar un nuevo campo de archivo
        function addFileInput() {
            const container = document.getElementById('fileUploadsContainer');
            const newDiv = document.createElement('div');
            newDiv.classList.add('input-group', 'mt-2');
            newDiv.innerHTML = `
                <input type="file" class="form-control" name="archivos[]">
                <button class="btn btn-outline-danger" type="button" onclick="removeFileInput(this)">-</button>
            `;
            container.appendChild(newDiv);
        }

        // Función para eliminar un campo de archivo
        function removeFileInput(button) {
            const inputGroup = button.closest('.input-group');
            if (inputGroup) {
                inputGroup.remove();
            }
        }
    </script>
</body>
</html>