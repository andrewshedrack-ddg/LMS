<?php
require_once __DIR__ . '/../config.php';
require_staff_or_admin();

$user = current_user();
$errors = [];

// Staff list for the "assign employee" dropdown.
$staff = $pdo->query(
    "SELECT Employee_id, Employee_name, Employee_role
     FROM Employee ORDER BY Employee_name"
)->fetchAll();

// --- Update one order: status (forward only) and assigned employee ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $orderId = (int) $_POST['order_id'];
    $newStatus = trim($_POST['order_status'] ?? '');
    $employeeId = $_POST['employee_id'] ?? '';
    $employeeId = ($employeeId === '' ? null : (int) $employeeId);

    // Load the order so we know its current status.
    $stmt = $pdo->prepare('SELECT Order_status FROM Laundry_Order WHERE Order_id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $errors[] = 'Order not found.';
    } else {
        $current = $order['Order_status'];
        $next = next_order_status($current);
        // Only allow staying at the current status or moving to the
        // immediate next status. Never backwards, never a jump.
        if ($newStatus !== $current && $newStatus !== $next) {
            $errors[] = 'Invalid status change. An order can only move one step '
                      . 'forward: Pending, Picked Up, Washing, Ready, Collected.';
        }
        if ($employeeId !== null) {
            $ok = false;
            foreach ($staff as $s) {
                if ((int) $s['Employee_id'] === $employeeId) { $ok = true; }
            }
            if (!$ok) { $errors[] = 'Please choose a valid employee.'; }
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'UPDATE Laundry_Order SET Order_status = ?, Employee_id = ? WHERE Order_id = ?'
        );
        $stmt->execute([$newStatus, $employeeId, $orderId]);
        flash('success', 'Order #' . $orderId . ' was updated.');
        redirect('orders.php');
    }
}

// All orders, newest first.
$orders = $pdo->query(
    "SELECT o.Order_id, o.Order_date, o.Due_date, o.Order_status,
            o.Total_amount, o.Employee_id, c.Customer_name,
            b.Branch_name, s.Service_name,
            (SELECT COUNT(*) FROM Payment p
              WHERE p.Order_id = o.Order_id AND p.Payment_status = 'Paid') AS paid_count
     FROM Laundry_Order o
     JOIN Customer c ON c.Customer_id = o.Customer_id
     JOIN Branch b ON b.Branch_id = o.Branch_id
     LEFT JOIN Order_Item oi ON oi.Order_id = o.Order_id
     LEFT JOIN Service s ON s.Service_id = oi.Service_id
     ORDER BY o.Order_date DESC, o.Order_id DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders - Laundry Booking System</title>
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
  <h2>All orders</h2>
  <p class="muted">An order moves one step forward at a time:
  Pending, Picked Up, Washing, Ready, Collected. It cannot go backwards.
  A Cancelled order cannot be changed here.</p>

  <?php if ($orders): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>Order</th><th>Customer</th><th>Branch</th><th>Service</th>
        <th>Order date</th><th>Due date</th><th>Total</th>
        <th>Payment</th><th>Status / Assigned employee</th>
      </tr>
      <?php foreach ($orders as $o): ?>
        <?php
          $next = next_order_status($o['Order_status']);
          $canChange = ($next !== null);
        ?>
        <tr>
          <td>#<?php echo (int) $o['Order_id']; ?></td>
          <td><?php echo e($o['Customer_name']); ?></td>
          <td><?php echo e($o['Branch_name']); ?></td>
          <td><?php echo e($o['Service_name'] ?? '-'); ?></td>
          <td><?php echo e($o['Order_date']); ?></td>
          <td><?php echo e($o['Due_date']); ?></td>
          <td><?php echo number_format((float) $o['Total_amount'], 0); ?> TZS</td>
          <td>
            <?php if ((int) $o['paid_count'] > 0): ?>
              <span class="badge badge-paid">Paid</span>
            <?php else: ?>
              <span class="badge badge-unpaid">Unpaid</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($canChange): ?>
              <form method="post" action="orders.php">
                <input type="hidden" name="order_id" value="<?php echo (int) $o['Order_id']; ?>">
                <select name="order_status">
                  <option value="<?php echo e($o['Order_status']); ?>">
                    <?php echo e($o['Order_status']); ?> (current)
                  </option>
                  <option value="<?php echo e($next); ?>">
                    <?php echo e($next); ?>
                  </option>
                </select>
                <select name="employee_id">
                  <option value="">-- No employee --</option>
                  <?php foreach ($staff as $s): ?>
                    <option value="<?php echo (int) $s['Employee_id']; ?>"
                      <?php echo (int) $o['Employee_id'] === (int) $s['Employee_id'] ? 'selected' : ''; ?>>
                      <?php echo e($s['Employee_name']); ?> (<?php echo e($s['Employee_role']); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-small" type="submit">Update</button>
              </form>
            <?php else: ?>
              <span class="badge <?php echo e(status_class($o['Order_status'])); ?>"><?php echo e($o['Order_status']); ?></span>
              <span class="muted">Final</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No orders yet.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
