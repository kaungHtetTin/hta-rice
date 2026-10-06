<?php
return ['up'=>[
    "CREATE TABLE purchase_items (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, purchase_id BIGINT UNSIGNED NOT NULL, rice_type_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(16,3) NOT NULL, weight_lb DECIMAL(16,3) NULL, unit_price DECIMAL(16,2) NOT NULL, amount DECIMAL(20,2) NOT NULL, FOREIGN KEY(purchase_id) REFERENCES purchases(id), FOREIGN KEY(rice_type_id) REFERENCES rice_types(id), INDEX(purchase_id,id)) ENGINE=InnoDB",
    "INSERT INTO purchase_items (purchase_id,rice_type_id,quantity,weight_lb,unit_price,amount) SELECT id,rice_type_id,quantity,weight_lb,unit_price,amount FROM purchases",
    "ALTER TABLE movements ADD INDEX movement_purchase_idx (purchase_id), DROP INDEX purchase_id"
], 'down'=>[
    "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Purchase items cannot be rolled back safely once multi-item purchases exist.'"
]];
