<?php
// Get the current page name (without the .php extension)
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>

<header>
    <div class="header-container">
        <div class="header-left">
            <img src="resources/logoChowNow.png" alt="ChowNow Logo" class="logo">
            <h1 class="brand-title">ChowNow</h1>
        </div>
        <nav class="nav-links">
            <ul>
                <li>
                    <a href="<?php echo $_SESSION['role'] === 'customer' ? 'customer_dashboard.php' : 'cashier_dashboard.php'; ?>"
                       class="<?php echo in_array($currentPage, ['customer_dashboard', 'cashier_dashboard']) ? 'active' : ''; ?>">
                        Dashboard
                    </a>
                </li>

                <?php if ($_SESSION['role'] === 'customer'): ?>
                    <li>
                        <a href="profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="view_orders.php" class="<?php echo $currentPage === 'view_orders' ? 'active' : ''; ?>">
                            My Orders
                        </a>
                    </li>
                <?php elseif ($_SESSION['role'] === 'cashier'): ?>
                    
                <?php endif; ?>

                <li>
                    <a href="logout.php" class="logout-link">Logout</a>
                </li>
            </ul>
        </nav>
    </div>
</header>