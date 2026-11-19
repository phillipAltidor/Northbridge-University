<?php
// Router for PHP's loopback-only development server. Keep local secrets, SQL,
// tests and tooling out of HTTP responses; production hosting needs its own rules.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
if (str_contains($path,"\0") || str_contains($path,'\\') || preg_match('~(?:^|/)\.|^/(?:database|config|includes|tools|tests|docs)(?:/|$)~',$path)) { http_response_code(404); exit('Not found.'); }
$target=realpath($root.($path==='/'?'/index.php':$path));
if (!$target || !str_starts_with($target,$root.DIRECTORY_SEPARATOR) || !is_file($target)) { http_response_code(404); exit('Not found.'); }
$extension=strtolower(pathinfo($target,PATHINFO_EXTENSION));
if ($extension==='php') { require $target; return true; }
if (str_starts_with($path,'/assets/') && in_array($extension,['css','js','png','jpg','jpeg','webp','gif','svg','ico','woff','woff2','ttf'],true)) return false;
http_response_code(404); exit('Not found.');
