# Laundry Management System — Deliverable 3

Hostel-based laundry service booking and management information system
for university students. This is Deliverable 3 (Development & Debugging
Phase) for the DBT 1303 Software Engineering group project at Strathmore
University.

## What Deliverable 3 covers

The lecturer's marking for Deliverable 3 is:

1. A database created in a DBMS.
2. A well-designed and developed front-end.
3. A well-designed and developed admin panel.
4. Connection of the front-end to the database.
5. Connection of the admin panel to the database.

All five are in this folder. The app is built with PHP and MySQL
(XAMPP). There is no framework.

## Setup

1. Install XAMPP and start **Apache** and **MySQL** from the XAMPP
   Control Panel.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`.
3. Click **Import**, choose `database/laundry_db.sql`, and click **Go**.
   This creates the `Laundry_db` database with 9 tables and sample data.
4. Copy this whole folder to `C:\xampp\htdocs\lms`.
5. Open `http://localhost/lms/` in a browser.

If your MySQL username or password is not `root` with an empty password,
change the four constants at the top of `config.php`
(`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).

## Demo logins

Passwords are stored hashed with PHP `password_hash()`.

| Who | Login | Password |
|---|---|---|
| Customer | `customer@test.com` (email) | `customer123` |
| Staff | `0700000011` (phone) | `staff123` |
| Admin | `0700000010` (phone) | `admin123` |

Customers log in with their **email address**. Staff and Admin log in
with their **phone number**. There is one login form; it checks the
Customer table first, then the Employee table.

## What works

**Front-end (customers)**
- Home page with the live service price list from the database.
- Customer registration (name, phone, email, address, password).
- Login and logout (sessions).
- Customer dashboard showing only that customer's own orders.
- New booking: choose branch, service, type of clothing, quantity,
  weight in kg, pickup date and time slot. Total price = service price
  per kg x weight. The order is saved with status Pending and the
  pickup time slot is stored in the order notes.
- My bookings: list of the customer's own orders. A Pending order can
  be cancelled; any other status cannot.
- Record a payment for an order (amount defaults to the order total,
  method Cash / M-Pesa / Bank Transfer). Cash and Bank Transfer are
  recorded by hand as Paid. M-Pesa sends a real STK push to the
  customer's phone: the customer enters their M-Pesa PIN, and the
  payment is only marked Paid when Safaricom confirms it through the
  callback. Until then it shows as Pending, and a failed or cancelled
  payment shows as Failed.

**Admin panel (`admin/`)**
- Dashboard: today's orders, pending orders, ready orders, and total
  amount collected from Paid payments.
- Orders: all orders with customer, branch, service, status, total and
  payment status. Staff and Admin can move an order one step forward
  (Pending, Picked Up, Washing, Ready, Collected) and assign an
  employee. An order cannot go backwards.
- Branches, Employees, Services: full add / edit / delete (Admin only).
- Payments: list payments and record a payment for an order (manual
  record only).
- Deliveries: schedule and update deliveries (type Pickup or Delivery,
  address, schedule date, status, assigned employee).
- Reports: daily report for a chosen date (orders, total value, amount
  collected) shown on screen with a print button.

**Technical**
- One `config.php` using PDO with prepared statements for every query.
  No user input is put straight into SQL.
- Passwords are hashed with `password_hash()` and checked with
  `password_verify()`.
- All user data printed on a page goes through `htmlspecialchars()`.
- Server-side validation on every form (not only JavaScript).
- Order status can only move forward; a customer can only cancel
  their own Pending order; a customer can only see and pay their own
  orders.

## Access rules

- Customer pages (`dashboard.php`, `book.php`, `my-bookings.php`,
  `payment.php`) need a logged-in customer.
- Admin pages need a logged-in employee. Staff and Admin can use the
  dashboard, orders, payments, deliveries and reports. Only Admin can
  manage branches, employees and services.

## Notes on the design (read this before marking)

- **Two added columns.** The Deliverable 2 schema has no users table
  and no password, so login could not work. The only two additions to
  the D2 schema are `Customer.Customer_password` and
  `Employee.Employee_password` (both `VARCHAR(255)`). Everything else
  matches Deliverable 2.
- **Phone numbers are `VARCHAR(20)`, not `INT`.** Deliverable 2 lists
  phone as `int`, but phone numbers need leading zeros, so they are
  stored as text. This applies to `Customer_phone`, `Branch_phone`
  and `Employee_phone`.
- **The pickup time slot is stored in `Order_Item.Order_notes`**
  (for example "Time slot: 08:00-10:00"), because the D2 schema has
  no separate time-slot column.
- **Order status `Cancelled`** is used when a customer cancels a
  Pending order. It is not part of the forward flow
  (Pending, Picked Up, Washing, Ready, Collected).
- **Sample data.** The SQL file inserts 2 branches, 3 services, 3 item
  types, 1 admin, 1 staff, 1 customer and 2 sample orders, plus one
  Paid payment and one delivery for the second order so the dashboard
  and reports have something to show.

## M-Pesa STK Push (payment verification)

M-Pesa is wired in through Safaricom Daraja. The files are
`mpesa_config.php`, `mpesa.php` and `mpesa_callback.php`, and the
Payment table has two extra columns, `Mpesa_checkout_id` and
`Mpesa_receipt`. A fresh import of `database/laundry_db.sql` already
has them. On a database that was imported before this change, run
`database/mpesa_payments_update.sql` once.

To make real payments work:

1. Create an app at developer.safaricom.co.ke and copy the Consumer
   Key, Consumer Secret and Passkey into `mpesa_config.php`, plus your
   shortcode. Keep those values secret.
2. Host the site on a public address (the app cannot take M-Pesa on
   localhost), and set `MPESA_CALLBACK_URL` to the public address of
   `mpesa_callback.php`. It must start with `https`.
3. Set `MPESA_SANDBOX` to `true` while testing, then `false` for live
   M-Pesa.

How verification works: when a customer pays by M-Pesa, the system
sends the STK push and records the payment as Pending with the
checkout request ID. Safaricom then calls `mpesa_callback.php`.
Only when that callback reports ResultCode 0 is the payment marked
Paid and the M-Pesa receipt saved. A cancelled or failed payment is
marked Failed. So an order is never marked Paid unless Safaricom
confirms the money arrived.

## What is NOT in this deliverable

- **No SMS.** Order status is shown inside the app, not sent by text.
- **No forgot-password page.** That is a Deliverable 4 item.
- **No Jasper or Crystal Reports.** The report is a simple on-screen
  daily report with a print button.
- **No two-factor authentication (2FA).**
- **No audit logs.** That is a Deliverable 4 item.
- **No online hosting.** The app runs on localhost through XAMPP.

## Files

```
README.md
database/laundry_db.sql, database/mpesa_payments_update.sql
config.php, mpesa_config.php, mpesa.php, mpesa_callback.php
index.php, register.php, login.php, logout.php
dashboard.php, book.php, my-bookings.php, payment.php
css/style.css
admin/index.php, admin/orders.php, admin/branches.php
admin/employees.php, admin/services.php, admin/payments.php
admin/deliveries.php, admin/reports.php
.gitignore
```
