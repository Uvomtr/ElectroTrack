<?php
session_start();  // Start the session

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Ensure user_id exists in session
if (!isset($_SESSION['user_id'])) {
    die("Error: User ID not set. Please log in again.");
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Capture filter values from the form with proper initialization
$category = isset($_GET['category']) ? $_GET['category'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

// Query to get distinct categories from the 'inventory' table
$categoryQuery = "SELECT DISTINCT category FROM inventory";
$categoryResult = $conn->query($categoryQuery);

// Check if the query was successful for fetching categories
if (!$categoryResult) {
    die("Error executing category query: " . $conn->error);
}

// Construct SQL query with filters
$query = "SELECT * FROM inventory WHERE 1=1";
$params = [];
$types = "";

// Filter by category if selected
if (!empty($category)) {
    $query .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add sorting based on user selection
switch ($sort) {
    case 'a-z':
        $query .= " ORDER BY item_name ASC";
        break;
    case 'z-a':
        $query .= " ORDER BY item_name DESC";
        break;
    case 'new-old':
        $query .= " ORDER BY date_added DESC";
        break;
    case 'old-new':
        $query .= " ORDER BY date_added ASC";
        break;
    default:
        // No sorting applied
        break;
}

// Prepare and execute the main query for displaying items
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Check for add order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $productId = (int)$_POST['product_id'];
    $userId = $_SESSION['user_id'];

    // Check if an order already exists for this user and product
    $stmtCheck = $conn->prepare("SELECT quantity FROM orders WHERE user_id = ? AND product_id = ?");
    $stmtCheck->bind_param("ii", $userId, $productId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    if ($resultCheck->num_rows > 0) {
        // Order exists, update the quantity
        $row = $resultCheck->fetch_assoc();
        $newQuantity = $row['quantity'] + 1; // Increment quantity

        $stmtUpdate = $conn->prepare("UPDATE orders SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmtUpdate->bind_param("iii", $newQuantity, $userId, $productId);
        if ($stmtUpdate->execute()) {
            header('Location: orders.php'); // Redirect to orders page after updating
            exit();
        } else {
            echo "Error updating order: " . $stmtUpdate->error;
        }
    } else {
        // Insert a new order
        $stmtInsert = $conn->prepare("INSERT INTO orders (user_id, product_id, quantity) VALUES (?, ?, 1)");
        $stmtInsert->bind_param("ii", $userId, $productId);
        if ($stmtInsert->execute()) {
            header('Location: orders.php'); // Redirect to orders page after adding
            exit();
        } else {
            echo "Error adding order: " . $stmtInsert->error;
        }
    }

    $stmtCheck->close();
}

// Close the database connection at the end of the script
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard</title>
	<link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }

        /* ----- Sidebar Styles ----- */
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
        /* ----- Logout Styles ----- */
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

        /* ----- Dashboard Styles ----- */
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
        }

        
        /* ----- Filter Container Styles ----- */
        .filter-container {
            border-radius: 0px;
            padding: 0px;
            width: calc(100% - 70px);
            margin: 30px auto;
            margin-top: 10px;
            font-weight: 600;
            color: #1a396e;
        }
        .category {
            display: inline-block;
            margin-bottom: .5rem;
            margin: 5px 50tpx 0px 0px;
        }
        /* ----- Button ----- */
        select {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            outline: none;
            transition: border-color 0.3s;
        }
        select:focus {
            border-color: #1a396e;
        }

        /* ----- Add to Order Button Styles ----- */
        button {
            padding: 8px 20px;
            margin-top: 8px;
            background-color: #1a396e;
            color: #FAFBFF;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #E23C51;
        }

        /* ----- Circle photo and mini circle photo ----- */
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

        /* ----- Item Container ----- */
        .item-container {
            padding: 0px;
            width: calc(100% - 70px);
            margin: 30px auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            grid-gap: 30px;
        }
        .item {
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 25px;
            margin: 10px
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .item:hover {
            transform: scale(1.02);
        }
        /* ----- Item Texts Styles ----- */
        .item h3 {
            color: #031124;
            font-size: 24px;
            font-weight: bold;
            margin: 0 0 10px 0;
            text-align: center;
        }
        .item p {
            margin: 5px 5px 3px 0px;
            color: #555;
        }
        h1 {
            color: #555;
            font-size: 40px;
            font-weight: bold;
        }

        /* Hamburger menu styles */
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
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="hamburger" onclick="toggleSidebar()">&#9776;</div>

<div class="sidebar">
    <div class="circle-photo">
        <img src="Assets/ADMIN.png" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%;">
    </div> 
    <h2><?php echo htmlspecialchars($_SESSION['username']); ?></h2>

    <a href="customer_dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'customer_dashboard.php') ? 'active' : ''; ?>">
    <div class="mini-circle-photo">
        <img src="Assets/HOME.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Home
    </a>
    <a href="orders.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'orders.php') ? 'active' : ''; ?>">
        <div class ="mini-circle-photo">
        <img src="Assets/CART.png" alt="Inventory Icon"
        style="width: 100%; height: 100%; border-radius: 50%;"></div> My Orders
    </a>
    
    <div class="logout-button">
    <a href="logout.php" class="logout-link">
        <div class="mini-circle-photo">
        <img src="Assets/LOGOUT.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Logout
    </a>
    </div>
</div>

<!----- Content ----->
<div class="content">
    <div class="dashboard-container">
        <h1 class="dashboard">Available Products</h1>
        <div class="date" id="dateTime"></div>
    </div>
    
    <!----- Filter & Category ----->
    <div class="filter-container">
        <label for="category">Filter by Category:</label>
            <select id="category" onchange="filterProducts()">
                <option value="">All</option>
                <?php while ($categoryRow = $categoryResult->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($categoryRow['category']); ?>" <?php if ($category === $categoryRow['category']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($categoryRow['category']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label for="sort">Sort By:</label>
            <select id="sort" onchange="sortProducts()">
                <option value="">None</option>
                <option value="a-z" <?php if ($sort === 'a-z') echo 'selected'; ?>>A-Z</option>
                <option value="z-a" <?php if ($sort === 'z-a') echo 'selected'; ?>>Z-A</option>
                <option value="new-old" <?php if ($sort === 'new-old') echo 'selected'; ?>>Newest First</option>
                <option value="old-new" <?php if ($sort === 'old-new') echo 'selected'; ?>>Oldest First</option>
            </select>
    </div>
    
    <!----- Item Container ----->
    <div class="item-container">
        <?php while ($item = $result->fetch_assoc()): ?>
            <div class="item">
                <h3><?php echo htmlspecialchars($item['item_name']); ?></h3>
                <p>Price: <?php echo htmlspecialchars($item['price']); ?></p>
                <p>Category: <?php echo htmlspecialchars($item['category']); ?></p>
                <p>Date Added: <?php echo isset($item['date_added']) ? htmlspecialchars($item['date_added']) : 'Not Available'; ?></p>
                <p>Available Quantity: <?php echo htmlspecialchars($item['quantity']); ?></p> <!-- Added quantity display -->
                <form method="POST" action="customer_dashboard.php">
                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($item['id']); ?>">
                    <button type="submit" name="add_order">Add to Orders</button>
                </form>
            </div>
        <?php endwhile; ?>
    </div>

</div>

<script>
function filterProducts() {
    const category = document.getElementById('category').value;
    const sort = document.getElementById('sort').value;
    const url = new URL(window.location.href);
    
    // Update the URL parameters
    url.searchParams.set('category', category);
    url.searchParams.set('sort', sort);
    
    // Navigate to the updated URL
    window.location.href = url.toString();
}

function sortProducts() {
    const category = document.getElementById('category').value;
    const sort = document.getElementById('sort').value;
    const url = new URL(window.location.href);
    
    // Update the URL parameters
    url.searchParams.set('category', category);
    url.searchParams.set('sort', sort);
    
    // Navigate to the updated URL
    window.location.href = url.toString();
}

	function updateDateTime() {
        const now = new Date();
        const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
        const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const dateString = now.toLocaleDateString('en-US', dateOptions);
        document.getElementById('dateTime').textContent = `${dateString} ${timeString}`;
    }
    
    // Update date and time every second
    setInterval(updateDateTime, 1000);
    updateDateTime(); // Initial call to display immediately
</script>

</body>
</html>