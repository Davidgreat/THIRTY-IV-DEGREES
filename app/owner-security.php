<?php
declare(strict_types=1);
function owner_security_save(string $kind):never{
 $owner=admin();if($owner['role']!=='admin')abort(403,'Owner access required');
 rate_limit('owner_security:'.$owner['id'],8,900);
 $current=$_POST[$kind==='password'?'password-current':'current_password']??'';
 if(!is_string($current)||strlen($current)>4096){flash('Enter your current password.','error');go('admin/security');}
 try{
  db()->beginTransaction();$account=one('SELECT id,email,password FROM users WHERE id=? FOR UPDATE',[$owner['id']]);
  if(!$account||!password_verify($current,$account['password']))throw new InvalidArgumentException('Your current password is incorrect.');
  if($kind==='email'){
   $email=strtolower(str('email',190));$confirm=strtolower(str('email_confirm',190));
   if(mb_strlen((string)($_POST['email']??''))>190||mb_strlen((string)($_POST['email_confirm']??''))>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||$email!==$confirm)throw new InvalidArgumentException('Enter a valid new email and repeat it exactly.');
   if($email===strtolower($account['email']))throw new InvalidArgumentException('Enter a different email address.');
   if(one('SELECT id FROM users WHERE email=? AND id<>?',[$email,$owner['id']]))throw new InvalidArgumentException('That email is already used by another account.');
   sql('UPDATE users SET email=? WHERE id=?',[$email,$owner['id']]);
  }else{
   $password=$_POST['new_password']??'';$confirm=$_POST['password_confirm']??'';
   if(!is_string($password)||mb_strlen($password)<10||strlen($password)>72||str_contains($password,"\0"))throw new InvalidArgumentException('Use at least 10 characters. If your password is very long, choose a shorter passphrase.');
   if(!is_string($confirm)||!hash_equals($password,$confirm))throw new InvalidArgumentException('The new passwords do not match.');
   if(password_verify($password,$account['password']))throw new InvalidArgumentException('Choose a different password from your current one.');
   sql('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$owner['id']]);
  }
  sql('DELETE FROM account_reset_tokens WHERE user_id=?',[$owner['id']]);
  sql('INSERT INTO audit_logs(user_id,action,details) VALUES(?,?,?)',[$owner['id'],'owner.security.'.$kind,'Self-service credential change']);
  db()->commit();
 }catch(Throwable $error){
  if(db()->inTransaction())db()->rollBack();
  $message=$error instanceof InvalidArgumentException?$error->getMessage():($error instanceof PDOException&&$error->getCode()==='23000'?'That email is already used by another account.':'Your account could not be updated. Please try again.');
  flash($message,'error');go('admin/security');
 }
 unset($_SESSION['uid'],$_SESSION['owner_auth_stamp'],$_SESSION['csrf']);session_regenerate_id(true);
 flash('Your login '.$kind.' was updated. Sign in again with your new details.');go('login');
}
function owner_password_input(string $name,string $label,bool $new=false):void{
 ?><label for="<?=esc($name)?>"><?=esc($label)?></label><input id="<?=esc($name)?>" name="<?=esc($name)?>" type="password" autocomplete="<?=$new?'new-password':'current-password'?>" required <?=$new?'minlength="10" maxlength="72"':''?>><label class="auth-password-toggle" hidden><input type="checkbox" data-password-toggle aria-controls="<?=esc($name)?>"><span>Show password</span></label><?php
}
function owner_security_page():void{
 $owner=admin();admin_page('My account & security',function()use($owner){
 ?><div class="notice">These are your sign-in details. Your public business email and payment settings are managed separately. Saving a change signs out your owner sessions; sign in again with your new details.</div>
 <div class="security-grid"><form class="form" method="post" action="/admin/security/email"><?=token()?><h2>Change login email</h2><p class="muted">Current login: <?=esc($owner['email'])?></p><label for="owner-email">New login email</label><input id="owner-email" name="email" type="email" autocomplete="email" maxlength="190" required><label for="owner-email-confirm">Repeat new email</label><input id="owner-email-confirm" name="email_confirm" type="email" autocomplete="off" maxlength="190" required><?php owner_password_input('current_password','Current password');?><p class="small muted">Use an inbox you control so you can receive password-reset emails.</p><button class="btn gold">Update login email</button></form>
 <form class="form" method="post" action="/admin/security/password"><?=token()?><h2>Change password</h2><?php owner_password_input('password-current','Current password');?><?php owner_password_input('new_password','New password',true);?><p class="small muted">Use at least 10 characters. A unique passphrase is a good choice.</p><?php owner_password_input('password_confirm','Repeat new password',true);?><button class="btn gold">Update password</button></form></div>
 <?php });
}
