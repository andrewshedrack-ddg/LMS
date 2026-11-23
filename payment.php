<?php
require_once __DIR__ . '/config.php';
require_customer();

$user = current_user();
$orderId = (int) ($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$errors = [];

// Load the order, and make sure it belongs to this customer.
$order = null;
if ($orderId > 0) {
    $stmt = $pdo->prepare(
        'SELECT o.Order_id, o.Order_date, o.Due_date, o.Order_status,
                o.Total_amount, b.Branch_name, s.Service_name
         FROM Laundry_Order o
         JOIN Branch b ON b.Branch_id = o.Branch_id
         LEFT JOIN Order_Item oi ON oi.Order_id = o.Order_id
         LEFT JOIN Service s ON s.Service_id = oi.Service_id
         WHERE o.Order_id = ? AND o.Customer_id = ?'
    );
    $stmt->execute([$orderId, $user['id']]);
    $order = $stmt->fetch();
}

if (!$order) {
    flash('error', 'Order not found.');
    redirect('my-bookings.php');
}

// Payments already recorded for this order.
$stmt = $pdo->prepare(
    'SELECT Payment_id, Payment_amount, Payment_method, Payment_time, Payment_status
     FROM Payment WHERE Order_id = ? ORDER BY Payment_time DESC'
);
$stmt->execute([$orderId]);
$payments = $stmt->fetchAll();

$alreadyPaid = false;
foreach ($payments as $p) {
    if ($p['Payment_status'] === 'Paid') { $alreadyPaid = true; }
}

$methods = ['Cash', 'M-Pesa', 'Bank Transfer'];
$amount = trim($_POST['amount'] ?? (string) $order['Total_amount']);
$method = trim($_POST['method'] ?? 'Cash');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyPaid) {
    $amountNum = filter_var($amount, FILTER_VALIDATE_FLOAT);
    if ($amountNum === false || $amountNum <= 0) {
        $errors[] = 'Please enter an amount greater than 0.';
    }
    if (!in_array($method, $methods, true)) {
        $errors[] = 'Please choose a payment method.';
    }
    if (!$errors) {
        // This only records the payment. No real money is taken.
        $stmt = $pdo->prepare(
            'INSERT INTO Payment
             (Order_id, Payment_amount, Payment_method, Payment_time, Payment_status)
             VALUES (?, ?, ?, NOW(), ?)'
        );
        $stmt->execute([$orderId, $amountNum, $method, 'Paid']);
        flash('success', 'Payment of ' . number_format($amountNum, 0)
            . ' TZS was recorded for order #' . $orderId . '.');
        redirect('payment.php?order_id=' . $orderId);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Record Payment - Laundry Booking System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
  <div class="wrap">
    <h1><a href="index.php">Laundry Booking System</a></h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="dashboard.php">My Dashboard</a>
      <a href="book.php">Book Laundry</a>
      <a href="my-bookings.php">My Bookings</a>
      <a href="logout.php">Logout</a>
      <span class="who">Hello, <?php echo e($user['name']); ?></span>
    </nav>
  </div>
</header>
<main><div class="wrap">

<div class="card">
  <h2>Record payment for order #<?php echo (int) $order['Order_id']; ?></h2>
  <p>
    Branch: <?php echo e($order['Branch_name']); ?><br>
    Service: <?php echo e($order['Service_name'] ?? '-'); ?><br>
    Order total: <strong><?php echo number_format((float) $order['Total_amount'], 0); ?> TZS</strong><br>
    Status: <span class="badge <?php echo e(status_class($order['Order_status'])); ?>"><?php echo e($order['Order_status']); ?></span>
  </p>
  <p class="muted">This only records the payment on the system. No real money
  is taken online. If you pay by M-Pesa, the payment is recorded here
  manually after you have paid.</p>

  <?php $flash = get_flash(); ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
  <?php endif; ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?php echo e($err); ?></div>
  <?php endforeach; ?>

  <?php if ($alreadyPaid): ?>
    <div class="alert alert-success">This order has already been paid.</div>
  <?php else: ?>
    <form method="post" action="payment.php?order_id=<?php echo (int) $order['Order_id']; ?>">
      <input type="hidden" name="order_id" value="<?php echo (int) $order['Order_id']; ?>">
      <label for="amount">Amount (TZS)</label>
      <input type="number" id="amount" name="amount" min="1" step="0.01"
             value="<?php echo e($amount); ?>" required>

      <label for="method">Payment method</label>
      <select id="method" name="method" required>
        <?php foreach ($methods as $m): ?>
          <option value="<?php echo e($m); ?>" <?php echo $method === $m ? 'selected' : ''; ?>>
            <?php echo e($m); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <button class="btn" type="submit">Record payment as Paid</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($payments): ?>
<div class="card">
  <h3>Payments for this order</h3>
  <div class="table-wrap"><table>
    <tr><th>Amount</th><th>Method</th><th>Time</th><th>Status</th></tr>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td><?php echo number_format((float) $p['Payment_amount'], 0); ?> TZS</td>
        <td><?php echo e($p['Payment_method']); ?></td>
        <td><?php echo e($p['Payment_time']); ?></td>
        <td><span class="badge <?php echo e(status_class($p['Payment_status'])); ?>"><?php echo e($p['Payment_status']); ?></span></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>

<p><a href="my-bookings.php">&larr; Back to my bookings</a></p>

</div></main>
</body>
</html>
