<?php
require_once './pdo_conexion.php';
require('./fpdf/fpdf.php'); // You might need to install FPDF library

// Check if animal ID is provided
if (!isset($_GET['tagid']) || empty($_GET['tagid'])) {
    die('Error: No animal ID provided');
}

$tagid = $_GET['tagid'];

// Connect to database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

// Fetch animal basic info
$sql_animal = "SELECT * FROM aviar WHERE tagid = ?";
$stmt_animal = $conn->prepare($sql_animal);
$stmt_animal->bind_param('s', $tagid);
$stmt_animal->execute();
$result_animal = $stmt_animal->get_result();

if ($result_animal->num_rows === 0) {
    die('Error: Animal not found');
}

$animal = $result_animal->fetch_assoc();

// Create PDF
class PDF extends FPDF
{
    // Animal data to access in header
    protected $animalData;
    
    // Set animal data
    function setAnimalData($data) {
        $this->animalData = $data;
    }
    
    // Page header
    function Header()
    {
        // Only show header on first page
        if ($this->PageNo() == 1) {
            // Set margins and padding
            $this->SetMargins(10, 10, 10);
            
            // Logo with adjusted position
            $this->Image('./images/default_image.png', 10, 6, 30);
            
            // Draw a subtle header background
            $this->SetFillColor(240, 240, 240);
            $this->Rect(0, 0, 210, 35, 'F');
            
            // Add a decorative line
            $this->SetDrawColor(0, 128, 0); // Green line
            $this->Line(10, 35, 200, 35);
            
            // Main report title
            $this->SetFont('Arial', 'B', 18);
            $this->SetTextColor(0, 80, 0); // Darker green for main title
            $this->Cell(0, 10, 'RESUMEN HISTORICO ANIMAL', 0, 1, 'C');
            $this->Ln(5);
            
            // Title section with animal name - larger, bold font
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(0, 100, 0); // Dark green color for title
            // Center alignment for animal name
            $this->Cell(0, 10, mb_strtoupper($this->animalData['nombre']), 0, 1, 'C');
            
            // Tag ID in a slightly smaller font, still professional
            $this->SetFont('Arial', 'B', 12);
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
        $this->SetFont('Arial', 'I', 8);
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
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(0, 100, 0); // Darker green
        $this->SetTextColor(255, 255, 255); // White text
        
        // Check if this is a main section title (all caps)
        if ($title == 'PRODUCCION' || $title == 'ALIMENTACION' || $title == 'SALUD' || $title == 'REPRODUCCION') {
            // Main section titles - centered, larger font, more space before/after
            $this->SetFont('Arial', 'B', 14);
            $this->Ln(5); // Extra space before main sections
            $this->Cell(0, 10, $title, 0, 1, 'C', true);
            $this->Ln(5); // Extra space after main sections
        } else {
            // Regular subsection titles - left aligned
            $this->Cell(0, 8, $title, 0, 1, 'L', true);
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
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(50, 120, 50); // Darker green for header
        $this->SetTextColor(255, 255, 255); // White text for better contrast
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0); // Reset to black text for data
        
        // Data
        $this->SetFont('Arial', '', 9); // Match SimpleTable font size
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
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(50, 120, 50); // Darker green for header
        $this->SetTextColor(255, 255, 255); // White text for better contrast
        for ($i = 0; $i < $columnCount; $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0); // Reset to black text for data
        
        // Data
        $this->SetFont('Arial', '', 9); // Slightly smaller font to fit more text
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

// Initialize PDF
$pdf = new PDF();
$pdf->AliasNbPages();
// Pass animal data to PDF for Header images
$pdf->setAnimalData($animal);
$pdf->AddPage();

// Basic animal information
$pdf->ChapterTitle('Datos del Animal');
$header = array('Concepto', 'Descripcion');
$data = array(
    array('Tag ID', $animal['tagid']),
    array('Nombre', $animal['nombre']),
    array('Fecha Nacimiento', $animal['fecha_nacimiento']),
    array('Genero', $animal['genero']),
    array('Raza', $animal['raza']),
    array('Etapa', $animal['etapa']),
    array('Grupo', $animal['grupo']),
    array('Estatus', $animal['estatus'])
);
$pdf->SimpleTable($header, $data);

// Peso history
$pdf->ChapterTitle('PRODUCCION');
$pdf->ChapterTitle('Pesajes Animal');
$sql_weight = "SELECT ah_peso_fecha, ah_peso_animal, ah_peso_precio FROM ah_peso WHERE ah_peso_tagid = ? ORDER BY ah_peso_fecha DESC";
$stmt_weight = $conn->prepare($sql_weight);
$stmt_weight->bind_param('s', $tagid);
$stmt_weight->execute();
$result_weight = $stmt_weight->get_result();

if ($result_weight->num_rows > 0) {
    $header = array('Fecha', 'Peso (kg)', 'Precio ($/Kg)');
    $data = array();
    while ($row = $result_weight->fetch_assoc()) {
        $data[] = array($row['ah_peso_fecha'], $row['ah_peso_animal'], $row['ah_peso_precio']);
    }
    $pdf->SimpleTable($header, $data);

    // Add weight history chart if data exists
    // Reset result pointer
    $stmt_weight->execute();
    $result_weight = $stmt_weight->get_result();
    
    // Collect data for chart
    $weights = array();
    $dates = array();
    while ($row = $result_weight->fetch_assoc()) {
        $weights[] = $row['ah_peso_animal'];
        $dates[] = date('d/m/y', strtotime($row['ah_peso_fecha']));
    }
    
    // Reverse arrays to show chronological order
    $weights = array_reverse($weights);
    $dates = array_reverse($dates);
    
    // Chart title
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'Grafico de Evolucion de Peso', 0, 1, 'C');
    $pdf->Ln(2);
    
    // Chart dimensions
    $chartX = 30;
    $chartY = $pdf->GetY();
    $chartWidth = 150;
    $chartHeight = 60;
    
    // Draw chart axes
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Line($chartX, $chartY, $chartX, $chartY + $chartHeight); // Y-axis
    $pdf->Line($chartX, $chartY + $chartHeight, $chartX + $chartWidth, $chartY + $chartHeight); // X-axis
    
    // Plot data points
    if (count($weights) > 0) {
        // Find min and max values for scaling
        $maxWeight = max($weights);
        $minWeight = min($weights);
        $range = max(1, $maxWeight - $minWeight); // Prevent division by zero
        
        // Add 10% padding to max and min
        $padding = $range * 0.1;
        $effectiveMax = $maxWeight + $padding;
        $effectiveMin = max(0, $minWeight - $padding); // Don't go below 0
        $effectiveRange = $effectiveMax - $effectiveMin;
        
        // Plot points and connect with lines
        $pdf->SetDrawColor(0, 100, 0); // Green lines
        $pdf->SetFillColor(0, 150, 0); // Darker green for points
        
        $pointCount = count($weights);
        $pointSpacing = $chartWidth / max(1, $pointCount - 1);
        
        $prevX = 0;
        $prevY = 0;
        
        for ($i = 0; $i < $pointCount; $i++) {
            // Calculate position
            $x = $chartX + ($i * $pointSpacing);
            $y = $chartY + $chartHeight - (($weights[$i] - $effectiveMin) / $effectiveRange * $chartHeight);
            
            // Draw point
            $pdf->Circle($x, $y, 2, 'F');
            
            // Connect points with a line (if not the first point)
            if ($i > 0) {
                $pdf->Line($prevX, $prevY, $x, $y);
            }
            
            $prevX = $x;
            $prevY = $y;
            
            // Add date labels
            if ($pointCount <= 10 || $i % ceil($pointCount / 10) == 0) {
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetXY($x - 7, $chartY + $chartHeight + 1);
                $pdf->Cell(14, 5, $dates[$i], 0, 0, 'C');
            }
        }
        
        // Add weight labels on Y-axis
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetDrawColor(200, 200, 200); // Light gray for grid lines
        
        $yLabels = 5; // Number of labels to show
        for ($i = 0; $i <= $yLabels; $i++) {
            $labelValue = $effectiveMin + ($effectiveRange * $i / $yLabels);
            $labelY = $chartY + $chartHeight - ($i * $chartHeight / $yLabels);
            
            // Draw label
            $pdf->SetXY($chartX - 20, $labelY - 2);
            $pdf->Cell(18, 4, round($labelValue, 0) . ' kg', 0, 0, 'R');
            
            // Draw grid line
            $pdf->Line($chartX, $labelY, $chartX + $chartWidth, $labelY);
        }
    }
    
    // Move position after chart
    $pdf->Ln($chartHeight + 15);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No peso encontrado', 0, 1);
    $pdf->Ln(2);
}

// Concentrado
$pdf->ChapterTitle('Consumo de Concentrado');
$sql_concentrado = "SELECT ah_concentrado_fecha, ah_concentrado_racion, ah_concentrado_costo FROM ah_concentrado WHERE ah_concentrado_tagid = ? ORDER BY ah_concentrado_fecha DESC";
$stmt_concentrado = $conn->prepare($sql_concentrado);
$stmt_concentrado->bind_param('s', $tagid);
$stmt_concentrado->execute();
$result_concentrado = $stmt_concentrado->get_result();

if ($result_concentrado->num_rows > 0) {
    $header = array('Fecha', 'Peso (kg)', 'Precio ($/Kg)');
    $data = array();
    while ($row = $result_concentrado->fetch_assoc()) {
        $data[] = array($row['ah_concentrado_fecha'], $row['ah_concentrado_racion'], $row['ah_concentrado_costo']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No consumo de concentrado encontrado', 0, 1);
    $pdf->Ln(2);
}

// Molasses
$pdf->ChapterTitle('Consumo de Melaza');
$sql_molasses = "SELECT ah_melaza_fecha, ah_melaza_racion, ah_melaza_costo FROM ah_melaza WHERE ah_melaza_tagid = ? ORDER BY ah_melaza_fecha DESC";
$stmt_molasses = $conn->prepare($sql_molasses);
$stmt_molasses->bind_param('s', $tagid);
$stmt_molasses->execute();
$result_molasses = $stmt_molasses->get_result();

if ($result_molasses->num_rows > 0) {
    $header = array('Fecha', 'Racion (Kg)', 'Costo ($/Kg)');
    $data = array();
    while ($row = $result_molasses->fetch_assoc()) {
        $data[] = array($row['ah_melaza_fecha'], $row['ah_melaza_racion'], $row['ah_melaza_costo']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No consumo de melaza encontrado', 0, 1);
    $pdf->Ln(2);
}

// Vaccination - colera
$pdf->ChapterTitle('SALUD');
$pdf->ChapterTitle('Vacunacion Colera');
$sql_colera = "SELECT ah_colera_fecha, ah_colera_producto, ah_colera_dosis FROM ah_colera WHERE ah_colera_tagid = ? ORDER BY ah_colera_fecha DESC";
$stmt_colera = $conn->prepare($sql_colera);
$stmt_colera->bind_param('s', $tagid);
$stmt_colera->execute();
$result_colera = $stmt_colera->get_result();

if ($result_colera->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_colera->fetch_assoc()) {
        $data[] = array($row['ah_colera_fecha'], $row['ah_colera_producto'], $row['ah_colera_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No vacunacion colera encontrada', 0, 1);
    $pdf->Ln(2);
}

// Vaccination - coriza
$pdf->ChapterTitle('Vacunacion Coriza');
$sql_coriza = "SELECT ah_coriza_fecha, ah_coriza_producto, ah_coriza_dosis FROM ah_coriza WHERE ah_coriza_tagid = ? ORDER BY ah_coriza_fecha DESC";
$stmt_coriza = $conn->prepare($sql_coriza);
$stmt_coriza->bind_param('s', $tagid);
$stmt_coriza->execute();
$result_coriza = $stmt_coriza->get_result();

if ($result_coriza->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_coriza->fetch_assoc()) {
        $data[] = array($row['ah_coriza_fecha'], $row['ah_coriza_producto'], $row['ah_coriza_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No vacunacion coriza encontrada', 0, 1);
    $pdf->Ln(2);
}

// Vaccination - encefalomielitis
$pdf->ChapterTitle('Vacunacion Encefalomielitis');
$sql_encefalomielitis = "SELECT ah_encefalomielitis_fecha, ah_encefalomielitis_producto, ah_encefalomielitis_dosis FROM ah_encefalomielitis WHERE ah_encefalomielitis_tagid = ? ORDER BY ah_encefalomielitis_fecha DESC";
$stmt_encefalomielitis = $conn->prepare($sql_encefalomielitis);
$stmt_encefalomielitis->bind_param('s', $tagid);
$stmt_encefalomielitis->execute();
$result_encefalomielitis = $stmt_encefalomielitis->get_result();

if ($result_encefalomielitis->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_encefalomielitis->fetch_assoc()) {
        $data[] = array($row['ah_encefalomielitis_fecha'], $row['ah_encefalomielitis_producto'], $row['ah_encefalomielitis_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No vacunacion encefalomielitis encontrada', 0, 1);
    $pdf->Ln(2);
}

// Vaccination - influenza
$pdf->ChapterTitle('Vacunacion Influenza');
$sql_influenza = "SELECT ah_influenza_fecha, ah_influenza_producto, ah_influenza_dosis FROM ah_influenza WHERE ah_influenza_tagid = ? ORDER BY ah_influenza_fecha DESC";
$stmt_influenza = $conn->prepare($sql_influenza);
$stmt_influenza->bind_param('s', $tagid);
$stmt_influenza->execute();
$result_influenza = $stmt_influenza->get_result();

if ($result_influenza->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_influenza->fetch_assoc()) {
        $data[] = array($row['ah_influenza_fecha'], $row['ah_influenza_producto'], $row['ah_influenza_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No vacunacion influenza encontrada', 0, 1);
    $pdf->Ln(2);
}

// Vaccination - marek
$pdf->ChapterTitle('Vacunacion Marek');
$sql_marek = "SELECT ah_marek_fecha, ah_marek_producto, ah_marek_dosis FROM ah_marek WHERE ah_marek_tagid = ? ORDER BY ah_marek_fecha DESC";
$stmt_marek = $conn->prepare($sql_marek);
$stmt_marek->bind_param('s', $tagid);
$stmt_marek->execute();
$result_marek = $stmt_marek->get_result();

if ($result_marek->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_marek->fetch_assoc()) {
        $data[] = array($row['ah_marek_fecha'], $row['ah_marek_producto'], $row['ah_marek_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No vacunacion marek encontrada', 0, 1);
    $pdf->Ln(2);
}

// Vaccination - newcastle
$pdf->ChapterTitle('Vacunacion Newcastle');
$sql_newcastle = "SELECT ah_newcastle_fecha, ah_newcastle_producto, ah_newcastle_dosis FROM ah_newcastle WHERE ah_newcastle_tagid = ? ORDER BY ah_newcastle_fecha DESC";
$stmt_newcastle = $conn->prepare($sql_newcastle);
$stmt_newcastle->bind_param('s', $tagid);
$stmt_newcastle->execute();
$result_newcastle = $stmt_newcastle->get_result();

if ($result_newcastle->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_newcastle->fetch_assoc()) {
        $data[] = array($row['ah_newcastle_fecha'], $row['ah_newcastle_producto'], $row['ah_newcastle_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No vacunacion newcastle encontrada', 0, 1);
    $pdf->Ln(2);
}


// Parasitos Treatment
$pdf->ChapterTitle('Tratamiento Parasitos');
$sql_para = "SELECT ah_parasitos_fecha, ah_parasitos_producto, ah_parasitos_dosis FROM ah_parasitos WHERE ah_parasitos_tagid = ? ORDER BY ah_parasitos_fecha DESC";
$stmt_para = $conn->prepare($sql_para);
$stmt_para->bind_param('s', $tagid);
$stmt_para->execute();
$result_para = $stmt_para->get_result();

if ($result_para->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_para->fetch_assoc()) {
        $data[] = array($row['ah_parasitos_fecha'], $row['ah_parasitos_producto'], $row['ah_parasitos_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No tratamiento parasitos encontrado', 0, 1);
    $pdf->Ln(2);
}

// Garrapatas Treatment
$pdf->ChapterTitle('Tratamiento Garrapatas');
$sql_tick = "SELECT ah_garrapatas_fecha, ah_garrapatas_producto, ah_garrapatas_dosis FROM ah_garrapatas WHERE ah_garrapatas_tagid = ? ORDER BY ah_garrapatas_fecha DESC";
$stmt_tick = $conn->prepare($sql_tick);
$stmt_tick->bind_param('s', $tagid);
$stmt_tick->execute();
$result_tick = $stmt_tick->get_result();

if ($result_tick->num_rows > 0) {
    $header = array('Fecha', 'Producto', 'Dosis (ml)');
    $data = array();
    while ($row = $result_tick->fetch_assoc()) {
        $data[] = array($row['ah_garrapatas_fecha'], $row['ah_garrapatas_producto'], $row['ah_garrapatas_dosis']);
    }
    $pdf->SimpleTable($header, $data);
} else {
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 5, 'No tratamiento garrapatas encontrado', 0, 1);
    $pdf->Ln(2);
}

// Generate PDF filename
$filename = 'animal_report_' . $tagid . '_' . date('Ymd') . '.pdf';
$filepath = './reports/' . $filename;

// Create directory if it doesn't exist
if (!file_exists('./reports/')) {
    mkdir('./reports/', 0777, true);
}

// Save PDF to file
$pdf->Output('F', $filepath);

// Close database connection
$conn->close();

// Redirect to sharing page
header('Location: aviar_share.php?file=' . urlencode($filename) . '&tagid=' . urlencode($tagid));
exit(); 