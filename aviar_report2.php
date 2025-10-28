<?php
// Database connection configuration - properly define variables for mysqli
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ganagram";  // Using ganagram database as referenced in pdo_conexion.php

require_once './pdo_conexion.php';
require('./fpdf/fpdf.php'); // You might need to install FPDF lencefalomielitisary

// Set memory and execution limits for large datasets
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300); // 5 minutes

// Check if reports directory exists, if not create it
$reportsDir = './reports';
if (!file_exists($reportsDir)) {
    if (!mkdir($reportsDir, 0777, true)) {
        error_log('Failed to create reports directory: ' . $reportsDir);
        die('Error: Cannot create reports directory. Please check file permissions.');
    }
    // Ensure permissions are correctly set
    chmod($reportsDir, 0777);
}

// Ensure no output has been sent before
if (ob_get_length()) ob_clean();

// Check if animal ID is provided
if (!isset($_GET['tagid']) || empty($_GET['tagid'])) {
    die('Error: No animal ID provided');
}

$tagid = $_GET['tagid'];

// Connect to database with enhanced error handling
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die('Error: Database connection failed. Please check server configuration.');
}

// Set charset to UTF-8 for proper character encoding in PDF
if (!mysqli_set_charset($conn, "utf8")) {
    error_log('Error setting charset: ' . mysqli_error($conn));
    die('Error: Failed to set database charset.');
}

// Fetch animal basic info with improved error handling
$sql_animal = "SELECT * FROM aviar WHERE tagid = ?";
$stmt_animal = $conn->prepare($sql_animal);

if (!$stmt_animal) {
    error_log('MySQL prepare error: ' . mysqli_error($conn));
    die('Error: Database query preparation failed.');
}

$stmt_animal->bind_param('s', $tagid);
if (!$stmt_animal->execute()) {
    error_log('MySQL execute error: ' . $stmt_animal->error);
    die('Error: Failed to execute animal query.');
}

$result_animal = $stmt_animal->get_result();
if (!$result_animal) {
    error_log('MySQL get_result error: ' . $stmt_animal->error);
    die('Error: Failed to retrieve animal data.');
}

if ($result_animal->num_rows === 0) {
    error_log('Animal not found with tagid: ' . $tagid);
    die('Error: Animal not found in database.');
}

$animal = $result_animal->fetch_assoc();
if (!$animal) {
    error_log('Failed to fetch animal data for tagid: ' . $tagid);
    die('Error: Failed to retrieve animal information.');
}

// Create PDF
class PDF extends FPDF
{
    // Animal data to access in header
    protected $animalData;
    
    // Set animal data
    function setAnimalData($data) {
        $this->animalData = $data;
    }
    
    // Helper function to ensure proper UTF-8 encoding for searchable text
    function EncodeText($text) {
        // Handle null or empty values
        if ($text === null || $text === '') {
            return '';
        }
        
        // Convert to string if needed
        $text = (string)$text;
        
        // Remove control characters and normalize text
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Convert text to proper encoding for FPDF
        if (mb_detect_encoding($text, 'UTF-8', true)) {
            // Text is UTF-8, convert to ISO-8859-1 for FPDF compatibility
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        }
        return $text;
    }
    
    // Override Cell method to ensure proper text encoding
    function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false, $link='') {
        // Ensure text is properly formatted for searchability
        $txt = trim($txt); // Remove extra whitespace
        $txt = preg_replace('/\s+/', ' ', $txt); // Normalize whitespace
        parent::Cell($w, $h, $this->EncodeText($txt), $border, $ln, $align, $fill, $link);
    }
    
    // Add method to set optimal font for searchability
    function SetSearchableFont($family='Arial', $style='', $size=10) {
        $this->SetFont($family, $style, $size);
        // Ensure text rendering mode is optimal for searchability
        $this->_out('2 Tr'); // Set text rendering mode to fill (most searchable)
    }
    
    // Page header
    function Header()
    {
        // Only show header on first page
        if ($this->PageNo() == 1) {
            // Set margins and padding
            $this->SetMargins(10, 10, 10);
            
            // Draw a subtle header background
            $this->SetFillColor(240, 240, 240);
            $this->Rect(0, 0, 210, 35, 'F');
            
            // Logo with adjusted position
            $this->Image('./images/Avegram_logo.png', 10, 6, 30);
            
            // Add current date on upper right
            $this->SetSearchableFont('Arial', '', 10);
            $this->SetTextColor(80, 80, 80); // Gray color for date
            $current_date = date('d/m/Y H:i:s');
            $this->SetXY(150, 8); // Position on upper right
            $this->Cell(50, 8, 'Fecha: ' . $current_date, 0, 0, 'R');
            
            // Add a decorative line
            $this->SetDrawColor(0, 128, 0); // Green line
            $this->Line(10, 35, 200, 35);
            
            // Main report title
            $this->SetFont('Arial', 'B', 18);
            $this->SetTextColor(0, 80, 0); // Darker green for main title
            
            $this->Ln(5);
            
            // Title section with animal name - larger, bold font
            $this->SetSearchableFont('Arial', 'B', 16);
            $this->SetTextColor(0, 100, 0); // Dark green color for title
            // Center alignment for animal name
            $this->Cell(0, 10, mb_strtoupper($this->animalData['nombre']), 0, 1, 'C');
            
            // Tag ID in a slightly smaller font, still professional
            $this->SetSearchableFont('Arial', 'B', 12);
            $this->SetTextColor(80, 80, 80); // Gray color for tag ID
            // Center alignment for Tag ID
            $this->Cell(0, 10, 'Tag ID: ' . $this->animalData['tagid'], 0, 1, 'C');
            $this->Ln(5);
            
            // Add animal images
            if (!empty($this->animalData)) {
                // Photo section title
                $this->SetFont('Arial', 'B', 12);
                $this->SetTextColor(0, 0, 0);
                $this->Cell(0, 5, 'CONDICION CORPORAL', 0, 1, 'C');
                $this->Ln(1);
                
                // Start position for images
                $y = 70; // Adjusted for the new title
                $imageWidth = 60;
                $spacing = 5;
                
                // Left position for first image
                $x1 = 10;
                // Left position for second image
                $x2 = $x1 + $imageWidth + $spacing;
                // Left position for third image
                $x3 = $x2 + $imageWidth + $spacing;
                
                // Add first image if exists
                if (!empty($this->animalData['image'])) {
                    $imagePath = $this->animalData['image'];
                    $imagePath = str_replace('\\', '/', $imagePath); // Normalize path
                    
                    // Paths to try
                    $pathsToTry = [
                        $imagePath,
                        './' . ltrim($imagePath, './'),
                        '../' . $imagePath,
                        $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/')
                    ];
                    
                    foreach ($pathsToTry as $path) {
                        if (file_exists($path)) {
                            $this->Image($path, $x1, $y, $imageWidth);
                            break;
                        }
                    }
                }
                
                // Add second image if exists
                if (!empty($this->animalData['image2'])) {
                    $imagePath = $this->animalData['image2'];
                    $imagePath = str_replace('\\', '/', $imagePath); // Normalize path
                    
                    // Paths to try
                    $pathsToTry = [
                        $imagePath,
                        './' . ltrim($imagePath, './'),
                        '../' . $imagePath,
                        $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/')
                    ];
                    
                    foreach ($pathsToTry as $path) {
                        if (file_exists($path)) {
                            $this->Image($path, $x2, $y, $imageWidth);
                            break;
                        }
                    }
                }
                
                // Add third image if exists
                if (!empty($this->animalData['image3'])) {
                    $imagePath = $this->animalData['image3'];
                    $imagePath = str_replace('\\', '/', $imagePath); // Normalize path
                    
                    // Paths to try
                    $pathsToTry = [
                        $imagePath,
                        './' . ltrim($imagePath, './'),
                        '../' . $imagePath,
                        $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($imagePath, '/')
                    ];
                    
                    foreach ($pathsToTry as $path) {
                        if (file_exists($path)) {
                            $this->Image($path, $x3, $y, $imageWidth);
                            break;
                        }
                    }
                }
                
                // Add image captions
                $this->SetFont('Arial', 'I', 8);
                $this->SetY($y + $imageWidth + 2);
                $this->SetX($x1);
                $this->Cell($imageWidth, 10, 'Foto Principal', 0, 0, 'C');
                $this->SetX($x2);
                $this->Cell($imageWidth, 10, 'Foto Secundaria', 0, 0, 'C');
                $this->SetX($x3);
                $this->Cell($imageWidth, 10, 'Foto Adicional', 0, 0, 'C');
                
                // Add extra space after images
                $this->Ln(10);
            }
        }
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetSearchableFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    // Draw a circle
    function Circle($x, $y, $r, $style='D')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }
    
    // Draw an ellipse
    function Ellipse($x, $y, $rx, $ry, $style='D')
    {
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
            
        $lx=4/3*(M_SQRT2-1)*$rx;
        $ly=4/3*(M_SQRT2-1)*$ry;
        $k=$this->k;
        $h=$this->h;
        
        $this->_out(sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x)*$k, ($h-$y)*$k,
            ($x+$lx)*$k, ($h-$y)*$k,
            ($x+$rx)*$k, ($h-$y+$ly)*$k,
            ($x+$rx)*$k, ($h-$y+$ry)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x+$rx)*$k, ($h-$y+$ry+$ly)*$k,
            ($x+$lx)*$k, ($h-$y+$ry+$ry)*$k,
            ($x)*$k, ($h-$y+$ry+$ry)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x-$lx)*$k, ($h-$y+$ry+$ry)*$k,
            ($x-$rx)*$k, ($h-$y+$ry+$ly)*$k,
            ($x-$rx)*$k, ($h-$y+$ry)*$k));
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x-$rx)*$k, ($h-$y+$ly)*$k,
            ($x-$lx)*$k, ($h-$y)*$k,
            ($x)*$k, ($h-$y)*$k,
            $op));
    }

    // Function to styled chapter titles
    function ChapterTitle($title)
    {
        // Add animal tagid and nombre to the title (except for farm-wide statistics)
        $animalInfo = '';
        if ($this->animalData && isset($this->animalData['tagid']) && isset($this->animalData['nombre'])) {
            // Don't add animal info for farm-wide statistics (any title containing "(Finca)" or distribution reports)
            if (strpos($title, '(Finca)') === false && $title !== 'Distribucion por Raza' && $title !== 'Distribucion de Animales por Grupo' && $title !== 'Indice de Conversion Alimenticia (ICA)' && $title !== 'Resumen de Vacunaciones y Tratamientos' && $title !== 'Duracion de Gestaciones' && $title !== 'Hembras Sin Registro de Gestacion' && $title !== 'Animales con mas de 365 Dias Desde Ultimo Parto' && $title !== 'ESTADISTICAS DE LA FINCA' && $title !== 'Pesos (Granja):' && $title !== 'Huevos (Granja):' && $title !== 'Concentrado (Granja)' && $title !== 'Salt (Granja)' && $title !== 'Melaza (Granja)' && $title !== 'Colera (Granja)' && $title !== 'Corona Virus (Granja)' && $title !== 'Viruela (Granja)' && $title !== 'Coriza (Granja)' && $title !== 'Encefalomielitis (Granja)' && $title !== 'Influenza (Granja)' && $title !== 'Marek (Granja)' && $title !== 'Newcastle (Granja)' && $title !== 'Parasitos (Granja)' && $title !== 'Garrapatas (Granja)' && $title !== 'Concentrado') {
                $animalInfo = ' ' . $this->animalData['tagid'] . ' (' . $this->animalData['nombre'] . ')';
            }
        }
        $fullTitle = $title . $animalInfo;
        
        $this->SetSearchableFont('Arial', 'B', 12);
        $this->SetFillColor(0, 100, 0); // Darker green
        $this->SetTextColor(255, 255, 255); // White text
        
        // Check if this is a main section title (all caps)
        if ($title == 'PRODUCCION' || $title == 'ALIMENTACION' || $title == 'SALUD' || 
            $title == 'REPRODUCCION' || $title == 'ESTADISTICAS DE LA FINCA') {
            // Main section titles - centered, larger font, more space before/after
            $this->SetSearchableFont('Arial', 'B', 14);
            $this->Ln(5); // Extra space before main sections
            $this->Cell(0, 10, $fullTitle, 0, 1, 'C', true);
            $this->Ln(5); // Extra space after main sections
        } else {
            // Regular subsection titles - left aligned
            $this->Cell(0, 8, $fullTitle, 0, 1, 'L', true);
            $this->Ln(3);
        }
        
        $this->SetTextColor(0, 0, 0); // Reset to black text
    }

    // Data table
    function DataTable($header, $data)
    {
        // Column widths
        $w = array(40, 50, 40, 50);
        
        // Header
        $this->SetSearchableFont('Arial', 'B', 10);
        $this->SetFillColor(50, 120, 50); // Darker green for header
        $this->SetTextColor(255, 255, 255); // White text for better contrast
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0); // Reset to black text for data
        
        // Data
        $this->SetSearchableFont('Arial', '', 9); // Match SimpleTable font size
        $this->SetFillColor(245, 250, 245); // Match SimpleTable fill color
        $fill = false;
        foreach ($data as $row) {
            for ($i = 0; $i < count($row); $i++) {
                $this->Cell($w[$i], 6, $row[$i], 1, 0, 'C', $fill); // Center align all cells
            }
            $this->Ln();
            $fill = !$fill;
        }
        $this->Ln(5);
    }
    
    // Simple table for two columns
    function SimpleTable($header, $data)
    {
        // Determine column count and adjust widths accordingly
        $columnCount = count($header);
        
        // Default column widths
        if ($columnCount == 2) {
            $w = array(60, 120); // Original 2-column layout
        } elseif ($columnCount == 3) {
            $w = array(50, 50, 80); // 3-column layout (date, value, price)
        } elseif ($columnCount == 4) {
            $w = array(40, 60, 40, 40); // 4-column layout
        } else {
            // Create automatic column widths
            $pageWidth = $this->GetPageWidth() - 20; // Adjust for margins
            $w = array_fill(0, $columnCount, $pageWidth / $columnCount);
        }
        
        // Check if this is a table that needs special formatting
        if (in_array('Precio ($/Kg)', $header) || in_array('Dosis', $header)) {
            // Special column widths for tables with price or dose fields
            if ($columnCount == 3) {
                $w = array(45, 60, 75); // Date, Weight/Product, Price/Dose
            }
        }
        
        // Header with background
        $this->SetSearchableFont('Arial', 'B', 10);
        $this->SetFillColor(50, 120, 50); // Darker green for header
        $this->SetTextColor(255, 255, 255); // White text for better contrast
        for ($i = 0; $i < $columnCount; $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0); // Reset to black text for data
        
        // Data
        $this->SetSearchableFont('Arial', '', 9); // Slightly smaller font to fit more text
        $this->SetFillColor(245, 250, 245); // Lighter green tint
        $fill = false;
        
        foreach ($data as $row) {
            // Make sure we have the right number of cells
            $rowCount = count($row);
            for ($i = 0; $i < $columnCount; $i++) {
                // If the cell exists in data, display it, otherwise display empty cell
                $cellContent = ($i < $rowCount) ? $row[$i] : '';
                
                // Center align all data cells for consistency
                $align = 'C';
                
                $this->Cell($w[$i], 6, $cellContent, 1, 0, $align, $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
        
        // Add space after table
        $this->Ln(5);
    }
}

// Create PDF instance
$pdf = new PDF();
$pdf->setAnimalData($animal);

// Set UTF-8 metadata for better searchability
$pdf->SetTitle('Reporte Veterinario - ' . $animal['nombre'] . ' (' . $animal['tagid'] . ')', true);
$pdf->SetAuthor('Sistema Ganagram', true);
$pdf->SetSubject('Historial Veterinario Completo', true);
$pdf->SetKeywords('veterinario, ganado, bovino, historial, ' . $animal['tagid'] . ', ' . $animal['nombre'], true);
$pdf->SetCreator('Ganagram - Sistema de Gestión Ganadera', true);

$pdf->AliasNbPages();
$pdf->AddPage();

// Basic animal information
$pdf->ChapterTitle('Datos (Parvada):');
$header = array('Concepto', 'Descripcion');
$data = array(
    array('Tag ID', $animal['tagid']),
    array('Nombre', $animal['nombre']),
    array('Fecha Nacimiento', $animal['fecha_nacimiento']),
    array('Genero', $animal['genero']),
    array('Raza', $animal['raza']),
    array('Etapa', $animal['etapa']),
    array('Grupo', $animal['grupo']),
    array('Estatus', $animal['estatus']),
    array('Aforo', $animal['poblacion'])
);
$pdf->SimpleTable($header, $data);

// Peso history

$pdf->AddPage();
$pdf->ChapterTitle('Pesos (Parvada):');
$sql_weight = "SELECT ah_peso_tagid, ah_peso_fecha, ah_peso_animal, ah_peso_precio FROM ah_peso WHERE ah_peso_tagid = ? ORDER BY ah_peso_fecha DESC";
$stmt_weight = $conn->prepare($sql_weight);
$stmt_weight->bind_param('s', $tagid);
$stmt_weight->execute();
$result_weight = $stmt_weight->get_result();

if ($result_weight->num_rows > 0) {
    $header = array('Tag ID', 'Fecha', 'Peso (kg)', 'Precio ($/Kg)');
    $data = array();
    while ($row = $result_weight->fetch_assoc()) {
        $data[] = array($row['ah_peso_tagid'], $row['ah_peso_fecha'], $row['ah_peso_animal'], $row['ah_peso_precio']);
    }
    $pdf->SimpleTable($header, $data);

} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay regisros de pesajes', 0, 1);
    $pdf->Ln(2);
}

// Add footnote with bird count
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 5, 'Aves en la Parvada: ' . ($animal['poblacion'] ?? 'No especificado'), 0, 1);
$pdf->Ln(2);

// Engorde - Weekly Average Weight Analysis (All Animals)
$pdf->AddPage();
$pdf->ChapterTitle('Pesos (Granja):');

// Function to create weight table for specific gender
function createWeightTableByGender($conn, $pdf, $gender_title, $gender_filter) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $gender_title, 0, 1);
    $pdf->Ln(3);
    
    // Query to get weekly averages filtered by gender
    $sql_weekly_averages = "SELECT 
                                YEARWEEK(ap.ah_peso_fecha, 1) as year_week,
                                DATE(DATE_SUB(ap.ah_peso_fecha, INTERVAL WEEKDAY(ap.ah_peso_fecha) DAY)) as week_start_date,
                                AVG(ap.ah_peso_animal) as weekly_avg_weight,
                                COUNT(*) as weekly_records,
                                COUNT(DISTINCT ap.ah_peso_tagid) as unique_animals
                            FROM ah_peso ap
                            INNER JOIN aviar a ON ap.ah_peso_tagid = a.tagid
                            WHERE ap.ah_peso_animal IS NOT NULL 
                            AND a.genero = ?
                            GROUP BY YEARWEEK(ap.ah_peso_fecha, 1)
                            ORDER BY year_week ASC";

    // Query to get total unique animals considered in the analysis by gender
    $sql_total_unique = "SELECT COUNT(DISTINCT ap.ah_peso_tagid) as total_unique_animals
                         FROM ah_peso ap
                         INNER JOIN aviar a ON ap.ah_peso_tagid = a.tagid
                         WHERE ap.ah_peso_animal IS NOT NULL 
                         AND a.genero = ?";
    
    try {
        // Execute total unique animals query
        $stmt_total_unique = $conn->prepare($sql_total_unique);
        if (!$stmt_total_unique) {
            throw new Exception('Failed to prepare total unique animals query for ' . $gender_title . ': ' . mysqli_error($conn));
        }
        
        $stmt_total_unique->bind_param('s', $gender_filter);
        if (!$stmt_total_unique->execute()) {
            throw new Exception('Failed to execute total unique animals query for ' . $gender_title . ': ' . $stmt_total_unique->error);
        }
        
        $result_total_unique = $stmt_total_unique->get_result();
        if (!$result_total_unique) {
            throw new Exception('Failed to get result for total unique animals query for ' . $gender_title . ': ' . $stmt_total_unique->error);
        }
        
        $total_unique_data = $result_total_unique->fetch_assoc();
        $total_unique_animals = $total_unique_data['total_unique_animals'] ?? 0;

        // Execute weekly averages query
        $stmt_weekly_averages = $conn->prepare($sql_weekly_averages);
        if (!$stmt_weekly_averages) {
            throw new Exception('Failed to prepare weekly averages query for ' . $gender_title . ': ' . mysqli_error($conn));
        }
        
        $stmt_weekly_averages->bind_param('s', $gender_filter);
        if (!$stmt_weekly_averages->execute()) {
            throw new Exception('Failed to execute weekly averages query for ' . $gender_title . ': ' . $stmt_weekly_averages->error);
        }
        
        $result_weekly_averages = $stmt_weekly_averages->get_result();
        if (!$result_weekly_averages) {
            throw new Exception('Failed to get result for weekly averages query for ' . $gender_title . ': ' . $stmt_weekly_averages->error);
        }

        $weekly_data = array();
        while ($row = $result_weekly_averages->fetch_assoc()) {
            $weekly_data[] = array(
                'week_start' => $row['week_start_date'],
                'avg_weight' => (float)$row['weekly_avg_weight'],
                'records' => $row['weekly_records']
            );
        }

        if (!empty($weekly_data)) {
            // Display the table with Fecha and Peso Semanal
            $header = array('Fecha', 'Peso Semanal');
            $data = array();
            
            foreach ($weekly_data as $week_info) {
                // Format date for better display (e.g., "15 Ene 2024")
                $date_formatted = date('d M Y', strtotime($week_info['week_start']));
                $data[] = array(
                    $date_formatted,
                    number_format($week_info['avg_weight'], 2) . ' kg'
                );
            }
            
            $pdf->SimpleTable($header, $data);
            
            // Calculate average weekly weight increment
            $avg_weekly_increment = 0;
            if (count($weekly_data) > 1) {
                $increments = array();
                for ($i = 1; $i < count($weekly_data); $i++) {
                    $increment = $weekly_data[$i]['avg_weight'] - $weekly_data[$i-1]['avg_weight'];
                    $increments[] = $increment;
                }
                $avg_weekly_increment = count($increments) > 0 ? array_sum($increments) / count($increments) : 0;
            }
            
            // Calculate increment per animal
            $avg_increment_per_animal = 0;
            if ($total_unique_animals > 0) {
                $avg_increment_per_animal = $avg_weekly_increment / $total_unique_animals;
            }
            
            // Add footnotes with weekly weight increment, number of animals, and increment per animal
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 5, 'Incremento semanal promedio: ' . number_format($avg_weekly_increment, 2) . ' kg/semana', 0, 1);
            $pdf->Cell(0, 5, 'Numero de aves pesadas (' . strtolower($gender_title) . '): ' . $total_unique_animals, 0, 1);
            $pdf->Cell(0, 5, 'Incremento semanal por animal pesado: ' . number_format($avg_increment_per_animal, 3) . ' kg/ave/semana', 0, 1);
            
        } else {
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 5, 'No hay registros de peso disponibles para ' . strtolower($gender_title), 0, 1);
            $pdf->Ln(2);
        }
        
        // Close prepared statements
        if (isset($stmt_total_unique)) {
            $stmt_total_unique->close();
        }
        if (isset($stmt_weekly_averages)) {
            $stmt_weekly_averages->close();
        }
        
    } catch (Exception $e) {
        // Log the error and display a user-friendly message in the PDF
        error_log('Weight analysis error for ' . $gender_title . ' in aviar_report.php: ' . $e->getMessage());
        
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'Error al generar análisis de peso para ' . strtolower($gender_title) . ': ' . $e->getMessage(), 0, 1);
        $pdf->Ln(2);
        
        // Close prepared statements if they exist
        if (isset($stmt_total_unique)) {
            $stmt_total_unique->close();
        }
        if (isset($stmt_weekly_averages)) {
            $stmt_weekly_averages->close();
        }
    }
    
    $pdf->Ln(10); // Space between tables
}

// Create tables for Hembras and Machos
createWeightTableByGender($conn, $pdf, 'Ponedoras', 'Hembra');
createWeightTableByGender($conn, $pdf, 'Pollos', 'Macho');

// Huevo - Egg Production Records
$pdf->AddPage();
$pdf->ChapterTitle('Huevos (Parvada):');

// Query to get huevo records for the specific animal
$sql_huevo = "SELECT 
                ah_huevo_fecha as fecha,
                ah_huevo_cantidad as cantidad,
                ah_huevo_precio as precio,
                (ah_huevo_cantidad * ah_huevo_precio) as total
              FROM ah_huevo 
              WHERE ah_huevo_tagid = ? 
              AND ah_huevo_cantidad IS NOT NULL 
              AND ah_huevo_precio IS NOT NULL
              ORDER BY ah_huevo_fecha DESC";

try {
    // Execute huevo query with error handling
    $stmt_huevo = $conn->prepare($sql_huevo);
    if (!$stmt_huevo) {
        throw new Exception('Failed to prepare huevo query: ' . mysqli_error($conn));
    }
    
    $stmt_huevo->bind_param('s', $tagid);
    if (!$stmt_huevo->execute()) {
        throw new Exception('Failed to execute huevo query: ' . $stmt_huevo->error);
    }
    
    $result_huevo = $stmt_huevo->get_result();
    if (!$result_huevo) {
        throw new Exception('Failed to get result for huevo query: ' . $stmt_huevo->error);
    }

    $huevo_data = array();
    $total_huevos = 0;
    $total_ingresos = 0;
    
    while ($row = $result_huevo->fetch_assoc()) {
        $cantidad = (float)$row['cantidad'];
        $precio = (float)$row['precio'];
        $total = $cantidad * $precio;
        
        $huevo_data[] = array(
            'fecha' => $row['fecha'],
            'cantidad' => $cantidad,
            'precio' => $precio,
            'total' => $total
        );
        
        $total_huevos += $cantidad;
        $total_ingresos += $total;
    }

    if (!empty($huevo_data)) {
        // Display the table with 4 columns
        $header = array('Fecha', 'Huevos', 'Precio', 'Total');
        $data = array();
        
        foreach ($huevo_data as $huevo_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($huevo_info['fecha']));
            $data[] = array(
                $date_formatted,
                number_format($huevo_info['cantidad'], 0) . ' huevos',
                '$' . number_format($huevo_info['precio'], 2),
                '$' . number_format($huevo_info['total'], 2)
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de huevos producidos: ' . number_format($total_huevos, 0) . ' huevos', 0, 1);
        $pdf->Cell(0, 5, 'Total de ingresos: $' . number_format($total_ingresos, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros: ' . count($huevo_data), 0, 1);
        
        // Calculate average price per egg if we have data
        if ($total_huevos > 0) {
            $avg_price_per_egg = $total_ingresos / $total_huevos;
            $pdf->Cell(0, 5, 'Precio promedio por huevo: $' . number_format($avg_price_per_egg, 3), 0, 1);
        }
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de huevos para esta parvada', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_huevo)) {
        $stmt_huevo->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Huevo section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de huevos: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_huevo)) {
        $stmt_huevo->close();
    }
}

// Huevos (Granja) - Farm-wide Egg Production Records
$pdf->AddPage();
$pdf->ChapterTitle('Huevos (Granja):');

// Query to get all huevo records from the farm
$sql_huevo_granja = "SELECT 
                        ah_huevo_fecha as fecha,
                        ah_huevo_cantidad as cantidad,
                        ah_huevo_precio as precio,
                        (ah_huevo_cantidad * ah_huevo_precio) as total
                     FROM ah_huevo 
                     WHERE ah_huevo_cantidad IS NOT NULL 
                     AND ah_huevo_precio IS NOT NULL
                     ORDER BY ah_huevo_fecha DESC";

try {
    // Execute huevo granja query with error handling
    $stmt_huevo_granja = $conn->prepare($sql_huevo_granja);
    if (!$stmt_huevo_granja) {
        throw new Exception('Failed to prepare huevo granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_huevo_granja->execute()) {
        throw new Exception('Failed to execute huevo granja query: ' . $stmt_huevo_granja->error);
    }
    
    $result_huevo_granja = $stmt_huevo_granja->get_result();
    if (!$result_huevo_granja) {
        throw new Exception('Failed to get result for huevo granja query: ' . $stmt_huevo_granja->error);
    }

    $huevo_granja_data = array();
    $total_huevos_granja = 0;
    $total_ingresos_granja = 0;
    
    while ($row = $result_huevo_granja->fetch_assoc()) {
        $cantidad = (float)$row['cantidad'];
        $precio = (float)$row['precio'];
        $total = $cantidad * $precio;
        
        $huevo_granja_data[] = array(
            'fecha' => $row['fecha'],
            'cantidad' => $cantidad,
            'precio' => $precio,
            'total' => $total
        );
        
        $total_huevos_granja += $cantidad;
        $total_ingresos_granja += $total;
    }

    if (!empty($huevo_granja_data)) {
        // Display the table with 4 columns
        $header = array('Date', 'Cantidad', 'Precio', 'Total');
        $data = array();
        
        foreach ($huevo_granja_data as $huevo_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($huevo_info['fecha']));
            $data[] = array(
                $date_formatted,
                number_format($huevo_info['cantidad'], 0) . ' huevos',
                '$' . number_format($huevo_info['precio'], 2),
                '$' . number_format($huevo_info['total'], 2)
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de huevos producidos (granja): ' . number_format($total_huevos_granja, 0) . ' huevos', 0, 1);
        $pdf->Cell(0, 5, 'Total de ingresos (granja): $' . number_format($total_ingresos_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($huevo_granja_data), 0, 1);
        
        // Calculate average price per egg if we have data
        if ($total_huevos_granja > 0) {
            $avg_price_per_egg_granja = $total_ingresos_granja / $total_huevos_granja;
            $pdf->Cell(0, 5, 'Precio promedio por huevo (granja): $' . number_format($avg_price_per_egg_granja, 3), 0, 1);
        }
        
        // Calculate unique animals involved
        $sql_unique_animals = "SELECT COUNT(DISTINCT ah_huevo_tagid) as total_animals FROM ah_huevo WHERE ah_huevo_cantidad IS NOT NULL AND ah_huevo_precio IS NOT NULL";
        $stmt_unique_animals = $conn->prepare($sql_unique_animals);
        if ($stmt_unique_animals && $stmt_unique_animals->execute()) {
            $result_unique_animals = $stmt_unique_animals->get_result();
            if ($result_unique_animals) {
                $unique_data = $result_unique_animals->fetch_assoc();
                $total_animals = $unique_data['total_animals'] ?? 0;
                $pdf->Cell(0, 5, 'Número de ponedoras registradas: ' . $total_animals, 0, 1);
                $stmt_unique_animals->close();
            }
        }
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de huevos disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_huevo_granja)) {
        $stmt_huevo_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Huevos Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de huevos de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_huevo_granja)) {
        $stmt_huevo_granja->close();
    }
}

// Concentrado
$pdf->AddPage();
$pdf->ChapterTitle('Concentrado (Parvada):');
$sql_concentrado = "SELECT ah_concentrado_tagid, ah_concentrado_fecha_inicio, ah_concentrado_racion, ah_concentrado_costo FROM ah_concentrado WHERE ah_concentrado_tagid = ? ORDER BY ah_concentrado_fecha_inicio DESC";
$stmt_concentrado = $conn->prepare($sql_concentrado);
$stmt_concentrado->bind_param('s', $tagid);
$stmt_concentrado->execute();
$result_concentrado = $stmt_concentrado->get_result();

if ($result_concentrado->num_rows > 0) {
    $header = array('Tag ID', 'Fecha', 'Semanal (kg)', 'Precio ($/Kg)');
    $data = array();
    while ($row = $result_concentrado->fetch_assoc()) {
        $data[] = array($row['ah_concentrado_tagid'], $row['ah_concentrado_fecha_inicio'], $row['ah_concentrado_racion'], $row['ah_concentrado_costo']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de consumo de concentrado', 0, 1);
    $pdf->Ln(2);
}


// Concentrado - Weekly Feed Consumption Analysis (All Animals)
$pdf->AddPage();
$pdf->ChapterTitle('Concentrado (Granja)');

// Query to get weekly feed consumption from all records in ah_concentrado table
$sql_weekly_feed = "SELECT 
                        YEARWEEK(ah_concentrado_fecha_inicio, 1) as year_week,
                        DATE(DATE_SUB(ah_concentrado_fecha_inicio, INTERVAL WEEKDAY(ah_concentrado_fecha_inicio) DAY)) as week_start_date,
                        SUM(ah_concentrado_racion) as weekly_feed_consumption,
                        COUNT(*) as weekly_records
                    FROM ah_concentrado 
                    WHERE ah_concentrado_racion IS NOT NULL
                    GROUP BY YEARWEEK(ah_concentrado_fecha_inicio, 1)
                    ORDER BY year_week ASC";

// Query to get total unique animals in ah_concentrado table
$sql_total_unique_feed = "SELECT COUNT(DISTINCT ah_concentrado_tagid) as total_unique_animals
                         FROM ah_concentrado 
                         WHERE ah_concentrado_racion IS NOT NULL";
$stmt_total_unique_feed = $conn->prepare($sql_total_unique_feed);
$stmt_total_unique_feed->execute();
$result_total_unique_feed = $stmt_total_unique_feed->get_result();
$total_unique_feed_data = $result_total_unique_feed->fetch_assoc();
$total_unique_feed_animals = $total_unique_feed_data['total_unique_animals'] ?? 0;

$stmt_weekly_feed = $conn->prepare($sql_weekly_feed);
$stmt_weekly_feed->execute();
$result_weekly_feed = $stmt_weekly_feed->get_result();

$weekly_feed_data = array();
while ($row = $result_weekly_feed->fetch_assoc()) {
    $weekly_feed_data[] = array(
        'week_start' => $row['week_start_date'],
        'feed_consumption' => (float)$row['weekly_feed_consumption'],
        'records' => $row['weekly_records']
    );
}

if (!empty($weekly_feed_data)) {
    // Display the table with Fecha and Consumo Semanal (Kg)
    $header = array('Fecha', 'Consumo Semanal (Kg)');
    $data = array();
    
    foreach ($weekly_feed_data as $week_info) {
        // Format date for better display (e.g., "15 Ene 2024")
        $date_formatted = date('d M Y', strtotime($week_info['week_start']));
        $data[] = array(
            $date_formatted,
            number_format($week_info['feed_consumption'], 2)
        );
    }
    
    $pdf->SimpleTable($header, $data);
    
    // Calculate average weekly feed consumption
    $avg_weekly_feed_consumption = 0;
    if (count($weekly_feed_data) > 0) {
        $total_consumption = array_sum(array_column($weekly_feed_data, 'feed_consumption'));
        $avg_weekly_feed_consumption = $total_consumption / count($weekly_feed_data);
    }
    
    // Calculate average consumption per animal
    $avg_consumption_per_animal = 0;
    if ($total_unique_feed_animals > 0) {
        $avg_consumption_per_animal = $avg_weekly_feed_consumption / $total_unique_feed_animals;
    }
    
    // Add footnotes
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Consumo promedio semanal: ' . number_format($avg_weekly_feed_consumption, 2) . ' kg/semana', 0, 1);
    $pdf->Cell(0, 5, 'Numero de animales: ' . $total_unique_feed_animals, 0, 1);
    $pdf->Cell(0, 5, 'Promedio consumido por animal: ' . number_format($avg_consumption_per_animal, 3) . ' kg/animal/semana', 0, 1);
    
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de concentrado disponibles para el análisis', 0, 1);
    $pdf->Ln(2);
}


// Salt
$pdf->AddPage();
$pdf->ChapterTitle('Sal (Parvada):');
$sql_salt = "SELECT ah_sal_tagid, ah_sal_fecha_inicio, ah_sal_racion, ah_sal_costo FROM ah_sal WHERE ah_sal_tagid = ? ORDER BY ah_sal_fecha_inicio DESC";
$stmt_salt = $conn->prepare($sql_salt);
$stmt_salt->bind_param('s', $tagid);
$stmt_salt->execute();
$result_salt = $stmt_salt->get_result();

if ($result_salt->num_rows > 0) {
    $header = array('Tag ID', 'Fecha', 'Consumo Sal Racion (Kg)', 'Costo ($/Kg)');
    $data = array();
    while ($row = $result_salt->fetch_assoc()) {
        $data[] = array($row['ah_sal_tagid'], $row['ah_sal_fecha_inicio'], $row['ah_sal_racion'], $row['ah_sal_costo']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de consumo de sal', 0, 1);
    $pdf->Ln(2);
}

// Salt (Granja) - Farm-wide Salt Consumption Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Salt (Granja)');

// Query to get weekly salt consumption from all records in ah_sal table
$sql_weekly_salt = "SELECT 
                        YEARWEEK(ah_sal_fecha_inicio, 1) as year_week,
                        DATE(DATE_SUB(ah_sal_fecha_inicio, INTERVAL WEEKDAY(ah_sal_fecha_inicio) DAY)) as week_start_date,
                        SUM(ah_sal_racion) as weekly_salt_consumption,
                        COUNT(*) as weekly_records
                    FROM ah_sal 
                    WHERE ah_sal_racion IS NOT NULL
                    GROUP BY YEARWEEK(ah_sal_fecha_inicio, 1)
                    ORDER BY year_week ASC";

try {
    // Query to get total unique animals in ah_sal table
    $sql_total_unique_salt = "SELECT COUNT(DISTINCT ah_sal_tagid) as total_unique_animals
                             FROM ah_sal 
                             WHERE ah_sal_racion IS NOT NULL";
    
    $stmt_total_unique_salt = $conn->prepare($sql_total_unique_salt);
    if (!$stmt_total_unique_salt) {
        throw new Exception('Failed to prepare total unique salt animals query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_total_unique_salt->execute()) {
        throw new Exception('Failed to execute total unique salt animals query: ' . $stmt_total_unique_salt->error);
    }
    
    $result_total_unique_salt = $stmt_total_unique_salt->get_result();
    if (!$result_total_unique_salt) {
        throw new Exception('Failed to get result for total unique salt animals query: ' . $stmt_total_unique_salt->error);
    }
    
    $total_unique_salt_data = $result_total_unique_salt->fetch_assoc();
    $total_unique_salt_animals = $total_unique_salt_data['total_unique_animals'] ?? 0;

    // Execute weekly salt consumption query
    $stmt_weekly_salt = $conn->prepare($sql_weekly_salt);
    if (!$stmt_weekly_salt) {
        throw new Exception('Failed to prepare weekly salt query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_weekly_salt->execute()) {
        throw new Exception('Failed to execute weekly salt query: ' . $stmt_weekly_salt->error);
    }
    
    $result_weekly_salt = $stmt_weekly_salt->get_result();
    if (!$result_weekly_salt) {
        throw new Exception('Failed to get result for weekly salt query: ' . $stmt_weekly_salt->error);
    }

    $weekly_salt_data = array();
    while ($row = $result_weekly_salt->fetch_assoc()) {
        $weekly_salt_data[] = array(
            'week_start' => $row['week_start_date'],
            'salt_consumption' => (float)$row['weekly_salt_consumption'],
            'records' => $row['weekly_records']
        );
    }

    if (!empty($weekly_salt_data)) {
        // Display the table with Fecha and Consumo Semanal (Kg)
        $header = array('Fecha', 'Consumo Semanal (Kg)');
        $data = array();
        
        foreach ($weekly_salt_data as $week_info) {
            // Format date for better display (e.g., "15 Ene 2024")
            $date_formatted = date('d M Y', strtotime($week_info['week_start']));
            $data[] = array(
                $date_formatted,
                number_format($week_info['salt_consumption'], 2)
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Calculate average weekly salt consumption
        $avg_weekly_salt_consumption = 0;
        if (count($weekly_salt_data) > 0) {
            $total_salt_consumption = array_sum(array_column($weekly_salt_data, 'salt_consumption'));
            $avg_weekly_salt_consumption = $total_salt_consumption / count($weekly_salt_data);
        }
        
        // Calculate average consumption per animal
        $avg_salt_consumption_per_animal = 0;
        if ($total_unique_salt_animals > 0) {
            $avg_salt_consumption_per_animal = $avg_weekly_salt_consumption / $total_unique_salt_animals;
        }
        
        // Add footnotes
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'Consumo promedio semanal de sal: ' . number_format($avg_weekly_salt_consumption, 2) . ' kg/semana', 0, 1);
        $pdf->Cell(0, 5, 'Numero de animales: ' . $total_unique_salt_animals, 0, 1);
        $pdf->Cell(0, 5, 'Promedio consumido por animal: ' . number_format($avg_salt_consumption_per_animal, 3) . ' kg/animal/semana', 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de sal disponibles para el análisis', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statements
    if (isset($stmt_total_unique_salt)) {
        $stmt_total_unique_salt->close();
    }
    if (isset($stmt_weekly_salt)) {
        $stmt_weekly_salt->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Salt Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de sal de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statements if they exist
    if (isset($stmt_total_unique_salt)) {
        $stmt_total_unique_salt->close();
    }
    if (isset($stmt_weekly_salt)) {
        $stmt_weekly_salt->close();
    }
}

// Molasses
$pdf->AddPage();
$pdf->ChapterTitle('Melaza (Parvada):');
$sql_molasses = "SELECT ah_melaza_tagid, ah_melaza_fecha_inicio, ah_melaza_racion, ah_melaza_costo FROM ah_melaza WHERE ah_melaza_tagid = ? ORDER BY ah_melaza_fecha_inicio DESC";
$stmt_molasses = $conn->prepare($sql_molasses);
$stmt_molasses->bind_param('s', $tagid);
$stmt_molasses->execute();
$result_molasses = $stmt_molasses->get_result();

if ($result_molasses->num_rows > 0) {
    $header = array('Tag ID', 'Fecha', 'Consumo Melaza Racion (Kg)', 'Costo ($/Kg)');
    $data = array();
    while ($row = $result_molasses->fetch_assoc()) {
        $data[] = array($row['ah_melaza_tagid'], $row['ah_melaza_fecha_inicio'], $row['ah_melaza_racion'], $row['ah_melaza_costo']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de consumo de melaza', 0, 1);
    $pdf->Ln(2);
}

// Melaza (Granja) - Farm-wide Molasses Consumption Analysis  
$pdf->AddPage();
$pdf->ChapterTitle('Melaza (Granja)');

// Query to get weekly molasses consumption from all records in ah_melaza table
$sql_weekly_molasses = "SELECT 
                           YEARWEEK(ah_melaza_fecha_inicio, 1) as year_week,
                           DATE(DATE_SUB(ah_melaza_fecha_inicio, INTERVAL WEEKDAY(ah_melaza_fecha_inicio) DAY)) as week_start_date,
                           SUM(ah_melaza_racion) as weekly_molasses_consumption,
                           COUNT(*) as weekly_records
                       FROM ah_melaza 
                       WHERE ah_melaza_racion IS NOT NULL
                       GROUP BY YEARWEEK(ah_melaza_fecha_inicio, 1)
                       ORDER BY year_week ASC";

try {
    // Query to get total unique animals in ah_melaza table
    $sql_total_unique_molasses = "SELECT COUNT(DISTINCT ah_melaza_tagid) as total_unique_animals
                                 FROM ah_melaza 
                                 WHERE ah_melaza_racion IS NOT NULL";
    
    $stmt_total_unique_molasses = $conn->prepare($sql_total_unique_molasses);
    if (!$stmt_total_unique_molasses) {
        throw new Exception('Failed to prepare total unique molasses animals query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_total_unique_molasses->execute()) {
        throw new Exception('Failed to execute total unique molasses animals query: ' . $stmt_total_unique_molasses->error);
    }
    
    $result_total_unique_molasses = $stmt_total_unique_molasses->get_result();
    if (!$result_total_unique_molasses) {
        throw new Exception('Failed to get result for total unique molasses animals query: ' . $stmt_total_unique_molasses->error);
    }
    
    $total_unique_molasses_data = $result_total_unique_molasses->fetch_assoc();
    $total_unique_molasses_animals = $total_unique_molasses_data['total_unique_animals'] ?? 0;

    // Execute weekly molasses consumption query
    $stmt_weekly_molasses = $conn->prepare($sql_weekly_molasses);
    if (!$stmt_weekly_molasses) {
        throw new Exception('Failed to prepare weekly molasses query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_weekly_molasses->execute()) {
        throw new Exception('Failed to execute weekly molasses query: ' . $stmt_weekly_molasses->error);
    }
    
    $result_weekly_molasses = $stmt_weekly_molasses->get_result();
    if (!$result_weekly_molasses) {
        throw new Exception('Failed to get result for weekly molasses query: ' . $stmt_weekly_molasses->error);
    }

    $weekly_molasses_data = array();
    while ($row = $result_weekly_molasses->fetch_assoc()) {
        $weekly_molasses_data[] = array(
            'week_start' => $row['week_start_date'],
            'molasses_consumption' => (float)$row['weekly_molasses_consumption'],
            'records' => $row['weekly_records']
        );
    }

    if (!empty($weekly_molasses_data)) {
        // Display the table with Fecha and Consumo Semanal (Kg)
        $header = array('Fecha', 'Consumo Semanal (Kg)');
        $data = array();
        
        foreach ($weekly_molasses_data as $week_info) {
            // Format date for better display (e.g., "15 Ene 2024")
            $date_formatted = date('d M Y', strtotime($week_info['week_start']));
            $data[] = array(
                $date_formatted,
                number_format($week_info['molasses_consumption'], 2)
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Calculate average weekly molasses consumption
        $avg_weekly_molasses_consumption = 0;
        if (count($weekly_molasses_data) > 0) {
            $total_molasses_consumption = array_sum(array_column($weekly_molasses_data, 'molasses_consumption'));
            $avg_weekly_molasses_consumption = $total_molasses_consumption / count($weekly_molasses_data);
        }
        
        // Calculate average consumption per animal
        $avg_molasses_consumption_per_animal = 0;
        if ($total_unique_molasses_animals > 0) {
            $avg_molasses_consumption_per_animal = $avg_weekly_molasses_consumption / $total_unique_molasses_animals;
        }
        
        // Add footnotes
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'Consumo promedio semanal de melaza: ' . number_format($avg_weekly_molasses_consumption, 2) . ' kg/semana', 0, 1);
        $pdf->Cell(0, 5, 'Numero de animales: ' . $total_unique_molasses_animals, 0, 1);
        $pdf->Cell(0, 5, 'Promedio consumido por animal: ' . number_format($avg_molasses_consumption_per_animal, 3) . ' kg/animal/semana', 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de melaza disponibles para el análisis', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statements
    if (isset($stmt_total_unique_molasses)) {
        $stmt_total_unique_molasses->close();
    }
    if (isset($stmt_weekly_molasses)) {
        $stmt_weekly_molasses->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Melaza Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de melaza de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statements if they exist
    if (isset($stmt_total_unique_molasses)) {
        $stmt_total_unique_molasses->close();
    }
    if (isset($stmt_weekly_molasses)) {
        $stmt_weekly_molasses->close();
    }
}

// Vaccination - Colera
$pdf->AddPage();
$pdf->ChapterTitle('Colera (Parvada):');
$sql_colera = "SELECT ah_colera_tagid, ah_colera_fecha, ah_colera_producto, ah_colera_dosis, ah_colera_costo FROM ah_colera WHERE ah_colera_tagid = ? ORDER BY ah_colera_fecha DESC";
$stmt_colera = $conn->prepare($sql_colera);
$stmt_colera->bind_param('s', $tagid);
$stmt_colera->execute();
$result_colera = $stmt_colera->get_result();


if ($result_colera->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_colera->fetch_assoc()) {
        $data[] = array(
            $row['ah_colera_fecha'], 
            $row['ah_colera_producto'], 
            $row['ah_colera_dosis'],
            '$' . number_format((float)$row['ah_colera_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion colera', 0, 1);
    $pdf->Ln(2);
}

// Colera (Granja) - Farm-wide Colera Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Colera (Granja)');

// Query to get all colera vaccination records from the farm
$sql_colera_granja = "SELECT 
                         ah_colera_fecha as fecha,
                         ah_colera_producto as producto,
                         ah_colera_dosis as dosis,
                         ah_colera_costo as costo,
                         ah_colera_tagid as tagid
                     FROM ah_colera 
                     WHERE ah_colera_dosis IS NOT NULL 
                     AND ah_colera_costo IS NOT NULL
                     ORDER BY ah_colera_fecha DESC";

try {
    // Execute colera granja query with error handling
    $stmt_colera_granja = $conn->prepare($sql_colera_granja);
    if (!$stmt_colera_granja) {
        throw new Exception('Failed to prepare colera granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_colera_granja->execute()) {
        throw new Exception('Failed to execute colera granja query: ' . $stmt_colera_granja->error);
    }
    
    $result_colera_granja = $stmt_colera_granja->get_result();
    if (!$result_colera_granja) {
        throw new Exception('Failed to get result for colera granja query: ' . $stmt_colera_granja->error);
    }

    $colera_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_colera_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $colera_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($colera_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($colera_granja_data as $colera_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($colera_info['fecha']));
            $data[] = array(
                $date_formatted,
                $colera_info['producto'],
                number_format($colera_info['dosis'], 1) . ' ml',
                '$' . number_format($colera_info['costo'], 2),
                $colera_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($colera_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($colera_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($colera_granja_data);
            $avg_costo = $total_costo_granja / count($colera_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($colera_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion colera disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_colera_granja)) {
        $stmt_colera_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Colera Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de colera de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_colera_granja)) {
        $stmt_colera_granja->close();
    }
}

// Vaccination - Corona Virus
$pdf->AddPage();
$pdf->ChapterTitle('Corona Virus (Parvada):');
$sql_corona_virus = "SELECT ah_corona_virus_tagid, ah_corona_virus_fecha, ah_corona_virus_producto, ah_corona_virus_dosis, ah_corona_virus_costo FROM ah_corona_virus WHERE ah_corona_virus_tagid = ? ORDER BY ah_corona_virus_fecha DESC";
$stmt_corona_virus = $conn->prepare($sql_corona_virus);
$stmt_corona_virus->bind_param('s', $tagid);
$stmt_corona_virus->execute();
$result_corona_virus = $stmt_corona_virus->get_result();


if ($result_corona_virus->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_corona_virus->fetch_assoc()) {
        $data[] = array(
            $row['ah_corona_virus_fecha'], 
            $row['ah_corona_virus_producto'], 
            $row['ah_corona_virus_dosis'],
            '$' . number_format((float)$row['ah_corona_virus_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion corona virus', 0, 1);
    $pdf->Ln(2);
}

// Corona Virus (Granja) - Farm-wide Corona Virus Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Corona Virus (Granja)');

// Query to get all corona virus vaccination records from the farm
$sql_corona_virus_granja = "SELECT 
                               ah_corona_virus_fecha as fecha,
                               ah_corona_virus_producto as producto,
                               ah_corona_virus_dosis as dosis,
                               ah_corona_virus_costo as costo,
                               ah_corona_virus_tagid as tagid
                           FROM ah_corona_virus 
                           WHERE ah_corona_virus_dosis IS NOT NULL 
                           AND ah_corona_virus_costo IS NOT NULL
                           ORDER BY ah_corona_virus_fecha DESC";

try {
    // Execute corona virus granja query with error handling
    $stmt_corona_virus_granja = $conn->prepare($sql_corona_virus_granja);
    if (!$stmt_corona_virus_granja) {
        throw new Exception('Failed to prepare corona virus granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_corona_virus_granja->execute()) {
        throw new Exception('Failed to execute corona virus granja query: ' . $stmt_corona_virus_granja->error);
    }
    
    $result_corona_virus_granja = $stmt_corona_virus_granja->get_result();
    if (!$result_corona_virus_granja) {
        throw new Exception('Failed to get result for corona virus granja query: ' . $stmt_corona_virus_granja->error);
    }

    $corona_virus_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_corona_virus_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $corona_virus_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($corona_virus_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($corona_virus_granja_data as $corona_virus_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($corona_virus_info['fecha']));
            $data[] = array(
                $date_formatted,
                $corona_virus_info['producto'],
                number_format($corona_virus_info['dosis'], 1) . ' ml',
                '$' . number_format($corona_virus_info['costo'], 2),
                $corona_virus_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($corona_virus_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($corona_virus_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($corona_virus_granja_data);
            $avg_costo = $total_costo_granja / count($corona_virus_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($corona_virus_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion corona virus disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_corona_virus_granja)) {
        $stmt_corona_virus_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Corona Virus Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de corona virus de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_corona_virus_granja)) {
        $stmt_corona_virus_granja->close();
    }
}

// Vaccination - Viruela
$pdf->AddPage();
$pdf->ChapterTitle('Viruela (Parvada):');
$sql_viruela = "SELECT ah_viruela_tagid, ah_viruela_fecha, ah_viruela_producto, ah_viruela_dosis, ah_viruela_costo FROM ah_viruela WHERE ah_viruela_tagid = ? ORDER BY ah_viruela_fecha DESC";
$stmt_viruela = $conn->prepare($sql_viruela);
$stmt_viruela->bind_param('s', $tagid);
$stmt_viruela->execute();
$result_viruela = $stmt_viruela->get_result();


if ($result_viruela->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_viruela->fetch_assoc()) {
        $data[] = array(
            $row['ah_viruela_fecha'], 
            $row['ah_viruela_producto'], 
            $row['ah_viruela_dosis'],
            '$' . number_format((float)$row['ah_viruela_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion viruela', 0, 1);
    $pdf->Ln(2);
}

// Viruela (Granja) - Farm-wide Viruela Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Viruela (Granja)');

// Query to get all viruela vaccination records from the farm
$sql_viruela_granja = "SELECT 
                          ah_viruela_fecha as fecha,
                          ah_viruela_producto as producto,
                          ah_viruela_dosis as dosis,
                          ah_viruela_costo as costo,
                          ah_viruela_tagid as tagid
                      FROM ah_viruela 
                      WHERE ah_viruela_dosis IS NOT NULL 
                      AND ah_viruela_costo IS NOT NULL
                      ORDER BY ah_viruela_fecha DESC";

try {
    // Execute viruela granja query with error handling
    $stmt_viruela_granja = $conn->prepare($sql_viruela_granja);
    if (!$stmt_viruela_granja) {
        throw new Exception('Failed to prepare viruela granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_viruela_granja->execute()) {
        throw new Exception('Failed to execute viruela granja query: ' . $stmt_viruela_granja->error);
    }
    
    $result_viruela_granja = $stmt_viruela_granja->get_result();
    if (!$result_viruela_granja) {
        throw new Exception('Failed to get result for viruela granja query: ' . $stmt_viruela_granja->error);
    }

    $viruela_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_viruela_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $viruela_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($viruela_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($viruela_granja_data as $viruela_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($viruela_info['fecha']));
            $data[] = array(
                $date_formatted,
                $viruela_info['producto'],
                number_format($viruela_info['dosis'], 1) . ' ml',
                '$' . number_format($viruela_info['costo'], 2),
                $viruela_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($viruela_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($viruela_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($viruela_granja_data);
            $avg_costo = $total_costo_granja / count($viruela_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($viruela_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion viruela disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_viruela_granja)) {
        $stmt_viruela_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Viruela Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de viruela de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_viruela_granja)) {
        $stmt_viruela_granja->close();
    }
}


// Vaccination - Coriza
$pdf->AddPage();
$pdf->ChapterTitle('Coriza (Parvada):');
$sql_coriza = "SELECT ah_coriza_tagid, ah_coriza_fecha, ah_coriza_producto, ah_coriza_dosis, ah_coriza_costo FROM ah_coriza WHERE ah_coriza_tagid = ? ORDER BY ah_coriza_fecha DESC";
$stmt_coriza = $conn->prepare($sql_coriza);
$stmt_coriza->bind_param('s', $tagid);
$stmt_coriza->execute();
$result_coriza = $stmt_coriza->get_result();

if ($result_coriza->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_coriza->fetch_assoc()) {
        $data[] = array(
            $row['ah_coriza_fecha'], 
            $row['ah_coriza_producto'], 
            $row['ah_coriza_dosis'],
            '$' . number_format((float)$row['ah_coriza_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion coriza', 0, 1);
    $pdf->Ln(2);
}

// Coriza (Granja) - Farm-wide Coriza Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Coriza (Granja)');

// Query to get all coriza vaccination records from the farm
$sql_coriza_granja = "SELECT 
                         ah_coriza_fecha as fecha,
                         ah_coriza_producto as producto,
                         ah_coriza_dosis as dosis,
                         ah_coriza_costo as costo,
                         ah_coriza_tagid as tagid
                     FROM ah_coriza 
                     WHERE ah_coriza_dosis IS NOT NULL 
                     AND ah_coriza_costo IS NOT NULL
                     ORDER BY ah_coriza_fecha DESC";

try {
    // Execute coriza granja query with error handling
    $stmt_coriza_granja = $conn->prepare($sql_coriza_granja);
    if (!$stmt_coriza_granja) {
        throw new Exception('Failed to prepare coriza granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_coriza_granja->execute()) {
        throw new Exception('Failed to execute coriza granja query: ' . $stmt_coriza_granja->error);
    }
    
    $result_coriza_granja = $stmt_coriza_granja->get_result();
    if (!$result_coriza_granja) {
        throw new Exception('Failed to get result for coriza granja query: ' . $stmt_coriza_granja->error);
    }

    $coriza_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_coriza_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $coriza_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($coriza_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($coriza_granja_data as $coriza_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($coriza_info['fecha']));
            $data[] = array(
                $date_formatted,
                $coriza_info['producto'],
                number_format($coriza_info['dosis'], 1) . ' ml',
                '$' . number_format($coriza_info['costo'], 2),
                $coriza_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($coriza_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($coriza_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($coriza_granja_data);
            $avg_costo = $total_costo_granja / count($coriza_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($coriza_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion coriza disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_coriza_granja)) {
        $stmt_coriza_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Coriza Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de coriza de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_coriza_granja)) {
        $stmt_coriza_granja->close();
    }
}

// Vaccination - Encefalomielitis
$pdf->AddPage();
$pdf->ChapterTitle('Encefalomielitis (Parvada):');
$sql_encefalomielitis = "SELECT ah_encefalomielitis_tagid, ah_encefalomielitis_fecha, ah_encefalomielitis_producto, ah_encefalomielitis_dosis, ah_encefalomielitis_costo FROM ah_encefalomielitis WHERE ah_encefalomielitis_tagid = ? ORDER BY ah_encefalomielitis_fecha DESC";
$stmt_encefalomielitis = $conn->prepare($sql_encefalomielitis);
$stmt_encefalomielitis->bind_param('s', $tagid);
$stmt_encefalomielitis->execute();
$result_encefalomielitis = $stmt_encefalomielitis->get_result();

if ($result_encefalomielitis->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_encefalomielitis->fetch_assoc()) {
        $data[] = array(
            $row['ah_encefalomielitis_fecha'], 
            $row['ah_encefalomielitis_producto'], 
            $row['ah_encefalomielitis_dosis'],
            '$' . number_format((float)$row['ah_encefalomielitis_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion encefalomielitis', 0, 1);
    $pdf->Ln(2);
}

// Encefalomielitis (Granja) - Farm-wide Encefalomielitis Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Encefalomielitis (Granja)');

// Query to get all encefalomielitis vaccination records from the farm
$sql_encefalomielitis_granja = "SELECT 
                                   ah_encefalomielitis_fecha as fecha,
                                   ah_encefalomielitis_producto as producto,
                                   ah_encefalomielitis_dosis as dosis,
                                   ah_encefalomielitis_costo as costo,
                                   ah_encefalomielitis_tagid as tagid
                               FROM ah_encefalomielitis 
                               WHERE ah_encefalomielitis_dosis IS NOT NULL 
                               AND ah_encefalomielitis_costo IS NOT NULL
                               ORDER BY ah_encefalomielitis_fecha DESC";

try {
    // Execute encefalomielitis granja query with error handling
    $stmt_encefalomielitis_granja = $conn->prepare($sql_encefalomielitis_granja);
    if (!$stmt_encefalomielitis_granja) {
        throw new Exception('Failed to prepare encefalomielitis granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_encefalomielitis_granja->execute()) {
        throw new Exception('Failed to execute encefalomielitis granja query: ' . $stmt_encefalomielitis_granja->error);
    }
    
    $result_encefalomielitis_granja = $stmt_encefalomielitis_granja->get_result();
    if (!$result_encefalomielitis_granja) {
        throw new Exception('Failed to get result for encefalomielitis granja query: ' . $stmt_encefalomielitis_granja->error);
    }

    $encefalomielitis_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_encefalomielitis_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $encefalomielitis_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($encefalomielitis_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($encefalomielitis_granja_data as $encefalomielitis_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($encefalomielitis_info['fecha']));
            $data[] = array(
                $date_formatted,
                $encefalomielitis_info['producto'],
                number_format($encefalomielitis_info['dosis'], 1) . ' ml',
                '$' . number_format($encefalomielitis_info['costo'], 2),
                $encefalomielitis_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($encefalomielitis_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($encefalomielitis_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($encefalomielitis_granja_data);
            $avg_costo = $total_costo_granja / count($encefalomielitis_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($encefalomielitis_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion encefalomielitis disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_encefalomielitis_granja)) {
        $stmt_encefalomielitis_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Encefalomielitis Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de encefalomielitis de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_encefalomielitis_granja)) {
        $stmt_encefalomielitis_granja->close();
    }
}

// Vaccination - Influenza
$pdf->AddPage();
$pdf->ChapterTitle('Influenza (Parvada):');
$sql_influenza = "SELECT ah_influenza_tagid, ah_influenza_fecha, ah_influenza_producto, ah_influenza_dosis, ah_influenza_costo FROM ah_influenza WHERE ah_influenza_tagid = ? ORDER BY ah_influenza_fecha DESC";
$stmt_influenza = $conn->prepare($sql_influenza);
$stmt_influenza->bind_param('s', $tagid);
$stmt_influenza->execute();
$result_influenza = $stmt_influenza->get_result();

if ($result_influenza->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_influenza->fetch_assoc()) {
        $data[] = array(
            $row['ah_influenza_fecha'], 
            $row['ah_influenza_producto'], 
            $row['ah_influenza_dosis'],
            '$' . number_format((float)$row['ah_influenza_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion influenza', 0, 1);
    $pdf->Ln(2);
}

// Influenza (Granja) - Farm-wide Influenza Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Influenza (Granja)');

// Query to get all influenza vaccination records from the farm
$sql_influenza_granja = "SELECT 
                            ah_influenza_fecha as fecha,
                            ah_influenza_producto as producto,
                            ah_influenza_dosis as dosis,
                            ah_influenza_costo as costo,
                            ah_influenza_tagid as tagid
                        FROM ah_influenza 
                        WHERE ah_influenza_dosis IS NOT NULL 
                        AND ah_influenza_costo IS NOT NULL
                        ORDER BY ah_influenza_fecha DESC";

try {
    // Execute influenza granja query with error handling
    $stmt_influenza_granja = $conn->prepare($sql_influenza_granja);
    if (!$stmt_influenza_granja) {
        throw new Exception('Failed to prepare influenza granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_influenza_granja->execute()) {
        throw new Exception('Failed to execute influenza granja query: ' . $stmt_influenza_granja->error);
    }
    
    $result_influenza_granja = $stmt_influenza_granja->get_result();
    if (!$result_influenza_granja) {
        throw new Exception('Failed to get result for influenza granja query: ' . $stmt_influenza_granja->error);
    }

    $influenza_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_influenza_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $influenza_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($influenza_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($influenza_granja_data as $influenza_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($influenza_info['fecha']));
            $data[] = array(
                $date_formatted,
                $influenza_info['producto'],
                number_format($influenza_info['dosis'], 1) . ' ml',
                '$' . number_format($influenza_info['costo'], 2),
                $influenza_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($influenza_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($influenza_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($influenza_granja_data);
            $avg_costo = $total_costo_granja / count($influenza_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($influenza_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion influenza disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_influenza_granja)) {
        $stmt_influenza_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Influenza Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de influenza de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_influenza_granja)) {
        $stmt_influenza_granja->close();
    }
}

// Vaccination - Marek
$pdf->AddPage();
$pdf->ChapterTitle('Tabla Marek');
$sql_marek = "SELECT ah_marek_tagid, ah_marek_fecha, ah_marek_producto, ah_marek_dosis, ah_marek_costo FROM ah_marek WHERE ah_marek_tagid = ? ORDER BY ah_marek_fecha DESC";
$stmt_marek = $conn->prepare($sql_marek);
$stmt_marek->bind_param('s', $tagid);
$stmt_marek->execute();
$result_marek = $stmt_marek->get_result();

if ($result_marek->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_marek->fetch_assoc()) {
        $data[] = array(
            $row['ah_marek_fecha'], 
            $row['ah_marek_producto'], 
            $row['ah_marek_dosis'],
            '$' . number_format((float)$row['ah_marek_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion marek', 0, 1);
    $pdf->Ln(2);
}

// Marek (Granja) - Farm-wide Marek Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Marek (Granja)');

// Query to get all marek vaccination records from the farm
$sql_marek_granja = "SELECT 
                        ah_marek_fecha as fecha,
                        ah_marek_producto as producto,
                        ah_marek_dosis as dosis,
                        ah_marek_costo as costo,
                        ah_marek_tagid as tagid
                    FROM ah_marek 
                    WHERE ah_marek_dosis IS NOT NULL 
                    AND ah_marek_costo IS NOT NULL
                    ORDER BY ah_marek_fecha DESC";

try {
    // Execute marek granja query with error handling
    $stmt_marek_granja = $conn->prepare($sql_marek_granja);
    if (!$stmt_marek_granja) {
        throw new Exception('Failed to prepare marek granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_marek_granja->execute()) {
        throw new Exception('Failed to execute marek granja query: ' . $stmt_marek_granja->error);
    }
    
    $result_marek_granja = $stmt_marek_granja->get_result();
    if (!$result_marek_granja) {
        throw new Exception('Failed to get result for marek granja query: ' . $stmt_marek_granja->error);
    }

    $marek_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_marek_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $marek_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($marek_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($marek_granja_data as $marek_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($marek_info['fecha']));
            $data[] = array(
                $date_formatted,
                $marek_info['producto'],
                number_format($marek_info['dosis'], 1) . ' ml',
                '$' . number_format($marek_info['costo'], 2),
                $marek_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($marek_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($marek_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($marek_granja_data);
            $avg_costo = $total_costo_granja / count($marek_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($marek_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion marek disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_marek_granja)) {
        $stmt_marek_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Marek Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de marek de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_marek_granja)) {
        $stmt_marek_granja->close();
    }
}

// Vaccination - Newcastle
$pdf->AddPage();
$pdf->ChapterTitle('Tabla Newcastle');
$sql_newcastle = "SELECT ah_newcastle_tagid, ah_newcastle_fecha, ah_newcastle_producto, ah_newcastle_dosis, ah_newcastle_costo FROM ah_newcastle WHERE ah_newcastle_tagid = ? ORDER BY ah_newcastle_fecha DESC";
$stmt_newcastle = $conn->prepare($sql_newcastle);
$stmt_newcastle->bind_param('s', $tagid);
$stmt_newcastle->execute();
$result_newcastle = $stmt_newcastle->get_result();

if ($result_newcastle->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_newcastle->fetch_assoc()) {
        $data[] = array(
            $row['ah_newcastle_fecha'], 
            $row['ah_newcastle_producto'], 
            $row['ah_newcastle_dosis'],
            '$' . number_format((float)$row['ah_newcastle_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de vacunacion newcastle', 0, 1);
    $pdf->Ln(2);
}

// Newcastle (Granja) - Farm-wide Newcastle Vaccination Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Newcastle (Granja)');

// Query to get all newcastle vaccination records from the farm
$sql_newcastle_granja = "SELECT 
                            ah_newcastle_fecha as fecha,
                            ah_newcastle_producto as producto,
                            ah_newcastle_dosis as dosis,
                            ah_newcastle_costo as costo,
                            ah_newcastle_tagid as tagid
                        FROM ah_newcastle 
                        WHERE ah_newcastle_dosis IS NOT NULL 
                        AND ah_newcastle_costo IS NOT NULL
                        ORDER BY ah_newcastle_fecha DESC";

try {
    // Execute newcastle granja query with error handling
    $stmt_newcastle_granja = $conn->prepare($sql_newcastle_granja);
    if (!$stmt_newcastle_granja) {
        throw new Exception('Failed to prepare newcastle granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_newcastle_granja->execute()) {
        throw new Exception('Failed to execute newcastle granja query: ' . $stmt_newcastle_granja->error);
    }
    
    $result_newcastle_granja = $stmt_newcastle_granja->get_result();
    if (!$result_newcastle_granja) {
        throw new Exception('Failed to get result for newcastle granja query: ' . $stmt_newcastle_granja->error);
    }

    $newcastle_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_newcastle_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $newcastle_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($newcastle_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($newcastle_granja_data as $newcastle_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($newcastle_info['fecha']));
            $data[] = array(
                $date_formatted,
                $newcastle_info['producto'],
                number_format($newcastle_info['dosis'], 1) . ' ml',
                '$' . number_format($newcastle_info['costo'], 2),
                $newcastle_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de vacunacion (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($newcastle_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($newcastle_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($newcastle_granja_data);
            $avg_costo = $total_costo_granja / count($newcastle_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals vaccinated
        $unique_animals = array_unique(array_column($newcastle_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales vacunados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de vacunacion newcastle disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_newcastle_granja)) {
        $stmt_newcastle_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Newcastle Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de newcastle de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_newcastle_granja)) {
        $stmt_newcastle_granja->close();
    }
}

// Treatment - Parasitos
$pdf->AddPage();
$pdf->ChapterTitle('Tabla Parasitos');
$sql_parasitos = "SELECT ah_parasitos_tagid, ah_parasitos_fecha, ah_parasitos_producto, ah_parasitos_dosis, ah_parasitos_costo FROM ah_parasitos WHERE ah_parasitos_tagid = ? ORDER BY ah_parasitos_fecha DESC";
$stmt_parasitos = $conn->prepare($sql_parasitos);
$stmt_parasitos->bind_param('s', $tagid);
$stmt_parasitos->execute();
$result_parasitos = $stmt_parasitos->get_result();

if ($result_parasitos->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_parasitos->fetch_assoc()) {
        $data[] = array(
            $row['ah_parasitos_fecha'], 
            $row['ah_parasitos_producto'], 
            $row['ah_parasitos_dosis'],
            '$' . number_format((float)$row['ah_parasitos_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de tratamiento parasitos', 0, 1);
    $pdf->Ln(2);
}

// Parasitos (Granja) - Farm-wide Parasite Treatment Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Parasitos (Granja)');

// Query to get all parasite treatment records from the farm
$sql_parasitos_granja = "SELECT 
                            ah_parasitos_fecha as fecha,
                            ah_parasitos_producto as producto,
                            ah_parasitos_dosis as dosis,
                            ah_parasitos_costo as costo,
                            ah_parasitos_tagid as tagid
                        FROM ah_parasitos 
                        WHERE ah_parasitos_dosis IS NOT NULL 
                        AND ah_parasitos_costo IS NOT NULL
                        ORDER BY ah_parasitos_fecha DESC";

try {
    // Execute parasitos granja query with error handling
    $stmt_parasitos_granja = $conn->prepare($sql_parasitos_granja);
    if (!$stmt_parasitos_granja) {
        throw new Exception('Failed to prepare parasitos granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_parasitos_granja->execute()) {
        throw new Exception('Failed to execute parasitos granja query: ' . $stmt_parasitos_granja->error);
    }
    
    $result_parasitos_granja = $stmt_parasitos_granja->get_result();
    if (!$result_parasitos_granja) {
        throw new Exception('Failed to get result for parasitos granja query: ' . $stmt_parasitos_granja->error);
    }

    $parasitos_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_parasitos_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $parasitos_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($parasitos_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($parasitos_granja_data as $parasitos_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($parasitos_info['fecha']));
            $data[] = array(
                $date_formatted,
                $parasitos_info['producto'],
                number_format($parasitos_info['dosis'], 1) . ' ml',
                '$' . number_format($parasitos_info['costo'], 2),
                $parasitos_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de tratamiento (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($parasitos_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($parasitos_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($parasitos_granja_data);
            $avg_costo = $total_costo_granja / count($parasitos_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals treated
        $unique_animals = array_unique(array_column($parasitos_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales tratados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de tratamiento parasitos disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_parasitos_granja)) {
        $stmt_parasitos_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Parasitos Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de parasitos de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_parasitos_granja)) {
        $stmt_parasitos_granja->close();
    }
}

// Treatment - Garrapatas
$pdf->AddPage();
$pdf->ChapterTitle('Tabla Garrapatas');
$sql_garrapatas = "SELECT ah_garrapatas_tagid, ah_garrapatas_fecha, ah_garrapatas_producto, ah_garrapatas_dosis, ah_garrapatas_costo FROM ah_garrapatas WHERE ah_garrapatas_tagid = ? ORDER BY ah_garrapatas_fecha DESC";
$stmt_garrapatas = $conn->prepare($sql_garrapatas);
$stmt_garrapatas->bind_param('s', $tagid);
$stmt_garrapatas->execute();
$result_garrapatas = $stmt_garrapatas->get_result();

if ($result_garrapatas->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)');
    $data = array();
    while ($row = $result_garrapatas->fetch_assoc()) {
        $data[] = array(
            $row['ah_garrapatas_fecha'], 
            $row['ah_garrapatas_producto'], 
            $row['ah_garrapatas_dosis'],
            '$' . number_format((float)$row['ah_garrapatas_costo'], 2)
        );
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No hay registros de tratamiento garrapatas', 0, 1);
    $pdf->Ln(2);
}

// Garrapatas (Granja) - Farm-wide Tick Treatment Analysis
$pdf->AddPage();
$pdf->ChapterTitle('Garrapatas (Granja)');

// Query to get all tick treatment records from the farm
$sql_garrapatas_granja = "SELECT 
                            ah_garrapatas_fecha as fecha,
                            ah_garrapatas_producto as producto,
                            ah_garrapatas_dosis as dosis,
                            ah_garrapatas_costo as costo,
                            ah_garrapatas_tagid as tagid
                        FROM ah_garrapatas 
                        WHERE ah_garrapatas_dosis IS NOT NULL 
                        AND ah_garrapatas_costo IS NOT NULL
                        ORDER BY ah_garrapatas_fecha DESC";

try {
    // Execute garrapatas granja query with error handling
    $stmt_garrapatas_granja = $conn->prepare($sql_garrapatas_granja);
    if (!$stmt_garrapatas_granja) {
        throw new Exception('Failed to prepare garrapatas granja query: ' . mysqli_error($conn));
    }
    
    if (!$stmt_garrapatas_granja->execute()) {
        throw new Exception('Failed to execute garrapatas granja query: ' . $stmt_garrapatas_granja->error);
    }
    
    $result_garrapatas_granja = $stmt_garrapatas_granja->get_result();
    if (!$result_garrapatas_granja) {
        throw new Exception('Failed to get result for garrapatas granja query: ' . $stmt_garrapatas_granja->error);
    }

    $garrapatas_granja_data = array();
    $total_dosis_granja = 0;
    $total_costo_granja = 0;
    
    while ($row = $result_garrapatas_granja->fetch_assoc()) {
        $dosis = (float)$row['dosis'];
        $costo = (float)$row['costo'];
        
        $garrapatas_granja_data[] = array(
            'fecha' => $row['fecha'],
            'producto' => $row['producto'],
            'dosis' => $dosis,
            'costo' => $costo,
            'tagid' => $row['tagid']
        );
        
        $total_dosis_granja += $dosis;
        $total_costo_granja += $costo;
    }

    if (!empty($garrapatas_granja_data)) {
        // Display the table with 5 columns including Tag ID
        $header = array('Fecha', 'Producto', 'Dosis (ml)', 'Costo ($)', 'Tag ID');
        $data = array();
        
        foreach ($garrapatas_granja_data as $garrapatas_info) {
            // Format date for better display
            $date_formatted = date('d/m/Y', strtotime($garrapatas_info['fecha']));
            $data[] = array(
                $date_formatted,
                $garrapatas_info['producto'],
                number_format($garrapatas_info['dosis'], 1) . ' ml',
                '$' . number_format($garrapatas_info['costo'], 2),
                $garrapatas_info['tagid']
            );
        }
        
        $pdf->SimpleTable($header, $data);
        
        // Add summary statistics for farm
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Resumen de la Granja:', 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Total de dosis aplicadas (granja): ' . number_format($total_dosis_granja, 1) . ' ml', 0, 1);
        $pdf->Cell(0, 5, 'Costo total de tratamiento (granja): $' . number_format($total_costo_granja, 2), 0, 1);
        $pdf->Cell(0, 5, 'Número de registros (granja): ' . count($garrapatas_granja_data), 0, 1);
        
        // Calculate average dose and cost if we have data
        if (count($garrapatas_granja_data) > 0) {
            $avg_dosis = $total_dosis_granja / count($garrapatas_granja_data);
            $avg_costo = $total_costo_granja / count($garrapatas_granja_data);
            $pdf->Cell(0, 5, 'Dosis promedio por aplicacion: ' . number_format($avg_dosis, 2) . ' ml', 0, 1);
            $pdf->Cell(0, 5, 'Costo promedio por aplicacion: $' . number_format($avg_costo, 2), 0, 1);
        }
        
        // Calculate unique animals treated
        $unique_animals = array_unique(array_column($garrapatas_granja_data, 'tagid'));
        $pdf->Cell(0, 5, 'Número de animales tratados: ' . count($unique_animals), 0, 1);
        
    } else {
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(0, 5, 'No hay registros de tratamiento garrapatas disponibles en la granja', 0, 1);
        $pdf->Ln(2);
    }
    
    // Close prepared statement
    if (isset($stmt_garrapatas_granja)) {
        $stmt_garrapatas_granja->close();
    }
    
} catch (Exception $e) {
    // Log the error and display a user-friendly message in the PDF
    error_log('Garrapatas Granja section error in aviar_report.php: ' . $e->getMessage());
    
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'Error al generar la sección de garrapatas de granja: ' . $e->getMessage(), 0, 1);
    $pdf->Ln(2);
    
    // Close prepared statement if it exists
    if (isset($stmt_garrapatas_granja)) {
        $stmt_garrapatas_granja->close();
    }
}


// At the end of the file:
// Clean any output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Sanitize animal name for filename (remove special characters and spaces)
$sanitized_name = preg_replace('/[^a-zA-Z0-9]/', '_', $animal['nombre']);
$sanitized_name = trim($sanitized_name, '_'); // Remove leading/trailing underscores

// Generate filename with timestamp to avoid conflicts
$filename = $sanitized_name . '_' . $tagid . '_' . date('Y-m-d_His') . '.pdf';
$filepath = __DIR__ . '/reports/' . $filename;

try {
    // Make sure reports directory exists with proper permissions
    $reportsDir = __DIR__ . '/reports';
    if (!file_exists($reportsDir)) {
        if (!mkdir($reportsDir, 0777, true)) {
            throw new Exception('Cannot create reports directory. Check file permissions.');
        }
        // Ensure permissions are set correctly after creation
        chmod($reportsDir, 0777);
    }

    // Verify directory is writable
    if (!is_writable($reportsDir)) {
        // Try to fix permissions
        if (chmod($reportsDir, 0777)) {
            error_log('Fixed reports directory permissions');
        } else {
            throw new Exception('Reports directory is not writable and permissions cannot be changed. Check file permissions.');
        }
    }

    // First save the PDF to file with improved error handling
    try {
        // Method 1: Try FPDF Output with error capture
        ob_start();
        $pdf->Output('F', $filepath);
        $output_errors = ob_get_clean();
        
        // Check if file was actually created
        if (!file_exists($filepath)) {
            // Method 2: Alternative approach using string output
            $pdf_content = $pdf->Output('S');
            if (file_put_contents($filepath, $pdf_content) === false) {
                throw new Exception('Failed to save PDF file to filesystem using alternative method');
            }
        }
    } catch (Exception $e) {
        throw new Exception('Failed to save PDF file to filesystem: ' . $e->getMessage());
    }
    
    // Verify the file was created and is a PDF
    if (!file_exists($filepath)) {
        throw new Exception('PDF file was not created on filesystem');
    }
    
    if (filesize($filepath) === 0) {
        unlink($filepath); // Delete empty file
        throw new Exception('Generated PDF file is empty - possible data or memory issue');
    }

    // Additional validation - check if file is actually a PDF
    $fileContent = file_get_contents($filepath, false, null, 0, 4);
    if ($fileContent !== '%PDF') {
        unlink($filepath); // Delete invalid file
        throw new Exception('Generated file is not a valid PDF');
    }
    
    // Log success with file size for debugging
    error_log("PDF generated successfully: " . $filepath . " (Size: " . filesize($filepath) . " bytes)");
    
    // Check if share page exists before redirecting
    if (!file_exists(__DIR__ . '/aviar_share.php')) {
        throw new Exception('Share page not found. PDF generated but cannot display.');
    }
    
    // Redirect to share page
    header('Location: aviar_share.php?file=' . urlencode($filename) . '&tagid=' . urlencode($tagid));
    exit;
} catch (Exception $e) {
    // Enhanced error logging with more context
    error_log('PDF Generation Error for tagid ' . $tagid . ': ' . $e->getMessage());
    error_log('PHP Memory Usage: ' . memory_get_usage(true) . ' bytes');
    
    // Clean up any failed file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    
    // Display user-friendly error message
    die('Error generating PDF report: ' . $e->getMessage() . 
        '<br><br>Please check:<ul>' .
        '<li>Animal ID exists in database</li>' .
        '<li>Server has sufficient memory and disk space</li>' .
        '<li>Directory permissions are correct</li>' .
        '</ul>Contact support if problem persists.');
}