<?php
require_once __DIR__ . '/config.php';
require_customer();

$user = current_user();
$errors = [];
$confirmation = null;

// Lists for the form, read live from the database.
$branches  = $pdo->query('SELECT Branch_id, Branch_name FROM Branch ORDER BY Branch_name')->fetchAll();
$services  = $pdo->query('SELECT Service_id, Service_name, Price_per_unit FROM Service ORDER BY Service_id')->fetchAll();
$itemTypes = $pdo->query('SELECT Item_type_id, Item_name, Item_category FROM Item_Type ORDER BY Item_name')->fetchAll();
$slots = ['08:00-10:00', '10:00-12:00', '12:00-14:00', '14:00-16:00', '16:00-18:00'];

// Keep the form values if there is an error.
$branchId = (int) ($_POST['branch_id'] ?? 0);
$serviceId = (int) ($_POST['service_id'] ?? 0);
$itemTypeId = (int) ($_POST['item_type_id'] ?? 0);
$quantity = (int) ($_POST['quantity'] ?? 1);
$weight = trim($_POST['weight'] ?? '');
$pickupDate = trim($_POST['pickup_date'] ?? '');
$slot = trim($_POST['time_slot'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- Server-side validation (never trust the form alone) ---
    $weightNum = filter_var($weight, FILTER_VALIDATE_FLOAT);

    $branchOk = false;
    foreach ($branches as $b) {
        if ((int) $b['Branch_id'] === $branchId) { $branchOk = true; }
    }
    if (!$branchOk) { $errors[] = 'Please choose a branch.'; }

    $service = null;
    foreach ($services as $s) {
        if ((int) $s['Service_id'] === $serviceId) { $service = $s; }
    }
    if (!$service) { $errors[] = 'Please choose a service.'; }

    $itemOk = false;
    foreach ($itemTypes as $t) {
        if ((int) $t['Item_type_id'] === $itemTypeId) { $itemOk = true; }
    }
    if (!$itemOk) { $errors[] = 'Please choose a type of clothing.'; }

    if ($quantity < 1) { $errors[] = 'Quantity must be at least 1.'; }
    if ($weightNum === false || $weightNum <= 0) {
        $errors[] = 'Please enter a weight in kg greater than 0.';
    }
    if ($pickupDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $pickupDate)) {
        $errors[] = 'Please choose a pickup date.';
    } elseif ($pickupDate < date('Y-m-d')) {
        $errors[] = 'The pickup date cannot be in the past.';
    }
    if (!in_array($slot, $slots, true)) {
        $errors[] = 'Please choose a pickup time slot.';
    }

    if (!$errors) {
        // Total price = service price per kg x weight in kg.
        $total = round((float) $service['Price_per_unit'] * $weightNum, 2);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO Laundry_Order
                 (Customer_id, Employee_id, Branch_id, Order_date, Due_date,
                  Order_status, Total_amount)
                 VALUES (?, NULL, ?, CURDATE(), ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'], $branchId, $pickupDate, 'Pending', $total,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO Order_Item
                 (Order_id, Service_id, Item_type_id, Order_quantity,
                  Unit_price, Order_notes)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $orderId, $serviceId, $itemTypeId, $quantity,
                $service['Price_per_unit'], 'Time slot: ' . $slot,
            ]);
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            $errors[] = 'Your booking could not be saved. Please try again.';
        }

        if (!$errors) {
            $confirmation = [
                'order_id' => $orderId,
                'total'    => $total,
                'service'  => $service['Service_name'],
                'date'     => $pickupDate,
                'slot'     => $slot,
            ];
            // Clear the form.
            $branchId = $serviceId = $itemTypeId = 0;
            $quantity = 1;
            $weight = $pickupDate = $slot = '';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Book Laundry - Laundry Booking System</title>
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

<div class="card">
  <h2>Book laundry</h2>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?php echo e($err); ?></div>
  <?php endforeach; ?>

  <?php if ($confirmation): ?>
    <div class="alert alert-success">
      <strong>Your booking was saved.</strong><br>
      Order number: <strong>#<?php echo (int) $confirmation['order_id']; ?></strong><br>
      Service: <?php echo e($confirmation['service']); ?><br>
      Pickup: <?php echo e($confirmation['date']); ?>,
      <?php echo e($confirmation['slot']); ?><br>
      Total to pay: <strong><?php echo number_format((float) $confirmation['total'], 0); ?> TZS</strong><br>
      Status: Pending.
    </div>
    <p><a class="btn" href="my-bookings.php">View my bookings</a>
    <a class="btn btn-secondary" href="payment.php?order_id=<?php echo (int) $confirmation['order_id']; ?>">Record payment</a></p>
  <?php endif; ?>

  <form method="post" action="book.php">
    <div class="form-row">
      <div>
        <label for="branch_id">Branch</label>
        <select id="branch_id" name="branch_id" required>
          <option value="">-- Choose a branch --</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?php echo (int) $b['Branch_id']; ?>" <?php echo $branchId === (int) $b['Branch_id'] ? 'selected' : ''; ?>>
              <?php echo e($b['Branch_name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="service_id">Service</label>
        <select id="service_id" name="service_id" required>
          <option value="">-- Choose a service --</option>
          <?php foreach ($services as $s): ?>
            <option value="<?php echo (int) $s['Service_id']; ?>" <?php echo $serviceId === (int) $s['Service_id'] ? 'selected' : ''; ?>>
              <?php echo e($s['Service_name']); ?> (<?php echo number_format((float) $s['Price_per_unit'], 0); ?> TZS/kg)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="item_type_id">Type of clothing</label>
        <select id="item_type_id" name="item_type_id" required>
          <option value="">-- Choose a type --</option>
          <?php foreach ($itemTypes as $t): ?>
            <option value="<?php echo (int) $t['Item_type_id']; ?>" <?php echo $itemTypeId === (int) $t['Item_type_id'] ? 'selected' : ''; ?>>
              <?php echo e($t['Item_name']); ?> (<?php echo e($t['Item_category']); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="quantity">Number of items</label>
        <input type="number" id="quantity" name="quantity" min="1" step="1"
               value="<?php echo (int) $quantity; ?>" required>
      </div>
      <div>
        <label for="weight">Weight in kg</label>
        <input type="number" id="weight" name="weight" min="0.1" step="0.1"
               value="<?php echo e($weight); ?>" required>
        <p class="help">Total price = service price per kg &times; weight.</p>
      </div>
    </div>

    <div class="form-row">
      <div>
        <label for="pickup_date">Pickup date</label>
        <input type="date" id="pickup_date" name="pickup_date"
               min="<?php echo date('Y-m-d'); ?>" value="<?php echo e($pickupDate); ?>" required>
      </div>
      <div>
        <label for="time_slot">Pickup time slot</label>
        <select id="time_slot" name="time_slot" required>
          <option value="">-- Choose a time slot --</option>
          <?php foreach ($slots as $s): ?>
            <option value="<?php echo e($s); ?>" <?php echo $slot === $s ? 'selected' : ''; ?>>
              <?php echo e($s); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <button class="btn" type="submit">Place booking</button>
  </form>
</div>

</div></main>
</body>
</html>
