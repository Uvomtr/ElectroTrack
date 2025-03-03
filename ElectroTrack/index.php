<?php
session_start();
include 'db_config.php'; // Ensure this points to your database connection file

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Fetch inventory items
$items = $pdo->query("SELECT * FROM inventory")->fetchAll(PDO::FETCH_ASSOC);

// Query to get total quantity
$stmt = $pdo->query("SELECT SUM(quantity) AS total_quantity FROM inventory");
$totalQuantity = $stmt->fetchColumn();

// Query to get total sales from the sales table only
$stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) AS total FROM sales");
$totalSales = $stmt->fetchColumn();



// Query to get available products
$stmt = $pdo->query("SELECT COUNT(*) AS available_products FROM inventory WHERE quantity > 0");
$availableProducts = $stmt->fetchColumn();

// Daily Sales (last 7 days)
$dailySalesQuery = $pdo->query("
    SELECT DATE(created_at) AS date, SUM(total) AS total_sales 
    FROM (
        SELECT created_at, total FROM customer_purchase 
        WHERE created_at >= CURDATE() - INTERVAL 7 DAY
        UNION ALL
        SELECT created_at, total_price AS total FROM sales 
        WHERE created_at >= CURDATE() - INTERVAL 7 DAY
    ) AS combined_sales
    GROUP BY DATE(created_at)
");
$dailySalesData = $dailySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Weekly Sales (last 4 weeks)
$weeklySalesQuery = $pdo->query("
    SELECT WEEK(created_at) AS week, SUM(total) AS total_sales 
    FROM (
        SELECT created_at, total FROM customer_purchase 
        WHERE created_at >= CURDATE() - INTERVAL 28 DAY
        UNION ALL
        SELECT created_at, total_price AS total FROM sales 
        WHERE created_at >= CURDATE() - INTERVAL 28 DAY
    ) AS combined_sales
    GROUP BY WEEK(created_at)
");
$weeklySalesData = $weeklySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Monthly Sales (last 12 months)
$monthlySalesQuery = $pdo->query("
    SELECT MONTHNAME(created_at) AS month, SUM(total) AS total_sales 
    FROM (
        SELECT created_at, total FROM customer_purchase 
        WHERE created_at >= CURDATE() - INTERVAL 1 YEAR
        UNION ALL
        SELECT created_at, total_price AS total FROM sales 
        WHERE created_at >= CURDATE() - INTERVAL 1 YEAR
    ) AS combined_sales
    GROUP BY MONTH(created_at)
");
$monthlySalesData = $monthlySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Yearly Sales (last 5 years)
$yearlySalesQuery = $pdo->query("
    SELECT YEAR(created_at) AS year, SUM(total) AS total_sales 
    FROM (
        SELECT created_at, total FROM customer_purchase 
        WHERE created_at >= CURDATE() - INTERVAL 5 YEAR
        UNION ALL
        SELECT created_at, total_price AS total FROM sales 
        WHERE created_at >= CURDATE() - INTERVAL 5 YEAR
    ) AS combined_sales
    GROUP BY YEAR(created_at)
");
$yearlySalesData = $yearlySalesQuery->fetchAll(PDO::FETCH_ASSOC);

// Pass the data to JavaScript using json_encode()
$dailySalesJSON = json_encode($dailySalesData);
$weeklySalesJSON = json_encode($weeklySalesData);
$monthlySalesJSON = json_encode($monthlySalesData);
$yearlySalesJSON = json_encode($yearlySalesData);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Main Menu</title>
	<link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> <!-- Include Chart.js -->
    
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }

        /* --- Sidebar Styles --- */
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
            font-family: 'Poppins', sans-serif;
            border-radius: 5px;
            margin: 10px 0;
            transition: background-color 0.3s, color 0.3s, padding-left 0.3s;
        }

        .sidebar a:hover {
            background-color: #E23C51;
            padding-left: 25px;
            color: #FAFBFF;
        }
        /* --- Logout Styles --- */
        .logout-link {
            position: absolute;
            bottom: 20px; /* Position it 20px from the bottom */
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

        /* --- Dashboard Styles --- */
         .content {
            margin-left: 300px;
            padding: 20px;
            background-color: #fff;
            transition: margin-left 0.3s ease;
        }
        .dashboard-container {
            width: 90%;
            padding: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 50px;
            margin-left: 30px;
        }
        .dashboard {
            font-size: px;
            font-family: Poppins;
            font-weight: bold;
            color: #031124;
            margin: 0;
        }

       .date {
            color: #031124;
            font-size: 18px;
            text-align: right;
            margin-left: auto;
            margin-right: 50px;
        }

        /* --- Summary Styles --- */
        .summary-container {
            background-color: #F2F3F7;
            border-radius: 0px;
            padding: 0px;
            width: calc(100% - 70px);
            margin: 30px auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .summary {
            background-color: #E2E3E7;
            font-size: 40px;
            font-weight: 600;
            color: #031124;
            font-family: Poppins;
            text-align: center;
            margin-top: -1vh;
            margin-bottom: 10px;
        }
        .summary-items {
            display: flex;
            justify-content: space-evenly;
            padding: 25px;
            margin: 10px;
            margin-bottom: 10px;
        }
        .summary-item {
            background-color: #D71445;
            color: #FAFBFF;
            border-radius: 10px;
            padding: 30px;
            width: 30%;
            text-align: center;
            font-size: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .summary-value {
            font-size: 45px;
        }

        /* --- Hamburger Menu Styles --- */
         .hamburger {
            display: none;
            font-size: 30px;
            cursor: pointer;
            color: #031124;
            padding: 10px;
            margin-left: 20px; /* Adjust margin as needed */
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

        /* Graph container and buttons */
        .graph-container {
            background-color: #F2F3F7;
            border-radius: 0px;
            padding: 20px;
            width: calc(100% - 70px);
            margin: 30px auto;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-buttons {
            text-align: center;
            margin-bottom: 20px;
        }

        .chart-buttons button {
            background-color: #D71445;
            color: white;
            border: none;
            padding: 10px 20px;
            margin: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .chart-buttons button:hover {
            background-color: #E23C51;
        }

        #salesChart {
            max-width: 100%;
            height: 400px;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="hamburger" onclick="toggleSidebar()">&#9776;</div>

<div class="sidebar" id="sidebar">
    <div class="circle-photo">
        <img src="Assets/ADMIN.png" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%;">
    </div> <!-- Circular photo placeholder -->
   <h2>Admin</h2>
    
     <!-- Add circle placeholder beside each link -->
    <a href="index.php">
        <div class="mini-circle-photo">
        <img src="Assets/HOME.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Home
    </a>
    <a href="inventory.php">
        <div class="mini-circle-photo">
        <img src="Assets/INVENTORY.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Manage Inventory
    </a>
    <a href="pos.php">
        <div class="mini-circle-photo">
        <img src="Assets/POS.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Point of Sale
    </a>
    <a href="sales.php">
        <div class="mini-circle-photo">
        <img src="Assets/SALES HISTORY.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Sales History
    </a>
    <a href="delete.php">
        <div class="mini-circle-photo">
        <img src="Assets/DELETE.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Delete Item
    </a>
    <a href="logout.php" class="logout-link">
        <div class="mini-circle-photo">
        <img src="Assets/LOGOUT.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Logout
    </a>
</div>

<!-- Content -->
<div class="content">
    <div class="dashboard-container">
        <h1 class="dashboard">Dashboard</h1>
        <div class="date" id="dateTime"></div>
    </div>

    <div class="summary-items">
  <div class="summary-item" onclick="window.location.href='list/generate_list.php';">
    <div class="summary-value"><?php echo number_format($totalQuantity); ?></div>
    <div>Total Quantity</div>
</div>
    <div class="summary-item" onclick="window.location.href='list/export_sales.php';">
        <div class="summary-value">₱<?php echo number_format($totalSales, 2); ?></div>
        <div>Total Sales</div>
    </div>
    <div class="summary-item" onclick="window.location.href='list/generate_csv.php';">
        <div class="summary-value"><?php echo number_format($availableProducts); ?></div>
        <div>Available Products</div>
    </div>
</div>

    <!-- Graph container -->
    <div class="graph-container">
        <div class="chart-buttons">
            <button onclick="updateChart('daily')">Daily</button>
            <button onclick="updateChart('weekly')">Weekly</button>
            <button onclick="updateChart('monthly')">Monthly</button>
            <button onclick="updateChart('yearly')">Yearly</button>
        </div>
        <canvas id="salesChart"></canvas>
    </div>
</div>

<script>
// Convert PHP sales data to JavaScript objects
const dailySales = <?php echo $dailySalesJSON; ?>;
const weeklySales = <?php echo $weeklySalesJSON; ?>;
const monthlySales = <?php echo $monthlySalesJSON; ?>;
const yearlySales = <?php echo $yearlySalesJSON; ?>;

const ctx = document.getElementById('salesChart').getContext('2d');
let salesChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: [], // Time labels for the period (e.g., days, weeks, months)
        datasets: [{
            label: 'Sales (₱)',
            data: [], // Sales data for the selected period
            backgroundColor: '#D71445',
            borderColor: '#D71445',
            borderWidth: 1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Function to extract data from PHP JSON and format it for Chart.js
function formatSalesData(periodData, labelKey, dataKey) {
    let labels = [];
    let data = [];
    
    periodData.forEach(entry => {
        labels.push(entry[labelKey]);
        data.push(parseFloat(entry[dataKey]));
    });
    
    return { labels, data };
}

function updateChart(period) {
    let labels = [];
    let data = [];

    if (period === 'daily') {
        const formattedData = formatSalesData(dailySales, 'date', 'total_sales');
        labels = formattedData.labels;
        data = formattedData.data;
    } else if (period === 'weekly') {
        const formattedData = formatSalesData(weeklySales, 'week', 'total_sales');
        labels = formattedData.labels;
        data = formattedData.data;
    } else if (period === 'monthly') {
        const formattedData = formatSalesData(monthlySales, 'month', 'total_sales');
        labels = formattedData.labels;
        data = formattedData.data;
    } else if (period === 'yearly') {
        const formattedData = formatSalesData(yearlySales, 'year', 'total_sales');
        labels = formattedData.labels;
        data = formattedData.data;
    }

    salesChart.data.labels = labels;
    salesChart.data.datasets[0].data = data;
    salesChart.update();
}

// Initialize with daily data
updateChart('daily');

// DateTime updater
  function updateDateTime() {
        const now = new Date();
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit' 
        };
        document.getElementById('dateTime').innerHTML = now.toLocaleDateString('en-US', options);
    }

    setInterval(updateDateTime, 1000); // Update date and time every second
function toggleSidebar() {
    var sidebar = document.getElementById("sidebar");
    if (sidebar.style.width === "300px") {
        sidebar.style.width = "0";
    } else {
        sidebar.style.width = "300px";
    }
}
</script>

</body>
</html>