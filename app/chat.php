<?php
declare(strict_types=1);
function chat_enabled():bool{return mail_setting('chat_enabled','1')==='1';}
function chat_staff(array $user):bool{
 if($user['role']==='admin')return true;if($user['role']!=='manager')return false;
 return (int)(one("SELECT allowed FROM staff_permissions WHERE user_id=? AND permission='chat_manage'",[$user['id']])['allowed']??0)===1;
}
function chat_json(int $status,array $data):never{http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
function chat_conversation(array $user,int $requested=0):?array{
 if(chat_staff($user)){if(!$requested)return null;return one('SELECT * FROM chat_conversations WHERE id=?',[$requested]);}
 if($user['role']!=='customer')return null;
 $conversation=one('SELECT * FROM chat_conversations WHERE user_id=?',[$user['id']]);
 if(!$conversation&&chat_enabled()){sql('INSERT INTO chat_conversations(user_id,updated_at) VALUES(?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id)',[$user['id']]);$conversation=one('SELECT * FROM chat_conversations WHERE user_id=?',[$user['id']]);}
 return $conversation;
}
function chat_api():never{
 $user=user();if(!$user)chat_json(401,['error'=>'Sign in again to continue chatting.']);$staff=chat_staff($user);if(!$staff&&$user['role']!=='customer')chat_json(403,['error'=>'Chat access is not enabled for this account.']);
 $token=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf']??'');if(!is_string($token)||!hash_equals(csrf(),$token))chat_json(419,['error'=>'Your chat session expired. Refresh the page.']);
 if(!post()&&isset($_GET['summary'])){$unread=$staff?one("SELECT COUNT(*) n FROM chat_messages m JOIN chat_conversations c ON c.id=m.conversation_id WHERE m.sender_role='customer' AND m.id>c.staff_read_id"):one("SELECT COUNT(*) n FROM chat_messages m JOIN chat_conversations c ON c.id=m.conversation_id WHERE c.user_id=? AND m.sender_role='staff' AND m.id>c.customer_read_id",[$user['id']]);chat_json(200,['unread'=>(int)$unread['n'],'enabled'=>chat_enabled()]);}
 $action=post()?(string)($_POST['action']??'send'):'poll';$requested=(int)($_GET['conversation']??$_POST['conversation']??0);$conversation=chat_conversation($user,$requested);
 if(post()&&!chat_enabled())chat_json(423,['error'=>'Chat is currently paused by the restaurant. Your history is still available.','enabled'=>false]);
 try{
  if($staff&&$action==='poll'&&!$requested){$threads=all("SELECT c.id,c.user_id,c.updated_at,u.name,(SELECT body FROM chat_messages m WHERE m.conversation_id=c.id ORDER BY m.id DESC LIMIT 1) preview,(SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id=c.id AND m.sender_role='customer' AND m.id>c.staff_read_id) unread FROM chat_conversations c JOIN users u ON u.id=c.user_id ORDER BY c.updated_at DESC,c.id DESC LIMIT 80");chat_json(200,['threads'=>$threads,'enabled'=>chat_enabled()]);}
  if(!$conversation){if($action==='poll')chat_json(200,['messages'=>[],'enabled'=>chat_enabled(),'typing'=>'','read_id'=>0]);chat_json(404,['error'=>'Conversation not found.']);}
  $cid=(int)$conversation['id'];$side=$staff?'staff':'customer';
  if($action==='typing'){
   rate_limit('chat_typing:'.$user['id'],60,60);sql('UPDATE chat_conversations SET '.$side.'_typing_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 8 SECOND)'.($staff?',staff_typing_user_id=?':'').' WHERE id=?',$staff?[$user['id'],$cid]:[$cid]);chat_json(200,['ok'=>true]);
  }
  if($action==='send'){
   rate_limit('chat_send:'.$user['id'],30,60);$body=$_POST['body']??'';$nonce=$_POST['nonce']??'';
   if(!is_string($body)||trim($body)===''||mb_strlen($body)>2000||!is_string($nonce)||!preg_match('/^[a-f0-9]{32}$/D',$nonce))chat_json(422,['error'=>'Write a message of up to 2,000 characters.']);$body=trim($body);
   $existing=one('SELECT id,body FROM chat_messages WHERE conversation_id=? AND sender_id=? AND client_nonce=?',[$cid,$user['id'],$nonce]);if($existing&&$existing['body']!==$body)chat_json(409,['error'=>'This message was already submitted. Refresh before trying a different message.']);
   if(!$existing){sql('INSERT INTO chat_messages(conversation_id,sender_id,sender_role,body,client_nonce,created_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE id=id',[$cid,$user['id'],$side,$body,$nonce]);$saved=one('SELECT body FROM chat_messages WHERE conversation_id=? AND sender_id=? AND client_nonce=?',[$cid,$user['id'],$nonce]);if(!$saved||$saved['body']!==$body)chat_json(409,['error'=>'This message was already submitted. Refresh before trying a different message.']);sql('UPDATE chat_conversations SET updated_at=UTC_TIMESTAMP(),'.$side.'_typing_until=NULL WHERE id=?',[$cid]);}chat_json(200,['ok'=>true]);
  }
  if($action==='react'){
   rate_limit('chat_react:'.$user['id'],90,60);$message=(int)($_POST['message_id']??0);$reaction=(string)($_POST['reaction']??'');if(!in_array($reaction,['like','heart','laugh','thanks',''],true))chat_json(422,['error'=>'Choose a supported reaction.']);if(!one('SELECT id FROM chat_messages WHERE id=? AND conversation_id=?',[$message,$cid]))chat_json(404,['error'=>'Message not found.']);
   if($reaction==='')sql('DELETE FROM chat_reactions WHERE message_id=? AND user_id=?',[$message,$user['id']]);else sql('INSERT INTO chat_reactions(message_id,user_id,reaction) VALUES(?,?,?) ON DUPLICATE KEY UPDATE reaction=VALUES(reaction)',[$message,$user['id'],$reaction]);chat_json(200,['ok'=>true,'reactions'=>all('SELECT reaction,COUNT(*) count,MAX(user_id=?) mine FROM chat_reactions WHERE message_id=? GROUP BY reaction',[$user['id'],$message])]);
  }
  if($action!=='poll')chat_json(400,['error'=>'Unknown chat action.']);
  $before=max(0,(int)($_GET['before']??0));$messages=all('SELECT m.id,m.sender_id,m.sender_role,m.body,m.created_at,u.name FROM chat_messages m JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=?'.($before?' AND m.id<?':'').' ORDER BY m.id DESC LIMIT 100',$before?[$cid,$before]:[$cid]);$messages=array_reverse($messages);$last=$messages?(int)end($messages)['id']:0;
  sql('UPDATE chat_conversations SET '.$side.'_read_id=GREATEST('.$side.'_read_id,?)'.($staff?',staff_seen_at=UTC_TIMESTAMP()':'').' WHERE id=?',[$last,$cid]);
  $reactionMap=[];if($messages){$ids=array_column($messages,'id');$marks=implode(',',array_fill(0,count($ids),'?'));foreach(all('SELECT message_id,reaction,COUNT(*) count,MAX(user_id=?) mine FROM chat_reactions WHERE message_id IN ('.$marks.') GROUP BY message_id,reaction',[$user['id'],...$ids]) as $reaction)$reactionMap[$reaction['message_id']][]=$reaction;}
  foreach($messages as &$message){$message['mine']=(int)$message['sender_id']===(int)$user['id'];$message['at']=str_replace(' ','T',$message['created_at']).'Z';$message['reactions']=$reactionMap[$message['id']]??[];unset($message['created_at']);}unset($message);
  $current=one("SELECT *,customer_typing_until>UTC_TIMESTAMP() customer_typing,staff_typing_until>UTC_TIMESTAMP() staff_typing,staff_seen_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 25 SECOND) team_online FROM chat_conversations WHERE id=?",[$cid]);
  $typing='';if(chat_enabled()&&($staff?$current['customer_typing']:$current['staff_typing'])){$typing=$staff?'Customer':(one('SELECT name FROM users WHERE id=?',[$current['staff_typing_user_id']])['name']??'The team');}
  chat_json(200,['conversation'=>$cid,'enabled'=>chat_enabled(),'messages'=>$messages,'has_more'=>$messages&&(bool)one('SELECT id FROM chat_messages WHERE conversation_id=? AND id<? LIMIT 1',[$cid,$messages[0]['id']]),'typing'=>$typing,'team_online'=>(bool)$current['team_online'],'read_id'=>(int)($staff?$current['customer_read_id']:$current['staff_read_id'])]);
 }catch(Throwable $error){chat_json(500,['error'=>'Chat could not update. Please try again shortly.']);}
}
function chat_markup(bool $staff=false,int $conversation=0):void{
 ?><section class="live-chat" data-live-chat data-staff="<?=$staff?'1':'0'?>" data-conversation="<?=$conversation?>" data-csrf="<?=esc(csrf())?>"><header class="chat-heading"><div><span class="chat-orb" aria-hidden="true"></span><strong><?=$staff?'Customer conversation':'The 36° concierge'?></strong><small data-chat-presence>Loading conversation…</small></div><span class="chat-private">Private conversation</span></header><p class="chat-error" data-chat-error role="alert" hidden></p><button class="chat-earlier" type="button" data-chat-earlier hidden>Load earlier messages</button><div class="chat-timeline" data-chat-timeline role="log" aria-label="Conversation messages"></div><p class="chat-typing" data-chat-typing aria-live="polite"></p><form data-chat-form><label class="sr-only" for="chat-message">Message</label><textarea id="chat-message" data-chat-input rows="2" maxlength="2000" placeholder="How can we help you today?" required></textarea><div class="chat-composer-actions"><button type="button" data-emoji-toggle aria-expanded="false" aria-controls="chat-emojis">☺ <span>Emoji</span></button><span class="chat-keyboard-hint">Enter to send · Shift + Enter for a new line</span><button type="submit" data-chat-send>Send ↗</button></div><div id="chat-emojis" class="chat-emojis" data-chat-emojis hidden><?php foreach(['😊','❤️','👍','🙏','🎉','🍽️','😋','👋','✅','✨','😄','🍰'] as $emoji):?><button type="button" data-emoji="<?=$emoji?>" aria-label="Insert <?=$emoji?>"><?=$emoji?></button><?php endforeach;?></div></form><p class="chat-service-note">Messages update while this chat is open. Our team may reply later when away.</p></section><?php
}
