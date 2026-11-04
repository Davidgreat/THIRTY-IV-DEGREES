<?php
declare(strict_types=1);
function account_reset_request(string $email):void {
 $account=one('SELECT id,email FROM users WHERE email=?',[$email]);if(!$account)return;
 $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
 $base=rtrim((string)(cfg()['app_url']??''),'/');
 db()->beginTransaction();try{
  sql('INSERT INTO account_reset_tokens(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at)',[$account['id'],$hash]);
  email_enqueue('password:reset:'.$hash,$account['email'],'Reset your 36° password',"Use this link within 30 minutes to choose a new password:\n\n".$base.'/reset-password?token='.$token."\n\nIf you did not request this, ignore this email.");
  db()->commit();
 }catch(Throwable $e){db()->rollBack();throw $e;}
}
function account_reset_password(string $token,string $password):bool {
 if(!preg_match('/^[a-f0-9]{64}$/D',$token)||strlen($password)<10||strlen($password)>4096)return false;
 db()->beginTransaction();try{
  $r=one('SELECT user_id FROM account_reset_tokens WHERE token_hash=? AND expires_at>NOW() FOR UPDATE',[hash('sha256',$token)]);
  if(!$r){db()->rollBack();return false;}
  sql('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$r['user_id']]);
  sql('DELETE FROM account_reset_tokens WHERE user_id=?',[$r['user_id']]);db()->commit();return true;
 }catch(Throwable $e){db()->rollBack();throw $e;}
}
