<?php
session_start(); // Iniciar la sesión al principio de todo (puede que ya no sea tan crítico sin el PDF, pero no molesta)

// Incluir el archivo de configuración de la base de datos
require_once 'db_config.php'; // Asegúrate de que este archivo provee el objeto $pdo

// Configurar la configuración regional para las fechas en español
setlocale(LC_TIME, 'es_ES.utf8', 'es_ES', 'spanish');

$feedbackMessage = '';
$feedbackType = '';
// Ya no necesitamos $last_inserted_id ni $_SESSION['formData'] para el PDF, pero mantenemos el proceso de guardado.

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
        $signaturesDir = 'signatures/';
        if (!is_dir($signaturesDir)) {
            if (!mkdir($signaturesDir, 0755, true)) {
                throw new Exception("Error: No se pudo crear el directorio de firmas: " . $signaturesDir);
            }
        }
        $filesDir = 'uploads/';
        if (!is_dir($filesDir)) {
            if (!mkdir($filesDir, 0755, true)) {
                throw new Exception("Error: No se pudo crear el directorio de archivos: " . $filesDir);
            }
        }

        // Validación y guardado de firmas
        $userSignaturePath = '';
        if (!empty($userSignatureData)) {
            $userSignaturePath = $signaturesDir . 'user_signature_' . uniqid() . '.png';
            $userSignatureData = str_replace('data:image/png;base64,', '', $userSignatureData);
            $userSignatureData = base64_decode($userSignatureData);
            if (!file_put_contents($userSignaturePath, $userSignatureData)) {
                throw new Exception("Error: Falló la escritura del archivo de firma del usuario: " . $userSignaturePath);
            }
        } else {
            throw new Exception("Falta la firma del usuario.");
        }

        $tiSignaturePath = '';
        if (!empty($tiSignatureData)) {
            $tiSignaturePath = $signaturesDir . 'ti_signature_' . uniqid() . '.png';
            $tiSignatureData = str_replace('data:image/png;base64,', '', $tiSignatureData);
            $tiSignatureData = base64_decode($tiSignatureData);
            if (!file_put_contents($tiSignaturePath, $tiSignatureData)) {
                throw new Exception("Error: Falló la escritura del archivo de firma de TI: " . $tiSignaturePath);
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

        // Insertar datos en la base de datos
        $stmt = $pdo->prepare("INSERT INTO conformidades_digitales (fecha_envio, equipo_usuario, tareas_usuario, componentes_ti, estado_referencia, observaciones, ruta_firma_usuario, ruta_firma_ti, aclaracion_usuario, aclaracion_ti, archivos) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $submissionDateDB,
            $userEquipment,
            $userTasksString,
            $tiComponentsString,
            $referenceStatus,
            $observaciones,
            $userSignaturePath,
            $tiSignaturePath,
            $userAclaracion,
            $tiAclaracion,
            $archivosString
        ]);

        $last_inserted_id = $pdo->lastInsertId();
        $pdo->commit();

        $feedbackMessage = "¡Formulario enviado y datos guardados con éxito!";
        $feedbackType = 'success';

        if ($feedbackType === 'success' && $last_inserted_id) {
            $feedbackMessage .= ' <a href="generate_pdf.php?id=' . $last_inserted_id . '" class="btn btn-sm btn-secondary" target="_blank">Descargar PDF</a>';
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $feedbackMessage = "Error al guardar los datos. Por favor, intente nuevamente o contacte al administrador.";
        $feedbackType = 'error';
        error_log("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $feedbackMessage = "Ha ocurrido un error inesperado. Por favor, intente nuevamente o contacte al administrador.";
        $feedbackType = 'error';
        error_log("Unexpected error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conformidad de Mantenimiento</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 900px;
            margin-top: 30px;
            margin-bottom: 30px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .signature-pad-container {
            border: 1px solid #ced4da;
            border-radius: 4px;
            background-color: #fff;
            margin-bottom: 15px;
            position: relative;
        }
        .signature-pad-container canvas {
            display: block;
            width: 100%;
            height: 150px;
        }
        .signature-pad-container .clear-button {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
        }
        .header-title {
            text-align: center;
            margin-bottom: 30px;
            color: #343a40; /* Color gris oscuro */
        }
        .form-section-title {
            background-color: #343a40; /* Color gris oscuro */
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="header-title">Hoja de Conformidad de Mantenimiento</h1>

        <?php if (!empty($feedbackMessage)): ?>
            <div class="alert alert-<?php echo $feedbackType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo $feedbackMessage; ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <form id="conformityForm" method="POST">
            <h2 class="form-section-title">Datos del Usuario</h2>
            <div class="form-group">
                <label for="userEquipment">Equipo del Usuario:</label>
                <input type="text" class="form-control" id="userEquipment" name="userEquipment" placeholder="Ej: Laptop, PC de escritorio, Impresora" required>
            </div>

            <h2 class="form-section-title">Tareas Realizadas por Usuario</h2>
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Limpieza del teclado" id="taskKeyboard">
                    <label class="form-check-label" for="taskKeyboard">Limpieza del teclado</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Limpieza del mouse" id="taskMouse">
                    <label class="form-check-label" for="taskMouse">Limpieza del mouse</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Limpieza de pantalla" id="taskScreen">
                    <label class="form-check-label" for="taskScreen">Limpieza de pantalla</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Revisión de cables y conexiones" id="taskCables">
                    <label class="form-check-label" for="taskCables">Revisión de cables y conexiones</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Verificación de actualizaciones pendientes" id="taskUpdates">
                    <label class="form-check-label" for="taskUpdates">Verificación de actualizaciones pendientes</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Comprobación de espacio en disco" id="taskDiskSpace">
                    <label class="form-check-label" for="taskDiskSpace">Comprobación de espacio en disco</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Vaciar papelera de reciclaje" id="taskRecycleBin">
                    <label class="form-check-label" for="taskRecycleBin">Vaciar papelera de reciclaje</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="userTasks[]" value="Limpieza de polvo de puertos USB/ventiladores" id="taskDust">
                    <label class="form-check-label" for="taskDust">Limpieza de polvo de puertos USB/ventiladores</label>
                </div>
                </div>

            <h2 class="form-section-title">Componentes de TI Verificados (por Personal de TI)</h2>
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Software base (S.O.)" id="compOS">
                    <label class="form-check-label" for="compOS">Software base (Sistema Operativo)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Suite Ofimática" id="compOffice">
                    <label class="form-check-label" for="compOffice">Suite Ofimática</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Navegadores web" id="compBrowsers">
                    <label class="form-check-label" for="compBrowsers">Navegadores web</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Antivirus" id="compAntivirus">
                    <label class="form-check-label" for="compAntivirus">Antivirus</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Conexión de red (LAN/WiFi)" id="compNetwork">
                    <label class="form-check-label" for="compNetwork">Conexión de red (LAN/WiFi)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Periféricos (impresora, escáner, etc.)" id="compPeripherals">
                    <label class="form-check-label" for="compPeripherals">Periféricos (impresora, escáner, etc.)</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tiComponents[]" value="Backup de datos" id="compBackup">
                    <label class="form-check-label" for="compBackup">Backup de datos</label>
                </div>
                </div>

            <h2 class="form-section-title">Estado y Observaciones</h2>
            <div class="form-group">
                <label for="referenceStatus">Estado de Referencia (Ej: 'Óptimo', 'Requiere atención'):</label>
                <input type="text" class="form-control" id="referenceStatus" name="referenceStatus" required>
            </div>
            <div class="form-group">
                <label for="observaciones">Observaciones adicionales:</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
            </div>

            <h2 class="form-section-title">Firmas</h2>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Firma del Usuario:</label>
                    <div class="signature-pad-container">
                        <canvas id="userSignaturePad"></canvas>
                        <button type="button" class="clear-button" data-signature-pad="userSignaturePad">Limpiar</button>
                    </div>
                    <input type="hidden" name="userSignatureData" id="userSignatureData">
                    <label for="userAclaracion">Aclaración Usuario:</label>
                    <input type="text" class="form-control" id="userAclaracion" name="userAclaracion" required>
                </div>
                <div class="form-group col-md-6">
                    <label>Firma de TI:</label>
                    <div class="signature-pad-container">
                        <canvas id="tiSignaturePad"></canvas>
                        <button type="button" class="clear-button" data-signature-pad="tiSignaturePad">Limpiar</button>
                    </div>
                    <input type="hidden" name="tiSignatureData" id="tiSignatureData">
                    <label for="tiAclaracion">Aclaración TI:</label>
                    <input type="text" class="form-control" id="tiAclaracion" name="tiAclaracion" required>
                </div>
            </div>

            <button type="submit" class="btn btn-secondary btn-block">Enviar Conformidad</button>

            </form>

    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script>
        // Configuración de SignaturePad para el usuario
        var userCanvas = document.getElementById('userSignaturePad');
        var userSignaturePad = new SignaturePad(userCanvas, {
            backgroundColor: 'rgb(255, 255, 255)' // Fondo blanco
        });

        // Configuración de SignaturePad para TI
        var tiCanvas = document.getElementById('tiSignaturePad');
        var tiSignaturePad = new SignaturePad(tiCanvas, {
            backgroundColor: 'rgb(255, 255, 255)' // Fondo blanco
        });

        // Función para limpiar una firma
        document.querySelectorAll('.clear-button').forEach(button => {
            button.addEventListener('click', function() {
                const canvasId = this.getAttribute('data-signature-pad');
                if (canvasId === 'userSignaturePad') {
                    userSignaturePad.clear();
                } else if (canvasId === 'tiSignaturePad') {
                    tiSignaturePad.clear();
                }
            });
        });

        // Ajustar el tamaño del canvas al redimensionar la ventana
        function resizeCanvas() {
            var ratio = Math.max(window.devicePixelRatio || 1, 1);
            userCanvas.width = userCanvas.offsetWidth * ratio;
            userCanvas.height = userCanvas.offsetHeight * ratio;
            userCanvas.getContext('2d').scale(ratio, ratio);
            userSignaturePad.clear(); // Limpiar el pad después de redimensionar

            tiCanvas.width = tiCanvas.offsetWidth * ratio;
            tiCanvas.height = tiCanvas.offsetHeight * ratio;
            tiCanvas.getContext('2d').scale(ratio, ratio);
            tiSignaturePad.clear(); // Limpiar el pad después de redimensionar
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas(); // Llamada inicial al cargar la página

        // Manejar el envío del formulario
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

            // Obtener los datos de la firma como URL de datos (Base64) y asignarlos a los inputs ocultos
            document.getElementById('userSignatureData').value = userSignaturePad.toDataURL('image/png');
            document.getElementById('tiSignatureData').value = tiSignaturePad.toDataURL('image/png');
        });

        // El JavaScript para el botón de descarga de PDF ha sido eliminado

    </script>
</body>
</html>
