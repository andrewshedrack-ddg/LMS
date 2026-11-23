<?php
require_once __DIR__ . '/config.php';
require_customer();

$user = current_user();

// The logged-in customer's own orders, newest first, with the
// service name and a note if the order has been paid.
$stmt = $pdo->prepare(
    'SELECT o.Order_id, o.Order_date, o.Due_date, o.Order_status,
            o.Total_amount, b.Branch_name, s.Service_name,
            (SELECT COUNT(*) FROM Payment p
              WHERE p.Order_id = o.Order_id AND p.Payment_status = ? ) AS paid_count
     FROM Laundry_Order o
     JOIN Branch b ON b.Branch_id = o.Branch_id
     LEFT JOIN Order_Item oi ON oi.Order_id = o.Order_id
     LEFT JOIN Service s ON s.Service_id = oi.Service_id
     WHERE o.Customer_id = ?
     ORDER BY o.Order_date DESC, o.Order_id DESC'
);
$stmt->execute(['Paid', $user['id']]);
$orders = $stmt->fetchAll();

// Simple counts for the top of the page.
$counts = ['total' => 0, 'pending' => 0, 'ready' => 0];
foreach ($orders as $o) {
    $counts['total']++;
    if ($o['Order_status'] === 'Pending') { $counts['pending']++; }
    if ($o['Order_status'] === 'Ready')   { $counts['ready']++; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Dashboard - Laundry Booking System</title>
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

<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
  <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
<?php endif; ?>

<h2>My Dashboard</h2>

<div class="grid">
  <div class="card"><div class="big"><?php echo (int) $counts['total']; ?></div>My orders</div>
  <div class="card"><div class="big"><?php echo (int) $counts['pending']; ?></div>Pending orders</div>
  <div class="card"><div class="big"><?php echo (int) $counts['ready']; ?></div>Ready to collect</div>
</div>

<div class="card">
  <p><a class="btn" href="book.php">Book new laundry</a>
  <a class="btn btn-secondary" href="my-bookings.php">View all my bookings</a></p>
</div>

<div class="card">
  <h3>My recent orders</h3>
  <?php if ($orders): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>Order</th><th>Branch</th><th>Service</th><th>Order date</th>
        <th>Due date</th><th>Total</th><th>Status</th><th>Payment</th>
      </tr>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?php echo (int) $o['Order_id']; ?></td>
          <td><?php echo e($o['Branch_name']); ?></td>
          <td><?php echo e($o['Service_name'] ?? '-'); ?></td>
          <td><?php echo e($o['Order_date']); ?></td>
          <td><?php echo e($o['Due_date']); ?></td>
          <td><?php echo number_format((float) $o['Total_amount'], 0); ?> TZS</td>
          <td><span class="badge <?php echo e(status_class($o['Order_status'])); ?>"><?php echo e($o['Order_status']); ?></span></td>
          <td>
            <?php if ((int) $o['paid_count'] > 0): ?>
              <span class="badge badge-paid">Paid</span>
            <?php else: ?>
              <span class="badge badge-unpaid">Unpaid</span>
              <a class="btn btn-small" href="payment.php?order_id=<?php echo (int) $o['Order_id']; ?>">Pay</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>You have no orders yet. <a href="book.php">Book your first laundry</a>.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
