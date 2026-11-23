<?php
require_once __DIR__ . '/config.php';
require_customer();

$user = current_user();

// --- Cancel an order (only while it is still Pending) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $orderId = (int) $_POST['cancel_id'];
    // Only the customer's own order, and only if it is still Pending.
    $stmt = $pdo->prepare(
        'UPDATE Laundry_Order SET Order_status = ?
         WHERE Order_id = ? AND Customer_id = ? AND Order_status = ?'
    );
    $stmt->execute(['Cancelled', $orderId, $user['id'], 'Pending']);
    if ($stmt->rowCount() > 0) {
        flash('success', 'Order #' . $orderId . ' was cancelled.');
    } else {
        flash('error', 'That order cannot be cancelled. Only a Pending order can be cancelled.');
    }
    redirect('my-bookings.php');
}

// The customer's own orders, newest first.
$stmt = $pdo->prepare(
    'SELECT o.Order_id, o.Order_date, o.Due_date, o.Order_status,
            o.Total_amount, b.Branch_name, s.Service_name,
            oi.Order_quantity, oi.Order_notes,
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Bookings - Laundry Booking System</title>
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

<div class="card">
  <h2>My bookings</h2>
  <p><a class="btn" href="book.php">Book new laundry</a></p>

  <?php if ($orders): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>Order</th><th>Branch</th><th>Service</th><th>Items</th>
        <th>Order date</th><th>Due date</th><th>Total</th>
        <th>Status</th><th>Payment</th><th>Action</th>
      </tr>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?php echo (int) $o['Order_id']; ?></td>
          <td><?php echo e($o['Branch_name']); ?></td>
          <td><?php echo e($o['Service_name'] ?? '-'); ?></td>
          <td><?php echo (int) $o['Order_quantity']; ?>
            <span class="muted"><?php echo e($o['Order_notes'] ?? ''); ?></span></td>
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
          <td>
            <?php if ($o['Order_status'] === 'Pending'): ?>
              <form method="post" action="my-bookings.php"
                    onsubmit="return confirm('Cancel order #<?php echo (int) $o['Order_id']; ?>?');">
                <input type="hidden" name="cancel_id" value="<?php echo (int) $o['Order_id']; ?>">
                <button class="btn btn-danger btn-small" type="submit">Cancel</button>
              </form>
            <?php else: ?>
              <span class="muted">Cannot cancel</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p class="muted">An order can only be cancelled while its status is Pending.</p>
  <?php else: ?>
    <p>You have no bookings yet. <a href="book.php">Book your first laundry</a>.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
