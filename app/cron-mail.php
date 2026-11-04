<?php
/** Run via cPanel cron using PHP CLI; not a web endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
$result=email_process_batch(15);
fwrite(STDOUT,json_encode($result).PHP_EOL);
