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
    <style>
        body, html {
            height: 100%;
            margin: 0;
        }

        .orders-container {
            min-height: 100vh;
            padding: 20px;
            padding-bottom: 70px; /* Reserve space for footer */
            box-sizing: border-box;
        }

        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 20px;
            overflow-x: auto;
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

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th, .orders-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }

        .orders-table th {
            background-color: #f4f4f4;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
        }

        .status-pending {
            background-color: #FFA500;
        }

        .status-completed {
            background-color: #28a745;
        }

        .status-cancelled {
            background-color: #dc3545;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .alert {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
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