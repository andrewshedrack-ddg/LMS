<?php
require_once __DIR__ . '/config.php';

// Price list, read live from the Service table.
$services = $pdo->query(
    'SELECT Service_name, Service_description, Price_per_unit
     FROM Service ORDER BY Service_id'
)->fetchAll();

$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laundry Booking System</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<header>
  <div class="wrap">
    <h1><a href="index.php">Laundry Booking System</a></h1>
    <nav>
      <a href="index.php">Home</a>
      <?php if ($user && $user['type'] === 'customer'): ?>
        <a href="dashboard.php">My Dashboard</a>
        <a href="book.php">Book Laundry</a>
        <a href="my-bookings.php">My Bookings</a>
        <a href="logout.php">Logout</a>
        <span class="who">Hello, <?php echo e($user['name']); ?></span>
      <?php elseif ($user && $user['type'] === 'employee'): ?>
        <a href="admin/index.php">Admin Panel</a>
        <a href="logout.php">Logout</a>
        <span class="who">Hello, <?php echo e($user['name']); ?> (<?php echo e($user['role']); ?>)</span>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main><div class="wrap">

<?php $flash = get_flash(); ?>
<?php if ($flash): ?>
  <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
<?php endif; ?>

<div class="card">
  <h2>Welcome to the Hostel Laundry Booking System</h2>
  <p>Book your laundry online. Choose a branch and a service, tell us the
  weight of your clothes, and pick a pickup date and time slot. We wash,
  iron and deliver, and you follow the status of your order here.</p>
  <?php if (!$user): ?>
    <p><a class="btn" href="register.php">Create an account</a>
    <a class="btn btn-secondary" href="login.php">Log in</a></p>
  <?php elseif ($user['type'] === 'customer'): ?>
    <p><a class="btn" href="book.php">Book laundry now</a></p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Our services and prices</h2>
  <p class="muted">Prices are per kilogram (TZS).</p>
  <?php if ($services): ?>
    <ul class="price-list">
      <?php foreach ($services as $s): ?>
        <li>
          <span><strong><?php echo e($s['Service_name']); ?></strong>
          <span class="muted"> &mdash; <?php echo e($s['Service_description']); ?></span></span>
          <span><strong><?php echo number_format((float) $s['Price_per_unit'], 0); ?> TZS/kg</strong></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p>No services have been added yet.</p>
  <?php endif; ?>
</div>

<div class="card">
  <h2>How it works</h2>
  <p>1. Register and log in. &nbsp; 2. Book your laundry and choose a pickup
  slot. &nbsp; 3. We pick it up, wash it and iron it. &nbsp; 4. You collect
  it or we deliver it. You can check the status of every order on your
  dashboard, and you can cancel an order while it is still Pending.</p>
  <p class="muted">Payment is recorded on the system as paid or unpaid.
  No real money is taken online.</p>
</div>

</div></main>
</body>
</html>
