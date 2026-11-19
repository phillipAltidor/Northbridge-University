<?php
// Explicit local-demo migration; not a general deployment/cutover tool.
declare(strict_types=1);
if (PHP_SAPI!=='cli'||($argv[1]??'')!=='--apply-local') {http_response_code(404);exit(1);}
$root=dirname(__DIR__);$env=parse_ini_file($root.'/.env',false,INI_SCANNER_RAW);
if (($env['DB_NAME']??'')!=='northbridge_ruba_demo_20261119'||($env['DB_PORT']??'')!=='3307'||($env['DB_HOST']??'')!=='127.0.0.1') throw new RuntimeException('Expected isolated local demo.');
$c=parse_ini_file($root.'/.local/mysql-admin.cnf',true,INI_SCANNER_RAW)['client'];
$db=new PDO('mysql:unix_socket='.$root.'/.local/mysql.sock;dbname=northbridge_ruba_demo_20261119','root',$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ((int)$db->query('SELECT @@port')->fetchColumn()!==3307) throw new RuntimeException('Unexpected server.');
$a=json_decode(file_get_contents($root.'/.local/demo-accounts.json'),true,512,JSON_THROW_ON_ERROR);$id=$a['Admin']['id']??null;
if (!is_int($id)&&!(is_string($id)&&ctype_digit($id))) throw new RuntimeException('Invalid demo account.');
$q=$db->prepare('SELECT 1 FROM Admin WHERE admin_ID=?');$q->execute([$id]);if (!$q->fetchColumn()) throw new RuntimeException('Missing demo Admin.');
$db->exec(file_get_contents($root.'/database/migrations/admin_features.sql'));
$s=file_get_contents($root.'/.env');
if (!preg_match('/^ADMIN_FEATURE_WRITER_IDS=/m',$s)) file_put_contents($root.'/.env',rtrim($s)."\nADMIN_FEATURE_WRITER_IDS=".$id."\n",LOCK_EX);
echo "Admin feature storage prepared in the isolated demo. Only its existing Admin is enabled.\n";
