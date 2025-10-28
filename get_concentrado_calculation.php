<?php
// Shared function for consistent concentrado expense calculation across all charts
function calculateConcentradoExpenses($conn) {
    // Step 1: Get all concentrado records for all animals, ordered by tagid and date
    $query = "SELECT 
                ah_concentrado_tagid,
                ah_concentrado_fecha,
                ah_concentrado_racion,
                ah_concentrado_costo
              FROM ah_concentrado
              WHERE 
                ah_concentrado_fecha IS NOT NULL AND 
                ah_concentrado_fecha != '0000-00-00' AND
                ah_concentrado_racion IS NOT NULL AND
                ah_concentrado_costo IS NOT NULL AND
                ah_concentrado_racion > 0 AND
                ah_concentrado_costo > 0
              ORDER BY ah_concentrado_tagid, ah_concentrado_fecha ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        return [];
    }
    
    // Step 2: Group records by animal
    $animalRecords = [];
    foreach ($records as $record) {
        $tagid = $record['ah_concentrado_tagid'];
        if (!isset($animalRecords[$tagid])) {
            $animalRecords[$tagid] = [];
        }
        $animalRecords[$tagid][] = $record;
    }
    
    // Step 3: Calculate monthly expenses using period-based logic
    $monthlyExpenses = [];
    
    foreach ($animalRecords as $tagid => $records) {
        for ($i = 0; $i < count($records) - 1; $i++) {
            $currentRecord = $records[$i];
            $nextRecord = $records[$i + 1];
            
            $startDate = new DateTime($currentRecord['ah_concentrado_fecha']);
            $endDate = new DateTime($nextRecord['ah_concentrado_fecha']);
            
            $daysDiff = $startDate->diff($endDate)->days;
            if ($daysDiff <= 0) continue;
            
            $dailyExpense = (float)$currentRecord['ah_concentrado_racion'] * (float)$currentRecord['ah_concentrado_costo'];
            $periodExpense = $dailyExpense * $daysDiff;
            
            // Distribute period expense across months
            $periodStart = clone $startDate;
            $periodEnd = clone $endDate;
            
            while ($periodStart < $periodEnd) {
                $monthKey = $periodStart->format('Y-m');
                $monthEnd = new DateTime($periodStart->format('Y-m-t'));
                
                $monthPeriodEnd = ($monthEnd < $periodEnd) ? $monthEnd : clone $periodEnd;
                $daysInMonth = $periodStart->diff($monthPeriodEnd)->days + 1;
                
                if ($daysInMonth > 0) {
                    $monthExpense = ($daysInMonth / $daysDiff) * $periodExpense;
                    
                    if (!isset($monthlyExpenses[$monthKey])) {
                        $monthlyExpenses[$monthKey] = 0;
                    }
                    $monthlyExpenses[$monthKey] += $monthExpense;
                }
                
                $periodStart = clone $monthEnd;
                $periodStart->add(new DateInterval('P1D'));
            }
        }
    }
    
    return $monthlyExpenses;
}
?>