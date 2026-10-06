<?php
return ['up'=>[
    'ALTER TABLE purchases ADD weight_lb DECIMAL(16,3) NULL AFTER quantity'
], 'down'=>[
    'ALTER TABLE purchases DROP COLUMN weight_lb'
]];
