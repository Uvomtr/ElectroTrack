
<?php
session_start();  // Start the session

// Database connection
$host = 'localhost';
$dbname = 'inventory_system';
$db_username = 'root';  // Change to your database username
$db_password = '';  // Change to your database password
$conn = new PDO("mysql:host=$host;dbname=$dbname", $db_username, $db_password);

// Registration logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];  // E.g., 'admin' or 'staff'

    // Check if username already exists
    $sql = "SELECT COUNT(*) FROM users WHERE username = :username";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $error_message = "Username already exists. Please choose another one.";
    } else {
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Insert new user into the database
        $sql = "INSERT INTO users (username, password, role) VALUES (:username, :password, :role)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':role', $role);

        if ($stmt->execute()) {
            $success_message = "Registration successful! <a href='login.php'>Login here</a>";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Inventory System</title>
	<link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
      body {
            font-family: 'Poppins', Arial, sans-serif;
            background-color: #f2f3f7;
            margin: 0;
            padding: 0;
        }


/* Register Container Styling */
.register-container {
    margin-top: 100px;
    max-width: 500px;
    background-color: #f8f9fa;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    margin-left: auto;
    margin-right: auto;
}

/* Form Title Styling */
.form-title {
    text-align: center;
    margin-bottom: 20px;
    color: #1a396e;
    font-size: 28px;
    font-weight: bold;
}

/* Input Field Styling */
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

select.form-select {
    font-family: 'Poppins', sans-serif;
}

/* Register Button Styling */
.register-btn {
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

.register-btn:hover {
            color: #fff;
            background-color: #0b105c;
  
}

/* Error and Success Message Styling */
.error, .success {
    text-align: center;
    margin-bottom: 15px;
}

.error {
    color: #E23C51;
}

.success {
    color: #28a745;
}

/* Back to Login Link Styling */
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

<div class="container d-flex justify-content-center">
    <div class="register-container">
        <h3 class="form-title">Register for ElectroTrack(Admin/Staff)</h3>

        <?php if (isset($error_message)): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <?php if (isset($success_message)): ?>
            <p class="success"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" class="form-control" id="username" placeholder="Enter your username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" class="form-control" id="password" placeholder="Enter your password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select name="role" class="form-select" id="role" required>
                    <option value="admin">Admin</option>
                    <option value="staff">Staff</option>
                </select>
            </div>
            <button type="submit" class="btn register-btn">Register</button>
        </form>

        <div class="back-to-login">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>