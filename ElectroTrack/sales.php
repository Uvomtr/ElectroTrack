<?php
session_start();
include 'db_config.php'; // Include database configuration

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Default sales data
$sales = [];
$totalQuantity = 0;
$totalSales = 0;

// Fetch sales data with optional filters
try {
    $query = '
        SELECT 
            s.id AS order_number, 
            s.item_id, 
            i.item_name, 
            s.quantity, 
            s.total_price AS total, 
            s.created_at 
        FROM sales s
        LEFT JOIN inventory i ON s.item_id = i.id
    ';

    // Check for date filters
    $conditions = [];
    $params = [];
    if (isset($_GET['startDate']) && !empty($_GET['startDate'])) {
        $conditions[] = 'DATE(s.created_at) >= :startDate';
        $params[':startDate'] = $_GET['startDate'];
    }
    if (isset($_GET['endDate']) && !empty($_GET['endDate'])) {
        $conditions[] = 'DATE(s.created_at) <= :endDate';
        $params[':endDate'] = $_GET['endDate'];
    }

    if ($conditions) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total quantity and sales
    foreach ($sales as $sale) {
        $totalQuantity += $sale['quantity'];
        $totalSales += $sale['total'];
    }
} catch (PDOException $e) {
    $salesError = 'Error fetching sales data: ' . htmlspecialchars($e->getMessage());
}

// Handle AJAX requests for filtered data
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    header('Content-Type: application/json');
    echo json_encode([
        'sales' => $sales,
        'totalQuantity' => $totalQuantity,
        'totalSales' => $totalSales
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History</title>
    <link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
 <style>
         body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Sidebar Styles */
        .sidebar {
            height: 100%;
            width: 300px;
            position: fixed;
            z-index: 1;
            top: 0;
            left: 0;
            background-color: #1a396e;
            padding-top: 20px;
            transition: width 0.3s ease;
            overflow-x: hidden;
            padding: 20px 0;
        }

        .logout-link {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            text-align: center;
            padding: 12px 0;
            background-color: #1a396e;
            color: #FAFBFF;
            font-size: 18px;
            border-radius: 5px;
            transition: background-color 0.3s, color 0.3s;
            text-decoration: none;
        }

        .logout-link:hover {
            background-color: #E23C51;
            color: #FAFBFF;
            padding-left: 0;
        }

        .sidebar h2 {
            color: #fff;
            font-size: 24px;
            text-align: center;
            margin-bottom: 20px;
        }

        .sidebar a {
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            color: #FAFBFF;
            font-size: 18px;
            border-radius: 5px;
            margin: 10px 0;
            transition: background-color 0.3s, color 0.3s, padding-left 0.3s;
        }

        .sidebar a:hover {
            background-color: #E23C51;
            padding-left: 25px;
            color: #FAFBFF;
        }

        /* Content Styles */
        .content {
            margin-left: 300px;
            padding: 30px;
            background-color: #fff;
            transition: margin-left 0.3s ease;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            margin-top: 20px;
        }

        .header h1 {
            color: #031124;
            font-size: 65px;
            font-weight: bold;
            margin: 0;
        }

        .date {
            color: #031124;
            font-size: 18px;
            text-align: right;
            margin-left: auto;
            margin-right: 50px;
        }
		
}

.filter-button {
    margin-top: 20px; /* Add space between date and filter button */
    align-self: flex-start; /* Align button to the left */
}


        .table {
            margin-top: 20px;
            background-color: #FAFBFF;
        }

        .table th {
            background-color: #EEE347;
            color: #031124;
            text-align: center;
        }

        .table td {
            text-align: center;
        }

        .totals {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 5px;
            text-align: center;
        }

        .totals p {
            font-size: 18px;
            margin: 5px 0;
        }

        .totals strong {
            color: #031124;
        }
		

        /* Hamburger menu styles */
        .hamburger {
            display: none;
            font-size: 30px;
            cursor: pointer;
            color: #031124;
            padding: 10px;
            margin-left: 20px;
        }

        @media (max-width: 750px) {
            .sidebar {
                width: 0;
                padding: 0;
                transition: 0.3s;
            }

            .content {
                margin-left: 0;
                transition: 0.3s;
            }

            .hamburger {
                display: block;
            }
        }

        /* Circle photo and mini circle photo */
        .mini-circle-photo {
            width: 35px;
            height: 35px;
            background-color: transparent;
            border-radius: 50%;
            margin-right: 20px; /* Spacing between the circle and the text */
            margin-left: 20px;
        }

        .circle-photo {
            width: 100px;
            height: 100px;
            background-color: #d9d9d9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FAFBFF;
            font-size: 20px;
            margin: 20px auto;
            text-align: center;
        }
		    /* Right sidebar for filter */
       
    </style>
</head>
<body>
    <div class="hamburger" onclick="toggleSidebar()">&#9776;</div>

    <div class="sidebar" id="sidebar">
        <div class="circle-photo">
            <img src="Assets/ADMIN.png" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> <!-- Circular photo placeholder -->
         <h2>Admin</h2>
        
        <!-- Add circle placeholder beside each link -->
        <a href="index.php">
            <div class="mini-circle-photo">
                <img src="Assets/HOME.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
            </div> Home
        </a>
        <a href="inventory.php">
            <div class="mini-circle-photo">
                <img src="Assets/INVENTORY.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
            </div> Manage Inventory
        </a>
        <a href="pos.php">
            <div class="mini-circle-photo">
                <img src="Assets/POS.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
            </div> Point of Sale
        </a>
        <a href="sales.php">
            <div class="mini-circle-photo">
                <img src="Assets/SALES HISTORY.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
            </div> Sales History
        </a>
        <a href="delete.php">
            <div class="mini-circle-photo">
                <img src="Assets/DELETE.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
            </div> Delete Item
        </a>
        <a href="logout.php" class="logout-link">
            <div class="mini-circle-photo">
                <img src="Assets/LOGOUT.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
            </div> Logout
        </a>
    </div>
  <div class="content">
        <div class="header">
            <h1>Sales History</h1>
            <div class="date-time" id="dateTime"></div>
        </div>

        <!-- Filter button and Export button -->
        <button class="btn btn-primary filter-button" data-toggle="modal" data-target="#filterModal">Filter Sales</button>
        <button class="btn btn-success" onclick="exportSales()">Export Sales Report</button>

        <?php if (isset($salesError)): ?>
            <div class="alert alert-danger"><?php echo $salesError; ?></div>
        <?php endif; ?>

        <table class="table" id="salesTable">
            <thead>
                <tr>
                    <th>Order#</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Total Price</th>
                    <th>Sale Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($sale['order_number'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($sale['item_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($sale['quantity'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($sale['total'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($sale['created_at'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Total Quantity and Total Sales -->
        <div class="totals">
            <p><strong>Total Quantity Sold:</strong> <?php echo number_format($totalQuantity); ?></p>
            <p><strong>Total Sales:</strong> ₱<?php echo number_format($totalSales, 2); ?></p>
        </div>
    </div>

    <!-- Modal for Date Filter -->
    <div class="modal fade" id="filterModal" tabindex="-1" role="dialog" aria-labelledby="filterModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filterModalLabel">Filter Sales by Date</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="date" id="startDate" class="form-control mb-2" placeholder="Start Date">
                    <input type="date" id="endDate" class="form-control mb-2" placeholder="End Date">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="resetFilter()">Reset</button>
                    <button type="button" class="btn btn-primary" onclick="filterSales()">Apply Filter</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.0.5/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        // Function to update date and time in real-time
        function updateDateTime() {
            var now = new Date();
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric', second: 'numeric', hour12: true };
            var dateTime = now.toLocaleString('en-US', options);
            document.getElementById("dateTime").textContent = dateTime;
        }

        // Update date and time every second
        setInterval(updateDateTime, 1000);
        updateDateTime(); // Initialize immediately

        // Function to reset filter inputs
        function resetFilter() {
            document.getElementById("startDate").value = '';
            document.getElementById("endDate").value = '';
        }

        // Function to filter sales based on date range
        function filterSales() {
            var startDate = document.getElementById("startDate").value;
            var endDate = document.getElementById("endDate").value;

            // Make AJAX request to fetch filtered data
            var xhr = new XMLHttpRequest();
            xhr.open('GET', `sales.php?ajax=true&startDate=${startDate}&endDate=${endDate}`, true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    updateSalesTable(response.sales);
                    updateTotals(response.totalQuantity, response.totalSales);
                    $('#filterModal').modal('hide'); // Close the modal after filtering
                } else {
                    console.error('Error fetching filtered sales data');
                }
            };
            xhr.send();
        }

        // Function to update the sales table with filtered data
        function updateSalesTable(salesData) {
            var tableBody = document.querySelector('#salesTable tbody');
            tableBody.innerHTML = ''; // Clear existing table rows

            if (salesData.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5">No sales found for the selected date range.</td></tr>';
                return;
            }

            salesData.forEach(function (sale) {
                var row = document.createElement('tr');
                row.innerHTML = `
                    <td>${sale.order_number}</td>
                    <td>${sale.item_name || 'N/A'}</td>
                    <td>${sale.quantity || 'N/A'}</td>
                    <td>${sale.total || 'N/A'}</td>
                    <td>${sale.created_at || 'N/A'}</td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Function to update the totals
        function updateTotals(totalQuantity, totalSales) {
            document.querySelector('.totals').innerHTML = `
                <p><strong>Total Quantity Sold:</strong> ${totalQuantity.toLocaleString()}</p>
                <p><strong>Total Sales:</strong> ₱${totalSales.toLocaleString()}</p>
            `;
        }

        // Function to export sales data
        function exportSales() {
            window.location.href = 'list/export_sales.php'; // Assume this script handles export logic
        }
    </script>
</body>
</html>