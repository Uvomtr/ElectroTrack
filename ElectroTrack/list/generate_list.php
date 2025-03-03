<?php
session_start();  // Start the session
include '../db_config.php';  // Include database connection

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');  // Redirect to login page if not logged in
    exit();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_list'])) {

    // SQL to fetch all inventory items
    $sql = "SELECT item_name, quantity, price, category, date_added FROM inventory ORDER BY item_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($items) > 0) {
        // Prepare the table content to display on the same page
        $tableContent = "<h2>ElectroTrack Product List</h2>";
        $tableContent .= "<table border='1'>";
        $tableContent .= "<thead>";
        $tableContent .= "<tr><th>Item Name</th><th>Quantity</th><th>Price</th><th>Category</th><th>Date Added</th></tr>";
        $tableContent .= "</thead><tbody>";

        foreach ($items as $item) {
            $tableContent .= "<tr>";
            $tableContent .= "<td>" . htmlspecialchars($item['item_name']) . "</td>";
            $tableContent .= "<td>" . htmlspecialchars($item['quantity']) . "</td>";
            $tableContent .= "<td>" . htmlspecialchars($item['price']) . "</td>";
            $tableContent .= "<td>" . htmlspecialchars($item['category']) . "</td>";
            $tableContent .= "<td>" . date('d-m-Y H:i:s', strtotime($item['date_added'])) . "</td>";  // Formatting the date
            $tableContent .= "</tr>";
        }

        $tableContent .= "</tbody></table>";

        // Output the table content directly to the page
        echo $tableContent;
        // Provide a CSV download option below the table
        echo "<form action=\"generate_csv.php\" method=\"POST\">";
        echo "<button type=\"submit\" name=\"download_csv\">Download CSV</button>";
        echo "</form>";
    } else {
        echo "<p>No products found in the inventory.</p>";
    }
}
?>
