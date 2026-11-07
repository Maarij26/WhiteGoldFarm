<?php
declare(strict_types=1);
$configFile = dirname(__DIR__).'/config.php';
if (!is_file($configFile)) { http_response_code(503); exit('Shop setup is not complete. Please contact the shop.'); }
$config = require $configFile;
date_default_timezone_set('Asia/Karachi');
ini_set('display_errors','0');
header('X-Content-Type-Options: nosniff'); header('X-Frame-Options: SAMEORIGIN'); header('Referrer-Policy: strict-origin-when-cross-origin');
session_set_cookie_params(['httponly'=>true,'secure'=>str_starts_with($config['site_url'],'https://'),'samesite'=>'Lax','path'=>'/']);
ini_set('session.use_strict_mode','1'); session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function db(): PDO { static $db; global $config; if (!$db) { $c=$config['db']; $db=new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4",$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); $db->exec("SET time_zone = '+00:00'"); } return $db; }
function query(string $sql,array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function uuid(): string { $b=random_bytes(16); $b[6]=chr((ord($b[6])&15)|64); $b[8]=chr((ord($b[8])&63)|128); $h=bin2hex($b); return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20); }
function money(mixed $n): string { return 'Rs. '.number_format((float)$n,2); }
function jsonResponse(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode($data,JSON_THROW_ON_ERROR); exit; }
function csrf(): void { if (!hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??$_POST['csrf']??''))) { http_response_code(403); exit('Session expired. Refresh and try again.'); } }
function admin(): void { if (empty($_SESSION['admin']) || time()-($_SESSION['admin_seen']??0)>1800) { unset($_SESSION['admin']); header('Location: /admin/login'); exit; } $_SESSION['admin_seen']=time(); }
function redirect(string $path): never { header('Location: '.$path, true,303); exit; }
function products(): array { return query('SELECT * FROM products ORDER BY sort_order,created_at,id')->fetchAll(); }
function categories(): array { return query('SELECT name FROM categories ORDER BY created_at,name')->fetchAll(PDO::FETCH_COLUMN); }
function limited(string $scope,int $max): bool { global $config; $key=hash_hmac('sha256',$scope.'|'.($_SERVER['REMOTE_ADDR']??'unknown'),$config['app_key']); query('DELETE FROM rate_limits WHERE expires_at<UTC_TIMESTAMP()'); query('INSERT INTO rate_limits(bucket,hits,expires_at) VALUES(?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE hits=hits+1',[$key]); return (int)query('SELECT hits FROM rate_limits WHERE bucket=?',[$key])->fetchColumn()>$max; }
function unlimited(array $cats): bool { foreach($cats as $c) if(in_array(strtolower(trim($c)),['butter','makhan','makhan (butter)','desi ghee'],true)) return true; return false; }
function distance(float $lat,float $lng): float { $a=sin(deg2rad($lat-33.5667)/2)**2+cos(deg2rad(33.5667))*cos(deg2rad($lat))*sin(deg2rad($lng-73.1499)/2)**2; return 6371*2*asin(sqrt(min(1,max(0,$a)))); }
function field(string $name,string $label,string $value='',string $type='text'): void { echo '<label class="field">'.e($label).'<input name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" required></label>'; }
function token(): void { echo '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
