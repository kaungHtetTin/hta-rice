<?php
return ['up' => [
    'ALTER TABLE purchases ADD amount_paid DECIMAL(20,2) NOT NULL DEFAULT 0 AFTER amount',
    // Existing purchases predate credit tracking. Preserve them as fully paid.
    'UPDATE purchases SET amount_paid = amount',
    "CREATE TABLE supplier_payments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, supplier_id BIGINT UNSIGNED NOT NULL, amount DECIMAL(20,2) NOT NULL CHECK(amount > 0), paid_on DATE NOT NULL, notes TEXT NOT NULL, user_id BIGINT UNSIGNED NOT NULL, request_key CHAR(64) NOT NULL UNIQUE, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX(supplier_id,paid_on), FOREIGN KEY(supplier_id) REFERENCES suppliers(id), FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB"
], 'down' => ['DROP TABLE supplier_payments', 'ALTER TABLE purchases DROP COLUMN amount_paid']];
