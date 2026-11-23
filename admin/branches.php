<?php
require_once __DIR__ . '/../config.php';
require_admin();

$user = current_user();
$errors = [];
$edit = null;

// --- Delete a branch ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    try {
        $stmt = $pdo->prepare('DELETE FROM Branch WHERE Branch_id = ?');
        $stmt->execute([$id]);
        flash('success', 'Branch deleted.');
    } catch (PDOException $ex) {
        flash('error', 'This branch cannot be deleted because it has orders or employees.');
    }
    redirect('branches.php');
}

// --- Add or update a branch ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int) ($_POST['branch_id'] ?? 0);
    $name = trim($_POST['branch_name'] ?? '');
    $address = trim($_POST['branch_address'] ?? '');
    $phone = trim($_POST['branch_phone'] ?? '');

    if ($name === '')    { $errors[] = 'Please enter the branch name.'; }
    if ($address === '') { $errors[] = 'Please enter the branch address.'; }
    if ($phone === '')   { $errors[] = 'Please enter the branch phone number.'; }

    if (!$errors) {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE Branch SET Branch_name = ?, Branch_address = ?, Branch_phone = ?
                 WHERE Branch_id = ?'
            );
            $stmt->execute([$name, $address, $phone, $id]);
            flash('success', 'Branch updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO Branch (Branch_name, Branch_address, Branch_phone)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([$name, $address, $phone]);
            flash('success', 'Branch added.');
        }
        redirect('branches.php');
    }
}

// Load a branch for editing.
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM Branch WHERE Branch_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$branches = $pdo->query('SELECT * FROM Branch ORDER BY Branch_name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Branches - Laundry Booking System</title>
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
  <h2><?php echo $edit ? 'Edit branch' : 'Add a branch'; ?></h2>
  <form method="post" action="branches.php">
    <input type="hidden" name="branch_id" value="<?php echo $edit ? (int) $edit['Branch_id'] : 0; ?>">
    <label for="branch_name">Branch name</label>
    <input type="text" id="branch_name" name="branch_name"
           value="<?php echo e($edit['Branch_name'] ?? ''); ?>" required>
    <label for="branch_address">Address</label>
    <input type="text" id="branch_address" name="branch_address"
           value="<?php echo e($edit['Branch_address'] ?? ''); ?>" required>
    <label for="branch_phone">Phone number</label>
    <input type="text" id="branch_phone" name="branch_phone"
           value="<?php echo e($edit['Branch_phone'] ?? ''); ?>" required>
    <button class="btn" type="submit" name="save">Save branch</button>
    <?php if ($edit): ?>
      <a class="btn btn-secondary" href="branches.php">Cancel</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>All branches</h2>
  <?php if ($branches): ?>
    <div class="table-wrap"><table>
      <tr><th>ID</th><th>Name</th><th>Address</th><th>Phone</th><th>Actions</th></tr>
      <?php foreach ($branches as $b): ?>
        <tr>
          <td><?php echo (int) $b['Branch_id']; ?></td>
          <td><?php echo e($b['Branch_name']); ?></td>
          <td><?php echo e($b['Branch_address']); ?></td>
          <td><?php echo e($b['Branch_phone']); ?></td>
          <td>
            <a class="btn btn-small" href="branches.php?edit=<?php echo (int) $b['Branch_id']; ?>">Edit</a>
            <form method="post" action="branches.php" style="display:inline;"
                  onsubmit="return confirm('Delete this branch?');">
              <input type="hidden" name="delete_id" value="<?php echo (int) $b['Branch_id']; ?>">
              <button class="btn btn-danger btn-small" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No branches yet.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
