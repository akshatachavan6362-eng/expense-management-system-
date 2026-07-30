<?php
include 'includes/db_connect.php';

if(isset($_POST['register'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $password]);
        $success = "Registration successful! Please login.";
    } catch(PDOException $e) {
        if($e->errorInfo[1] == 1062) {
            $error = "Email already registered!";
        } else {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Register</title>
</head>
<body>
    <div class="login-wrapper">
        <form method="POST" class="login-box">
            <h2><i class="fas fa-user-plus"></i> Create Account</h2>
            <?php 
                if(isset($error)) echo "<p style='color:red; margin-bottom: 15px;'>$error</p>";
                if(isset($success)) echo "<p style='color:green; margin-bottom: 15px;'>$success</p>";
            ?>
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="register">Register</button>
            <p style="margin-top: 20px;">Already have an account? <a href="login.php" style="color: #3498db;">Login here</a></p>
        </form>
    </div>
</body>
</html>