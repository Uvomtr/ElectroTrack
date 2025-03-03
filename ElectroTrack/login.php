<?php
session_start();  // Start the session

// Database connection
$host = 'localhost';
$dbname = 'inventory_system';
$db_username = 'root';  // Change to your database username
$db_password = '';  // Change to your database password
$conn = new PDO("mysql:host=$host;dbname=$dbname", $db_username, $db_password);

// Login logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Check if the user exists
    $sql = "SELECT * FROM users WHERE username = :username";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_data && password_verify($password, $user_data['password'])) {
        // Password is correct, start a session
        $_SESSION['username'] = $user_data['username'];
        $_SESSION['role'] = $user_data['role'];

        // Redirect based on role
        if ($user_data['role'] == 'staff') {
            header('Location: Staff/index.php');
        } else {
            header('Location: index.php');
        }
        exit();
    } else {
        $error_message = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ElectroTrack</title>
    <link rel="icon" href="Assets/electro.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		* {
			margin: 0;
			padding: 0;
			box-sizing: border-box;
		}
		body {
			height: 100vh;
			width: 100vw;
			overflow: hidden; /* Prevent scrolling */
			font-family: 'Poppins', sans-serif;
			display: flex;
		}
		.loginpage {
			display: flex;
			height: 100%;
			width: 100%;
		}
		.rectangle45 {
			background-color: #fafbff;
			width: 44%;
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			padding: 2rem;
		}
		.rectangle1 {
			background-image: url('Assets/Login.png');
			width: 56%;
			display: flex;
			flex-direction: column;
			justify-content: center;
			align-items: center;
			color: #fafbff;
			text-align: center;
			padding: 2rem;
		}
		.welcomeback {
			font-size: 3rem;
			font-weight: bold;
			color: #1a396e;
			margin-bottom: 2rem;
		}
		form {
			width: 100%;
			max-width: 400px;
			text-align: center;
		}
		.form-control {
			margin-bottom: 1rem;
			padding: 1rem;
			font-size: 1rem;
			width: 100%;
			border: 1px solid #ccc;
			border-radius: 5px;
		}
		.forgotyourpassword-btn {
			font-size: 0.9rem;
			color: #686d76;
			display: block;
			margin-top: 5px;
			text-decoration: none;
			text-align: right;
		}
		.login-btn, .register-btn { 
			display: inline-block; /* Ensure it behaves like a block element */ 
			width: 100%; 
			padding: 10px; 
			font-size: 1.2rem; 
			border-radius: 5px; 
			border: none; 
			cursor: pointer; 
			margin-bottom: 10px; 
			text-align: center; /* Center the text */ 
			text-decoration: none; /* Remove underline */ 
			color: white;
		} 
		.login-btn { 
			background-color: #0000FE; 
			color: white; 
			margin-top: 10px;
		} 
		.login-btn:hover { 
			background-color: #0000b3; 
		} 
		.register-btn { 
			background-color: #860021; 
			color: white; 
			margin-top: 5px;
		} 
		.register-btn:hover { 
			background-color: #5a0016; 
		}
	</style>

</head>
<body>
    <div class="loginpage">
        <div class="rectangle45">
            <div class="welcomeback">Welcome Back!</div>
            <?php if (isset($error_message)): ?>
                <p class="text-danger"><?php echo $error_message; ?></p>
            <?php endif; ?>
            <form method="POST" action="login.php">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <a href="forgot_password.php" class="forgotyourpassword-btn">Forgot your password?</a>
                <button type="submit" class="login-btn">Login</button>
                <a href="register.php" class="register-btn">Register</a>

            </form>
        </div>
        <div class="rectangle1">

        </div>
    </div>
</body>
</html>
