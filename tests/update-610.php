<?php
/** Upload the new package into a separate 6.0.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8088';$cookie='/tmp/update610.cookies';$n=0;
function req610(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check610(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.0.0' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade610/config.php');file_put_contents('/srv/upgrade610/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req610('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req610('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check610($code===200&&str_contains($body,'Business Overview'),'6.0.0 fixture login');
[$code,$body]=req610('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req610('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update610.zip','application/zip','thirtyvi-degrees-update-6.1.0.zip')]);
check610($code===200&&str_contains($body,'Update installed.'),'6.0.0 dashboard uploader accepts 6.1.0');
check610($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.1.0','Version recorded as 6.1.0');
check610($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check610(hash_file('sha256','/srv/upgrade610/config.php')===$config,'Hosting configuration preserved');
check610(file_get_contents('/srv/upgrade610/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check610($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check610($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req610('/admin/workspace');check610($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req610('/private-chef');check610($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['paystack.php','payment-verification.php','paystack-refunds.php','integrations.php'] as $file)check610(hash_file('sha256','/srv/upgrade610/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade610/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check610($manifest['has_migrations']===false&&$manifest['from']==='6.0.0'&&$manifest['to']==='6.1.0','Code-only restore point created');
[$code,$body]=req610('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update610.zip','application/zip','thirtyvi-degrees-update-6.1.0.zip')]);check610(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
echo "TOTAL: $n passed\n";
