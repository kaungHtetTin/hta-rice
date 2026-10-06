<?php
return ['up'=>[
    'CREATE TABLE voucher_settings (id TINYINT UNSIGNED PRIMARY KEY, settings TEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB'
], 'down'=>['DROP TABLE voucher_settings']];
