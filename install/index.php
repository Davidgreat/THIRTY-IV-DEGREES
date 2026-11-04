<?php
require dirname(__DIR__).'/app/core.php';
if(is_file(ROOT.'/config.php')){http_response_code(403);exit('Already installed. Remove config.php only if intentionally reinstalling.');}
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 check_csrf();
 try{
  $host=trim($_POST['db_host']??'localhost');$name=trim($_POST['db_name']??'');$dbuser=trim($_POST['db_user']??'');$pass=$_POST['db_pass']??'';
  $email=trim($_POST['email']??'');$adminpass=$_POST['admin_pass']??'';
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($adminpass)<12)throw new Exception('Use a valid email and an admin password of at least 12 characters.');
  foreach([$host,$name,$dbuser] as $v)if(!preg_match('/^[a-zA-Z0-9_.-]+$/',$v))throw new Exception('Invalid database connection field.');
  $pdo=new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4",$dbuser,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $schema=file_get_contents(ROOT.'/schema.sql');foreach(explode(';',$schema) as $stmt){if(trim($stmt)!=='')$pdo->exec($stmt);}
  $st=$pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'admin')");$st->execute([trim($_POST['admin_name']??'Administrator'),$email,password_hash($adminpass,PASSWORD_DEFAULT)]);
  $config=['db_host'=>$host,'db_name'=>$name,'db_user'=>$dbuser,'db_pass'=>$pass,'paystack_secret'=>'','app_url'=>rtrim((isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'],'/')];
  $out='<?php return '.var_export($config,true).';';if(file_put_contents(ROOT.'/config.php',$out,LOCK_EX)===false)throw new Exception('Unable to write config.php. Check folder permissions.');
  @chmod(ROOT.'/config.php',0600);header('Location: /login');exit;
 }catch(Throwable $e){$error='Installation failed: '.$e->getMessage();}
}
?><!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/style.css"><div class="container section" style="max-width:640px"><span class="eyebrow">Thirthy Vi Degrees</span><h1 style="font-size:3rem">Installation Wizard</h1><p>Create an empty MySQL database and user in cPanel, then enter the details below.</p><?php if($error):?><div class="notice error"><?=esc($error)?></div><?php endif;?><form method="post" class="form"><?=token()?><label>MySQL host</label><input name="db_host" value="localhost" required><label>Database name</label><input name="db_name" required><label>Database username</label><input name="db_user" required><label>Database password</label><input name="db_pass" type="password"><hr><label>Administrator name</label><input name="admin_name" required><label>Administrator email</label><input name="email" type="email" required><label>Administrator password (12+ characters)</label><input name="admin_pass" type="password" minlength="12" required><button class="btn gold full">Install platform</button></form></div>
