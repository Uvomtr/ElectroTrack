<?php
session_start();
include 'db.php'; // Ensure this points to your database connection file

$error_message = '';
$success_message = '';

// Check if a success message is available in the session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the success message after displaying it
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? ''); // New name field
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } 
    // Validate username (only allow alphanumeric characters)
    elseif (!preg_match('/^[A-Za-z0-9]+$/', $username)) {
        $error_message = "Username can only contain letters and numbers.";
    } 
    else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert into customer table
        $stmt = $conn->prepare("INSERT INTO customers (name, username, email, password) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $username, $email, $hashed_password])) {
            // Set the success message
            $_SESSION['success_message'] = "You have registered successfully! Please log in.";
            header('Location: register.php'); // Redirect back to the registration page or login page
            exit();
        } else {
            $error_message = "Error: Could not register user.";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register as Customer - ElectroTrack</title>
    <link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
       body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 500px;
            margin-top: 100px;
            width: 100%;
            padding: 30px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .title {
            text-align: center;
            margin-bottom: 20px;
            color: #1a396e;
            font-size: 28px;
            font-weight: bold;
        }

        .form-label {
            font-weight: bold;
            color: #1a396e;
            margin-bottom: 5px;
        }

        .form-control {
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

        .error {
            color: red;
        }

        .btn {
            background-color: blue;
            color: white;
            width: 100%;
            padding: 10px;
            font-size: 18px;
            font-family: 'Poppins', sans-serif;
            border-radius: 5px;
            border: none;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .btn:hover {
            color: #fff;
            background-color: #0b105c;
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }

        .back-to-login a {
            text-decoration: none;
            color: #1a396e;
            font-weight: 500;
        }

        .back-to-login a:hover {
            color: #E23C51;
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2 class="title">Register as Customer</h2>
    
    <!-- Display error message if any -->
    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <!-- Display success message if any -->
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="name" class="form-label">Full Name</label>
            <input type="text" name="name" id="name" placeholder="Full Name" required class="form-control">
        </div>
        <div class="form-group">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" id="username" placeholder="Username" required class="form-control" pattern="[A-Za-z0-9]+" title="Username can only contain letters and numbers.">
        </div>
        <div class="form-group">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" placeholder="Email" required class="form-control">
        </div>
        <div class="form-group">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" placeholder="Password" required class="form-control">
        </div>

        <button type="submit" class="btn btn-primary mt-3 w-100">Register</button>

        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
