<?php
declare(strict_types=1);
// Fresh, separate demo installation only. Never loads the partner's database.
if (PHP_SAPI !== 'cli' || ($argv[1]??'') !== '--initialize') exit("CLI: php tools/setup-local-demo.php --initialize\n");
$root=dirname(__DIR__); $private=$root.'/.local';
$database='northbridge_ruba_demo_20261119';
if (file_exists($root.'/.env') || file_exists($private.'/demo-accounts.json')) throw new RuntimeException('Private setup already exists. Refusing to overwrite it.');
if (!is_file($private.'/mysql.pid') || !file_exists($private.'/mysql.sock')) throw new RuntimeException('Start the dedicated local MySQL instance first.');
umask(0077);
$pdo=new PDO('mysql:unix_socket='.$private.'/mysql.sock;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if ((int)$pdo->query('SELECT @@port')->fetchColumn()!==3307) throw new RuntimeException('Expected dedicated local port 3307.');
$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute([$database]);
if ((int)$q->fetchColumn()!==0) throw new RuntimeException('Demo database already exists. Refusing to overwrite it.');
$rootPassword=bin2hex(random_bytes(24));
file_put_contents($private.'/mysql-admin.cnf',"[client]\nuser=root\npassword=$rootPassword\nsocket=$private/mysql.sock\n");
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($rootPassword));
$pdo->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$database`");
$schema=preg_replace('/^USE northbridge_university;\s*/','',file_get_contents($root.'/database/schema.sql'));
$pdo->exec($schema);
preg_match_all('/^\d+\. `(database\/[^`]+\.sql)`/m',file_get_contents($root.'/database/LOAD_ORDER.md'),$matches);
if (count($matches[1])!==46) throw new RuntimeException('Review changed seed load order before continuing.');
foreach ($matches[1] as $path) {
 $sql=file_get_contents($root.'/'.$path);
 if (preg_match('/^\s*(USE |CREATE DATABASE|DROP DATABASE)/mi',$sql)) throw new RuntimeException('Unexpected database selector in seed.');
 $pdo->exec($sql);
 echo 'Loaded '.basename($path)."\n";
}
foreach (['admin_53_update_log.sql','admin_70_master_schedule.sql','admin_70_section_update_log.sql','password_reset.sql','admin_features.sql'] as $file) $pdo->exec(file_get_contents($root.'/database/migrations/'.$file));
// Install the existing project's trigger definitions after seed rosters are loaded.
foreach (require $root.'/database/migrations/section_seat_triggers.php' as $sql) $pdo->exec($sql);
$admin=(int)$pdo->query('SELECT admin_ID FROM Admin ORDER BY admin_ID LIMIT 1')->fetchColumn();
$faculty=(int)$pdo->query("SELECT f.faculty_ID FROM Faculty f WHERE f.faculty_Type='Full-Time' ORDER BY (SELECT COUNT(*) FROM Enrollment e JOIN Course_Section cs ON cs.CRN=e.CRN JOIN Semester s ON s.semester_ID=e.semester_ID WHERE cs.faculty_ID=f.faculty_ID AND s.semester_Name='Fall 2026') DESC, (SELECT COUNT(*) FROM Advisor a WHERE a.faculty_ID=f.faculty_ID) DESC, f.faculty_ID LIMIT 1")->fetchColumn();
$q=$pdo->prepare("SELECT e.student_ID FROM Enrollment e JOIN Course_Section cs ON cs.CRN=e.CRN JOIN Student st ON st.student_ID=e.student_ID JOIN Semester s ON s.semester_ID=e.semester_ID WHERE cs.faculty_ID=? AND st.student_Type='Undergraduate' AND s.semester_Name='Fall 2026' ORDER BY e.student_ID LIMIT 1");$q->execute([$faculty]);$student=(int)$q->fetchColumn();
if (!$admin || !$faculty || !$student) throw new RuntimeException('Seed data lacks required demo roles.');
$accounts=[];$instructions="LOCAL DEMO ONLY — not Phillip’s database\nWebsite: http://127.0.0.1:8000\nDatabase: $database (dedicated MySQL port 3307)\nThese credentials are private. Do not commit this file.\n\n";
$pdo->beginTransaction();
foreach (['Admin'=>$admin,'Faculty'=>$faculty,'Student'=>$student] as $role=>$id) {
 $email='ruba.demo.'.strtolower($role).'@northbridge.test'; $password='Local-'.bin2hex(random_bytes(8)).'!';
 $q=$pdo->prepare('UPDATE Login SET user_Email=?,user_Password=?,no_Of_Tries=0,lock_var=0 WHERE user_ID=? AND user_Type=?');
 $q->execute([$email,password_hash($password,PASSWORD_DEFAULT),$id,$role]);
 if ($q->rowCount()!==1) throw new RuntimeException('Demo role mismatch.');
 $accounts[$role]=['id'=>$id,'email'=>$email,'password'=>$password];
 $instructions.="$role\nEmail: $email\nPassword: $password\n\n";
}
$pdo->commit();
$appPassword=bin2hex(random_bytes(24));
$pdo->exec("CREATE USER 'northbridge_ruba_app'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($appPassword));
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `$database`.* TO 'northbridge_ruba_app'@'127.0.0.1'");
file_put_contents($private.'/demo-accounts.json',json_encode($accounts,JSON_PRETTY_PRINT));
file_put_contents($private.'/demo-logins.txt',$instructions);
file_put_contents($root.'/.env',"DB_HOST=127.0.0.1\nDB_PORT=3307\nDB_NAME=$database\nDB_USER=northbridge_ruba_app\nDB_PASS=$appPassword\nAPP_ENV=local\nAPP_INSTANCE_LABEL=Ruba local demo\nADMIN_DEPARTMENT_WRITER_IDS=$admin\nADMIN_FEATURE_WRITER_IDS=$admin\nMAIL_HOST=\n");
$summary=['database'=>$database,'seed_snapshot'=>'repository installation seeds; not private V11','tables'=>(int)$pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchColumn(),'accounts_configured'=>array_keys($accounts),'seed_files'=>count($matches[1])];
file_put_contents($private.'/setup-receipt.json',json_encode($summary,JSON_PRETTY_PRINT));
echo json_encode($summary,JSON_PRETTY_PRINT)."\nCredentials saved privately in .local/demo-logins.txt.\n";
