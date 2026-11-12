<?php
/** Upload the new package into a separate 6.2.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8090';$cookie='/tmp/update621.cookies';$n=0;
function req621(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check621(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.2.0' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade621/config.php');file_put_contents('/srv/upgrade621/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req621('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req621('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check621($code===200&&str_contains($body,'Business overview'),'6.2.0 fixture login');
[$code,$body]=req621('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req621('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update621.zip','application/zip','thirtyvi-degrees-update-6.2.1.zip')]);
check621($code===200&&str_contains($body,'Update installed.'),'6.2.0 dashboard uploader accepts 6.2.1');
check621($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.2.1','Version recorded as 6.2.1');
check621($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check621(hash_file('sha256','/srv/upgrade621/config.php')===$config,'Hosting configuration preserved');
check621(file_get_contents('/srv/upgrade621/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check621($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check621($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req621('/admin/workspace');check621($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req621('/private-chef');check621($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','paystack-refunds.php','integrations.php'] as $file)check621(hash_file('sha256','/srv/upgrade621/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade621/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check621($manifest['has_migrations']===false&&$manifest['from']==='6.2.0'&&$manifest['to']==='6.2.1','Code-only restore point created');
[$code,$body]=req621('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update621.zip','application/zip','thirtyvi-degrees-update-6.2.1.zip')]);check621(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check621(is_file('/srv/upgrade621/app/checkout.php'),'Checkout helper installed');check621(is_file('/srv/upgrade621/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req621('/public/checkout.php?probe=1');$probe=json_decode($body,true);check621($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check621(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req621('/admin/checkout-health');check621($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
check621(is_file('/srv/upgrade621/public/assets/auth-621.css'),'Password stylesheet installed');
check621(is_file('/srv/upgrade621/public/assets/auth-621.js'),'Password interaction script installed');
echo "TOTAL: $n passed\n";
