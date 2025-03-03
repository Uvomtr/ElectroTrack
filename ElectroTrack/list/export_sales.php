<?php
require('fpdf/fpdf.php'); // Ensure this path is correct
session_start();
include '../db_config.php';

// Redirect if user is not logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Capture start and end dates from GET parameters
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

try {
    // Build the SQL query to fetch sales data
    $query = '
        SELECT 
            s.id AS order_number, 
            i.item_name, 
            s.quantity, 
            s.total_price AS total, 
            s.created_at 
        FROM sales s
        LEFT JOIN inventory i ON s.item_id = i.id
    ';

    // Add date filtering if necessary
    $conditions = [];
    $params = [];
    if ($startDate) {
        $conditions[] = 'DATE(s.created_at) >= :startDate';
        $params[':startDate'] = $startDate;
    }
    if ($endDate) {
        $conditions[] = 'DATE(s.created_at) <= :endDate';
        $params[':endDate'] = $endDate;
    }
    if ($conditions) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    // Execute the query to fetch sales data
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total sales and total quantity sold for the filtered period
    $totalSalesQuery = '
        SELECT 
            SUM(s.total_price) AS total_sales,
            SUM(s.quantity) AS total_quantity_sold 
        FROM sales s
        LEFT JOIN inventory i ON s.item_id = i.id
    ';
    
    // Execute the total sales and total quantity query
    if ($conditions) {
        $totalSalesQuery .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $stmtTotal = $pdo->prepare($totalSalesQuery);
    $stmtTotal->execute($params);
    $totalSalesResult = $stmtTotal->fetch(PDO::FETCH_ASSOC);
    $totalSales = $totalSalesResult['total_sales'] ?? 0;
    $totalQuantitySold = $totalSalesResult['total_quantity_sold'] ?? 0;

    // Query for the top 5 best-selling products
    $topProductsQuery = '
        SELECT 
            i.item_name, 
            SUM(s.quantity) AS total_quantity_sold, 
            SUM(s.total_price) AS total_sales
        FROM sales s
        LEFT JOIN inventory i ON s.item_id = i.id
        GROUP BY i.item_name
        ORDER BY total_quantity_sold DESC
        LIMIT 5
    ';
    $stmtTopProducts = $pdo->prepare($topProductsQuery);
    $stmtTopProducts->execute($params);
    $topProducts = $stmtTopProducts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($sales)) {
        die('No sales data found for the selected period.');
    }

    // Create PDF document
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);

    // Title: "ElectroTrack"
    $pdf->Cell(190, 10, 'ElectroTrack Sales Report', 0, 1, 'C');
    $pdf->Ln(10); // Space after title

    // User who downloaded the report and the download time
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(95, 10, 'Downloaded by: ' . $_SESSION['username'], 0, 0, 'L');
    $pdf->Cell(95, 10, 'Download Time: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
    $pdf->Ln(6); // Space after user info

    // Display total sales and total quantity sold at the top of the table
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(95, 10, 'Total Sales: ' . number_format($totalSales, 2), 0, 0, 'L');
    $pdf->Cell(95, 10, 'Total Quantity Sold: ' . number_format($totalQuantitySold), 0, 1, 'R');
    $pdf->Ln(10); // Space after totals

    // Add header row for the sales data table
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 10, 'Order Number', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Item Name', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Quantity', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Total Price', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Sale Date', 1, 1, 'C'); // Newline after the header

    // Add data rows for sales data
    $pdf->SetFont('Arial', '', 9);
    foreach ($sales as $sale) {
        $pdf->Cell(40, 10, $sale['order_number'], 1, 0, 'C');
        $pdf->Cell(60, 10, $sale['item_name'] ?? 'N/A', 1, 0, 'C');
        $pdf->Cell(30, 10, $sale['quantity'] ?? 0, 1, 0, 'C');
        $pdf->Cell(30, 10, number_format($sale['total'], 2) ?? '0.00', 1, 0, 'C');
        $pdf->Cell(40, 10, $sale['created_at'], 1, 1, 'C');
    }

    // Add a section for Top 5 Best Selling Products
    $pdf->Ln(10); // Space before Top 5 section
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 10, 'Top 5 Best Selling Products', 0, 1, 'L');
    $pdf->Ln(5); // Space after the title

    // Add header row for top products
    $pdf->Cell(80, 10, 'Product Name', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Quantity Sold', 1, 0, 'C');
    $pdf->Cell(50, 10, 'Total Sales', 1, 1, 'C');
    
    // Add data rows for top products
    $pdf->SetFont('Arial', '', 9);
    foreach ($topProducts as $product) {
        $pdf->Cell(80, 10, $product['item_name'], 1, 0, 'C');
        $pdf->Cell(50, 10, $product['total_quantity_sold'], 1, 0, 'C');
        $pdf->Cell(50, 10, number_format($product['total_sales'], 2), 1, 1, 'C');
    }

    // Output the PDF as a download
    $pdf->Output('D', 'sales_report.pdf');
    exit();
} catch (PDOException $e) {
    die('Error generating PDF: ' . $e->getMessage());
}
?>
