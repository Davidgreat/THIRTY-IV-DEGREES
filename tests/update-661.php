<?php
/** Upload the new package into a separate 6.6.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8095';$cookie='/tmp/update661.cookies';$n=0;
function req661(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check661(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_upgrade661','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.6.0' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$emailSettings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade661/config.php');file_put_contents('/srv/upgrade661/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req661('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req661('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check661($code===200&&str_contains($body,'Business overview'),'6.6.0 fixture login');
[$code,$body]=req661('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req661('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update661.zip','application/zip','thirtyvi-degrees-update-6.6.1.zip')]);
check661($code===200&&str_contains($body,'Update installed.'),'6.6.0 dashboard uploader accepts 6.6.1');
check661($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.6.1','Version recorded as 6.6.1');
check661($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check661(hash_file('sha256','/srv/upgrade661/config.php')===$config,'Hosting configuration preserved');
check661(hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)))===$emailSettings,'Existing email settings preserved');
check661(file_get_contents('/srv/upgrade661/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check661($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check661($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req661('/admin/workspace');check661($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req661('/private-chef');check661($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','integrations.php'] as $file)check661(hash_file('sha256','/srv/upgrade661/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade661/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check661($manifest['has_migrations']===false&&$manifest['from']==='6.6.0'&&$manifest['to']==='6.6.1','Code-only restore point created');
[$code,$body]=req661('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update661.zip','application/zip','thirtyvi-degrees-update-6.6.1.zip')]);check661(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check661(is_file('/srv/upgrade661/app/checkout.php'),'Checkout helper installed');check661(is_file('/srv/upgrade661/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req661('/public/checkout.php?probe=1');$probe=json_decode($body,true);check661($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check661(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req661('/admin/checkout-health');check661($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
check661(is_file('/srv/upgrade661/public/assets/email-640.css'),'Email stylesheet installed');
check661(is_file('/srv/upgrade661/public/assets/email-640.js'),'Email feedback script installed');
[$code,$body]=req661('/admin/security');check661($code===200&&str_contains($body,'Change login email'),'Owner security page available');
[$code,$body]=req661('/admin/categories');check661($code===200&&str_contains($body,'Create category'),'Category editor available');
[$code,$body]=req661('/admin/email');check661($code===200&&str_contains($body,'Send test email')&&str_contains($body,'Microsoft modern authentication'),'Microsoft setup and test-mail UI available');
check661(is_file('/srv/upgrade661/app/microsoft-mail.php'),'OAuth module installed');
[$code,$body]=req661('/admin/email');check661(str_contains($body,'Send up to 5 pending emails now')&&str_contains($body,'email-cron-command'),'Cron health and manual queue controls installed');
check661(is_file('/srv/upgrade661/app/email-automation.php'),'Automation health module installed');
foreach(['customer_profiles','wallet_accounts','wallet_topups','wallet_ledger','wallet_spends','chat_conversations','chat_messages','chat_reactions'] as $table)check661((bool)$db->query("SHOW TABLES LIKE '$table'")->fetchColumn(),'Existing customer/wallet/chat table preserved: '.$table);
[$code,$body]=req661('/admin/chat');check661($code===200&&str_contains($body,'Chat availability'),'Owner chat pause controls installed');
[$code,$body]=req661('/admin/wallets');check661($code===200&&str_contains($body,'Customer wallets'),'Owner wallet records installed');
echo "TOTAL: $n passed\n";
