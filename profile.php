<?php
// Prevent caching of this page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$uid = $_SESSION['uid'];
$userQuery = "SELECT fname, lname, username, password FROM tbluser WHERE uid = $uid";
$userResult = mysqli_query($connection, $userQuery);
$user = mysqli_fetch_assoc($userResult);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fname = mysqli_real_escape_string($connection, $_POST['fname']);
    $lname = mysqli_real_escape_string($connection, $_POST['lname']);
    $username = mysqli_real_escape_string($connection, $_POST['username']);
    
    $checkUsernameQuery = "SELECT uid FROM tbluser WHERE username = '$username' AND uid != $uid";
    $checkResult = mysqli_query($connection, $checkUsernameQuery);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $error = "Username already taken!";
    } else {
        mysqli_begin_transaction($connection);
        try {
            $updateQuery = "UPDATE tbluser SET fname = '$fname', lname = '$lname', username = '$username' WHERE uid = $uid";
            if (!mysqli_query($connection, $updateQuery)) {
                throw new Exception("Error updating profile!");
            }
            mysqli_commit($connection);
            $success = "Profile updated successfully!";
            $_SESSION['name'] = "$fname $lname";
            $_SESSION['username'] = $username;
            $user = ['fname' => $fname, 'lname' => $lname, 'username' => $username, 'password' => $user['password']];
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = $e->getMessage();
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (!password_verify($currentPassword, $user['password'])) {
        $error = "Current password is incorrect!";
    } elseif (strlen($newPassword) < 6) {
        $error = "New password must be at least 6 characters long!";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match!";
    } else {
        mysqli_begin_transaction($connection);
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePasswordQuery = "UPDATE tbluser SET password = '$hashedPassword' WHERE uid = $uid";
            if (!mysqli_query($connection, $updatePasswordQuery)) {
                throw new Exception("Error updating password!");
            }
            mysqli_commit($connection);
            $success = "Password updated successfully!";
            $user['password'] = $hashedPassword;
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = $e->getMessage();
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
    <style>
        body, html {
    height: 50%;
    margin: 0;
}

.profile-container {
    min-height: 100%; /* This ensures that the container takes at least the full height of the page */
    padding-bottom: 50px; /* Give space for the footer */
}

footer {
    background-color: #3F7D58;
    color: white;
    text-align: center;
    padding: 10px;
    position: fixed;
    bottom: 0;
    width: 100%;
    left: 0;
    right: 0;
}

    </style>
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
                <input type="text" name="fname" id="fname" class="form-control" value="<?php echo htmlspecialchars($user['fname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="lname">Last Name</label>
                <input type="text" name="lname" id="lname" class="form-control" value="<?php echo htmlspecialchars($user['lname']); ?>" required>
            </div>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <button type="submit" name="update_profile" id="updateProfileBtn" class="btn-primary" disabled onclick="return confirm('Are you sure you want to update your profile?');">Update Profile</button>
        </form>

        <button class="btn-primary" id="showPasswordFormBtn">Change Password</button>
        <form method="post" class="password-form" id="passwordForm" style="display: none;">
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" name="update_password" class="btn-primary" onclick="return confirm('Are you sure you want to change your password?');">Change Password</button>
            <button type="button" class="btn-secondary" onclick="document.getElementById('passwordForm').style.display='none';">Cancel</button>
        </form>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Handle Change Password button
            const showPasswordFormBtn = document.getElementById('showPasswordFormBtn');
            const passwordForm = document.getElementById('passwordForm');
            showPasswordFormBtn.addEventListener('click', () => {
                passwordForm.style.display = passwordForm.style.display === 'none' ? 'block' : 'none';
            });

            // Handle dynamic Update Profile button
            const updateProfileBtn = document.getElementById('updateProfileBtn');
            const initialValues = {
                fname: document.getElementById('fname').value,
                lname: document.getElementById('lname').value,
                username: document.getElementById('username').value
            };

            function checkForChanges() {
                const currentValues = {
                    fname: document.getElementById('fname').value,
                    lname: document.getElementById('lname').value,
                    username: document.getElementById('username').value
                };
                const hasChanges = (
                    currentValues.fname !== initialValues.fname ||
                    currentValues.lname !== initialValues.lname ||
                    currentValues.username !== initialValues.username
                );
                updateProfileBtn.disabled = !hasChanges;
            }

            document.getElementById('fname').addEventListener('input', checkForChanges);
            document.getElementById('lname').addEventListener('input', checkForChanges);
            document.getElementById('username').addEventListener('input', checkForChanges);
        });
    </script>
</body>
</html>