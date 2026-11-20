<?php
/** Run via hosting cron using PHP CLI; not a web endpoint. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/core.php';
try{fwrite(STDOUT,json_encode(cron_worker_run()).PHP_EOL);}
catch(Throwable $error){error_log('Scheduled worker failed: '.get_class($error));fwrite(STDERR,"Scheduled worker failed before completion. Check Cron health and the hosting PHP error log.\n");exit(1);}
