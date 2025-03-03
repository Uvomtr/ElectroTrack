
<?php 
session_start();  // Start the session
include 'db_config.php';
$startDate = null;
$endDate = null;
$minPrice = null;
$maxPrice = null;
$category = null;

// Initialize messages
$successMessage = '';
$errorMessage = '';

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Handle Add Item Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_item'])) {
    $item_name = trim($_POST['item_name']);
    $quantity = intval($_POST['quantity']);
    $price = floatval($_POST['price']);
	$category = trim($_POST['category']);

    // Validate Inputs
    if (empty($item_name)) {
        $errorMessage = "Item name is required.";
    } elseif ($quantity < 0) {
        $errorMessage = "Quantity cannot be negative.";
    } elseif ($price < 0) {
        $errorMessage = "Price cannot be negative.";
	} elseif (empty($category)) { // Validate category
        $errorMessage = "Category is required.";
    } else {
        try {
            // Check if the item already exists
            $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE item_name = :item_name");
            $stmt->bindParam(':item_name', $item_name);
            $stmt->execute();

            $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingItem) {
                // Item exists, update quantity
                $newQuantity = $existingItem['quantity'] + $quantity;
                  $stmt = $pdo->prepare("UPDATE inventory SET quantity = :quantity, price = :price, category = :category, date_added = NOW() WHERE item_name = :item_name");
                $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
                $stmt->bindParam(':price', $price, PDO::PARAM_STR);
				$stmt->bindParam(':category', $category, PDO::PARAM_STR); // Include category
                $stmt->bindParam(':item_name', $item_name);
                $stmt->execute();
            } else {
                // Item does not exist, insert a new item
                $stmt = $pdo->prepare("INSERT INTO inventory (item_name, quantity, price, category) VALUES (:item_name, :quantity, :price, :category)");
                $stmt->bindParam(':item_name', $item_name);
                $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                $stmt->bindParam(':price', $price, PDO::PARAM_STR);
				$stmt->bindParam(':category', $category, PDO::PARAM_STR);
                $stmt->execute();
            }

            $successMessage = "Item added successfully!";
        } catch (PDOException $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    }
}

	// Default sorting (A-Z by item_name)
	$orderBy = "ORDER BY item_name ASC";

	if (isset($_GET['sort_order'])) {
		switch ($_GET['sort_order']) {
			case 'name_desc':
				$orderBy = "ORDER BY item_name DESC";
				break;
			case 'name_asc':
			default:
				$orderBy = "ORDER BY item_name ASC";
				break;
			case 'date_desc':
				$orderBy = "ORDER BY date_added DESC";
				break;
			case 'date_asc':
				$orderBy = "ORDER BY date_added ASC";
				break;
		}
	}

// Handle date and price filtering
$filterByDateAndPrice = '';
$conditions = [];

if (isset($_GET['start_date']) && !empty($_GET['start_date'])) {
    $startDate = date('Y-m-d', strtotime($_GET['start_date'])); // Convert to the correct format
    $conditions[] = "date_added >= :start_date"; // Added a condition
}

if (isset($_GET['end_date']) && !empty($_GET['end_date'])) {
    $endDate = date('Y-m-d 23:59:59', strtotime($_GET['end_date'])); // Include the entire end day
    $conditions[] = "date_added <= :end_date"; // Added a condition
}

if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $minPrice = $_GET['min_price'];
    $conditions[] = "price >= :min_price";
}

if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $maxPrice = $_GET['max_price'];
    $conditions[] = "price <= :max_price";
}

if (isset($_GET['category']) && $_GET['category'] !== '') {
    $category = $_GET['category'];
    $conditions[] = "category = :category";
}

// Build the WHERE clause
if (count($conditions) > 0) {
    $filterByDateAndPrice = "WHERE " . implode(" AND ", $conditions);
}

// Prepare the SQL statement
$sql = "SELECT * FROM inventory $filterByDateAndPrice $orderBy";
$stmt = $pdo->prepare($sql);

// Bind parameters only if the condition exists
if (!empty($conditions)) {
    if (isset($startDate)) {
        $stmt->bindParam(':start_date', $startDate);
    }
    if (isset($endDate)) {
        $stmt->bindParam(':end_date', $endDate);
    }
    if (isset($minPrice)) {
        $stmt->bindParam(':min_price', $minPrice);
    }
    if (isset($maxPrice)) {
        $stmt->bindParam(':max_price', $maxPrice);
    }
    if (isset($category)) {
        $stmt->bindParam(':category', $category);
    }
}

// Execute the statement
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory</title>
	<link rel="icon" href="Assets/electro.png" type="image/x-icon">
    
    <!-- Google Font for Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css">
    
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
            color: #fff;
            font-size: 20px;
            margin: 20px auto;
            text-align: center;
        }

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

        /* Content Area Styling */
        .content {
            margin-left: 300px;
            padding: 30px;
            background-color: #fff;
            transition: margin-left 0.3s ease;
            min-height: 100vh; /* Ensure the content area takes full height */
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            margin-top: 20px;
            margin-left: 30px;
            background-color: #fff;
            /* Optional styling
            border-bottom: 2px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            */
        }

        .header h1 {
            color: #031124;
            font-size: 65px;
            font-weight: bold;
            font-family: 'Poppins', sans-serif;
            margin: 0;
        }

        .header .date-time {
            font-size: 18px;
            color: #031124;
            font-family: 'Poppins', sans-serif;
            text-align: right;
            margin: 0; /* No extra space around the date/time */
        }

        .date {
            color: #031124;
            font-size: 18px;
            text-align: right;
            margin-left: auto;
            margin-right: 50px;
        }

        .table-container {
            margin-left: 40px;
            margin-right: 40px;
            margin-top: 30px;
        }

        .table {
            width: 100%;
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

		.add-item-form {
            margin-left: 50px;
            margin-right: 50px;
			flex: 1;  /* Allow each form group to take equal width */
		}
		
		.add-item-form .form-control {
			/* Custom styles for input fields inside the Add Item Form */
			width: 100%;
		}
			
		.filter-form {
			/* Add specific styles for the Filter Form here */
			padding: 20px;
		}

		.filter-form .form-control {
			/* Custom styles for input fields inside the Filter Form */
			width: 100%;
		}	

        .add-button {
            background-color: #D71445;
            color: white;
            border: none;
            padding: 10px 20px;
            margin-left: 50px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
			margin: 0;
        }

        .add-button:hover {
            background-color: #E23C51;
        }

        /* Alert Messages */
        .alert {
            margin-left: 40px;
            margin-right: 40px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<!-- Hamburger Menu for Mobile -->
<div class="hamburger" onclick="toggleSidebar()">&#9776;</div>

<!-- Sidebar with icons -->
<div class="sidebar" id="sidebar">
    <div class="circle-photo">
        <img src="Assets/ADMIN.png" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%;">
    </div>
    <h2>Admin</h2>
    
    <a href="index.php">
        <div class="mini-circle-photo">
            <img src="Assets/HOME.png" alt="Home Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Home
    </a>
    <a href="inventory.php">
        <div class="mini-circle-photo">
            <img src="Assets/INVENTORY.png" alt="Inventory Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Manage Inventory
    </a>
    <a href="pos.php">
        <div class="mini-circle-photo">
            <img src="Assets/POS.png" alt="POS Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Point of Sale
    </a>
    <a href="sales.php">
        <div class="mini-circle-photo">
            <img src="Assets/SALES HISTORY.png" alt="Sales History Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Sales History
    </a>
    <a href="delete.php">
        <div class="mini-circle-photo">
            <img src="Assets/DELETE.png" alt="Delete Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Delete Item
    </a>
    <a href="logout.php" class="logout-link">
        <div class="mini-circle-photo">
            <img src="Assets/LOGOUT.png" alt="Logout Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Logout
    </a>
</div>

<!-- Content -->
<div class="content">
    <div class="header">
        <h1>Manage Inventory</h1>
        <div class="date" id="dateTime"></div>
    </div>

    <!-- Success and Error Messages -->
    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success" role="alert">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- Form for Adding Inventory Items -->
	<form method="post" class="mb-4 add-item-form">
        <div class="form-group">
            <label for="item_name">Item Name:</label>
            <input type="text" class="form-control" id="item_name" name="item_name" required>
        </div>
        <div class="form-group">
            <label for="quantity">Quantity:</label>
            <input type="number" min="0" class="form-control" id="quantity" name="quantity" required>
        </div>
        <div class="form-group">
            <label for="price">Price (₱):</label>
            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" required>
        </div>
		<div class="form-group">
			<label for="category">Category:</label>
			<select name="category" id="category" class="form-control" required>
				<option value="">Select Category</option>
				<option value="Alternator Brush Holder">Alternator Brush Holder</option>
				<option value="Alternator Brush">Alternator Brush</option>
				<option value="Bearing">Bearing</option>
				<option value="Bendix Drive">Bendix Drive</option>
				<option value="Blower">Blower</option>
				<option value="Diode Set">Diode Set</option>
				<option value="Field (Oil)">Field (Oil)</option>
				<option value="Heater Plug">Heater Plug</option>
				<option value="IC Regulator">IC Regulator</option>
				<option value="Starter Brush">Starter Brush</option>
				<option value="Switch">Switch</option>
				<option value="Tail Light or Light Top">Tail Light or Light Top</option>
			</select>
		</div>
        <button type="submit" name="add_item" class="add-button">Add Item</button>
    </form>

<!-- Combined Filter and Sort Form -->
<div class="d-flex justify-content-end mb-4" style="margin-right: 40px;">
    <form method="get" class="form-inline">
        <label for="sort_order" class="mr-2">Sort by:</label>
        <select name="sort_order" id="sort_order" class="form-control mr-2">
            <option value="name_asc" <?php if(isset($_GET['sort_order']) && $_GET['sort_order'] == 'name_asc') echo 'selected'; ?>>A-Z</option>
            <option value="name_desc" <?php if(isset($_GET['sort_order']) && $_GET['sort_order'] == 'name_desc') echo 'selected'; ?>>Z-A</option>
            <option value="date_asc" <?php if(isset($_GET['sort_order']) && $_GET['sort_order'] == 'date_asc') echo 'selected'; ?>>Date Added (Oldest First)</option>
            <option value="date_desc" <?php if(isset($_GET['sort_order']) && $_GET['sort_order'] == 'date_desc') echo 'selected'; ?>>Date Added (Newest First)</option>
        </select>
        <button type="submit" name="sort" class="btn btn-primary mr-2">Sort</button>
        <button type="button" class="btn btn-secondary" data-toggle="modal" data-target="#filterModal">Filter</button>
    </form>
</div>
	
<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" role="dialog" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filterModalLabel">Filter Items</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="get" id="filterForm" class="filter-form">
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="start_date">Start Date:</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo isset($_GET['start_date']) ? $_GET['start_date'] : ''; ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="end_date">End Date:</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo isset($_GET['end_date']) ? $_GET['end_date'] : ''; ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label for="min_price">Min Price:</label>
                            <input type="number" name="min_price" id="min_price" class="form-control" step="0.01" min="0" value="<?php echo isset($_GET['min_price']) ? $_GET['min_price'] : ''; ?>">
                        </div>
                        <div class="form-group col-md-6">
                            <label for="max_price">Max Price:</label>
                            <input type="number" name="max_price" id="max_price" class="form-control" step="0.01" min="0" value="<?php echo isset($_GET['max_price']) ? $_GET['max_price'] : ''; ?>">
                        </div>
                    </div>
                    <div class="form-group">
						<label for="category">Category:</label>
						<select name="category" id="category" class="form-control" >
							<option value="">All Categories</option> <!-- Placeholder indicating all categories -->
							<option value="Alternator Brush Holder">Alternator Brush Holder</option>
							<option value="Alternator Brush">Alternator Brush</option>
							<option value="Bearing">Bearing</option>
							<option value="Bendix Drive">Bendix Drive</option>
							<option value="Blower">Blower</option>
							<option value="Diode Set">Diode Set</option>
							<option value="Field (Oil)">Field (Oil)</option>
							<option value="Heater Plug">Heater Plug</option>
							<option value="IC Regulator">IC Regulator</option>
							<option value="Starter Brush">Starter Brush</option>
							<option value="Switch">Switch</option>
							<option value="Tail Light or Light Top">Tail Light or Light Top</option>
						</select>
					</div>
                    <button type="submit" name="filter" class="btn btn-primary">Apply Filter</button>
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function clearFilters() {
    document.getElementById('filterForm').reset(); // Resets the form fields
    window.location.href = window.location.pathname; // Reloads the page without query parameters
}
</script>


    <!-- Table for Displaying Inventory Items -->
     <div class="table-container">
        <table id="inventoryTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Price (₱)</th>
					<th>Category</th>
					<th>Date Added</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['id']); ?></td>
                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td><?php echo number_format($item['price'], 2); ?></td>
						<td><?php echo htmlspecialchars($item['category']); ?></td>
						 <td><?php echo htmlspecialchars($item['date_added']); ?></td>
					</tr>
                <?php endforeach; ?>
            </tbody>
        </table>
			<form action="list/generate_list.php" method="POST">
    <button type="submit" name="generate_list">Available Product List</button>
</form>
    </div>
</div>

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>

<script>

	function toggleFilter() {
    const filterSection = document.getElementById("filterSection");
    if (filterSection.style.display === "none") {
        filterSection.style.display = "block";
    } else {
        filterSection.style.display = "none";
    }
}

    // Toggle Sidebar Function
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const content = document.querySelector(".content");
        if (sidebar.style.width === "300px") {
            sidebar.style.width = "0";
            content.style.marginLeft = "0";
        } else {
            sidebar.style.width = "300px";
            content.style.marginLeft = "300px";
        }
    }

    // Update Date and Time Function
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