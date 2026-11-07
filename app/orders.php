<?php
function sendOrderEmail(string $id): void {
 global $config;
 try {
  require_once dirname(__DIR__).'/vendor/autoload.php';
  $o=query('SELECT * FROM orders WHERE id=?',[$id])->fetch(); $items=query('SELECT * FROM order_items WHERE order_id=?',[$id])->fetchAll();
  $s=$config['smtp']; $mail=new PHPMailer\PHPMailer\PHPMailer(true); $mail->isSMTP(); $mail->Host=$s['host']; $mail->Port=(int)$s['port']; $mail->SMTPAuth=true; $mail->Username=$s['user']; $mail->Password=$s['password']; $mail->SMTPSecure=$mail->Port===465?'ssl':'tls'; $mail->Timeout=10; $mail->CharSet='UTF-8'; $mail->setFrom($s['from']?:$s['user'],'White Gold Farm'); $mail->addAddress($s['notify']); $mail->Subject='New COD order '.$o['order_reference'].' · White Gold Farm';
  $text="WHITE GOLD FARM\nOrder: {$o['order_reference']}\n".(new DateTimeImmutable($o['created_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Karachi'))->format('d M Y H:i').' PKT'."\n{$o['full_name']}\n{$o['phone']}\n{$o['address']}, {$o['city']}\n";
  $rows=''; foreach($items as $i) { $text.="\n{$i['product_name']} ({$i['unit']}) × {$i['quantity']} @ ".money($i['unit_price']).' = '.money($i['line_total']); $rows.='<tr><td>'.e($i['product_name'].' ('.$i['unit'].')').'</td><td>'.e($i['quantity']).'</td><td>'.money($i['unit_price']).'</td><td>'.money($i['line_total']).'</td></tr>'; }
  $text.="\nTotal: ".money($o['total'])."\nCash on delivery\nNotes: {$o['notes']}";
  $mail->isHTML(true); $mail->Body='<html><body style="font:16px Arial;color:#073b29;background:#faf8ee;padding:24px"><h1>White Gold Farm</h1><h2>'.e($o['order_reference']).'</h2><p>'.nl2br(e(explode("\n\n",$text)[0])).'</p><table cellpadding="12"><tr><th>Product</th><th>Qty</th><th>Unit price</th><th>Total</th></tr>'.$rows.'</table><h2>'.money($o['total']).'</h2><p>Cash on delivery</p><p>'.nl2br(e($o['notes'])).'</p></body></html>'; $mail->AltBody=$text; $mail->send(); query("UPDATE orders SET email_status='sent' WHERE id=?",[$id]);
 } catch(Throwable $e) { error_log('Order email failed: '.$id.' ('.$e::class.')'); query("UPDATE orders SET email_status='failed' WHERE id=?",[$id]); }
}
function placeOrder(): never {
 csrf();
 if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) jsonResponse(['error'=>'Request too large.'],413);
 $raw=file_get_contents('php://input',false,null,0,20001); if(strlen($raw)>20000) jsonResponse(['error'=>'Request too large.'],413);
 $x=json_decode($raw,true); if(!is_array($x)) jsonResponse(['error'=>'Invalid request.'],400);
 $valid=true; foreach(['full_name'=>[2,100],'address'=>[10,500],'city'=>[2,100],'notes'=>[0,1000]] as $k=>$range) { if(isset($x[$k])&&!is_string($x[$k])) {$valid=false;continue;} $x[$k]=trim($x[$k]??''); if(strlen($x[$k])<$range[0]||strlen($x[$k])>$range[1])$valid=false; }
 $phone=is_string($x['phone']??null)?preg_replace('/[\s()-]/','',$x['phone']):'';
 $id=$x['request_id']??''; $items=$x['items']??[];
 if(!$valid||!preg_match('/^(?:\+92|92|0)3\d{9}$/',$phone)||!is_string($id)||!preg_match('/^[0-9a-f-]{36}$/i',$id)||!empty($x['website'])||!is_array($items)||count($items)<1||count($items)>30) jsonResponse(['error'=>'Please check your details and cart.'],422);
 $wanted=[]; foreach($items as $item) { if(!is_array($item)||!is_string($item['product_id']??null)||!is_int($item['quantity']??null)||$item['quantity']<1||$item['quantity']>99||isset($wanted[$item['product_id']])) jsonResponse(['error'=>'Invalid cart.'],422); $wanted[$item['product_id']]=$item['quantity']; }
 if(limited('order',5)) jsonResponse(['error'=>'Too many attempts. Please wait 15 minutes or contact us.'],429);
 try {
  db()->beginTransaction();
  $existing=query('SELECT id,order_reference FROM orders WHERE request_id=?',[$id])->fetch();
  if($existing) {db()->commit(); $_SESSION['confirmation']=$existing['order_reference']; jsonResponse(['reference'=>$existing['order_reference']]);}
  $keys=array_keys($wanted); sort($keys); $ps=query('SELECT * FROM products WHERE id IN ('.implode(',',array_fill(0,count($keys),'?')).') ORDER BY id FOR UPDATE',$keys)->fetchAll();
  if(count($ps)!==count($keys)) throw new DomainException('A product is no longer available. Refresh your basket.');
  if(!unlimited(array_column($ps,'category'))) { $loc=$x['delivery_location']??[]; $lat=$loc['latitude']??null; $lng=$loc['longitude']??null; if(!is_numeric($lat)||!is_numeric($lng)||abs((float)$lat)>90||abs((float)$lng)>180) throw new DomainException('Choose a valid delivery location.'); if(distance((float)$lat,(float)$lng)>5) throw new DomainException('Your address is outside our 5 km delivery area. Orders containing Butter, Makhan or Desi Ghee have no distance limit.'); $x['notes'].="\nDelivery pin: https://www.google.com/maps?q=".(float)$lat.','.(float)$lng; }
  $total=0; foreach($ps as $p) { if(!$p['in_stock']) throw new DomainException($p['name'].' is currently out of stock.'); $total+=(int)round((float)$p['price']*100)*$wanted[$p['id']]; }
  $day=date('Y-m-d'); query('INSERT INTO daily_counters(day,last_number) VALUES(?,1) ON DUPLICATE KEY UPDATE last_number=last_number+1',[$day]); $n=query('SELECT last_number FROM daily_counters WHERE day=? FOR UPDATE',[$day])->fetchColumn(); $ref='WG-'.date('ymd').'-'.str_pad((string)$n,3,'0',STR_PAD_LEFT); $orderId=uuid();
  query('INSERT INTO orders(id,request_id,order_reference,full_name,phone,address,city,notes,total) VALUES(?,?,?,?,?,?,?,?,?)',[$orderId,$id,$ref,$x['full_name'],$phone,$x['address'],$x['city'],$x['notes'],$total/100]);
  foreach($ps as $p)query('INSERT INTO order_items(order_id,product_id,product_name,unit,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?,?)',[$orderId,$p['id'],$p['name'],$p['unit'],$wanted[$p['id']],$p['price'],round((float)$p['price']*$wanted[$p['id']],2)]);
  db()->commit(); $_SESSION['confirmation']=$ref;
 } catch(Throwable $e) {if(db()->inTransaction())db()->rollBack(); if($e instanceof PDOException && $e->getCode()==='23000') { $old=query('SELECT order_reference FROM orders WHERE request_id=?',[$id])->fetchColumn(); if($old){$_SESSION['confirmation']=$old;jsonResponse(['reference'=>$old]);} } if($e instanceof DomainException)jsonResponse(['error'=>$e->getMessage()],422); error_log('Order failure: '.$e::class); jsonResponse(['error'=>'Order service is temporarily unavailable. Please try again.'],503);}
 sendOrderEmail($orderId); jsonResponse(['reference'=>$ref],201);
}
