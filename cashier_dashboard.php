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

$revenueQuery = "SELECT SUM(totalPrice) as total FROM tblOrder";
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChowNow Cashier Dashboard</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h2 class="dashboard-title">Cashier Dashboard</h2>
                <p class="welcome-text">Welcome, <?php echo htmlspecialchars(isset($_SESSION['name']) ? $_SESSION['name'] : "Cashier"); ?>!</p>
            </div>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-value"><?php echo $customerCount; ?></div>
                <div class="stat-label">Total Number of Customers</div>
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
            <h3>Filter Dashboard Data</h3>
            <form class="filter-form" method="GET">
                <div class="filter-group">
                    <label for="dateFrom">From Date</label>
                    <input type="date" id="dateFrom" name="dateFrom" class="filter-input" value="<?php echo isset($_GET['dateFrom']) ? $_GET['dateFrom'] : ''; ?>">
                </div>
                <div class="filter-group">
                    <label for="dateTo">To Date</label>
                    <input type="date" id="dateTo" name="dateTo" class="filter-input" value="<?php echo isset($_GET['dateTo']) ? $_GET['dateTo'] : ''; ?>">
                </div>
                <button type="submit" class="filter-btn">Apply Filter</button>
            </form>
        </div>
        
        <div class="charts-container">
            <div class="chart-card">
                <h3 class="chart-title">Orders Over Time</h3>
                <canvas id="ordersChart"></canvas>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">Best-Selling Foods</h3>
                <canvas id="bestsellerChart"></canvas>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">Popular Food Items</h3>
                <canvas id="foodChart"></canvas>
            </div>
        </div>
        
        <div class="pending-orders">
            <h3>Pending Orders</h3>
            <?php if (empty($pendingOrders)): ?>
                <p>No pending orders.</p>
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
                                <td><?php echo $order['paymentMethod']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="process_order.php" class="btn-primary">Process</a>
                                    <a href="process_order.php" class="btn-danger">Reject</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
    <?php include 'includes/footer.php'; ?>
    
    <script>
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
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        const foodCtx = document.getElementById('foodChart').getContext('2d');
        new Chart(foodCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(",", $foodLabels); ?>],
                datasets: [{
                    data: [<?php echo implode(",", $foodData); ?>],
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#8BC34A',
                        '#FF9800', '#9C27B0', '#00BCD4', '#E91E63',
                        '#3F51B5', '#CDDC39'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
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
                    borderColor: '#2st2E8BC0',
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