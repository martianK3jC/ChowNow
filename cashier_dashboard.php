<?php
include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'cashier') {
    header('Location: login.php');
    exit;
}

$whereClause = '';
if (!empty($_GET['dateFrom']) && !empty($_GET['dateTo'])) {
    $dateFrom = mysqli_real_escape_string($connection, $_GET['dateFrom']);
    $dateTo = mysqli_real_escape_string($connection, $_GET['dateTo']);
    $whereClause = " WHERE o.orderTime BETWEEN '$dateFrom 00:00:00' AND '$dateTo 23:59:59'";
}

$chartQuery = "
    SELECT DATE(orderTime) as orderDate, COUNT(*) as orderCount
    FROM tblOrder o
    $whereClause
    GROUP BY DATE(orderDate)
    ORDER BY orderDate
    LIMIT 30
";
$chartLabels = [];
$chartData = [];
$chartResult = mysqli_query($connection, $chartQuery);
if ($chartResult) {
    while ($row = mysqli_fetch_assoc($chartResult)) {
        $chartLabels[] = "'" . $row['orderDate'] . "'";
        $chartData[] = $row['orderCount'];
    }
    mysqli_free_result($chartResult);
}

$foodChartQuery = "
    SELECT f.foodName, SUM(od.quantity) as totalQty
    FROM tblOrderDetails od
    JOIN tblFood f ON od.fid = f.fid
    JOIN tblOrder o ON od.oid = o.oid
    $whereClause
    GROUP BY od.fid
    ORDER BY totalQty DESC
    LIMIT 10
";
$foodLabels = [];
$foodData = [];
$foodResult = mysqli_query($connection, $foodChartQuery);
if ($foodResult) {
    while ($row = mysqli_fetch_assoc($foodResult)) {
        $foodLabels[] = "'" . $row['foodName'] . "'";
        $foodData[] = $row['totalQty'];
    }
    mysqli_free_result($foodResult);
}

$userQuery = "SELECT COUNT(*) as total FROM tbluser WHERE role = 'customer'";
$userResult = mysqli_query($connection, $userQuery);
$customerCount = $userResult ? mysqli_fetch_assoc($userResult)['total'] : 0;
mysqli_free_result($userResult);

$orderQuery = "SELECT COUNT(*) as total FROM tblOrder";
$orderResult = mysqli_query($connection, $orderQuery);
$orderCount = $orderResult ? mysqli_fetch_assoc($orderResult)['total'] : 0;
mysqli_free_result($orderResult);

$revenueQuery = "SELECT SUM(totalPrice) as total FROM tblOrder WHERE status = 'resolved'";
$revenueResult = mysqli_query($connection, $revenueQuery);
$totalRevenue = $revenueResult ? mysqli_fetch_assoc($revenueResult)['total'] ?: 0 : 0;
mysqli_free_result($revenueResult);

$pendingOrders = [];
$pendingQuery = "
    SELECT o.oid, o.orderTime, o.totalPrice, o.status, o.paymentMethod, 
           CONCAT(u.fname, ' ', u.lname) as customer_name
    FROM tblOrder o
    JOIN tblCustomer c ON o.cuid = c.cuid
    JOIN tblUser u ON c.uid = u.uid
    WHERE o.status = 'pending'
    ORDER BY o.orderTime ASC
    LIMIT 100
";
$pendingResult = mysqli_query($connection, $pendingQuery);
if ($pendingResult) {
    while ($row = mysqli_fetch_assoc($pendingResult)) {
        $pendingOrders[] = $row;
    }
    mysqli_free_result($pendingResult);
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
            header('Location: cashier_dashboard.php');
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
            header('Location: cashier_dashboard.php');
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
    <title>ChowNow Cashier Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        header {
    background-color: #3F7D58;
    padding: 10px 20px;
    color: white;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.header-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.logo {
    width: 50px;
    height: auto;
}

.brand-title {
    font-size: 1.8rem;
    margin: 0;
}

.nav-links ul {
    list-style: none;
    display: flex;
    gap: 15px;
    padding: 0;
    margin: 0;
}

.nav-links ul li a {
    text-decoration: none;
    color: white;
    font-weight: 500;
    padding: 8px 12px;
    border-radius: 5px;
    transition: background 0.3s ease;
}

.nav-links ul li a.active,
.nav-links ul li a:hover {
    background-color: #345c44;
}

body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f9f9f9;
}

.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.dashboard-header {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    text-align: center;
    margin-bottom: 30px;
}

.dashboard-title {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.welcome-text {
    font-size: 18px;
    color: #666;
}

.stat-card {
    background-color: #fff;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    margin: 10px;
    flex: 1;
}

.stats-container {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
}

.stat-value {
    font-size: 24px;
    font-weight: bold;
}

.stat-label {
    font-size: 14px;
    color: #777;
}

.filter-container {
    margin-top: 30px;
    margin-bottom: 30px;
}

.filter-form {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-input {
    padding: 6px;
}

.filter-btn {
    padding: 8px 12px;
    background-color: #3F7D58;
    color: white;
    border: none;
    cursor: pointer;
    border-radius: 5px;
}

.charts-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 30px;
}

.chart-card {
    background-color: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    flex: 1;
    min-width: 300px;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.orders-table th,
.orders-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: center;
}

.btn-primary, .btn-danger {
    padding: 5px 10px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    color: white;
}

.btn-primary {
    background-color: #4CAF50;
}

.btn-danger {
    background-color: #f44336;
}

.status-badge {
    padding: 5px 10px;
    border-radius: 5px;
    color: white;
}

.status-pending {
    background-color: #FFA000;
}

.status-processing {
    background-color: #29B6F6;
}

.status-completed {
    background-color: #4CAF50;
}

.status-rejected {
    background-color: #f44336;
}

footer {
    background-color: #3F7D58;
    color: white;
    text-align: center;
    padding: 10px;
    position: relative;
    bottom: 0;
    width: 100%;
}

.modal {
    display: flex;
    justify-content: center; /* Centers horizontally */
    align-items: center;     /* Centers vertically */
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5); /* dark overlay background */
    z-index: 1000;
}

.modal-content {
    background-color: white;
    padding: 20px;
    border-radius: 8px;
    max-width: 600px;
    width: 100%;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    box-sizing: border-box; /* Ensures padding doesn't overflow width */
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative; /* Ensures close button positions correctly */
}

.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 20px;
    color: #aaa;
    cursor: pointer;
}

.close-btn:hover {
    color: #333;
}

.order-details-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    margin-bottom: 20px; /* Space between table and buttons */
    text-align: left; /* Left-align text in the table */
}

.order-details-table th,
.order-details-table td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left; /* Left-align table contents */
}

.popup-actions {
    display: flex;
    justify-content: center; /* Centers buttons horizontally */
    gap: 10px;
}


@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="dashboard-container">
    <div class="dashboard-header">
        <h1 class="dashboard-title">Cashier Dashboard</h1>
        <p class="welcome-text">Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Cashier'); ?>!</p>
    </div>

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
            <span class="close-btn" onclick="closeOrderPopup()">X</span>
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
                <form method="post" action="cashier_dashboard.php">
                    <input type="hidden" name="oid" id="popupOid">
                    <button type="submit" name="confirm_order" class="btn-primary" id="confirmButton">Confirm</button>
                </form>
                <form method="post" action="cashier_dashboard.php">
                    <input type="hidden" name="oid" id="popupOidReject">
                    <button type="submit" name="reject_order" class="btn-danger" id="rejectButton">Reject</button>
                </form>
            </div>
        </div>
    </div>

    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-value"><?php echo $customerCount; ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $orderCount; ?></div>
            <div class="stat-label">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
            <div class="stat-label">Total Revenue</div>
        </div>
    </div>

    <div class="filter-container">
        <h2>Filter Dashboard Data</h2>
        <form class="filter-form" method="GET">
            <div class="filter-group">
                <label for="dateFrom">From Date</label>
                <input type="date" id="dateFrom" name="dateFrom" class="filter-input" value="<?php echo $_GET['dateFrom'] ?? ''; ?>">
            </div>
            <div class="filter-group">
                <label for="dateTo">To Date</label>
                <input type="date" id="dateTo" name="dateTo" class="filter-input" value="<?php echo $_GET['dateTo'] ?? ''; ?>">
            </div>
            <button type="submit" class="filter-btn">Apply Filter</button>
        </form>
    </div>

    <div class="charts-container">
        <div class="chart-card">
            <h3>Orders Over Time</h3>
            <canvas id="ordersChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Best-Selling Foods</h3>
            <canvas id="bestsellerChart"></canvas>
        </div>
        <div class="chart-card">
            <h3>Popular Food Items</h3>
            <canvas id="foodChart"></canvas>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
    function showOrderPopup(oid, customer, date, total, payment) {
            document.getElementById('orderPopup').style.display = 'flex';
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

    const ordersCtx = document.getElementById('ordersChart').getContext('2d');
    new Chart(ordersCtx, {
        type: 'line',
        data: {
            labels: [<?php echo implode(",", $chartLabels); ?>],
            datasets: [{
                label: 'Orders',
                data: [<?php echo implode(",", $chartData); ?>],
                borderColor: '#3F7D58',
                backgroundColor: 'rgba(63, 125, 88, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });

    const foodCtx = document.getElementById('foodChart').getContext('2d');
    new Chart(foodCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo implode(",", $foodLabels); ?>],
            datasets: [{
                data: [<?php echo implode(",", $foodData); ?>],
                backgroundColor: ['#FF6384','#36A2EB','#FFCE56','#8BC34A','#FF9800','#9C27B0','#00BCD4','#E91E63','#3F51B5','#CDDC39']
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
    });

    const bestsellerCtx = document.getElementById('bestsellerChart').getContext('2d');
    new Chart(bestsellerCtx, {
        type: 'bar',
        data: {
            labels: [<?php echo implode(",", $foodLabels); ?>],
            datasets: [{
                label: 'Units Sold',
                data: [<?php echo implode(",", $foodData); ?>],
                backgroundColor: '#36A2EB',
                borderColor: '#2E8BC0',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Quantity Sold' } },
                x: { title: { display: true, text: 'Food Item' } }
            },
            plugins: {
                legend: { display: false },
                title: { display: true, text: 'Top Best-Sellers' }
            }
        }
    });
</script>
</body>
</html>
