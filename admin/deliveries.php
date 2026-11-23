<?php
require_once __DIR__ . '/../config.php';
require_staff_or_admin();

$user = current_user();
$errors = [];
$edit = null;

$types = ['Pickup', 'Delivery'];
$statuses = ['Scheduled', 'Out for Delivery', 'Delivered', 'Collected'];

$staff = $pdo->query(
    "SELECT Employee_id, Employee_name, Employee_role
     FROM Employee ORDER BY Employee_name"
)->fetchAll();

// Orders that do not have a delivery yet (for the add form).
$ordersWithoutDelivery = $pdo->query(
    'SELECT o.Order_id, o.Total_amount, c.Customer_name
     FROM Laundry_Order o
     JOIN Customer c ON c.Customer_id = o.Customer_id
     WHERE NOT EXISTS (SELECT 1 FROM Delivery d WHERE d.Order_id = o.Order_id)
     ORDER BY o.Order_id DESC'
)->fetchAll();

// --- Add or update a delivery ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int) ($_POST['delivery_id'] ?? 0);
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $employeeId = $_POST['employee_id'] ?? '';
    $employeeId = ($employeeId === '' ? null : (int) $employeeId);
    $type = trim($_POST['delivery_type'] ?? '');
    $address = trim($_POST['delivery_address'] ?? '');
    $status = trim($_POST['delivery_status'] ?? '');
    $schedule = trim($_POST['delivery_schedule'] ?? '');

    if ($id === 0) {
        $orderOk = false;
        foreach ($ordersWithoutDelivery as $o) {
            if ((int) $o['Order_id'] === $orderId) { $orderOk = true; }
        }
        if (!$orderOk) { $errors[] = 'Please choose an order without a delivery.'; }
    } else {
        // Editing: the order must exist.
        $stmt = $pdo->prepare('SELECT Order_id FROM Laundry_Order WHERE Order_id = ?');
        $stmt->execute([$orderId]);
        if (!$stmt->fetch()) { $errors[] = 'Order not found.'; }
    }
    if (!in_array($type, $types, true)) {
        $errors[] = 'Please choose Pickup or Delivery.';
    }
    if ($address === '') { $errors[] = 'Please enter the delivery address.'; }
    if (!in_array($status, $statuses, true)) {
        $errors[] = 'Please choose a delivery status.';
    }
    if ($schedule === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule)) {
        $errors[] = 'Please choose a schedule date.';
    }
    if ($employeeId !== null) {
        $ok = false;
        foreach ($staff as $s) {
            if ((int) $s['Employee_id'] === $employeeId) { $ok = true; }
        }
        if (!$ok) { $errors[] = 'Please choose a valid employee.'; }
    }

    if (!$errors) {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE Delivery
                 SET Order_id = ?, Employee_id = ?, Delivery_type = ?,
                     Delivery_address = ?, Delivery_status = ?, Delivery_schedule = ?
                 WHERE Delivery_id = ?'
            );
            $stmt->execute([
                $orderId, $employeeId, $type, $address, $status, $schedule, $id,
            ]);
            flash('success', 'Delivery updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO Delivery
                 (Order_id, Employee_id, Delivery_type, Delivery_address,
                  Delivery_status, Delivery_schedule)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $orderId, $employeeId, $type, $address, $status, $schedule,
            ]);
            flash('success', 'Delivery scheduled.');
        }
        redirect('deliveries.php');
    }
}

// Load a delivery for editing.
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM Delivery WHERE Delivery_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$deliveries = $pdo->query(
    'SELECT d.*, o.Total_amount, c.Customer_name, e.Employee_name
     FROM Delivery d
     JOIN Laundry_Order o ON o.Order_id = d.Order_id
     JOIN Customer c ON c.Customer_id = o.Customer_id
     LEFT JOIN Employee e ON e.Employee_id = d.Employee_id
     ORDER BY d.Delivery_schedule DESC, d.Delivery_id DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Deliveries - Laundry Booking System</title>
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
  <h2><?php echo $edit ? 'Edit delivery' : 'Schedule a delivery'; ?></h2>
  <form method="post" action="deliveries.php">
    <input type="hidden" name="delivery_id" value="<?php echo $edit ? (int) $edit['Delivery_id'] : 0; ?>">
    <div class="form-row">
      <div>
        <label for="order_id">Order</label>
        <?php if ($edit): ?>
          <input type="text" value="Order #<?php echo (int) $edit['Order_id']; ?>" disabled>
          <input type="hidden" name="order_id" value="<?php echo (int) $edit['Order_id']; ?>">
        <?php else: ?>
          <select id="order_id" name="order_id" required>
            <option value="">-- Choose an order --</option>
            <?php foreach ($ordersWithoutDelivery as $o): ?>
              <option value="<?php echo (int) $o['Order_id']; ?>">
                Order #<?php echo (int) $o['Order_id']; ?> &mdash;
                <?php echo e($o['Customer_name']); ?> &mdash;
                <?php echo number_format((float) $o['Total_amount'], 0); ?> TZS
              </option>
            <?php endforeach; ?>
          </select>
          <p class="help">Only orders without a delivery are listed.</p>
        <?php endif; ?>
      </div>
      <div>
        <label for="delivery_type">Type</label>
        <select id="delivery_type" name="delivery_type" required>
          <?php foreach ($types as $t): ?>
            <option value="<?php echo e($t); ?>"
              <?php echo ($edit['Delivery_type'] ?? '') === $t ? 'selected' : ''; ?>>
              <?php echo e($t); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label for="delivery_address">Address</label>
    <input type="text" id="delivery_address" name="delivery_address"
           value="<?php echo e($edit['Delivery_address'] ?? ''); ?>" required>
    <div class="form-row">
      <div>
        <label for="delivery_schedule">Schedule date</label>
        <input type="date" id="delivery_schedule" name="delivery_schedule"
               value="<?php echo e($edit['Delivery_schedule'] ?? ''); ?>" required>
      </div>
      <div>
        <label for="delivery_status">Status</label>
        <select id="delivery_status" name="delivery_status" required>
          <?php foreach ($statuses as $s): ?>
            <option value="<?php echo e($s); ?>"
              <?php echo ($edit['Delivery_status'] ?? 'Scheduled') === $s ? 'selected' : ''; ?>>
              <?php echo e($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="employee_id">Assigned employee</label>
        <select id="employee_id" name="employee_id">
          <option value="">-- No employee --</option>
          <?php foreach ($staff as $s): ?>
            <option value="<?php echo (int) $s['Employee_id']; ?>"
              <?php echo (int) ($edit['Employee_id'] ?? 0) === (int) $s['Employee_id'] ? 'selected' : ''; ?>>
              <?php echo e($s['Employee_name']); ?> (<?php echo e($s['Employee_role']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <button class="btn" type="submit" name="save">Save delivery</button>
    <?php if ($edit): ?>
      <a class="btn btn-secondary" href="deliveries.php">Cancel</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>All deliveries</h2>
  <?php if ($deliveries): ?>
    <div class="table-wrap"><table>
      <tr>
        <th>ID</th><th>Order</th><th>Customer</th><th>Type</th>
        <th>Address</th><th>Schedule</th><th>Status</th><th>Employee</th><th>Actions</th>
      </tr>
      <?php foreach ($deliveries as $d): ?>
        <tr>
          <td><?php echo (int) $d['Delivery_id']; ?></td>
          <td>#<?php echo (int) $d['Order_id']; ?></td>
          <td><?php echo e($d['Customer_name']); ?></td>
          <td><?php echo e($d['Delivery_type']); ?></td>
          <td><?php echo e($d['Delivery_address']); ?></td>
          <td><?php echo e($d['Delivery_schedule']); ?></td>
          <td><span class="badge <?php echo e(status_class($d['Delivery_status'])); ?>"><?php echo e($d['Delivery_status']); ?></span></td>
          <td><?php echo e($d['Employee_name'] ?? '-'); ?></td>
          <td>
            <a class="btn btn-small" href="deliveries.php?edit=<?php echo (int) $d['Delivery_id']; ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No deliveries scheduled yet.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
