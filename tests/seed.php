<?php
require __DIR__.'/../app/core.php';
if(cfg()['db_name']!=='thirtyvi_dev')exit('Development database only');
foreach(['admin','customer','manager'] as $role)sql('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)',[$role.' Demo',$role.'@example.test',password_hash('Test-password-600',PASSWORD_DEFAULT),$role]);
