<?php
// Include the database connection
include '../db_config.php';  // Adjust the path if needed

// SQL query to get all items in inventory
$sql = "SELECT item_name, quantity, price, category, date_added FROM inventory";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers to download CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="product_list.csv"');

// Open PHP output stream as a file
$output = fopen('php://output', 'w');

// Write CSV header row
fputcsv($output, ['Item Name', 'Quantity', 'Price', 'Category', 'Date Added']);

// Write each item to the CSV file
foreach ($items as $item) {
    fputcsv($output, $item);
}

// Close the output stream
fclose($output);
exit();
?>
