<?php
/** Upload the new package into a separate 6.4.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8093';$cookie='/tmp/update650.cookies';$n=0;
function req650(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check650(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.4.0' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$emailSettings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade650/config.php');file_put_contents('/srv/upgrade650/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req650('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req650('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check650($code===200&&str_contains($body,'Business overview'),'6.4.0 fixture login');
[$code,$body]=req650('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req650('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update650.zip','application/zip','thirtyvi-degrees-update-6.5.0.zip')]);
check650($code===200&&str_contains($body,'Update installed.'),'6.4.0 dashboard uploader accepts 6.5.0');
check650($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.5.0','Version recorded as 6.5.0');
check650($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check650(hash_file('sha256','/srv/upgrade650/config.php')===$config,'Hosting configuration preserved');
check650(hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)))===$emailSettings,'Existing email settings preserved');
check650(file_get_contents('/srv/upgrade650/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check650($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check650($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req650('/admin/workspace');check650($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req650('/private-chef');check650($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','integrations.php'] as $file)check650(hash_file('sha256','/srv/upgrade650/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade650/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check650($manifest['has_migrations']===false&&$manifest['from']==='6.4.0'&&$manifest['to']==='6.5.0','Code-only restore point created');
[$code,$body]=req650('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update650.zip','application/zip','thirtyvi-degrees-update-6.5.0.zip')]);check650(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check650(is_file('/srv/upgrade650/app/checkout.php'),'Checkout helper installed');check650(is_file('/srv/upgrade650/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req650('/public/checkout.php?probe=1');$probe=json_decode($body,true);check650($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check650(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req650('/admin/checkout-health');check650($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
check650(is_file('/srv/upgrade650/public/assets/email-640.css'),'Email stylesheet installed');
check650(is_file('/srv/upgrade650/public/assets/email-640.js'),'Email feedback script installed');
[$code,$body]=req650('/admin/security');check650($code===200&&str_contains($body,'Change login email'),'Owner security page available');
[$code,$body]=req650('/admin/categories');check650($code===200&&str_contains($body,'Create category'),'Category editor available');
[$code,$body]=req650('/admin/email');check650($code===200&&str_contains($body,'Send test email')&&str_contains($body,'Microsoft modern authentication'),'Microsoft setup and test-mail UI available');
check650(is_file('/srv/upgrade650/app/microsoft-mail.php'),'OAuth module installed');
[$code,$body]=req650('/admin/email');check650(str_contains($body,'Send up to 5 pending emails now')&&str_contains($body,'email-cron-command'),'Cron health and manual queue controls installed');
check650(is_file('/srv/upgrade650/app/email-automation.php'),'Automation health module installed');
echo "TOTAL: $n passed\n";
