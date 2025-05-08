<?php
// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$success = null;
$error = null;

// Fetch customer orders
$orders = [];
$cuid = $_SESSION['cuid'];
$query = "SELECT oid, orderTime, totalPrice, status, paymentMethod 
          FROM tblOrder 
          WHERE cuid = ? 
          ORDER BY orderTime DESC";
$stmt = mysqli_prepare($connection, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $cuid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $error = "Failed to load orders.";
}

// Handle cancel order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $oid = (int)$_POST['oid'];
    $stmt = mysqli_prepare($connection, "UPDATE tblOrder SET status = 'cancelled' WHERE oid = ? AND status = 'pending'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $oid);
        mysqli_begin_transaction($connection);
        try {
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) == 0) {
                throw new Exception("Order not pending or not found");
            }
            mysqli_commit($connection);
            $success = "Order #$oid cancelled!";
            header('Location: view_orders.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = $e->getMessage();
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Failed to cancel order.";
    }
}

// Close connection
mysqli_close($connection);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>My Orders - ChowNow</title>
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
    <div class="orders-container">
        <h2>My Orders</h2>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <p>You have no orders.</p>
        <?php else: ?>
            <div class="card">
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr data-oid="<?php echo $order['oid']; ?>">
                                <td>#<?php echo $order['oid']; ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($order['orderTime'])); ?></td>
                                <td>$<?php echo number_format($order['totalPrice'], 2); ?></td>
                                <td><?php echo htmlspecialchars($order['paymentMethod']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <form method="post">
                                            <input type="hidden" name="oid" value="<?php echo $order['oid']; ?>">
                                            <button type="submit" name="cancel_order" class="btn-danger">Cancel Order</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
