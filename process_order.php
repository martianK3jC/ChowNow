<?php
// Prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'cashier') {
    header('Location: login.php');
    exit;
}

// Debug: Log status schema
$statusQuery = "SHOW COLUMNS FROM tblOrder LIKE 'status'";
$statusResult = mysqli_query($connection, $statusQuery);
if ($statusResult) {
    $statusInfo = mysqli_fetch_assoc($statusResult);
    error_log("tblOrder.status column info: " . json_encode($statusInfo));
}
mysqli_free_result($statusResult);

// Fetch pending orders
$pendingOrders = [];
$pendingQuery = "SELECT o.oid, o.orderTime, o.totalPrice, o.status, o.paymentMethod, 
                        CONCAT(u.fname, ' ', u.lname) as customer_name
                 FROM tblOrder o
                 JOIN tblCustomer c ON o.cuid = c.cuid
                 JOIN tblUser u ON c.uid = u.uid
                 WHERE o.status = 'pending'
                 ORDER BY o.orderTime ASC";
$stmt = mysqli_prepare($connection, $pendingQuery);
if ($stmt) {
    mysqli_stmt_execute($stmt);
    $pendingResult = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($pendingResult)) {
        $pendingOrders[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    error_log("Pending query prepare failed: " . mysqli_error($connection));
    $error = "Failed to load pending orders.";
}

// Handle confirm order (pop-up Confirm button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    $oid = (int)$_POST['oid'];
    $stmt = mysqli_prepare($connection, "UPDATE tblOrder SET status = 'resolved' WHERE oid = ? AND status = 'pending'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $oid);
        mysqli_begin_transaction($connection);
        try {
            mysqli_stmt_execute($stmt);
            if (mysqli_stmt_affected_rows($stmt) == 0) {
                throw new Exception("Order not pending or not found");
            }
            mysqli_commit($connection);
            $success = "Order #$oid is now resolved!";
            header('Location: process_order.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = $e->getMessage();
            error_log("Confirm order error oid=$oid: " . $e->getMessage());
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Failed to confirm order.";
        error_log("Confirm order prepare failed oid=$oid: " . mysqli_error($connection));
    }
}

// Handle reject order (pop-up Reject button)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_order'])) {
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
            $success = "Order #$oid rejected!";
            header('Location: process_order.php');
            exit;
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = $e->getMessage();
            error_log("Reject order error oid=$oid: " . $e->getMessage());
        }
        mysqli_stmt_close($stmt);
    } else {
        $error = "Failed to reject order.";
        error_log("Reject order prepare failed oid=$oid: " . mysqli_error($connection));
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
    <title>Process Orders - ChowNow</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        /* Main content area takes up all available space */
        .process-container {
            flex-grow: 1;
        }

        /* Footer stays at the bottom */
        footer {
            margin-top: auto; /* Push footer to the bottom */
        }
        .loading::after {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="process-container">
        <h2>Process Pending Orders</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (empty($pendingOrders)): ?>
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
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingOrders as $order): ?>
                        <tr data-oid="<?php echo $order['oid']; ?>">
                            <td>#<?php echo $order['oid']; ?></td>
                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($order['orderTime'])); ?></td>
                            <td>$<?php echo number_format($order['totalPrice'], 2); ?></td>
                            <td><?php echo htmlspecialchars($order['paymentMethod']); ?></td>
                            <td>
                                <span class="status-badge status-pending">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn-primary" onclick="showOrderPopup(<?php echo $order['oid']; ?>, '<?php echo addslashes(htmlspecialchars($order['customer_name'])); ?>', '<?php echo date('M d, Y H:i', strtotime($order['orderTime'])); ?>', '<?php echo number_format($order['totalPrice'], 2); ?>', '<?php echo addslashes(htmlspecialchars($order['paymentMethod'])); ?>')">Process Order</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Process Order Pop-Up -->
    <div id="orderPopup" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close-btn" onclick="closeOrderPopup()">×</span>
            <h3>Process Order</h3>
            <p><strong>Order ID:</strong> <span id="popupOrderId"></span></p>
            <p><strong>Customer:</strong> <span id="popupCustomer"></span></p>
            <p><strong>Date:</strong> <span id="popupDate"></span></p>
            <p><strong>Total:</strong> $<span id="popupTotal"></span></p>
            <p><strong>Payment Method:</strong> <span id="popupPayment"></span></p>
            <h4>Items:</h4>
            <table class="order-details-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Quantity</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody id="popupItems">
                </tbody>
            </table>
            <div class="popup-actions">
                <form method="post" action="process_order.php" style="display: inline;">
                    <input type="hidden" name="oid" id="popupOid">
                    <button type="submit" name="confirm_order" class="btn-primary" id="confirmButton">Confirm</button>
                </form>
                <form method="post" action="process_order.php" style="display: inline;">
                    <input type="hidden" name="oid" id="popupOidReject">
                    <button type="submit" name="reject_order" class="btn-danger" id="rejectButton">Reject</button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script>
        function showOrderPopup(oid, customer, date, total, payment) {
            document.getElementById('orderPopup').style.display = 'block';
            document.getElementById('popupOrderId').innerText = '#' + oid;
            document.getElementById('popupCustomer').innerText = customer;
            document.getElementById('popupDate').innerText = date;
            document.getElementById('popupTotal').innerText = total;
            document.getElementById('popupPayment').innerText = payment;
            document.getElementById('popupOid').value = oid;
            document.getElementById('popupOidReject').value = oid;

            const itemsBody = document.getElementById('popupItems');
            itemsBody.innerHTML = '<tr><td colspan="3">Loading items...</td></tr>';
            fetch('get_order_details.php?oid=' + oid)
                .then(response => {
                    if (!response.ok) throw new Error('Network response not ok');
                    return response.json();
                })
                .then(data => {
                    itemsBody.innerHTML = '';
                    if (data.error) {
                        itemsBody.innerHTML = '<tr><td colspan="3">' + data.error + '</td></tr>';
                    } else if (data.items.length === 0) {
                        itemsBody.innerHTML = '<tr><td colspan="3">No items found</td></tr>';
                    } else {
                        data.items.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.foodName}</td>
                                <td>${item.quantity}</td>
                                <td>$${parseFloat(item.linePrice).toFixed(2)}</td>
                            `;
                            itemsBody.appendChild(row);
                        });
                    }
                })
                .catch(error => {
                    itemsBody.innerHTML = '<tr><td colspan="3">Failed to load items</td></tr>';
                    console.error('Error fetching order details:', error);
                });
        }

        function closeOrderPopup() {
            document.getElementById('orderPopup').style.display = 'none';
        }

        document.getElementById('confirmButton')?.addEventListener('submit', function() {
            this.disabled = true;
            this.classList.add('loading');
            this.innerText = 'Confirming...';
        });

        document.getElementById('rejectButton')?.addEventListener('submit', function() {
            this.disabled = true;
            this.classList.add('loading');
            this.innerText = 'Rejecting...';
        });
    </script>
</body>
</html>