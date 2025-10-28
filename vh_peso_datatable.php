<?php
require_once './pdo_conexion.php';  // Include connection file
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registros de Peso (Vacuno)</title>
<!-- Link to the Favicon -->
<link rel="icon" href="images/Ganagram_icono.ico" type="image/x-icon">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- Bootstrap 5 -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery Core (main library) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.dataTables.min.css">

<!-- DataTables JavaScript -->
<script type="text/javascript" src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>

<!-- DataTables Buttons CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

<!-- DataTables Buttons JS -->
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center mb-4">Registros de Peso - Vacuno</h2>
    
    <!-- Search Form -->
    <form method="GET" class="text-center mb-4">
        <div class="input-group text-center">
            <input type="text" id="search" name="search" placeholder="Buscar por Tag ID..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            <button type="submit" class="btn btn-success">Buscar</button>
        </div>
    </form>

    <!-- DataTable for vh_peso records -->
    <div class="table-responsive">
        <table id="vh_pesoTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tag ID</th>
                    <th>Peso (kg)</th>
                    <th>Precio ($/kg)</th>
                    <th>Valor Total ($)</th>
                    <th>Fecha</th>
                    <th>Nombre Animal</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Build the query based on search parameter
                    $pesoQuery = "SELECT p.*, v.nombre as animal_nombre,
                                 CAST((p.vh_peso_animal * p.vh_peso_precio) AS DECIMAL(10,2)) as total_value
                                 FROM vh_peso p 
                                 LEFT JOIN vacuno v ON p.vh_peso_tagid = v.tagid";
                                 
                    // Add search condition if provided
                    if (isset($_GET['search']) && !empty($_GET['search'])) {
                        $pesoQuery .= " WHERE p.vh_peso_tagid = :tagid";
                        $tagid = $_GET['search'];
                    }
                    
                    $pesoQuery .= " ORDER BY p.vh_peso_fecha DESC";
                    
                    $stmt = $conn->prepare($pesoQuery);
                    
                    // Bind parameters if search is provided
                    if (isset($tagid)) {
                        $stmt->bindParam(':tagid', $tagid, PDO::PARAM_STR);
                    }
                    
                    $stmt->execute();
                    $stmt->setFetchMode(PDO::FETCH_ASSOC);
                    $pesosData = $stmt->fetchAll();
                    
                    foreach ($pesosData as $row) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['vh_peso_tagid']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['vh_peso_animal']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['vh_peso_precio']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['total_value']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['vh_peso_fecha']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['animal_nombre'] ?? 'N/A') . "</td>";
                        echo "</tr>";
                    }
                } catch (PDOException $e) {
                    error_log("Error in peso table: " . $e->getMessage());
                    echo "<tr><td colspan='7' class='text-center'>Error al cargar los datos: " . $e->getMessage() . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Link back to main page -->
    <div class="mt-4 text-center">
        <a href="vacuno_historial.php" class="btn btn-primary">Volver a Historial</a>
    </div>
</div>

<!-- Initialize DataTable for VH Peso -->
<script>
$(document).ready(function() {
    $('#vh_pesoTable').DataTable({
        // Set initial page length
        pageLength: 10,
        
        // Configure length menu options
        lengthMenu: [
            [5, 10, 25, 50, 100, -1],
            [5, 10, 25, 50, 100, "Todos"]
        ],
        
        // Order by fecha (date) column descending
        order: [[5, 'desc']],
        
        // Spanish language
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
            lengthMenu: "Mostrar _MENU_ registros por página",
            zeroRecords: "No se encontraron resultados",
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 a 0 de 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros totales)",
            search: "Buscar:",
            paginate: {
                first: "Primero",
                last: "Último",
                next: "Siguiente",
                previous: "Anterior"
            }
        },
        
        // Enable responsive features
        responsive: true,
        
        // Configure DOM layout and buttons
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12 col-md-6"l>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        
        buttons: [
            {
                extend: 'collection',
                text: 'Exportar',
                buttons: [
                    'copy',
                    'excel',
                    'csv',
                    'pdf',
                    'print'
                ]
            }
        ],
        
        // Column specific settings
        columnDefs: [
            {
                targets: [2, 3, 4], // Peso, Precio, Valor Total columns
                render: function(data, type, row) {
                    if (type === 'display') {
                        return parseFloat(data).toLocaleString('es-ES', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    return data;
                }
            },
            {
                targets: [5], // Fecha column
                render: function(data, type, row) {
                    if (type === 'display') {
                        return new Date(data).toLocaleDateString('es-ES');
                    }
                    return data;
                }
            }
        ]
    });
});
</script>

</body>
</html> 