<?php
require_once __DIR__ . '/config.php';

// If already logged in, go to the right home page.
if (is_logged_in()) {
    $u = current_user();
    redirect($u['type'] === 'customer' ? 'dashboard.php' : 'admin/index.php');
}

$errors = [];
$name = $phone = $email = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($name === '')    { $errors[] = 'Please enter your full name.'; }
    if ($phone === '')   { $errors[] = 'Please enter your phone number.'; }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($address === '') { $errors[] = 'Please enter your address.'; }
    if (strlen($password) < 6) {
        $errors[] = 'Your password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors) {
        // Email, phone and address must be unique (the database enforces it).
        $check = $pdo->prepare(
            'SELECT Customer_id FROM Customer
             WHERE Customer_email = ? OR Customer_phone = ? OR Customer_address = ?'
        );
        $check->execute([$email, $phone, $address]);
        if ($check->fetch()) {
            $errors[] = 'That email, phone number or address is already registered. '
                      . 'Please log in or use different details.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO Customer
             (Customer_name, Customer_phone, Customer_email, Customer_address,
              Customer_password, Created_at)
             VALUES (?, ?, ?, ?, ?, CURDATE())'
        );
        $stmt->execute([
            $name, $phone, $email, $address,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        flash('success', 'Your account was created. Please log in.');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register - Laundry Booking System</title>
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
  <h2>Create a customer account</h2>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?php echo e($err); ?></div>
  <?php endforeach; ?>

  <form method="post" action="register.php">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="<?php echo e($name); ?>" required>

    <label for="phone">Phone number</label>
    <input type="text" id="phone" name="phone" value="<?php echo e($phone); ?>" required>
    <p class="help">Start with 0, for example 0700000020.</p>

    <label for="email">Email address</label>
    <input type="email" id="email" name="email" value="<?php echo e($email); ?>" required>

    <label for="address">Address</label>
    <input type="text" id="address" name="address" value="<?php echo e($address); ?>" required>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
    <p class="help">At least 6 characters.</p>

    <label for="confirm">Confirm password</label>
    <input type="password" id="confirm" name="confirm" required>

    <button class="btn" type="submit">Register</button>
  </form>
  <p>Already have an account? <a href="login.php">Log in here</a>.</p>
</div>

</div></main>
</body>
</html>
