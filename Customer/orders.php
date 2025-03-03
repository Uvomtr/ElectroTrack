<?php
session_start();
include 'db.php';  // Ensure this points to your database connection file

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];
$userId = $_SESSION['user_id']; // Assuming you have user_id stored in session

// Update order quantity based on the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_order'])) {
        $orderId = $_POST['order_id'];

        // Check if the action is an update
        if ($_POST['update_order'] === 'update') {
            // Get the new quantity from the input
            $newQuantity = intval($_POST['new_quantity']); // Convert to integer

            // Ensure new quantity is valid
            if ($newQuantity < 1) {
                // If quantity is less than 1, remove the order instead of updating
                $stmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
                $stmt->execute([$orderId, $userId]);
            } else {
                // Fetch the available quantity from the inventory
                $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE id = (SELECT product_id FROM orders WHERE id = ?)");
                $stmt->execute([$orderId]);
                $availableQuantity = $stmt->fetchColumn();

                // Check if the new quantity exceeds the available quantity
                if ($newQuantity > $availableQuantity) {
                    $errorMessage = "Insufficient stock. Please reduce the quantity.";
                } else {
                    // Update the order in the database
                    $stmt = $conn->prepare("UPDATE orders SET quantity = ? WHERE id = ? AND user_id = ?");
                    if ($stmt->execute([$newQuantity, $orderId, $userId])) {
                        $successMessage = "Order updated successfully.";
                    } else {
                        $errorMessage = "Failed to update order.";
                    }
                }
            }
        }
    }

    // Check if the action is a removal
    if (isset($_POST['remove_order'])) {
        $orderId = $_POST['order_id'];

        // Delete the order from the database
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ? AND user_id = ?");
        if ($stmt->execute([$orderId, $userId])) {
            $successMessage = "Order removed successfully.";
        } else {
            $errorMessage = "Failed to remove order.";
        }
    }
}

// Fetch the user's orders including the created_at timestamp
$stmt = $conn->prepare("SELECT o.id, o.quantity, i.item_name, i.price, o.created_at, o.product_id FROM orders o JOIN inventory i ON o.product_id = i.id WHERE o.user_id = ?");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate the total bill
$totalBill = 0;
foreach ($orders as $order) {
    $totalBill += $order['quantity'] * $order['price'];
}

// Check inventory before proceeding to checkout
if (isset($_POST['checkout'])) {
    $canProceed = true;
    $orderErrors = [];

    // Loop through each order and check if the quantity exceeds available stock
    foreach ($orders as $order) {
        $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
        $stmt->execute([$order['product_id']]);
        $availableQuantity = $stmt->fetchColumn();

        if ($order['quantity'] > $availableQuantity) {
            $canProceed = false;
            $orderErrors[] = "Insufficient stock for " . $order['item_name'] . ". Available: " . $availableQuantity . ", Ordered: " . $order['quantity'];
        }
    }

    if ($canProceed) {
        // Proceed with checkout
        header('Location: customer_checkout.php');
        exit();
    } else {
        // Join errors into a single string and display them
        $errorMessage = implode('<br>', $orderErrors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders</title>
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
        .order-item {
            transition: transform 0.3s ease;
        }

        .order {
            background-color: #E2E3E7;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-secondary {
            margin-left: 5px;
        }

        h1 {
            color: #555;
            font-size: 40px;
            font-weight: bold;
        }

        .total-bill {
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
            text-align: center; /* Center align total bill */
        }

        .logout-button {
            margin-top: auto;
            padding: 20px 0;
            text-align: center;
        }

        .order-time {
            text-align: right;
            font-size: 14px;
            color: #555;
        }

        .date {
            color: #031124;
            font-size: 18px;
            text-align: right;
            margin-left: auto;
        }

		
        /* ----- Button Styles ----- */
        .continue-shopping {
            padding: 0px;
            width: 50%;
            margin: 30px auto 10px;
            display: grid;
        }

        /* ----- Button Styles ----- */
        button {
            font-family: 'Poppins', Arial, sans-serif;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        /* Add/Decrease Buttons */
        .add-btn, .minus-btn {
            background-color: #1a396e;
            color: #FAFBFF;
            padding: 5px 10px;
            font-size: 14px;
            margin: 0 5px;
        }

        .add-btn:hover, .minus-btn:hover {
            background-color: #E23C51;
        }

        /* Checkout Button */
        .checkout-button {
            background-color: #1a396e;
            color: #FAFBFF;
            padding: 10px 20px;
            font-size: 16px;
            font-weight: bold;
        }

        .checkout-button:hover {
            background-color: #E23C51;
        }

        /* Add Parts Link */
        .add-button {
            display: inline-block;
            background-color: #1a396e;
            color: #FAFBFF;
            padding: 10px 20px;
            margin-top: 10px;
            font-size: 14px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        .add-button:hover {
            background-color: #E23C51;
        }

        .total-bill {
            font-size: 20px;
            font-weight: bold;
            margin-top: 20px;
            text-align: center; /* Center align total bill */
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
			.total-bill-container {
    position: relative; /* Set position context for absolute positioning */
}


			

           
    </style>
</head>
<body>

<!-- Sidebar -->


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
        <h1 class="dashboard">Orders</h1>
        <div class="date" id="dateTime"></div>
    </div>
    

    <?php if (isset($successMessage)): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($errorMessage)): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

   <?php foreach ($orders as $order): ?>
    <div class="order">
        <div>
            <h5><?php echo htmlspecialchars($order['item_name']); ?></h5>
            <p>Price: <?php echo number_format($order['price'], 2); ?></p>
            <p>Quantity: </p>
            <form method="POST" action="orders.php" style="display: flex; align-items: center;">
                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                
                <!-- Decrease Button -->
                <button type="submit" name="update_order" value="update" class="btn btn-secondary" onclick="this.nextElementSibling.stepDown(); this.nextElementSibling.dispatchEvent(new Event('change'));">-</button>
                
                <!-- Quantity Input -->
                <input type="number" name="new_quantity" value="<?php echo htmlspecialchars($order['quantity']); ?>" min="1" class="form-control" style="width: 80px; text-align: center; margin: 0 10px;" onchange="this.form.submit();">
                
                <!-- Increase Button -->
                <button type="submit" name="update_order" value="update" class="btn btn-secondary" onclick="this.previousElementSibling.stepUp(); this.previousElementSibling.dispatchEvent(new Event('change'));">+</button>
            </form>
        </div>
        <form method="POST" action="orders.php">
            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
            <button type="submit" name="remove_order" class="btn btn-danger">Remove</button>
        </form>
    </div>
<?php endforeach; ?>

 <div class="continue-shopping">
            <a href="customer_dashboard.php" class="add-button">Add parts</a>
        </div>

        <div class="total-bill" style="text-align: right; margin-top: 20px;">
            <strong>Total Bill:</strong> ₱<?php echo htmlspecialchars(number_format($totalBill, 2)); ?>
			
        </div>
        <div style="text-align: right; margin-top: 20px;">
            <form action="customer_checkout.php" method="post">
                <input type="hidden" name="total_bill" value="<?php echo htmlspecialchars($totalBill); ?>">
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($userId); ?>">
                <button type="submit" class="checkout-button">Proceed to Checkout</button>
            </form>
        </div>
       
    </div>
</div>
<script>
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
