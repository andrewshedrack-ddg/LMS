-- ============================================================
-- M-Pesa STK push: database update.
-- Run this ONCE on a database that already has the Payment
-- table (for example the live Laundry_db). It adds the two
-- columns the STK push needs. Safe to run once.
--
-- A fresh import of laundry_db.sql already includes these
-- two columns, so you do not need this file for a new install.
-- ============================================================

ALTER TABLE Payment
    ADD COLUMN Mpesa_checkout_id VARCHAR(64) NULL AFTER Payment_status,
    ADD COLUMN Mpesa_receipt VARCHAR(32) NULL AFTER Mpesa_checkout_id;
