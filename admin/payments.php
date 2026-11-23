<?php
require_once __DIR__ . '/../config.php';
require_staff_or_admin();

$user = current_user();
$errors = [];
$methods = ['Cash', 'M-Pesa', 'Bank Transfer'];

// Orders that can be paid (any order; the form defaults the amount
// to the order total).
$orders = $pdo->query(
    'SELECT o.Order_id, o.Total_amount, c.Customer_name
     FROM Laundry_Order o
     JOIN Customer c ON c.Customer_id = o.Customer_id
     ORDER BY o.Order_id DESC'
)->fetchAll();

// --- Record a payment (manual record only; no real money is taken) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $amount = filter_var($_POST['amount'] ?? '', FILTER_VALIDATE_FLOAT);
    $method = trim($_POST['method'] ?? '');

    $orderOk = false;
    $orderTotal = null;
    foreach ($orders as $o) {
        if ((int) $o['Order_id'] === $orderId) {
            $orderOk = true;
            $orderTotal = (float) $o['Total_amount'];
        }
    }
    if (!$orderOk) { $errors[] = 'Please choose an order.'; }
    if ($amount === false || $amount <= 0) {
        $errors[] = 'Please enter an amount greater than 0.';
    }
    if (!in_array($method, $methods, true)) {
        $errors[] = 'Please choose a payment method.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO Payment
             (Order_id, Payment_amount, Payment_method, Payment_time, Payment_status)
             VALUES (?, ?, ?, NOW(), ?)'
        );
        $stmt->execute([$orderId, $amount, $method, 'Paid']);
        flash('success', 'Payment of ' . number_format($amount, 0)
            . ' TZS was recorded for order #' . $orderId . '.');
        redirect('payments.php');
    }
}

// All payments, newest first.
$payments = $pdo->query(
    'SELECT p.Payment_id, p.Payment_amount, p.Payment_method,
            p.Payment_time, p.Payment_status,
            o.Order_id, o.Total_amount, c.Customer_name
     FROM Payment p
     JOIN Laundry_Order o ON o.Order_id = p.Order_id
     JOIN Customer c ON c.Customer_id = o.Customer_id
     ORDER BY p.Payment_time DESC, p.Payment_id DESC'
)->fetchAll();

$totalCollected = 0.0;
foreach ($payments as $p) {
    if ($p['Payment_status'] === 'Paid') {
        $totalCollected += (float) $p['Payment_amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payments - Laundry Booking System</title>
<link rel="stylesheet" href="../css/style.css">
</head>
<body>
<header>
  <div class="wrap">
    <h1><a href="index.php">Laundry Booking System</a></h1>
    <nav>
      <a href="index.php">Dashboard</a>
      <a href="orders.php">Orders</a>
      <a href="payments.php">Payments</a>
      <a href="deliveries.php">Deliveries</a>
      <a href="reports.php">Reports</a>
      <?php if ($user['role'] === 'Admin'): ?>
        <a href="branches.php">Branches</a>
        <a href="employees.php">Employees</a>
        <a href="services.php">Services</a>
      <?php endif; ?>
      <a href="../logout.php">Logout</a>
      <span class="who">Hello, <?php echo e($user['name']); ?> (<?php echo e($user['role']); ?>)</span>
    </nav>
  </div>
</header>
<main><div class="wrap">

<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
  <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><?php echo e($err); ?></div>
<?php endforeach; ?>

<div class="card">
  <h2>Record a payment</h2>
  <p class="muted">This only records the payment on the system. No real money
  is taken. If a customer pays by M-Pesa, record it here manually after
  the money has been received.</p>
  <form method="post" action="payments.php">
    <div class="form-row">
      <div>
        <label for="order_id">Order</label>
        <select id="order_id" name="order_id" required>
          <option value="">-- Choose an order --</option>
          <?php foreach ($orders as $o): ?>
            <option value="<?php echo (int) $o['Order_id']; ?>">
              Order #<?php echo (int) $o['Order_id']; ?> &mdash;
              <?php echo e($o['Customer_name']); ?> &mdash;
              <?php echo number_format((float) $o['Total_amount'], 0); ?> TZS
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="amount">Amount (TZS)</label>
        <input type="number" id="amount" name="amount" min="1" step="0.01" required>
        <p class="help">Enter the amount received. It is usually the order total.</p>
      </div>
      <div>
        <label for="method">Payment method</label>
        <select id="method" name="method" required>
          <?php foreach ($methods as $m): ?>
            <option value="<?php echo e($m); ?>"><?php echo e($m); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="btn" type="submit" name="save">Record payment as Paid</button>
  </form>
</div>

<div class="card">
  <h2>All payments</h2>
  <p>Total collected (Paid): <strong><?php echo number_format($totalCollected, 0); ?> TZS</strong></p>
  <?php if ($payments): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>ID</th><th>Order</th><th>Customer</th><th>Amount</th>
        <th>Method</th><th>Time</th><th>Status</th>
      </tr>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td><?php echo (int) $p['Payment_id']; ?></td>
          <td>#<?php echo (int) $p['Order_id']; ?></td>
          <td><?php echo e($p['Customer_name']); ?></td>
          <td><?php echo number_format((float) $p['Payment_amount'], 0); ?> TZS</td>
          <td><?php echo e($p['Payment_method']); ?></td>
          <td><?php echo e($p['Payment_time']); ?></td>
          <td><span class="badge <?php echo e(status_class($p['Payment_status'])); ?>"><?php echo e($p['Payment_status']); ?></span></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No payments recorded yet.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
