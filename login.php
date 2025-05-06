<?php
include 'includes/connect.php';
session_start();

// Redirect if already logged in
if (isset($_SESSION['uid'])) {
    header('Location: ' . ($_SESSION['role'] === 'customer' ? 'customer_dashboard.php' : 'cashier_dashboard.php'));
    exit;
}

// Process login
if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($connection, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT u.*, CASE 
                WHEN u.role = 'customer' THEN c.cuid 
                WHEN u.role = 'cashier' THEN ca.caid 
                END AS specific_id 
              FROM tbluser u 
              LEFT JOIN tblcustomer c ON u.uid = c.uid 
              LEFT JOIN tblcashier ca ON u.uid = ca.uid 
              WHERE u.username = '$username'";
    
    $result = mysqli_query($connection, $query);
    
    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['uid'] = $user['uid'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['fname'] . ' ' . $user['lname'];
            
            if ($user['role'] === 'customer') {
                $_SESSION['cuid'] = $user['specific_id'];
            } else if ($user['role'] === 'cashier') {
                $_SESSION['caid'] = $user['specific_id'];
                $currentTime = date('Y-m-d H:i:s');
                $cashierId = $user['specific_id'];
                
                $checkQuery = "SELECT * FROM tblcashier WHERE caid = $cashierId AND timeOut IS NULL";
                $checkResult = mysqli_query($connection, $checkQuery);
                
                if (mysqli_num_rows($checkResult) === 0) {
                    $updateQuery = "UPDATE tblcashier SET timeIn = '$currentTime' WHERE caid = $cashierId";
                    mysqli_query($connection, $updateQuery);
                }
            }
            
            header('Location: ' . ($_SESSION['role'] === 'customer' ? 'customer_dashboard.php' : 'cashier_dashboard.php'));
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "Username not found!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChowNow - Login</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="login-container">
        <img src="resources/logoChowNow.png" alt="ChowNow Logo" class="login-logo">
        <h1>ChowNow</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="btn-primary">Login</button>
        </form>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>