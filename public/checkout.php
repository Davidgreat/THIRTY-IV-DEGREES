<?php
declare(strict_types=1);
// Physical endpoint: avoid canonical redirects on the public checkout page.
define('THIRTYVI_CHECKOUT_ENDPOINT',true);
define('THIRTYVI_CHECKOUT_BUFFER',ob_get_level());
ob_start();
header('X-Checkout-Build: 6.1.2');
set_exception_handler(static function(Throwable $error):void {
 $reference=function_exists('checkout_request_id')?checkout_request_id():bin2hex(random_bytes(6));
 $extra=$error instanceof PDOException?['database_code'=>(int)($error->errorInfo[1]??0),'sqlstate'=>(string)$error->getCode()]:[];
 if(function_exists('checkout_trace'))checkout_trace('server_error',$extra);
 error_log('Checkout '.$reference.' exception='.get_class($error));
 while(ob_get_level()>THIRTYVI_CHECKOUT_BUFFER)ob_end_clean();
 http_response_code(500);header('Cache-Control: private, no-store, max-age=0');
 if(str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json')){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['service'=>'thirtyvi-checkout','build'=>'6.1.2','request_id'=>$reference,'ok'=>false,'stage'=>'server','message'=>'The checkout server could not process this request. Please contact our team with this reference.']);
 }else{
  header('Content-Type: text/html; charset=utf-8');
  echo '<h1>Checkout could not continue</h1><p>Please contact our team with checkout reference '.htmlspecialchars($reference,ENT_QUOTES,'UTF-8').'.</p><a href="/checkout">Return to checkout</a>';
 }
});
require dirname(__DIR__).'/index.php';
