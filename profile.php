<?php
include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$uid = $_SESSION['uid'];
$userQuery = "SELECT fname, lname, username FROM tbluser WHERE uid = $uid";
$userResult = mysqli_query($connection, $userQuery);
$user = mysqli_fetch_assoc($userResult);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname = mysqli_real_escape_string($connection, $_POST['fname']);
    $lname = mysqli_real_escape_string($connection, $_POST['lname']);
    $username = mysqli_real_escape_string($connection, $_POST['username']);
    
    // Check if username is already taken by another user
    $checkUsernameQuery = "SELECT uid FROM tbluser WHERE username = '$username' AND uid != $uid";
    $checkResult = mysqli_query($connection, $checkUsernameQuery);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $error = "Username already taken!";
    } else {
        $updateQuery = "UPDATE tbluser SET fname = '$fname', lname = '$lname', username = '$username' WHERE uid = $uid";
        if (mysqli_query($connection, $updateQuery)) {
            $success = "Profile updated successfully!";
            // Update session name
            $_SESSION['name'] = "$fname $lname";
            $_SESSION['username'] = $username;
            // Refresh user data
            $user = ['fname' => $fname, 'lname' => $lname, 'username' => $username];
        } else {
            $error = "Error updating profile!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ChowNow</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="profile-container">
        <h2>My Profile</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" class="profile-form" id="profileForm">
            <div class="form-group">
                <label for="fname">First Name</label>
                <input type="text" name="fname" class="form-control" value="<?php echo htmlspecialchars($user['fname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="lname">Last Name</label>
                <input type="text" name="lname" class="form-control" value="<?php echo htmlspecialchars($user['lname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <button type="submit" name="update_profile" class="btn-primary" onclick="return confirm('Are you sure you want to update your profile?');">Update Profile</button>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>