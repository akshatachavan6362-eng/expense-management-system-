<?php
include 'includes/db_connect.php';
session_start();

if(isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_type'] = $user['user_type'];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid credentials!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="assets/css/style.css">
    <title>Login</title>
</head>
<body>
    <div class="login-wrapper">
        <form method="POST" class="login-box">
            <h2>Login</h2>
            <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
            <input type="email" name="email" placeholder="Email" required>