<?php
include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'cashier') {
    header('Location: login.php');
    exit;
}

$orders = [];
$orderQuery = "
    SELECT o.oid, o.orderTime, o.totalPrice, o.status, o.paymentMethod, 
           CONCAT(u.fname, ' ', u.lname) as customer_name
    FROM tblOrder o
    JOIN tblCustomer c ON o.cuid = c.cuid
    JOIN tblUser u ON c.uid = u.uid
    WHERE o.status = 'pending'
    ORDER BY o.orderTime ASC
";
$orderResult = mysqli_query($connection, $orderQuery);
while ($row = mysqli_fetch_assoc($orderResult)) {
    $orders[] = $row;
}

if (isset($_POST['process_order'])) {
    $oid = (int)$_POST['oid'];
    $updateQuery = "UPDATE tblOrder SET status = 'resolved' WHERE oid = $oid";
    if (mysqli_query($connection, $updateQuery)) {
        $insertQuery = "INSERT INTO tblhandledorders (oid, handledAt) VALUES ($oid, NOW())";
        mysqli_query($connection, $insertQuery);
        header('Location: process_order.php');
        exit;
    } else {
        $error = "Error processing order";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Orders - ChowNow</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="process-container">
        <h2>Process Pending Orders</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (empty($orders)): ?>
            <p>No pending orders to process.</p>
        <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment Method</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['oid']; ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($order['orderTime'])); ?></td>
                            <td>$<?php echo number_format($order['totalPrice'], 2); ?></td>
                            <td><?php echo $order['paymentMethod']; ?></td>
                            <td>
                                <form method="post" class="process-order-form">
                                    <input type="hidden" name="oid" value="<?php echo $order['oid']; ?>">
                                    <button type="submit" name="process_order" class="btn-primary" onclick="return confirm('Are you sure you want to process order #<?php echo $order['oid']; ?>?');">Process</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>