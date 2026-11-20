<?php
/** Upload the new package into a separate 6.5.0 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8094';$cookie='/tmp/update660.cookies';$n=0;
function req660(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check660(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_upgrade660','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.5.0' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$emailSettings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade660/config.php');file_put_contents('/srv/upgrade660/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req660('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req660('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check660($code===200&&str_contains($body,'Business overview'),'6.5.0 fixture login');
[$code,$body]=req660('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req660('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update660.zip','application/zip','thirtyvi-degrees-update-6.6.0.zip')]);
check660($code===200&&str_contains($body,'Update installed.'),'6.5.0 dashboard uploader accepts 6.6.0');
check660($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.6.0','Version recorded as 6.6.0');
check660($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check660(hash_file('sha256','/srv/upgrade660/config.php')===$config,'Hosting configuration preserved');
check660(hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'smtp%' OR `key` LIKE 'ms_mail_%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)))===$emailSettings,'Existing email settings preserved');
check660(file_get_contents('/srv/upgrade660/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check660($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check660($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req660('/admin/workspace');check660($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req660('/private-chef');check660($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','integrations.php'] as $file)check660(hash_file('sha256','/srv/upgrade660/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade660/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check660($manifest['has_migrations']===true&&$manifest['from']==='6.5.0'&&$manifest['to']==='6.6.0','Migration-aware restore point created');
[$code,$body]=req660('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update660.zip','application/zip','thirtyvi-degrees-update-6.6.0.zip')]);check660(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check660(is_file('/srv/upgrade660/app/checkout.php'),'Checkout helper installed');check660(is_file('/srv/upgrade660/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req660('/public/checkout.php?probe=1');$probe=json_decode($body,true);check660($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check660(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req660('/admin/checkout-health');check660($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
check660(is_file('/srv/upgrade660/public/assets/email-640.css'),'Email stylesheet installed');
check660(is_file('/srv/upgrade660/public/assets/email-640.js'),'Email feedback script installed');
[$code,$body]=req660('/admin/security');check660($code===200&&str_contains($body,'Change login email'),'Owner security page available');
[$code,$body]=req660('/admin/categories');check660($code===200&&str_contains($body,'Create category'),'Category editor available');
[$code,$body]=req660('/admin/email');check660($code===200&&str_contains($body,'Send test email')&&str_contains($body,'Microsoft modern authentication'),'Microsoft setup and test-mail UI available');
check660(is_file('/srv/upgrade660/app/microsoft-mail.php'),'OAuth module installed');
[$code,$body]=req660('/admin/email');check660(str_contains($body,'Send up to 5 pending emails now')&&str_contains($body,'email-cron-command'),'Cron health and manual queue controls installed');
check660(is_file('/srv/upgrade660/app/email-automation.php'),'Automation health module installed');
foreach(['customer_profiles','wallet_accounts','wallet_topups','wallet_ledger','wallet_spends','chat_conversations','chat_messages','chat_reactions'] as $table)check660((bool)$db->query("SHOW TABLES LIKE '$table'")->fetchColumn(),'Migration created '.$table);
[$code,$body]=req660('/admin/chat');check660($code===200&&str_contains($body,'Chat availability'),'Owner chat pause controls installed');
[$code,$body]=req660('/admin/wallets');check660($code===200&&str_contains($body,'Customer wallets'),'Owner wallet records installed');
echo "TOTAL: $n passed\n";
