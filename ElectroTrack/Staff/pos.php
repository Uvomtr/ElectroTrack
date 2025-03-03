
<?php 
session_start(); // Start the session
include 'db_config.php';

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$successMessage = '';
$errorMessage = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'];

    // Check if the item exists and if there's enough quantity
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = :item_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item) {
        if ($item['quantity'] >= $quantity) {
            // Update inventory
            $new_quantity = $item['quantity'] - $quantity;
            $stmt = $pdo->prepare("UPDATE inventory SET quantity = :new_quantity WHERE id = :item_id");
            $stmt->bindParam(':new_quantity', $new_quantity);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();

            // Insert into sales
            $total_price = $item['price'] * $quantity;
            $stmt = $pdo->prepare("INSERT INTO sales (item_id, quantity, total_price, created_at) 
                       VALUES (:item_id, :quantity, :total_price, NOW())");
			$stmt->bindParam(':item_id', $item_id);
			$stmt->bindParam(':quantity', $quantity);
			$stmt->bindParam(':total_price', $total_price);
			$stmt->execute();


            $successMessage = "Sale processed successfully!";
        } else {
            $errorMessage = "Not enough inventory to fulfill the sale.";
        }
    } else {
        $errorMessage = "Item not found.";
    }
}

$items = $pdo->query("SELECT * FROM inventory")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Point of Sale</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Include Bootstrap CSS if necessary -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            color: #333;
        }

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
        }

        .logout-link:hover {
            background-color: #E23C51;
            padding-left: 0;
        }

        .sidebar h2 {
            color: #FAFBFF;
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
        }

        .content {
            margin-left: 300px;
			
            padding: 30px;
            background-color: #fff;
            transition: margin-left 0.3s ease;
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
            color: #FAFBFF;
            font-size: 20px;
            margin: 20px auto;
            text-align: center;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
			margin-top: 50px;
			margin-left: 30px;
			margin-right: 20px;
            /* border-bottom: 2px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); */
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

        .table th {
            background-color: #0d1128;
            color: 031124;
            text-align: center;
        }

        .table td {
            text-align: center;
        }
		
		.form-group {
			margin-left: 50px;
			margin-right: 30px;
		}

        button {
            background-color: #D71445;
            color: #FAFBFF;
            border: none;
            padding: 10px 20px;
			margin-left: 50px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button:hover {
            background-color: #E23C51;
			font-weight: 300;
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

<div class="hamburger" onclick="toggleSidebar()">&#9776;</div>

<div class="sidebar" id="sidebar">
    <div class="circle-photo">
        <img src="Assets/ADMIN.png" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%;">
    </div> <!-- Circular photo placeholder -->
    <h2>Staff</h2>
    
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

    <a href="logout.php" class="logout-link">
        <div class="mini-circle-photo">
        <img src="Assets/LOGOUT.png" alt="Inventory Icon" 
        style="width: 100%; height: 100%; border-radius: 50%;"></div> Logout
    </a>
</div>

<div class="content">
    <div class="header">
        <h1>Point of Sale</h1>
        <div class="date-time" id="dateTime"></div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $successMessage; ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="mb-4">
        <div class="form-group">
            <label for="item_id">Select Item:</label>
            <select class="form-control" id="item_id" name="item_id" required>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['item_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="quantity">Quantity:</label>
            <input type="number" class="form-control" id="quantity" name="quantity" required>
        </div>
        <button type="submit">Process Sale</button>
    </form>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.style.width === '250px' || sidebar.style.width === '') {
            sidebar.style.width = '0';
            document.querySelector('.content').style.marginLeft = '0';
        } else {
            sidebar.style.width = '250px';
            document.querySelector('.content').style.marginLeft = '250px';
        }
    }

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
</script>

</body>
</html>