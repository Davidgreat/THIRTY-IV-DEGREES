<?php
/** Exercise the uploaded platform's ORIGINAL web updater through multipart HTTP. */
$base='http://127.0.0.1:8087';$cookie='/tmp/update-test.cookies';$n=0;
function request(string $path,?array $data=null):array{global $base,$cookie;$c=curl_init($base.$path);$opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEJAR=>$cookie,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_TIMEOUT=>20];if($data!==null){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=$data;}curl_setopt_array($c,$opts);$body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);return [$code,$body];}
function test(bool $b,string $label):void{global $n;if(!$b)throw new RuntimeException('FAIL: '.$label);$n++;echo "PASS: $label\n";}
[$status,$body]=request('/login');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);
[$status,$body]=request('/login',['csrf'=>$m[1],'email'=>'admin@example.test','password'=>'Test-password-600']);test($status===200&&str_contains($body,'Business Overview'),'Original application administrator login');
[$status,$body]=request('/admin/updates');preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m);$token=$m[1];
$configHash=hash_file('sha256','/srv/original/config.php');
file_put_contents('/srv/original/public/uploads/update-preservation.txt','Customer upload sentinel');
$db=new PDO('mysql:host=127.0.0.1;dbname=thirtyvi_dev','dev','local-dev-only');$before=$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$db->exec("UPDATE settings SET value='5.8.3' WHERE `key`='app_version'");
[$status,$body]=request('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/thirtyvi-update.zip','application/zip','thirtyvi-degrees-update-6.0.0.zip')]);
test($status===200&&str_contains($body,'Update installed.'),'Original admin uploader installs release ZIP');
test($db->query("SELECT value FROM settings WHERE `key`='app_version'")->fetchColumn()==='6.0.0','Version updated to 6.0.0');
test(hash_file('sha256','/srv/original/config.php')===$configHash,'Hosting configuration preserved');
test(file_get_contents('/srv/original/public/uploads/update-preservation.txt')==='Customer upload sentinel','Customer uploads preserved');
test($db->query('SELECT COUNT(*) FROM orders')->fetchColumn()===$before,'Existing orders preserved');
test($db->query("SELECT COUNT(*) FROM schema_migrations WHERE name='600_payment_context.sql'")->fetchColumn()==1,'Additive migration recorded once');
// The original updater does not invalidate OPcache; allow its configured revalidation interval.
for($attempt=0;$attempt<10;$attempt++){
 [$status,$body]=request('/admin/paystack');if($status===200&&str_contains($body,'Your payment workspace'))break;usleep(500000);
}
test($status===200&&str_contains($body,'Your payment workspace'),'New payment setup accessible after opcode-cache revalidation');
[$status,$body]=request('/admin/updates');test(str_contains($body,'database migration, manual recovery only'),'Migration-aware restore warning displayed');
[$status,$body]=request('/admin/package/install',['csrf'=>$token,'type'=>'update','package'=>new CURLFile('/tmp/thirtyvi-update.zip','application/zip','thirtyvi-degrees-update-6.0.0.zip')]);
test(str_contains($body,'Update version must be newer'),'Reinstalling identical version is rejected');
echo "TOTAL: $n passed\n";
