<?php
/** Run via cPanel cron using PHP CLI; not a web endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
$result=email_worker_run(15,'cron');
fwrite(STDOUT,json_encode($result).PHP_EOL);
