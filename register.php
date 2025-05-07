<?php
include 'includes/connect.php';

// Redirect if already logged in
if (isset($_SESSION['uid'])) {
    header('Location: dashboard');
    exit;
}

$success = false;
$errors = [];

if (isset($_POST['register'])) {
    $fname = mysqli_real_escape_string($connection, $_POST['fname']);
    $lname = mysqli_real_escape_string($connection, $_POST['lname']);
    $username = mysqli_real_escape_string($connection, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Server-side validation
    if (empty($fname) || empty($lname) || empty($username) || empty($password)) {
        $errors[] = "All fields are required";
    }
    if (strlen($username) < 4) {
        $errors[] = "Username must be at least 4 characters long";
    }
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    $check_query = "SELECT * FROM tbluser WHERE username = '$username'";
    $check_result = mysqli_query($connection, $check_query);
    if (mysqli_num_rows($check_result) > 0) {
        $errors[] = "Username already exists";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        mysqli_begin_transaction($connection);
        
        try {
            $user_query = "INSERT INTO tbluser (fname, lname, username, password, role) 
                          VALUES ('$fname', '$lname', '$username', '$hashed_password', 'customer')";
            if (mysqli_query($connection, $user_query)) {
                $uid = mysqli_insert_id($connection);
                $customer_query = "INSERT INTO tblcustomer (uid) VALUES ($uid)";
                if (mysqli_query($connection, $customer_query)) {
                    mysqli_commit($connection);
                    $success = true;
                } else {
                    throw new Exception("Error creating customer account");
                }
            }
        }
            catch (Exception $e) {
            mysqli_rollback($connection);
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChowNow - Register</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="register-container">
        <img src="resources/logoChowNow.png" alt="ChowNow Logo" class="register-logo">
        <h1>Create an Account</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                Registration successful!
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
            <form method="post" action="" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="fname">First Name</label>
                        <input type="text" class="form-control" id="fname" name="fname" value="<?php echo isset($_POST['fname']) ? $_POST['fname'] : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" class="form-control" id="lname" name="lname" value="<?php echo isset($_POST['lname']) ? $_POST['lname'] : ''; ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo isset($_POST['username']) ? $_POST['username'] : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="register" class="btn-primary">Register</button>
            </form>
        <?php endif; ?>
        
        <div class="login-link">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</body>
</html>