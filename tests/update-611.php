<?php
/** Upload the new package into a separate 6.0.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8089';$cookie='/tmp/update611.cookies';$n=0;
function req611(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check611(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.1.0' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade611/config.php');file_put_contents('/srv/upgrade611/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req611('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req611('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check611($code===200&&str_contains($body,'Business overview'),'6.1.0 fixture login');
[$code,$body]=req611('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req611('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update611.zip','application/zip','thirtyvi-degrees-update-6.1.1.zip')]);
check611($code===200&&str_contains($body,'Update installed.'),'6.1.0 dashboard uploader accepts 6.1.1');
check611($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.1.1','Version recorded as 6.1.1');
check611($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check611(hash_file('sha256','/srv/upgrade611/config.php')===$config,'Hosting configuration preserved');
check611(file_get_contents('/srv/upgrade611/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check611($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check611($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req611('/admin/workspace');check611($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req611('/private-chef');check611($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','paystack-refunds.php','integrations.php'] as $file)check611(hash_file('sha256','/srv/upgrade611/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade611/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check611($manifest['has_migrations']===false&&$manifest['from']==='6.1.0'&&$manifest['to']==='6.1.1','Code-only restore point created');
[$code,$body]=req611('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update611.zip','application/zip','thirtyvi-degrees-update-6.1.1.zip')]);check611(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check611(is_file('/srv/upgrade611/app/checkout.php'),'Checkout helper installed');check611(is_file('/srv/upgrade611/public/assets/checkout-611.js'),'Checkout validation script installed');
echo "TOTAL: $n passed\n";
