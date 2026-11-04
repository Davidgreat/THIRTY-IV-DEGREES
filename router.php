<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(preg_match('~^/(app|storage|plugins)/|^/(config\.php|schema\.sql|README\.md)$~i',$path)){http_response_code(403);exit;}
if($path!=='/'&&is_file(__DIR__.$path)){return false;}
if(preg_match('~^/(app|storage|plugins|install)/|^/(config\.php|schema\.sql|README\.md)$~i',$path)){http_response_code(403);exit;}
require __DIR__.'/index.php';
