<?php
session_start();
include 'db.php';  // Ensure this points to your database connection file

// Include PHPMailer
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure the user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['username'];  // Get the username from session
$userId = $_SESSION['user_id'];

// Encryption key for sensitive data (must be kept secure)
$encryptionKey = 'your-secure-encryption-key'; // Use a strong, secure key

// Initialize totalBill and purchase flags
$totalBill = 0;
$purchaseComplete = false;
$confirmPurchase = false;

// Fetch orders for this user to calculate total bill and product details
$stmt = $conn->prepare("SELECT o.product_id, o.quantity, i.item_name, i.price FROM orders o JOIN inventory i ON o.product_id = i.id WHERE o.user_id = ?");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare receipt details
$receiptDetails = [];

foreach ($orders as $order) {
    $productId = $order['product_id'];
    $quantity = $order['quantity'];
    $productName = $order['item_name'];
    $price = $order['price'];

    // Calculate total bill
    if ($price) {
        $lineTotal = $price * $quantity;
        $totalBill += $lineTotal;

        // Add details to the receipt
        $receiptDetails[] = [
            'name' => $productName,
            'quantity' => $quantity,
            'price' => $price,
            'lineTotal' => $lineTotal
        ];
    }
}

// Fetch the user's saved email if available
$stmt = $conn->prepare("SELECT email FROM customers WHERE id = ?");
$stmt->execute([$userId]);
$savedEmail = $stmt->fetchColumn();

// Encrypt account number for secure storage
function encryptAccountNumber($accountNumber, $encryptionKey) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($accountNumber, 'aes-256-cbc', $encryptionKey, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);  // Store both encrypted data and IV
}

// Decrypt account number for display
function decryptAccountNumber($encrypted, $encryptionKey) {
    list($encryptedData, $iv) = explode('::', base64_decode($encrypted), 2);
    return openssl_decrypt($encryptedData, 'aes-256-cbc', $encryptionKey, 0, $iv);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = !empty($_POST['email']) ? $_POST['email'] : $savedEmail;
    $address = $_POST['address'] ?? '';
    $accountNumber = $_POST['account_number'] ?? '';

    // Remove dashes for validation
    $cleanedAccountNumber = str_replace('-', '', $accountNumber);

    // Validate the cleaned account number
    if (!is_numeric($cleanedAccountNumber) || strlen($cleanedAccountNumber) < 10) {
        echo "";
    } else {
        // Further processing can happen here (e.g., saving to a database)
        echo "<p class='alert alert-success'>Account number is valid: $cleanedAccountNumber</p>";
    }
} else {
    // Handle the case where the form has not been submitted
    echo "<p>Please fill out the form.</p>";
}

    // Encrypt the account number
    $encryptedAccountNumber = encryptAccountNumber($accountNumber, $encryptionKey);

    // Check if all required fields are filled
    if (!$email || !$address || !$accountNumber) {
        echo "";
    } else {
        if (isset($_POST['confirm'])) {
            // Process payment
            try {
                $conn->beginTransaction();

                // Insert into customer_purchase and sales tables first
                foreach ($orders as $order) {
                    $productId = $order['product_id'];
                    $quantity = $order['quantity'];

                    // Insert into sales table first, which will auto-generate the order_number
                    $stmt = $conn->prepare("INSERT INTO sales (item_id, quantity, total_price, created_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$productId, $quantity, $totalBill, date('Y-m-d H:i:s')]);

                    // Get the generated order_number from the sales table (id)
                    $orderNumber = $conn->lastInsertId();  // This will return the last inserted id (order number)

                    // Insert into customer_purchase table using the generated order_number
                    $stmt = $conn->prepare("INSERT INTO customer_purchase (user_id, product_id, quantity, total, name, email, address, account_number, order_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$userId, $productId, $quantity, $totalBill, $name, $email, $address, $encryptedAccountNumber, $orderNumber]);

                    // Check current quantity in the inventory
                    $stmt = $conn->prepare("SELECT quantity FROM inventory WHERE id = ?");
                    $stmt->execute([$productId]);
                    $currentQuantity = $stmt->fetchColumn();

                    // Reduce the quantity in the inventory
                    if ($currentQuantity >= $quantity) {
                        $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                        $stmt->execute([$quantity, $productId]);
                    } else {
                        throw new Exception("Not enough quantity for Product ID: $productId. Available: $currentQuantity, Requested: $quantity.");
                    }
                }

                // Clear the user's orders after purchase
                $stmt = $conn->prepare("DELETE FROM orders WHERE user_id = ?");
                $stmt->execute([$userId]);

                $conn->commit();
                $purchaseComplete = true;

                // Send the transaction invoice via email
                $mail = new PHPMailer(true);
                $invoiceSent = false;

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'uvomtrjoshua@gmail.com'; // Your Gmail email
                    $mail->Password = 'wpei jzpi rkel jqhs'; // Your generated App Password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Recipients
                    $mail->setFrom('uvomtrjoshua@gmail.com', 'ElectroTrack');
                    $mail->addAddress($email, $name);

                    // Prepare email content
                    $body = '<h1>Invoice Confirmation</h1>';
                    $body .= '<p><strong>Order Number:</strong> #' . htmlspecialchars($orderNumber) . '</p>';
                    $body .= '<p><strong>Date:</strong> ' . date('F j, Y') . '</p>';
                    $body .= '<p><strong>Customer Name:</strong> ' . htmlspecialchars($name) . '</p>';
                    $body .= '<p><strong>Address:</strong> ' . htmlspecialchars($address) . '</p>';
                    $body .= '<h2>Order Details:</h2>';
                    $body .= '<table border="1" cellpadding="10" style="border-collapse:collapse; width:100%;">';
                    $body .= '<tr><th>Product Name</th><th>Quantity</th><th>Price</th><th>SubTotal</th></tr>';

                    foreach ($receiptDetails as $item) {
                        $body .= '<tr>';
                        $body .= '<td>' . htmlspecialchars($item['name']) . '</td>';
                        $body .= '<td>' . htmlspecialchars($item['quantity']) . '</td>';
                        $body .= '<td>' . htmlspecialchars($item['price']) . '</td>';
                        $body .= '<td>' . htmlspecialchars($item['lineTotal']) . '</td>';
                        $body .= '</tr>';
                    }

                    $body .= '</table>';

                    // Subtotal and Total
                    $body .= '<p><strong>Total Amount:</strong> ₱' . htmlspecialchars($totalBill) . '</p>';

                    // Available payment methods
                    $body .= '<p><strong>Available Payment Methods:</strong> CASH, GCASH, and MAYA</p>';

                    // Store address and contact information
                     // Store address and contact information
                    $body .= '<p><strong>Store Address:</strong> Electro Track Inventory Hub 256 15th Ave. Barangay Silangan Cubao Qc. </p>';
                    $body .= '<p>If you have any questions, contact us at ElectroTrack@gmail.com or 8-912-3997/8-912-4547.</p>';


                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Purchase Invoice from ElectroTrack';
                    $mail->Body    = $body;
                    $mail->AltBody = strip_tags($body);

                    $mail->send();
                    $invoiceSent = true;
                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }

                // Display success message for email sending
                if ($invoiceSent) {
                    echo "<p class='alert alert-success'>Invoice sent successfully to: " . htmlspecialchars($email) . "</p>";
                } else {
                    echo "<p class='alert alert-danger'>Failed to send invoice. Please check your email settings.</p>";
                }

            } catch (Exception $e) {
                $conn->rollBack();
                echo "<p class='alert alert-danger'>Failed to complete the purchase: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            // Show confirmation before payment
            $confirmPurchase = true;
        }
    }

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ElectroTrack</title>
    <link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
	 body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }

  .container {
    width: 100%; /* Changed max-width to width */
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Form input fields */
.form-group label {
    font-weight: bold;
    color: #1a396e;
    margin-bottom: 5px;
}

.form-control {
    width: 100%; /* Make input fields full width */
    border: 2px solid #ced4da;
    border-radius: 4px;
    padding: 10px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
}

.form-control:focus {
    border-color: #1a396e;
    box-shadow: none;
}

/* Submit and Confirm buttons */
.btn-primary, .btn-success {
    background-color: #1a396e;
    border-color: #1a396e;
    color: #fff;
    padding: 10px 20px;
    font-size: 1rem;
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

.btn-primary:hover, .btn-success:hover {
    background-color: #143258;
    border-color: #143258;
}

.btn-primary:focus, .btn-success:focus {
    box-shadow: none;
}

/* Alerts styling */
.alert {
    font-size: 1.1rem;
    padding: 15px;
    margin-top: 20px;
    border-radius: 4px;
    border: 1px solid transparent;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
    border-color: #ffeeba;
}

/* Receipt section */
h1, h2 {
    color: #1a396e;
}

table {
    width: 100%; /* Ensure the table takes full width */
    margin-top: 20px;
    border-collapse: collapse;
}

table, th, td {
    border: 1px solid #1a396e;
}

th, td {
    padding: 10px;
    text-align: left;
}

th {
    background-color: #f1f1f1;
}

.btn-print {
    background-color: #1a396e;
    color: #fff;
    font-size: 1rem;
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-top: 15px;
    transition: background-color 0.3s ease;
}

.btn-print:hover {
    background-color: #143258;
}

/* Small customizations for mobile */
@media (max-width: 768px) {
    .container {
        padding: 15px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-control {
        font-size: 0.9rem;
    }

    .btn-primary, .btn-success {
        font-size: 0.9rem;
    }
}

    </style>
  <script>
    function printReceipt() {
        let receiptContent = `
            <h1 style="text-align:center;">Receipt from ElectroTrack</h1>
            <link rel="icon" href="Assets/electro.png" type="image/x-icon">
            <img src="Assets/electro.png" alt="ElectroTrack Logo" style="float:right; width:100px; height:auto; margin-bottom: 20px;">
            <p style="text-align:center;">Thank you for your purchase, <?php echo htmlspecialchars($username); ?>!</p>
            <p style="text-align:center;">Order Number: #<?php echo $orderNumber; ?></p>
            <p style="text-align:center;">Address: <?php echo htmlspecialchars($_POST['address']); ?></p>
            <p style="text-align:center;">Account Number: ****-****-<?php echo substr(htmlspecialchars($_POST['account_number']), -4); ?></p>
            <h2 style="border-bottom: 2px solid #1a396e;">Order Details:</h2>
            <table border="1" cellpadding="10" style="border-collapse:collapse; width:100%; margin: auto;">
                <tr>
                    <th>Product Name</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>SubTotal</th>
                </tr>
                <?php foreach ($receiptDetails as $item): ?>
                    <tr>
                        <td style="text-align:left;"><?php echo htmlspecialchars($item['name']); ?></td>
                        <td style="text-align:left;"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td style="text-align:left;"><?php echo htmlspecialchars($item['price']); ?></td>
                        <td style="text-align:left;"><?php echo htmlspecialchars($item['lineTotal']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p style="text-align:right;">Total Bill: <?php echo htmlspecialchars($totalBill); ?></p>
        `;
        let printWindow = window.open('', '', 'height=600,width=800');
        printWindow.document.write('<html><head><title>Receipt</title>');
        printWindow.document.write('</head><body>');
        printWindow.document.write(receiptContent);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }
</script>
</head>
<body>
    <div class="container mt-5">
        <h1>Checkout - ElectroTrack</h1>
		<link rel="icon" href="Assets/electro.png" type="image/x-icon">
		  <link rel="icon" href="Assets/electro.png" type="image/x-icon">
        <?php if ($purchaseComplete): ?>
            <div class="alert alert-success">
                <h2>Thank you for your purchase! <?php echo htmlspecialchars($username); ?></h2>
          
			 <button onclick="printReceipt()" class="btn btn-primary"><i class="fas fa-print" style="text-align=right"></i></button>
			  <a href="customer_dashboard.php" class="btn btn-primary" style="margin-left: 10px;">
                <i class="fas fa-shopping-cart"></i> Shop Again </a>
				  </div>
            </div>
        <?php elseif ($confirmPurchase): ?>
            <div class="alert alert-warning">
                <h2>Confirm Your Purchase! <?php echo htmlspecialchars($username); ?></h2>
                <p>Please confirm your details below before completing the purchase:</p>
                <ul>
                    <li><strong>Name:</strong> <?php echo htmlspecialchars($name); ?></li>
                    <li><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></li>
                    <li><strong> Address:</strong> <?php echo htmlspecialchars($address); ?></li>
                    <li><strong>Account Number:</strong>  ****-****-<?php echo substr(htmlspecialchars($accountNumber), -4); ?></li> <!-- Masked -->
                </ul>
                <form method="post">
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="address" value="<?php echo htmlspecialchars($address); ?>">
                    <input type="hidden" name="account_number" value="<?php echo htmlspecialchars($accountNumber); ?>">
                    <button type="submit" name="confirm" class="btn btn-success">Confirm and Pay</button>
                </form>
            </div>
        <?php else: ?>
            <form method="post" action="">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($savedEmail); ?>" required>
                </div>
                <div class="form-group">
        <label for="address">Address:</label>
        <textarea class="form-control" id="address" name="address" rows="3" required placeholder="Enter your address"></textarea>
    </div>
<div class="form-group">
    <label for="account_number">Account Number:</label>
    <input type="text" class="form-control" id="account_number" name="account_number" required minlength="8" maxlength="14" pattern="[\d\-]+" placeholder="8-12 digits(e.g., 2019-2010-1020)">
    <small class="form-text text-muted">Your account number should be in the format XXXX-XXXX-XXXX.</small>
</div>
                <button type="submit" class="btn btn-primary">Proceed to Confirm</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>