<?php
require_once __DIR__ . '/../config.php';
require_admin();

$user = current_user();
$errors = [];
$edit = null;

// --- Delete a service ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    try {
        $stmt = $pdo->prepare('DELETE FROM Service WHERE Service_id = ?');
        $stmt->execute([$id]);
        flash('success', 'Service deleted.');
    } catch (PDOException $ex) {
        flash('error', 'This service cannot be deleted because it is used in orders.');
    }
    redirect('services.php');
}

// --- Add or update a service ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int) ($_POST['service_id'] ?? 0);
    $name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['service_description'] ?? '');
    $price = filter_var($_POST['price_per_unit'] ?? '', FILTER_VALIDATE_FLOAT);

    if ($name === '')        { $errors[] = 'Please enter the service name.'; }
    if ($description === '') { $errors[] = 'Please enter a short description.'; }
    if ($price === false || $price <= 0) {
        $errors[] = 'Please enter a price greater than 0.';
    }

    if (!$errors) {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE Service
                 SET Service_name = ?, Service_description = ?, Price_per_unit = ?
                 WHERE Service_id = ?'
            );
            $stmt->execute([$name, $description, $price, $id]);
            flash('success', 'Service updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO Service (Service_name, Service_description, Price_per_unit)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$name, $description, $price]);
            flash('success', 'Service added.');
        }
        redirect('services.php');
    }
}

// Load a service for editing.
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM Service WHERE Service_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$services = $pdo->query('SELECT * FROM Service ORDER BY Service_id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Services - Laundry Booking System</title>
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
      <a href="branches.php">Branches</a>
      <a href="employees.php">Employees</a>
      <a href="services.php">Services</a>
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
  <h2><?php echo $edit ? 'Edit service' : 'Add a service'; ?></h2>
  <form method="post" action="services.php">
    <input type="hidden" name="service_id" value="<?php echo $edit ? (int) $edit['Service_id'] : 0; ?>">
    <label for="service_name">Service name</label>
    <input type="text" id="service_name" name="service_name"
           value="<?php echo e($edit['Service_name'] ?? ''); ?>" required>
    <label for="service_description">Description</label>
    <input type="text" id="service_description" name="service_description"
           value="<?php echo e($edit['Service_description'] ?? ''); ?>" required>
    <label for="price_per_unit">Price per kg (TZS)</label>
    <input type="number" id="price_per_unit" name="price_per_unit"
           min="1" step="0.01"
           value="<?php echo e($edit['Price_per_unit'] ?? ''); ?>" required>
    <button class="btn" type="submit" name="save">Save service</button>
    <?php if ($edit): ?>
      <a class="btn btn-secondary" href="services.php">Cancel</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>All services</h2>
  <?php if ($services): ?>
    <div class="table-wrap"><table>
      <tr><th>ID</th><th>Name</th><th>Description</th><th>Price per kg</th><th>Actions</th></tr>
      <?php foreach ($services as $s): ?>
        <tr>
          <td><?php echo (int) $s['Service_id']; ?></td>
          <td><?php echo e($s['Service_name']); ?></td>
          <td><?php echo e($s['Service_description']); ?></td>
          <td><?php echo number_format((float) $s['Price_per_unit'], 0); ?> TZS</td>
          <td>
            <a class="btn btn-small" href="services.php?edit=<?php echo (int) $s['Service_id']; ?>">Edit</a>
            <form method="post" action="services.php" style="display:inline;"
                  onsubmit="return confirm('Delete this service?');">
              <input type="hidden" name="delete_id" value="<?php echo (int) $s['Service_id']; ?>">
              <button class="btn btn-danger btn-small" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No services yet.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
