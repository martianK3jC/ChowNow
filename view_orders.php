<?php
include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$cuid = $_SESSION['cuid'];

$query = "
    SELECT o.oid, o.orderTime, o.totalPrice, o.status, o.paymentMethod
    FROM tblOrder o
    WHERE o.cuid = $cuid
    ORDER BY o.orderTime DESC
";

$result = mysqli_query($connection, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Orders - ChowNow</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="order-history-container">
    <h2>My Orders</h2>
    <?php if (mysqli_num_rows($result) > 0): ?>
        <table class="order-history-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Date & Time</th>
                    <th>Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><?php echo $order['oid']; ?></td>
                        <td><?php echo $order['orderTime']; ?></td>
                        <td>$<?php echo number_format($order['totalPrice'], 2); ?></td>
                        <td><?php echo ucfirst($order['paymentMethod']); ?></td>
                        <td><?php echo ucfirst($order['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You have not placed any orders yet.</p>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
