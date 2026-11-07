<?php
// Offline import only. JSON format documented in README. Never import Auth hashes.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';
$file=$argv[1]??'';if(!is_file($file))exit("Usage: php bin/import-supabase.php export.json\n");
$data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if(!isset($data['products'],$data['orders'],$data['order_items']))exit("Missing export arrays\n");
if((int)query('SELECT count(*) FROM orders')->fetchColumn()>0)exit("Import requires a new destination database without orders.\n");
try {db()->beginTransaction();
 query('DELETE FROM products');query('DELETE FROM categories');query('DELETE FROM daily_counters');
 $cats=array_unique([...array_column($data['categories']??[],'name'),...array_column($data['products'],'category')]);foreach($cats as $c)query('INSERT INTO categories(name) VALUES(?)',[$c]);
 foreach($data['products'] as $p)query('INSERT INTO products(id,name,category,description,image,price,compare_price,unit,in_stock,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?)',[$p['id'],$p['name'],$p['category'],$p['description'],$p['image'],$p['price'],$p['compare_price'],$p['unit'],$p['in_stock']?1:0,$p['sort_order']??2147483647]);
 usort($data['orders'],fn($a,$b)=>strcmp($a['created_at'],$b['created_at']));$counters=[];
 foreach($data['orders'] as $o){$utc=new DateTimeImmutable($o['created_at']);$day=$utc->setTimezone(new DateTimeZone('Asia/Karachi'))->format('Y-m-d');$n=($counters[$day]??0)+1;$ref=$o['order_reference']??('WG-'.str_replace('-','',substr($day,2)).'-'.str_pad((string)$n,3,'0',STR_PAD_LEFT));if(preg_match('/^WG-\d{6}-(\d+)$/',$ref,$m))$n=max($n,(int)$m[1]);$counters[$day]=$n;query('INSERT INTO orders(id,request_id,order_reference,full_name,phone,address,city,notes,total,status,email_status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$o['id'],$o['request_id'],$ref,$o['full_name'],$o['phone'],$o['address'],$o['city'],$o['notes']??'',$o['total'],$o['status']??'new',$o['email_status']??'pending',$utc->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);}
 foreach($data['order_items'] as $i)query('INSERT INTO order_items(order_id,product_id,product_name,unit,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?,?)',[$i['order_id'],$i['product_id'],$i['product_name'],$i['unit'],$i['quantity'],$i['unit_price'],$i['line_total']]);
 foreach($counters as $day=>$n)query('INSERT INTO daily_counters VALUES(?,?)',[$day,$n]);db()->commit();echo "Imported successfully. Review remote image URLs before retiring Supabase.\n";
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();fwrite(STDERR,"Import rolled back: ".$e->getMessage()."\n");exit(1);}
