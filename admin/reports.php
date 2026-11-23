<?php
require_once __DIR__ . '/../config.php';
require_staff_or_admin();

$user = current_user();

// The report date, defaulting to today.
$date = trim($_GET['date'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = date('Y-m-d');
}

// Orders placed on the chosen date.
$stmt = $pdo->prepare(
    'SELECT o.Order_id, o.Order_date, o.Due_date, o.Order_status,
            o.Total_amount, c.Customer_name, b.Branch_name, s.Service_name
     FROM Laundry_Order o
     JOIN Customer c ON c.Customer_id = o.Customer_id
     JOIN Branch b ON b.Branch_id = o.Branch_id
     LEFT JOIN Order_Item oi ON oi.Order_id = o.Order_id
     LEFT JOIN Service s ON s.Service_id = oi.Service_id
     WHERE o.Order_date = ?
     ORDER BY o.Order_id'
);
$stmt->execute([$date]);
$orders = $stmt->fetchAll();

$totalAmount = 0.0;
foreach ($orders as $o) {
    $totalAmount += (float) $o['Total_amount'];
}

// Money collected on the chosen date (payments recorded that day).
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(Payment_amount), 0) AS collected
     FROM Payment
     WHERE Payment_status = 'Paid' AND DATE(Payment_time) = ?"
);
$stmt->execute([$date]);
$collected = (float) $stmt->fetchColumn();

// Payments recorded on the chosen date, for the detail list.
$stmt = $pdo->prepare(
    'SELECT p.Payment_amount, p.Payment_method, p.Payment_time,
            o.Order_id, c.Customer_name
     FROM Payment p
     JOIN Laundry_Order o ON o.Order_id = p.Order_id
     JOIN Customer c ON c.Customer_id = o.Customer_id
     WHERE p.Payment_status = ? AND DATE(p.Payment_time) = ?
     ORDER BY p.Payment_time'
);
$stmt->execute(['Paid', $date]);
$payments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Report - Laundry Booking System</title>
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

<div class="card no-print">
  <h2>Daily report</h2>
  <form method="get" action="reports.php">
    <label for="date">Choose a date</label>
    <input type="date" id="date" name="date" value="<?php echo e($date); ?>" required>
    <button class="btn" type="submit">Show report</button>
  </form>
</div>

<div class="card">
  <h2>Daily Report &mdash; <?php echo e($date); ?></h2>
  <p>Laundry Booking System</p>

  <div class="grid">
    <div class="card"><div class="big"><?php echo count($orders); ?></div>Orders on this date</div>
    <div class="card"><div class="big"><?php echo number_format($totalAmount, 0); ?> TZS</div>Total value of orders</div>
    <div class="card"><div class="big"><?php echo number_format($collected, 0); ?> TZS</div>Amount collected (Paid)</div>
  </div>

  <h3>Orders</h3>
  <?php if ($orders): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>Order</th><th>Customer</th><th>Branch</th><th>Service</th>
        <th>Due date</th><th>Status</th><th>Total</th>
      </tr>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?php echo (int) $o['Order_id']; ?></td>
          <td><?php echo e($o['Customer_name']); ?></td>
          <td><?php echo e($o['Branch_name']); ?></td>
          <td><?php echo e($o['Service_name'] ?? '-'); ?></td>
          <td><?php echo e($o['Due_date']); ?></td>
          <td><span class="badge <?php echo e(status_class($o['Order_status'])); ?>"><?php echo e($o['Order_status']); ?></span></td>
          <td><?php echo number_format((float) $o['Total_amount'], 0); ?> TZS</td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No orders on this date.</p>
  <?php endif; ?>

  <h3>Payments received</h3>
  <?php if ($payments): ?>
    <div class="table-wrap"><table>
      <tr><th>Order</th><th>Customer</th><th>Method</th><th>Time</th><th>Amount</th></tr>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td>#<?php echo (int) $p['Order_id']; ?></td>
          <td><?php echo e($p['Customer_name']); ?></td>
          <td><?php echo e($p['Payment_method']); ?></td>
          <td><?php echo e($p['Payment_time']); ?></td>
          <td><?php echo number_format((float) $p['Payment_amount'], 0); ?> TZS</td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No payments recorded on this date.</p>
  <?php endif; ?>

  <p class="no-print"><button class="btn" type="button" onclick="window.print();">Print this report</button></p>
  <p class="muted">This is a simple on-screen report. It is not a Crystal or Jasper report.</p>
</div>

</div></main>
</body>
</html>
