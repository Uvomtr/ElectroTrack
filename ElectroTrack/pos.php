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
$saleDetails = [];

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
            $stmt = $pdo->prepare("INSERT INTO sales (item_id, quantity, total_price) VALUES (:item_id, :quantity, :total_price)");
            $stmt->bindParam(':item_id', $item_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':total_price', $total_price);
            $stmt->execute();

            // Save sale details for printing
            $saleDetails = [
                'item_name' => $item['item_name'],
                'quantity' => $quantity,
                'total_price' => $total_price,
                'date' => date('Y-m-d H:i:s'),
            ];

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
    <link rel="icon" href="Assets/electro.png" type="image/x-icon">
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
            margin-top: 20px;
            margin-left: 30px;
            margin-right: 20px;
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

        /* Print receipt styles */
        .receipt {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ccc;
            width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .receipt-header {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
        }

        .receipt-body {
            margin-top: 15px;
            font-size: 16px;
        }

        .receipt-footer {
            margin-top: 15px;
            text-align: center;
        }

        @media print {
            .content, .sidebar {
                display: none;
            }
            .receipt {
                width: 100%;
            }
        }
    </style>
</head>
<body>


<div class="sidebar" id="sidebar">
    <div class="circle-photo">
        <img src="Assets/ADMIN.png" alt="Profile" style="width: 100%; height: 100%; border-radius: 50%;">
    </div> <!-- Circular photo placeholder -->
     <h2>Admin</h2>
    
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
            <img src="Assets/DELETE.png" alt="Delete Icon" style="width: 100%; height: 100%; border-radius: 50%;">
        </div> Delete Item
    </a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>

<div class="content">
    <div class="header">
        <h1>Point of Sale</h1>
        <p class="date-time"><?php echo date("l, F j, Y H:i:s"); ?></p>
    </div>

    <form action="pos.php" method="POST">
        <div class="form-group">
            <label for="item_id">Select Item</label>
            <select name="item_id" id="item_id" class="form-control">
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo $item['id']; ?>"><?php echo $item['item_name']; ?> (₱<?php echo number_format($item['price'], 2); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
        </div>

        <button type="submit" class="btn btn-primary">Process Sale</button>
    </form>

    <?php if ($successMessage): ?>
        <div class="alert alert-success mt-4">
            <?php echo $successMessage; ?>
        </div>
    <?php elseif ($errorMessage): ?>
        <div class="alert alert-danger mt-4">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <?php if ($saleDetails): ?>
        <div class="receipt" id="receipt">
            <div class="receipt-header">
                <h3>Sale Receipt</h3>
                <p>Date: <?php echo $saleDetails['date']; ?></p>
            </div>
            <div class="receipt-body">
                <p><strong>Item:</strong> <?php echo $saleDetails['item_name']; ?></p>
                <p><strong>Quantity:</strong> <?php echo $saleDetails['quantity']; ?></p>
                <p><strong>Total Price:</strong> ₱<?php echo number_format($saleDetails['total_price'], 2); ?></p>
            </div>
            <div class="receipt-footer">
                <p>Thank you for your purchase!</p>
            </div>
        </div>

        <button onclick="printReceipt()">Print Receipt</button>
    <?php endif; ?>
</div>

<script>
function printReceipt() {
    const printContents = document.getElementById('receipt').innerHTML;
    const originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
}
</script>

</body>
</html>
