<?php
/** Upload the new package into a separate 6.6.2 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8097';$cookie='/tmp/update670.cookies';$n=0;
function req670(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check670(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_upgrade670','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.6.2' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$emailSettings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade670/config.php');file_put_contents('/srv/upgrade670/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req670('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req670('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check670($code===200&&str_contains($body,'Business overview'),'6.6.2 fixture login');
[$code,$body]=req670('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req670('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update670.zip','application/zip','thirtyvi-degrees-update-6.7.0.zip')]);
check670($code===200&&str_contains($body,'Update installed.'),'6.6.2 dashboard uploader accepts 6.7.0');
check670($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.7.0','Version recorded as 6.7.0');
check670($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check670(hash_file('sha256','/srv/upgrade670/config.php')===$config,'Hosting configuration preserved');
check670(hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)))===$emailSettings,'Existing email settings preserved');
check670(file_get_contents('/srv/upgrade670/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check670($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check670($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req670('/admin/workspace');check670($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req670('/private-chef');check670($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','integrations.php'] as $file)check670(hash_file('sha256','/srv/upgrade670/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade670/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check670($manifest['has_migrations']===false&&$manifest['from']==='6.6.2'&&$manifest['to']==='6.7.0','Code-only restore point created');
[$code,$body]=req670('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update670.zip','application/zip','thirtyvi-degrees-update-6.7.0.zip')]);check670(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check670(is_file('/srv/upgrade670/app/checkout.php'),'Checkout helper installed');check670(is_file('/srv/upgrade670/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req670('/public/checkout.php?probe=1');$probe=json_decode($body,true);check670($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check670(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req670('/admin/checkout-health');check670($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
check670(is_file('/srv/upgrade670/public/assets/email-640.css'),'Email stylesheet installed');
check670(is_file('/srv/upgrade670/public/assets/email-640.js'),'Email feedback script installed');
[$code,$body]=req670('/admin/security');check670($code===200&&str_contains($body,'Change login email'),'Owner security page available');
[$code,$body]=req670('/admin/categories');check670($code===200&&str_contains($body,'Create category'),'Category editor available');
[$code,$body]=req670('/admin/email');check670($code===200&&str_contains($body,'Send test email')&&str_contains($body,'Microsoft modern authentication'),'Microsoft setup and test-mail UI available');
check670(is_file('/srv/upgrade670/app/microsoft-mail.php'),'OAuth module installed');
[$code,$body]=req670('/admin/email');check670(str_contains($body,'Send up to 5 pending emails now')&&str_contains($body,'email-cron-command'),'Cron health and manual queue controls installed');
check670(is_file('/srv/upgrade670/app/email-automation.php'),'Automation health module installed');
foreach(['customer_profiles','wallet_accounts','wallet_topups','wallet_ledger','wallet_spends','chat_conversations','chat_messages','chat_reactions'] as $table)check670((bool)$db->query("SHOW TABLES LIKE '$table'")->fetchColumn(),'Existing customer/wallet/chat table preserved: '.$table);
[$code,$body]=req670('/admin/chat');check670($code===200&&str_contains($body,'Chat availability'),'Owner chat pause controls installed');
[$code,$body]=req670('/admin/wallets');check670($code===200&&str_contains($body,'Customer wallets'),'Owner wallet records installed');
[$code,$body]=req670('/admin/cron');check670($code===200&&str_contains($body,'Start scheduled cron & email test'),'Cron test page installed');
[$code,$body]=req670('/admin/cron/status');check670($code===200&&is_array(json_decode($body,true)),'Owner cron status API installed');
check670($db->query("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_queue'")->fetchColumn()==='utf8mb4_unicode_ci','Email queue migrated to Unicode');
[$code,$body]=req670('/digital-card');check670($code===200&&str_contains($body,'Tosin Olateru'),'Public digital card installed');
[$code,$body]=req670('/admin/digital-card');check670($code===200&&str_contains($body,'Save digital card'),'Owner digital card editor installed');
[$code,$body]=req670('/digital-card/contact.vcf');check670($code===200&&str_contains($body,'BEGIN:VCARD'),'Downloadable contact installed');
echo "TOTAL: $n passed\n";
