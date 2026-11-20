<?php
/** Run via cPanel cron using PHP CLI; not a web endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
try{
 $result=email_worker_run(15,'cron');
 fwrite(STDOUT,json_encode($result).PHP_EOL);
}catch(Throwable $error){
 // Mail infrastructure must not prevent financial reconciliation below.
 error_log('Mail worker failed: '.get_class($error));
 fwrite(STDOUT,json_encode(['mail'=>'worker_failed']).PHP_EOL);
}
// Reconcile pending top-ups even if a callback/webhook was missed. No new charges.
try{fwrite(STDOUT,json_encode(['wallet'=>wallet_reconcile_batch(3)]).PHP_EOL);}catch(Throwable $error){wallet_verification_diagnostic('', 'worker', $error);}
