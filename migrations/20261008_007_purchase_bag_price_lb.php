<?php
return ['up'=>[
    "ALTER TABLE purchase_items ADD quantity_bag DECIMAL(16,3) NULL AFTER quantity, ADD price_lb DECIMAL(16,2) NOT NULL DEFAULT 0 AFTER unit_price"
], 'down'=>[
    "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Bag quantities and pound prices cannot be rolled back safely after purchases are recorded.'"
]];
