<?php
require_once __DIR__ . '/config.php';

// If already logged in, go to the right home page.
if (is_logged_in()) {
    $u = current_user();
    redirect($u['type'] === 'customer' ? 'dashboard.php' : 'admin/index.php');
}

$error = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // One field for both: customers use their email, staff and admin
    // use their phone number. Customers are checked first.
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $error = 'Please enter your email or phone number and your password.';
    } else {
        // 1. Try the Customer table by email.
        $stmt = $pdo->prepare(
            'SELECT Customer_id, Customer_name, Customer_email, Customer_password
             FROM Customer WHERE Customer_email = ?'
        );
        $stmt->execute([$login]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['Customer_password'])) {
            $_SESSION['user_id']    = (int) $customer['Customer_id'];
            $_SESSION['user_type']  = 'customer';
            $_SESSION['user_role']  = 'customer';
            $_SESSION['user_name']  = $customer['Customer_name'];
            $_SESSION['user_email'] = $customer['Customer_email'];
            redirect('dashboard.php');
        }

        // 2. Try the Employee table by phone number.
        $stmt = $pdo->prepare(
            'SELECT Employee_id, Employee_name, Employee_phone,
                    Employee_role, Employee_password
             FROM Employee WHERE Employee_phone = ?'
        );
        $stmt->execute([$login]);
        $employee = $stmt->fetch();

        if ($employee && password_verify($password, $employee['Employee_password'])) {
            $_SESSION['user_id']    = (int) $employee['Employee_id'];
            $_SESSION['user_type']  = 'employee';
            $_SESSION['user_role']  = $employee['Employee_role'];
            $_SESSION['user_name']  = $employee['Employee_name'];
            $_SESSION['user_email'] = null;
            redirect('admin/index.php');
        }

        $error = 'Invalid email/phone number or password. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Laundry Booking System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
  <div class="wrap">
    <h1><a href="index.php">Laundry Booking System</a></h1>
    <nav>
      <a href="index.php">Home</a>
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
    </nav>
  </div>
</header>
<main><div class="wrap">

<div class="card">
  <h2>Log in</h2>

  <?php $flash = get_flash(); ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
  <?php endif; ?>

  <form method="post" action="login.php">
    <label for="login">Email or phone number</label>
    <input type="text" id="login" name="login" value="<?php echo e($login); ?>" required>
    <p class="help">Customers: use your email address.
       Staff and Admin: use your phone number.</p>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>

    <button class="btn" type="submit">Log in</button>
  </form>
  <p>No account yet? <a href="register.php">Register as a customer</a>.</p>
</div>

</div></main>
</body>
</html>
