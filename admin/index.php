<?php
require_once __DIR__ . '/../config.php';
require_staff_or_admin();

$user = current_user();

// Counts for the dashboard.
$todayCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Laundry_Order WHERE Order_date = CURDATE()"
)->fetchColumn();
$readyCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Laundry_Order WHERE Order_status = 'Ready'"
)->fetchColumn();
$collected = (float) $pdo->query(
    "SELECT COALESCE(SUM(Payment_amount), 0) FROM Payment WHERE Payment_status = 'Paid'"
)->fetchColumn();
$pendingCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Laundry_Order WHERE Order_status = 'Pending'"
)->fetchColumn();

// Today's orders, newest first.
$stmt = $pdo->prepare(
    'SELECT o.Order_id, o.Order_date, o.Due_date, o.Order_status,
            o.Total_amount, c.Customer_name, b.Branch_name, s.Service_name,
            (SELECT COUNT(*) FROM Payment p
              WHERE p.Order_id = o.Order_id AND p.Payment_status = ? ) AS paid_count
     FROM Laundry_Order o
     JOIN Customer c ON c.Customer_id = o.Customer_id
     JOIN Branch b ON b.Branch_id = o.Branch_id
     LEFT JOIN Order_Item oi ON oi.Order_id = o.Order_id
     LEFT JOIN Service s ON s.Service_id = oi.Service_id
     WHERE o.Order_date = CURDATE()
     ORDER BY o.Order_id DESC'
);
$stmt->execute(['Paid']);
$todayOrders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Laundry Booking System</title>
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

<h2>Admin dashboard</h2>

<div class="grid">
  <div class="card"><div class="big"><?php echo $todayCount; ?></div>Orders today</div>
  <div class="card"><div class="big"><?php echo $pendingCount; ?></div>Pending orders</div>
  <div class="card"><div class="big"><?php echo $readyCount; ?></div>Orders ready</div>
  <div class="card"><div class="big"><?php echo number_format($collected, 0); ?> TZS</div>Amount collected (Paid)</div>
</div>

<div class="card">
  <h3>Today's orders</h3>
  <?php if ($todayOrders): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>Order</th><th>Customer</th><th>Branch</th><th>Service</th>
        <th>Due date</th><th>Total</th><th>Status</th><th>Payment</th>
      </tr>
      <?php foreach ($todayOrders as $o): ?>
        <tr>
          <td>#<?php echo (int) $o['Order_id']; ?></td>
          <td><?php echo e($o['Customer_name']); ?></td>
          <td><?php echo e($o['Branch_name']); ?></td>
          <td><?php echo e($o['Service_name'] ?? '-'); ?></td>
          <td><?php echo e($o['Due_date']); ?></td>
          <td><?php echo number_format((float) $o['Total_amount'], 0); ?> TZS</td>
          <td><span class="badge <?php echo e(status_class($o['Order_status'])); ?>"><?php echo e($o['Order_status']); ?></span></td>
          <td>
            <?php if ((int) $o['paid_count'] > 0): ?>
              <span class="badge badge-paid">Paid</span>
            <?php else: ?>
              <span class="badge badge-unpaid">Unpaid</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No orders today yet.</p>
  <?php endif; ?>
  <p><a class="btn" href="orders.php">View all orders</a></p>
</div>

</div></main>
</body>
</html>
