<?php
// Recover earlier multi-item submissions from their deterministic item keys.
$numbers=implode(' UNION ALL ',array_map(fn($n)=>'SELECT '.$n.' n',range(1,49)));
return ['up'=>[
    'ALTER TABLE purchases ADD version INT UNSIGNED NOT NULL DEFAULT 1',
    'ALTER TABLE movements ADD operation_id BIGINT UNSIGNED NULL, ADD version INT UNSIGNED NOT NULL DEFAULT 1, ADD INDEX movements_operation (operation_id)',
    "UPDATE movements SET operation_id=id WHERE kind IN ('transfer','production')",
    "UPDATE movements child JOIN movements root ON root.kind=child.kind AND root.id<child.id JOIN ($numbers) numbers ON child.request_key=SHA2(CONCAT(root.request_key,':',root.kind,'-item:',numbers.n),256) SET child.operation_id=root.id WHERE child.kind IN ('transfer','production')",
], 'down'=>[
    'ALTER TABLE movements DROP INDEX movements_operation, DROP COLUMN operation_id, DROP COLUMN version',
    'ALTER TABLE purchases DROP COLUMN version',
]];
