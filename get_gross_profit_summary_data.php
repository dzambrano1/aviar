<?php
require_once './pdo_conexion.php';

header('Content-Type: application/json');

try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Get total egg revenue
    $eggQuery = "SELECT SUM(ah_huevo_cantidad * ah_huevo_precio) AS total_egg_revenue
                 FROM ah_huevo
                 WHERE ah_huevo_fecha IS NOT NULL AND ah_huevo_fecha != '0000-00-00'
                   AND ah_huevo_cantidad IS NOT NULL AND ah_huevo_precio IS NOT NULL";
    $eggStmt = $conn->prepare($eggQuery);
    $eggStmt->execute();
    $eggResult = $eggStmt->fetch();
    $totalEggRevenue = (float)($eggResult['total_egg_revenue'] ?? 0);

    // 2. Get total broiler revenue using same logic as get_revenue_pollos_engorde_data.php
    $broilerQuery = "SELECT SUM(max_individual_value) AS total_broiler_revenue
                     FROM (
                       SELECT
                           DATE_FORMAT(p.ah_peso_fecha, '%Y-%m') AS month,
                           p.ah_peso_tagid AS animal_tagid,
                           MAX((p.ah_peso_animal/1000) * p.ah_peso_precio * a.poblacion) AS max_individual_value
                       FROM ah_peso p
                       INNER JOIN aviar a ON p.ah_peso_tagid = a.tagid
                       WHERE
                           a.genero = 'Macho' AND
                           p.ah_peso_fecha IS NOT NULL AND
                           p.ah_peso_fecha != '0000-00-00' AND
                           p.ah_peso_animal IS NOT NULL AND
                           p.ah_peso_precio IS NOT NULL AND
                           a.poblacion IS NOT NULL AND
                           p.ah_peso_animal > 0 AND
                           p.ah_peso_precio > 0 AND
                           a.poblacion > 0
                       GROUP BY DATE_FORMAT(p.ah_peso_fecha, '%Y-%m'), p.ah_peso_tagid
                     ) AS individual_maxes";
    $broilerStmt = $conn->prepare($broilerQuery);
    $broilerStmt->execute();
    $broilerResult = $broilerStmt->fetch();
    $totalBroilerRevenue = (float)($broilerResult['total_broiler_revenue'] ?? 0);

    // 3. Calculate total farm income
    $totalFarmIncome = $totalEggRevenue + $totalBroilerRevenue;

    // 4. Get total variable costs using same logic as get_variable_costs_data.php
    
    // Get total concentrado expenses using new date columns
    $concentradoQuery = "SELECT SUM(
                            DATEDIFF(ah_concentrado_fecha_fin, ah_concentrado_fecha_inicio) *
                            ah_concentrado_racion/1000 *
                            ah_concentrado_costo
                        ) AS total_cost
                        FROM ah_concentrado
                        WHERE
                          ah_concentrado_fecha_inicio IS NOT NULL AND
                          ah_concentrado_fecha_inicio != '0000-00-00' AND
                          ah_concentrado_fecha_fin IS NOT NULL AND
                          ah_concentrado_fecha_fin != '0000-00-00' AND
                          ah_concentrado_racion IS NOT NULL AND
                          ah_concentrado_costo IS NOT NULL AND
                          ah_concentrado_racion > 0 AND
                          ah_concentrado_costo > 0 AND
                          ah_concentrado_fecha_fin >= ah_concentrado_fecha_inicio";
    $concentradoStmt = $conn->prepare($concentradoQuery);
    $concentradoStmt->execute();
    $concentradoResult = $concentradoStmt->fetch();
    $totalConcentradoCost = (float)($concentradoResult['total_cost'] ?? 0);

    // Get total melaza expenses using new date columns
    $melazaQuery = "SELECT SUM(
                            DATEDIFF(ah_melaza_fecha_fin, ah_melaza_fecha_inicio) *
                            ah_melaza_racion *
                            ah_melaza_costo
                        ) AS total_cost
                        FROM ah_melaza
                        WHERE
                          ah_melaza_fecha_inicio IS NOT NULL AND
                          ah_melaza_fecha_inicio != '0000-00-00' AND
                          ah_melaza_fecha_fin IS NOT NULL AND
                          ah_melaza_fecha_fin != '0000-00-00' AND
                          ah_melaza_racion IS NOT NULL AND
                          ah_melaza_costo IS NOT NULL AND
                          ah_melaza_racion > 0 AND
                          ah_melaza_costo > 0 AND
                          ah_melaza_fecha_fin >= ah_melaza_fecha_inicio";
    $melazaStmt = $conn->prepare($melazaQuery);
    $melazaStmt->execute();
    $melazaResult = $melazaStmt->fetch();
    $totalMelazaCost = (float)($melazaResult['total_cost'] ?? 0);

    // Get total sal expenses using new date columns
    $salQuery = "SELECT SUM(
                            DATEDIFF(ah_sal_fecha_fin, ah_sal_fecha_inicio) *
                            ah_sal_racion *
                            ah_sal_costo
                        ) AS total_cost
                        FROM ah_sal
                        WHERE
                          ah_sal_fecha_inicio IS NOT NULL AND
                          ah_sal_fecha_inicio != '0000-00-00' AND
                          ah_sal_fecha_fin IS NOT NULL AND
                          ah_sal_fecha_fin != '0000-00-00' AND
                          ah_sal_racion IS NOT NULL AND
                          ah_sal_costo IS NOT NULL AND
                          ah_sal_racion > 0 AND
                          ah_sal_costo > 0 AND
                          ah_sal_fecha_fin >= ah_sal_fecha_inicio";
    $salStmt = $conn->prepare($salQuery);
    $salStmt->execute();
    $salResult = $salStmt->fetch();
    $totalSalCost = (float)($salResult['total_cost'] ?? 0);

    // Get total vaccine costs
    $vaccineQueries = [
        "colera" => "SELECT SUM(COALESCE(ah_colera_costo, 0)) AS total_cost FROM ah_colera WHERE ah_colera_fecha IS NOT NULL AND ah_colera_costo IS NOT NULL AND ah_colera_costo > 0",
        "coriza" => "SELECT SUM(COALESCE(ah_coriza_costo, 0)) AS total_cost FROM ah_coriza WHERE ah_coriza_fecha IS NOT NULL AND ah_coriza_costo IS NOT NULL AND ah_coriza_costo > 0",
        "corona_virus" => "SELECT SUM(COALESCE(ah_corona_virus_costo, 0)) AS total_cost FROM ah_corona_virus WHERE ah_corona_virus_fecha IS NOT NULL AND ah_corona_virus_costo IS NOT NULL AND ah_corona_virus_costo > 0",
        "encefalomielitis" => "SELECT SUM(COALESCE(ah_encefalomielitis_costo, 0)) AS total_cost FROM ah_encefalomielitis WHERE ah_encefalomielitis_fecha IS NOT NULL AND ah_encefalomielitis_costo IS NOT NULL AND ah_encefalomielitis_costo > 0",
        "influenza" => "SELECT SUM(COALESCE(ah_influenza_costo, 0)) AS total_cost FROM ah_influenza WHERE ah_influenza_fecha IS NOT NULL AND ah_influenza_costo IS NOT NULL AND ah_influenza_costo > 0",
        "marek" => "SELECT SUM(COALESCE(ah_marek_costo, 0)) AS total_cost FROM ah_marek WHERE ah_marek_fecha IS NOT NULL AND ah_marek_costo IS NOT NULL AND ah_marek_costo > 0",
        "newcastle" => "SELECT SUM(COALESCE(ah_newcastle_costo, 0)) AS total_cost FROM ah_newcastle WHERE ah_newcastle_fecha IS NOT NULL AND ah_newcastle_costo IS NOT NULL AND ah_newcastle_costo > 0",
        "viruela" => "SELECT SUM(COALESCE(ah_viruela_costo, 0)) AS total_cost FROM ah_viruela WHERE ah_viruela_fecha IS NOT NULL AND ah_viruela_costo IS NOT NULL AND ah_viruela_costo > 0",
        "garrapatas" => "SELECT SUM(COALESCE(ah_garrapatas_costo, 0)) AS total_cost FROM ah_garrapatas WHERE ah_garrapatas_fecha IS NOT NULL AND ah_garrapatas_costo IS NOT NULL AND ah_garrapatas_costo > 0",
        "parasitos" => "SELECT SUM(COALESCE(ah_parasitos_costo, 0)) AS total_cost FROM ah_parasitos WHERE ah_parasitos_fecha IS NOT NULL AND ah_parasitos_costo IS NOT NULL AND ah_parasitos_costo > 0"
    ];

    $totalVaccineCost = 0;
    foreach ($vaccineQueries as $vaccineType => $query) {
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch();
            $cost = (float)($result['total_cost'] ?? 0);
            if ($cost > 0) {
                $totalVaccineCost += $cost;
            }
        } catch (PDOException $e) {
            error_log("Table ah_$vaccineType might not exist: " . $e->getMessage());
        }
    }

    // 5. Calculate total variable costs
    $totalVariableCosts = $totalConcentradoCost + $totalMelazaCost + $totalSalCost + $totalVaccineCost;

    // 6. Calculate gross profit
    $grossProfit = $totalFarmIncome - $totalVariableCosts;

    // 7. Prepare bar chart data
    $chartData = [
        [
            'category' => 'Ingresos Totales',
            'value' => round($totalFarmIncome, 2),
            'type' => 'income',
            'details' => [
                'huevos' => round($totalEggRevenue, 2),
                'pollos_engorde' => round($totalBroilerRevenue, 2)
            ]
        ],
        [
            'category' => 'Gastos Totales',
            'value' => round($totalVariableCosts, 2),
            'type' => 'expense',
            'details' => [
                'concentrado' => round($totalConcentradoCost, 2),
                'melaza' => round($totalMelazaCost, 2),
                'sal' => round($totalSalCost, 2),
                'vacunas' => round($totalVaccineCost, 2)
            ]
        ],
        [
            'category' => 'Ganancia Bruta',
            'value' => round($grossProfit, 2),
            'type' => $grossProfit >= 0 ? 'profit' : 'loss',
            'details' => [
                'margen_porcentaje' => $totalFarmIncome > 0 ? round(($grossProfit / $totalFarmIncome) * 100, 1) : 0
            ]
        ]
    ];

    $summary = [
        'total_income' => round($totalFarmIncome, 2),
        'total_expenses' => round($totalVariableCosts, 2),
        'gross_profit' => round($grossProfit, 2),
        'profit_margin_percentage' => $totalFarmIncome > 0 ? round(($grossProfit / $totalFarmIncome) * 100, 1) : 0,
        'expense_ratio' => $totalFarmIncome > 0 ? round(($totalVariableCosts / $totalFarmIncome) * 100, 1) : 0
    ];

    echo json_encode([
        'data' => $chartData,
        'summary' => $summary
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    error_log('Error in get_gross_profit_summary_data.php: ' . $e->getMessage());
}
?>