<?php
// Local development only: php -S 127.0.0.1:8080 -t public router.php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$file=realpath(__DIR__.'/public'.$path);
if($path!=='/' && $file && str_starts_with($file,realpath(__DIR__.'/public').DIRECTORY_SEPARATOR) && is_file($file)) return false;
require __DIR__.'/public/index.php';
