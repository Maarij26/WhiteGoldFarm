<?php
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';
echo "Admin email: "; $email=trim(fgets(STDIN)); if(!filter_var($email,FILTER_VALIDATE_EMAIL))exit("Invalid email\n");
echo "Password (12+ characters; input may be visible in this terminal): "; $password=rtrim(fgets(STDIN)); if(strlen($password)<12)exit("Password too short\n");
query('INSERT INTO admins(email,password_hash) VALUES(?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)',[$email,password_hash($password,PASSWORD_DEFAULT)]); echo "Admin saved.\n";
