<?php
include 'includes/connect.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['role'] !== 'customer') {
    header('Location: login.php');
    exit;
}

$categories = [];
$foods = [];
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
$success = null;
$error = null;

$categoryQuery = "SELECT DISTINCT category FROM tblfood WHERE category IS NOT NULL";
$categoryResult = mysqli_query($connection, $categoryQuery);
while ($row = mysqli_fetch_assoc($categoryResult)) {
    $categories[] = $row['category'];
}

$foodQuery = "SELECT * FROM tblfood ORDER BY category, foodName";
$foodResult = mysqli_query($connection, $foodQuery);
while ($row = mysqli_fetch_assoc($foodResult)) {
    $foods[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $fid = (int)$_POST['fid'];
    $quantity = (int)$_POST['quantity'];
    
    if ($quantity > 0) {
        $foodQuery = "SELECT * FROM tblfood WHERE fid = $fid";
        $foodResult = mysqli_query($connection, $foodQuery);
        if ($food = mysqli_fetch_assoc($foodResult)) {
            $cart[$fid] = [
                'foodName' => $food['foodName'],
                'price' => $food['price'],
                'quantity' => isset($cart[$fid]) ? $cart[$fid]['quantity'] + $quantity : $quantity
            ];
            $_SESSION['cart'] = $cart;
        }
    }
    header('Location: order.php');
    exit;
}

if (isset($_POST['remove_item'])) {
    $fid = (int)$_POST['fid'];
    unset($cart[$fid]);
    $_SESSION['cart'] = $cart;
    header('Location: order.php');
    exit;
}

if (isset($_POST['checkout'])) {
    $paymentMethod = mysqli_real_escape_string($connection, $_POST['payment_method']);
    $cuid = $_SESSION['cuid'];
    
    if (empty($cart)) {
        $error = "Cart is empty!";
    } else {
        $totalPrice = 0;
        foreach ($cart as $item) {
            $totalPrice += $item['price'] * $item['quantity'];
        }
        
        mysqli_begin_transaction($connection);
        try {
            $orderQuery = "INSERT INTO tblOrder (cuid, caid, orderTime, totalPrice, status, paymentMethod) 
                          VALUES ($cuid, 1, NOW(), $totalPrice, 'pending', '$paymentMethod')";
            if (mysqli_query($connection, $orderQuery)) {
                $oid = mysqli_insert_id($connection);
                foreach ($cart as $fid => $item) {
                    $linePrice = $item['price'] * $item['quantity'];
                    $quantity = $item['quantity'];
                    $detailQuery = "INSERT INTO tblorderdetails (oid, fid, linePrice, quantity) 
                                   VALUES ($oid, $fid, $linePrice, $quantity)";
                    if (!mysqli_query($connection, $detailQuery)) {
                        throw new Exception("Error adding order details");
                    }
                }
                mysqli_commit($connection);
                unset($_SESSION['cart']);
                $success = "Order placed successfully!";
                header('Location: view_orders.php');
                exit;
            } else {
                throw new Exception("Error creating order");
            }
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - ChowNow</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="order-container">
        <h2>Place Your Order</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="menu-container">
            <div class="menu-filters">
                <button class="filter-btn active" data-category="all">All</button>
                <?php foreach ($categories as $category): ?>
                    <button class="filter-btn" data-category="<?php echo htmlspecialchars($category); ?>">
                        <?php echo htmlspecialchars($category); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="menu-items">
                <?php foreach ($foods as $food): ?>
                    <div class="menu-item" data-category="<?php echo htmlspecialchars($food['category']); ?>">
                        <h4><?php echo htmlspecialchars($food['foodName']); ?></h4>
                        <p>$<?php echo number_format($food['price'], 2); ?></p>
                        <form method="post" class="add-to-cart-form">
                            <input type="hidden" name="fid" value="<?php echo $food['fid']; ?>">
                            <input type="number" name="quantity" min="1" value="1" class="quantity-input">
                            <button type="submit" name="add_to_cart" class="btn-primary">Add to Order</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="cart-container">
            <h3>Your Order</h3>
            <?php if (empty($cart)): ?>
                <p>Your order is empty.</p>
            <?php else: ?>
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $total = 0; ?>
                        <?php foreach ($cart as $fid => $item): ?>
                            <?php $subtotal = $item['price'] * $item['quantity']; $total += $subtotal; ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['foodName']); ?></td>
                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>$<?php echo number_format($subtotal, 2); ?></td>
                                <td>
                                    <form method="post" class="remove-item-form">
                                        <input type="hidden" name="fid" value="<?php echo $fid; ?>">
                                        <button type="submit" name="remove_item" class="btn-danger" onclick="return confirm('Are you sure you want to remove <?php echo htmlspecialchars($item['foodName']); ?> from your cart?');">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Total</strong></td>
                            <td colspan="2">$<?php echo number_format($total, 2); ?></td>
                        </tr>
                    </tfoot>
                </table>
                
                <form method="post" id="checkoutForm" class="checkout-form">
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="credit">Credit Card</option>
                            <option value="debit">Debit Card</option>
                        </select>
                    </div>
                    <button type="submit" name="checkout" class="btn-primary" onclick="return confirm('Are you sure you want to place this order for $<?php echo number_format($total, 2); ?>?');">Checkout</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="js/script.js"></script>
</body>
</html>