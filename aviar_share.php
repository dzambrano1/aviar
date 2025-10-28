<?php
// Enable error reporting for debugging (TEMPORARILY - remove after fixing)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Use PDO connection from pdo_conexion.php
try {
    require_once './pdo_conexion.php';
} catch (Exception $e) {
    die('Error loading database connection: ' . $e->getMessage());
}

// Check if file is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die('Error: No file specified');
}

// Check if tagid is provided
if (!isset($_GET['tagid']) || empty($_GET['tagid'])) {
    die('Error: No animal ID specified');
}

// Sanitize inputs
$filename = htmlspecialchars($_GET['file']);
$tagid = htmlspecialchars($_GET['tagid']);

// Verify file exists
$filepath = './reports/' . $filename;
if (!file_exists($filepath)) {
    die('Error: File not found - ' . $filepath);
}

// Fetch animal info for display using PDO
try {
    $sql = "SELECT tagid, nombre FROM aviar WHERE tagid = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$tagid]);
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$animal) {
        die('Error: Animal not found');
    }
} catch (PDOException $e) {
    error_log('Database error in aviar_share.php: ' . $e->getMessage());
    die('Error: Unable to retrieve animal information');
}

// Prepare download URL (absolute URL path for web access)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['PHP_SELF']);
$downloadUrl = $protocol . $host . $scriptDir . '/reports/' . $filename;

// For direct file download link in HTML (relative to web root)
$downloadLink = 'reports/' . $filename;

// Prepare WhatsApp sharing text
$shareText = "Animal Report for " . $animal['nombre'] . " (ID: " . $animal['tagid'] . ")";

// For large files, don't create base64 data URI (causes memory issues)
// Instead, use the download link for sharing
$dataUri = null; // We'll handle file sharing differently

// Standard link for WhatsApp sharing
$whatsappUrl = "https://api.whatsapp.com/send?text=" . urlencode($shareText . " - " . $downloadUrl);

// Get file size
$fileSize = filesize($filepath);
$formattedSize = $fileSize < 1024*1024 
    ? round($fileSize/1024, 2) . " KB" 
    : round($fileSize/(1024*1024), 2) . " MB";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compartir Reporte de Animal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 40px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 30px;
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 20px;
        }
        .card-body {
            padding: 30px;
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 50px;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            transition: all 0.3s ease;
        }
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4);
        }
        .btn-outline-secondary {
            border-radius: 50px;
            padding: 12px 25px;
            font-weight: 600;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            color: white;
        }
        .btn-lg {
            font-size: 1.1rem;
        }
        .icon-large {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #28a745;
        }
        .success-message {
            margin-bottom: 25px;
            font-weight: 500;
            font-size: 1.2rem;
        }
        .whatsapp-btn {
            background-color: #25D366;
            color: white;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        .whatsapp-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(37, 211, 102, 0.4);
            background-color: #20ba5a;
            color: white;
        }
        .whatsapp-link-btn {
            background-color: #128C7E;
            color: white;
            font-weight: 600;
            padding: 12px 25px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 12px rgba(18, 140, 126, 0.3);
            transition: all 0.3s ease;
        }
        .whatsapp-link-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(18, 140, 126, 0.4);
            background-color: #0E6B61;
            color: white;
        }
        .download-info {
            margin-top: 20px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        .animal-info {
            font-weight: 500;
            margin-bottom: 20px;
            color: #343a40;
        }
        .file-size {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header text-center">
                        <h3 class="mb-0">Reporte de Animal Generado</h3>
                    </div>
                    <div class="card-body text-center">
                        <i class="fas fa-file-pdf icon-large"></i>
                        <p class="success-message">Su reporte esta listo!</p>
                        
                        <div class="animal-info">
                            <p class="mb-1"><strong>Animal:</strong> <?php echo $animal['nombre']; ?></p>
                            <p><strong>Tag ID:</strong> <?php echo $animal['tagid']; ?></p>
                        </div>
                        
                        <div class="d-grid gap-3">
                            <a href="<?php echo $downloadLink; ?>" download class="btn btn-success btn-lg">
                                <i class="fas fa-download me-2"></i> Descargar PDF
                            </a>
                            
                            <!-- Direct WhatsApp File Share Button -->
                            <button class="btn whatsapp-btn btn-lg direct-share-btn" id="directShareBtn">
                                <i class="fab fa-whatsapp me-2"></i> Compartir PDF via WhatsApp
                            </button>
                            
                            <!-- Fallback WhatsApp Link Share -->
                            <a href="<?php echo $whatsappUrl; ?>" target="_blank" class="btn whatsapp-link-btn btn-lg">
                                <i class="fab fa-whatsapp me-2"></i> Compartir Link via WhatsApp
                            </a>
                            
                            <a href="inventario_aviar.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> Volver al Inventario
                            </a>
                        </div>
                        
                        <div class="download-info">
                            <p><small>El reporte esta almacenado de manera segura y contiene todas las informaciones de los registros del animal.</small></p>
                            <p><small>Tamaño del archivo: <?php echo $formattedSize; ?></small></p>
                            <p><small><strong>Nota:</strong> El compartir el PDF directo puede no funcionar en todos los dispositivos. Si encuentra problemas, use la opción "Compartir Link" en su lugar.</small></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle direct PDF sharing via WhatsApp
        document.getElementById('directShareBtn').addEventListener('click', function() {
            // Check if using mobile device
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            
            if (isMobile) {
                // For mobile, try to use Web Share API to share the PDF file
                if (navigator.share) {
                    // Fetch the PDF file as blob
                    fetch("<?php echo $downloadLink; ?>")
                        .then(res => res.blob())
                        .then(blob => {
                            const file = new File([blob], "<?php echo $filename; ?>", { type: "application/pdf" });
                            return navigator.share({
                                title: "Animal Report for <?php echo htmlspecialchars($animal['nombre']); ?>",
                                text: "<?php echo $shareText; ?>",
                                files: [file]
                            });
                        })
                        .catch(err => {
                            console.error("Share failed:", err);
                            // Fallback to WhatsApp link sharing
                            window.open("<?php echo $whatsappUrl; ?>", "_blank");
                        });
                } else {
                    // Fallback for browsers that don't support Web Share API
                    alert("El compartir el PDF directamente no es soportado en su dispositivo. Usando el link de comparticion en su lugar.");
                    window.open("<?php echo $whatsappUrl; ?>", "_blank");
                }
            } else {
                // For desktop, guide the user
                alert("Para compartir el PDF directamente en WhatsApp:\n\n1. Descargue el PDF primero\n2. Abra WhatsApp Web o Desktop\n3. Arrastre el PDF descargado a su chat");
                // Trigger download
                const downloadLinkEl = document.createElement('a');
                downloadLinkEl.href = "<?php echo $downloadLink; ?>";
                downloadLinkEl.download = "<?php echo $filename; ?>";
                downloadLinkEl.click();
            }
        });
    </script>
</body>
</html> 