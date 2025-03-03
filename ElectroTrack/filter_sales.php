<?php
include 'db_config.php';

if (isset($_GET['start']) && isset($_GET['end'])) {
    $startDate = $_GET['start'];
    $endDate = $_GET['end'];

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) AS total 
        FROM (
            SELECT created_at, total FROM customer_purchase WHERE created_at BETWEEN ? AND ?
            UNION ALL
            SELECT created_at, total_price AS total FROM sales WHERE created_at BETWEEN ? AND ?
        ) AS combined_sales
    ");

    $stmt->execute([$startDate, $endDate, $startDate, $endDate]);
    $totalSales = $stmt->fetchColumn();

    echo json_encode(['total_sales' => number_format($totalSales, 2)]);
} else {
    echo json_encode(['error' => 'Invalid date range']);
}
?>
