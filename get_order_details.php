<?php
header('Content-Type: application/json');
include 'includes/connect.php';

$oid = isset($_GET['oid']) ? (int)$_GET['oid'] : 0;
$response = ['items' => [], 'error' => null];

if ($oid <= 0) {
    $response['error'] = 'Invalid order ID';
    echo json_encode($response);
    exit;
}

$query = "SELECT od.quantity, od.linePrice, f.foodName
          FROM tblOrderDetails od
          JOIN tblFood f ON od.fid = f.fid
          WHERE od.oid = ?";
$stmt = mysqli_prepare($connection, $query);
mysqli_stmt_bind_param($stmt, "i", $oid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $response['items'][] = $row;
    }
} else {
    $response['error'] = 'Failed to fetch order details: ' . mysqli_error($connection);
    error_log("Order details query failed for oid=$oid: " . mysqli_error($connection));
}

mysqli_stmt_close($stmt);
echo json_encode($response);
?>