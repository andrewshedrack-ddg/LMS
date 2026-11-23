<?php
require_once __DIR__ . '/../config.php';
require_admin();

$user = current_user();
$errors = [];
$edit = null;

$branches = $pdo->query('SELECT Branch_id, Branch_name FROM Branch ORDER BY Branch_name')->fetchAll();
$roles = ['Staff', 'Admin'];

// --- Delete an employee ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    // Do not let an admin delete their own account.
    if ($id === (int) $user['id']) {
        flash('error', 'You cannot delete your own account.');
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM Employee WHERE Employee_id = ?');
            $stmt->execute([$id]);
            flash('success', 'Employee deleted.');
        } catch (PDOException $ex) {
            flash('error', 'This employee cannot be deleted because they have orders or deliveries.');
        }
    }
    redirect('employees.php');
}

// --- Add or update an employee ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $id = (int) ($_POST['employee_id'] ?? 0);
    $name = trim($_POST['employee_name'] ?? '');
    $phone = trim($_POST['employee_phone'] ?? '');
    $role = trim($_POST['employee_role'] ?? '');
    $branchId = (int) ($_POST['branch_id'] ?? 0);
    $password = $_POST['employee_password'] ?? '';

    if ($name === '')  { $errors[] = 'Please enter the employee name.'; }
    if ($phone === '') { $errors[] = 'Please enter the phone number.'; }
    if (!in_array($role, $roles, true)) {
        $errors[] = 'Please choose Staff or Admin.';
    }
    $branchOk = false;
    foreach ($branches as $b) {
        if ((int) $b['Branch_id'] === $branchId) { $branchOk = true; }
    }
    if (!$branchOk) { $errors[] = 'Please choose a branch.'; }
    if ($id === 0 && strlen($password) < 6) {
        $errors[] = 'A new employee needs a password of at least 6 characters.';
    }
    if ($password !== '' && strlen($password) < 6) {
        $errors[] = 'The password must be at least 6 characters.';
    }

    if (!$errors) {
        if ($id > 0) {
            if ($password !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE Employee
                     SET Employee_name = ?, Employee_phone = ?, Employee_role = ?,
                         Branch_id = ?, Employee_password = ?
                     WHERE Employee_id = ?'
                );
                $stmt->execute([
                    $name, $phone, $role, $branchId,
                    password_hash($password, PASSWORD_DEFAULT), $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE Employee
                     SET Employee_name = ?, Employee_phone = ?, Employee_role = ?,
                         Branch_id = ?
                     WHERE Employee_id = ?'
                );
                $stmt->execute([$name, $phone, $role, $branchId, $id]);
            }
            flash('success', 'Employee updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO Employee
                 (Branch_id, Employee_name, Employee_phone, Employee_role, Employee_password)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $branchId, $name, $phone, $role,
                password_hash($password, PASSWORD_DEFAULT),
            ]);
            flash('success', 'Employee added.');
        }
        redirect('employees.php');
    }
}

// Load an employee for editing.
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM Employee WHERE Employee_id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

$employees = $pdo->query(
    'SELECT e.*, b.Branch_name FROM Employee e
     LEFT JOIN Branch b ON b.Branch_id = e.Branch_id
     ORDER BY e.Employee_name'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Employees - Laundry Booking System</title>
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
  <h2><?php echo $edit ? 'Edit employee' : 'Add an employee'; ?></h2>
  <form method="post" action="employees.php">
    <input type="hidden" name="employee_id" value="<?php echo $edit ? (int) $edit['Employee_id'] : 0; ?>">
    <label for="employee_name">Full name</label>
    <input type="text" id="employee_name" name="employee_name"
           value="<?php echo e($edit['Employee_name'] ?? ''); ?>" required>
    <label for="employee_phone">Phone number</label>
    <input type="text" id="employee_phone" name="employee_phone"
           value="<?php echo e($edit['Employee_phone'] ?? ''); ?>" required>
    <p class="help">Staff and Admin log in with this phone number.</p>
    <div class="form-row">
      <div>
        <label for="employee_role">Role</label>
        <select id="employee_role" name="employee_role" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?php echo e($r); ?>"
              <?php echo ($edit['Employee_role'] ?? '') === $r ? 'selected' : ''; ?>>
              <?php echo e($r); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="branch_id">Branch</label>
        <select id="branch_id" name="branch_id" required>
          <option value="">-- Choose a branch --</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?php echo (int) $b['Branch_id']; ?>"
              <?php echo (int) ($edit['Branch_id'] ?? 0) === (int) $b['Branch_id'] ? 'selected' : ''; ?>>
              <?php echo e($b['Branch_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label for="employee_password">Password</label>
    <input type="password" id="employee_password" name="employee_password"
           <?php echo $edit ? '' : 'required'; ?>>
    <p class="help">
      <?php echo $edit
        ? 'Leave blank to keep the current password.'
        : 'At least 6 characters.'; ?>
    </p>
    <button class="btn" type="submit" name="save">Save employee</button>
    <?php if ($edit): ?>
      <a class="btn btn-secondary" href="employees.php">Cancel</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>All employees</h2>
  <?php if ($employees): ?>
    <div class="table-wrap"><table>
      <tr><th>ID</th><th>Name</th><th>Phone</th><th>Role</th><th>Branch</th><th>Actions</th></tr>
      <?php foreach ($employees as $emp): ?>
        <tr>
          <td><?php echo (int) $emp['Employee_id']; ?></td>
          <td><?php echo e($emp['Employee_name']); ?></td>
          <td><?php echo e($emp['Employee_phone']); ?></td>
          <td><?php echo e($emp['Employee_role']); ?></td>
          <td><?php echo e($emp['Branch_name'] ?? '-'); ?></td>
          <td>
            <a class="btn btn-small" href="employees.php?edit=<?php echo (int) $emp['Employee_id']; ?>">Edit</a>
            <form method="post" action="employees.php" style="display:inline;"
                  onsubmit="return confirm('Delete this employee?');">
              <input type="hidden" name="delete_id" value="<?php echo (int) $emp['Employee_id']; ?>">
              <button class="btn btn-danger btn-small" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php else: ?>
    <p>No employees yet.</p>
  <?php endif; ?>
</div>

</div></main>
</body>
</html>
