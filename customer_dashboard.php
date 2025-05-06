<?php
include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$userOrders = [];
if (isset($_SESSION['cuid'])) {
    $cuid = $_SESSION['cuid'];
    $userOrderQuery = "
        SELECT o.oid, o.orderTime, o.totalPrice, o.status, o.paymentMethod
        FROM tblOrder o
        WHERE o.cuid = $cuid
        ORDER BY o.orderTime DESC
        LIMIT 5
    ";
    $userOrderResult = mysqli_query($connection, $userOrderQuery);
    if ($userOrderResult) {
        while ($row = mysqli_fetch_assoc($userOrderResult)) {
            $userOrders[] = $row;
        }
        mysqli_free_result($userOrderResult);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChowNow Customer Dashboard</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h2 class="dashboard-title">Customer Dashboard</h2>
                <p class="welcome-text">Welcome, <?php echo htmlspecialchars(isset($_SESSION['name']) ? $_SESSION['name'] : "User"); ?>!</p>
            </div>
            <a href="order.php" class="order-btn">Place Order</a>
        </div>
        
        <?php if (!empty($userOrders)): ?>
        <div class="recent-orders">
            <h3>Your Recent Orders</h3>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userOrders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['oid']; ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($order['orderTime'])); ?></td>
                        <td>$<?php echo number_format($order['totalPrice'], 2); ?></td>
                        <td><?php echo $order['paymentMethod']; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <p>No recent orders found.</p>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>