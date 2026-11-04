<?php
/** Upload the new package into a separate 6.0.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8090';$cookie='/tmp/update612.cookies';$n=0;
function req612(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check612(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.1.1' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade612/config.php');file_put_contents('/srv/upgrade612/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req612('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req612('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check612($code===200&&str_contains($body,'Business overview'),'6.1.1 fixture login');
[$code,$body]=req612('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req612('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update612.zip','application/zip','thirtyvi-degrees-update-6.1.2.zip')]);
check612($code===200&&str_contains($body,'Update installed.'),'6.1.1 dashboard uploader accepts 6.1.2');
check612($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.1.2','Version recorded as 6.1.2');
check612($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check612(hash_file('sha256','/srv/upgrade612/config.php')===$config,'Hosting configuration preserved');
check612(file_get_contents('/srv/upgrade612/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check612($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check612($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req612('/admin/workspace');check612($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req612('/private-chef');check612($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','paystack-refunds.php','integrations.php'] as $file)check612(hash_file('sha256','/srv/upgrade612/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade612/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check612($manifest['has_migrations']===false&&$manifest['from']==='6.1.1'&&$manifest['to']==='6.1.2','Code-only restore point created');
[$code,$body]=req612('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update612.zip','application/zip','thirtyvi-degrees-update-6.1.2.zip')]);check612(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check612(is_file('/srv/upgrade612/app/checkout.php'),'Checkout helper installed');check612(is_file('/srv/upgrade612/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req612('/public/checkout.php?probe=1');$probe=json_decode($body,true);check612($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check612(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req612('/admin/checkout-health');check612($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
echo "TOTAL: $n passed\n";
