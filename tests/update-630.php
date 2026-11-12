<?php
/** Upload the new package into a separate 6.2.1 fixture, preserving payment configuration. */
$base='http://127.0.0.1:8091';$cookie='/tmp/update630.cookies';$n=0;
function req630(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);return [$code,$body];}
function check630(bool $value,string $label):void{global $n;if(!$value)throw new RuntimeException('FAIL: '.$label);$n++;echo 'PASS: '.$label.PHP_EOL;}
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');
$db->exec("UPDATE settings SET value='6.2.1' WHERE `key`='app_version'");
$settings=hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC)));
$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();$beforeB=$db->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$config=hash_file('sha256','/srv/upgrade630/config.php');file_put_contents('/srv/upgrade630/public/uploads/preserve.txt','Existing customer media');
[$code,$body]=req630('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req630('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check630($code===200&&str_contains($body,'Business overview'),'6.2.1 fixture login');
[$code,$body]=req630('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
[$code,$body]=req630('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update630.zip','application/zip','thirtyvi-degrees-update-6.3.0.zip')]);
check630($code===200&&str_contains($body,'Update installed.'),'6.2.1 dashboard uploader accepts 6.3.0');
[$code,$body]=req630('/admin/security');check630(str_contains($body,'Welcome Back'),'Legacy owner session requires fresh sign-in');
[$code,$body]=req630('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$code,$body]=req630('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);check630(str_contains($body,'Business overview'),'Existing owner credentials still work');
[$code,$body]=req630('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];

check630($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.3.0','Version recorded as 6.3.0');
check630($settings===hash('sha256',json_encode($db->query("SELECT `key`,value FROM settings WHERE `key` LIKE 'paystack%' ORDER BY `key`")->fetchAll(PDO::FETCH_ASSOC))),'All Paystack credentials, activation and mode preserved');
check630(hash_file('sha256','/srv/upgrade630/config.php')===$config,'Hosting configuration preserved');
check630(file_get_contents('/srv/upgrade630/public/uploads/preserve.txt')==='Existing customer media','Customer uploads preserved');
check630($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Orders preserved');check630($db->query('SELECT COUNT(*) FROM bookings')->fetchColumn()===$beforeB,'Bookings preserved');
[$code,$body]=req630('/admin/workspace');check630($code===200&&str_contains($body,'What would you like to do?'),'Grouped workspace available after upload');
[$code,$body]=req630('/private-chef');check630($code===200&&str_contains($body,'data-booking-wizard'),'New booking experience available after upload');
foreach(['payment-verification.php','paystack-refunds.php','integrations.php'] as $file)check630(hash_file('sha256','/srv/upgrade630/app/'.$file)===hash_file('sha256','/srv/site/app/'.$file),'Existing backend unchanged: '.$file);
$points=glob('/srv/upgrade630/storage/.backups/*/restore-point.json');$latest=end($points);$manifest=json_decode(file_get_contents($latest),true);check630($manifest['has_migrations']===true&&$manifest['from']==='6.2.1'&&$manifest['to']==='6.3.0','Migration-marked restore point created');
[$code,$body]=req630('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/update630.zip','application/zip','thirtyvi-degrees-update-6.3.0.zip')]);check630(str_contains($body,'Update version must be newer'),'Duplicate version rejected');
check630(is_file('/srv/upgrade630/app/checkout.php'),'Checkout helper installed');check630(is_file('/srv/upgrade630/public/assets/checkout-612.js'),'Checkout validation script installed');
[$status,$body]=req630('/public/checkout.php?probe=1');$probe=json_decode($body,true);check630($status===200&&($probe['build']??'')==='6.1.2','Physical endpoint installed and running matching build');check630(!isset($probe['csrf']),'Public probe does not expose CSRF/session details');
[$status,$body]=req630('/admin/checkout-health');check630($status===200&&str_contains($body,'Required database structure'),'Protected diagnostic page available');
check630(is_file('/srv/upgrade630/public/assets/catalogue-630.css'),'Catalogue stylesheet installed');
check630(is_file('/srv/upgrade630/public/assets/catalogue-630.js'),'Gallery interaction script installed');
check630((int)$db->query("SELECT COUNT(*) FROM schema_migrations WHERE name='630_catalogue.sql'")->fetchColumn()===1,'Catalogue migration recorded exactly once');
[$code,$body]=req630('/admin/security');check630($code===200&&str_contains($body,'Change login email'),'Owner security page available');
[$code,$body]=req630('/admin/categories');check630($code===200&&str_contains($body,'Create category'),'Category editor available');
echo "TOTAL: $n passed\n";
