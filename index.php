<?php
declare(strict_types=1);
session_start();

/* Single-file NJ Task Management System for XAMPP/WAMP/shared hosting.
   Edit these constants for production. */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'nj_team_task';
const DB_USER = 'root';
const DB_PASS = '';
const JWT_SECRET = 'change-this-secret-in-production';
const APP_TZ = 'Asia/Kolkata';
date_default_timezone_set(APP_TZ);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsnServer = 'mysql:host='.DB_HOST.';charset=utf8mb4';
    $server = new PDO($dsnServer, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $server->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    init_db($pdo);
    return $pdo;
}

function init_db(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(160) NOT NULL,
            employee_id VARCHAR(32) UNIQUE,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            avatar_color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
            avatar_url TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            assigned_to INT NOT NULL,
            assigned_by INT NOT NULL,
            priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            status ENUM('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
            deadline DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(assigned_to), INDEX(assigned_by), INDEX(status), INDEX(deadline)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS task_updates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            old_status VARCHAR(40) NULL,
            new_status VARCHAR(40) NOT NULL,
            note TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(task_id), INDEX(user_id), INDEX(updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS attendance_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date_key DATE NOT NULL,
            status ENUM('present') NOT NULL DEFAULT 'present',
            first_login_at DATETIME NULL,
            last_logout_at DATETIME NULL,
            total_seconds INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_date(user_id,date_key),
            INDEX(date_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS attendance_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            attendance_day_id INT NOT NULL,
            user_id INT NOT NULL,
            date_key DATE NOT NULL,
            login_at DATETIME NOT NULL,
            logout_at DATETIME NULL,
            duration_seconds INT NOT NULL DEFAULT 0,
            INDEX(user_id,date_key), INDEX(attendance_day_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS daily_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            report_date DATE NOT NULL,
            summary TEXT NULL,
            incomplete_reason TEXT NULL,
            tasks_json LONGTEXT NULL,
            file_name VARCHAR(255) NULL,
            file_data LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_report(user_id,report_date),
            INDEX(report_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            message TEXT NOT NULL,
            type VARCHAR(40) DEFAULT 'info',
            is_read TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id,is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($count === 0) {
$ins = $pdo->prepare("INSERT INTO users(username,password,full_name,employee_id,role,avatar_color) VALUES(?,?,?,?,?,?)");
         $ins->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'ADM001', 'admin', '#ef4444']);
         foreach ([
            ['lavanya','lavanya123','Lavanya','NJ001','#6366f1'],
            ['siva','siva123','Siva','NJ002','#0ea5e9'],
            ['ab','ab123','AB','NJ003','#10b981'],
            ['tamil','tamil123','Tamil','NJ004','#f59e0b'],
            ['naveen','naveen123','Naveen','NJ005','#ec4899'],
            ['hari','hari123','Hari','NJ006','#8b5cf6'],
            ['venket','venket123','Venket','NJ007','#14b8a6']
         ] as $m) $ins->execute([$m[0], password_hash($m[1], PASSWORD_DEFAULT), $m[2], $m[3], 'user', $m[4]]);
        $admin = (int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
        $users = $pdo->query("SELECT id FROM users WHERE role='user'")->fetchAll();
        $samples = [
            ['Design Homepage Mockup','Create wireframes and high-fidelity designs','high','in_progress',date('Y-m-d')],
            ['API Integration','Integrate payment gateway API','high','pending',date('Y-m-d')],
            ['Weekly Report Q2','Compile and submit weekly report','medium','completed',date('Y-m-d')],
            ['Database Optimization','Review and optimize queries','medium','pending',date('Y-m-d', strtotime('+1 day'))],
        ];
        $task = $pdo->prepare("INSERT INTO tasks(title,description,assigned_to,assigned_by,priority,status,deadline) VALUES(?,?,?,?,?,?,?)");
        $upd = $pdo->prepare("INSERT INTO task_updates(task_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)");
        foreach ($samples as $i => $s) {
            $uid = (int)$users[$i % count($users)]['id'];
            $task->execute([$s[0],$s[1],$uid,$admin,$s[2],$s[3],$s[4]]);
            $tid = (int)$pdo->lastInsertId();
            $upd->execute([$tid,$uid,'pending',$s[3],'Initial status set']);
        }
    }
}

function input(): array { return json_decode(file_get_contents('php://input'), true) ?: $_POST ?: []; }
function json_out($data, int $code=200): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; }
function clean($v): string { return trim((string)$v); }
function today(): string { return date('Y-m-d'); }
function nowdt(): string { return date('Y-m-d H:i:s'); }
function seconds_between(?string $a, ?string $b): int { if (!$a || !$b) return 0; return max(0, strtotime($b) - strtotime($a)); }
function fmt_seconds(int $s): string { $s=max(0,$s); return sprintf('%02d:%02d', intdiv($s,3600), intdiv($s%3600,60)); }
function fmt_time(?string $t): string { return $t ? date('h:i:s A', strtotime($t)) : '-'; }

function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function jwt_sign(array $payload): string {
    $payload['iat'] = time(); $payload['exp'] = time() + 86400;
    $h = b64url(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $p = b64url(json_encode($payload));
    return "$h.$p.".b64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
}
function jwt_verify(?string $token): ?array {
    if (!$token || substr_count($token,'.') !== 2) return null;
    [$h,$p,$s] = explode('.', $token);
    if (!hash_equals(b64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true)), $s)) return null;
    $data = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    return ($data && ($data['exp'] ?? 0) >= time()) ? $data : null;
}
function current_user(): ?array {
    if (!empty($_SESSION['user'])) return $_SESSION['user'];
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = substr($hdr, 0, 7) === 'Bearer ' ? substr($hdr, 7) : ($_COOKIE['auth_token'] ?? null);
    $data = jwt_verify($token);
    if ($data && !empty($data['id'])) { $_SESSION['user'] = $data; return $data; }
    return null;
}
function require_user(): array { $u=current_user(); if (!$u) json_out(['error'=>'Unauthorized'],401); return $u; }
function require_admin(): array { $u=require_user(); if (($u['role']??'') !== 'admin') json_out(['error'=>'Forbidden'],403); return $u; }

function start_attendance(PDO $pdo, array $u): array {
    if (($u['role'] ?? '') === 'admin') return [];
    $date = today(); $now = nowdt();
    $pdo->prepare("INSERT INTO attendance_days(user_id,date_key,status,first_login_at) VALUES(?,?, 'present', ?) ON DUPLICATE KEY UPDATE status='present', first_login_at=COALESCE(first_login_at, VALUES(first_login_at))")->execute([$u['id'],$date,$now]);
    $day = $pdo->prepare("SELECT * FROM attendance_days WHERE user_id=? AND date_key=?"); $day->execute([$u['id'],$date]); $d=$day->fetch();
    $open = $pdo->prepare("SELECT id FROM attendance_sessions WHERE user_id=? AND date_key=? AND logout_at IS NULL LIMIT 1"); $open->execute([$u['id'],$date]);
    if (!$open->fetch()) $pdo->prepare("INSERT INTO attendance_sessions(attendance_day_id,user_id,date_key,login_at) VALUES(?,?,?,?)")->execute([$d['id'],$u['id'],$date,$now]);
    return attendance_me($pdo, (int)$u['id']);
}
function end_attendance(PDO $pdo, array $u): void {
    if (($u['role'] ?? '') === 'admin') return;
    $date=today(); $now=nowdt();
    $q=$pdo->prepare("SELECT * FROM attendance_sessions WHERE user_id=? AND date_key=? AND logout_at IS NULL ORDER BY login_at DESC LIMIT 1"); $q->execute([$u['id'],$date]); $s=$q->fetch();
    if ($s) {
        $dur=seconds_between($s['login_at'],$now);
        $pdo->prepare("UPDATE attendance_sessions SET logout_at=?, duration_seconds=? WHERE id=?")->execute([$now,$dur,$s['id']]);
        $pdo->prepare("UPDATE attendance_days SET last_logout_at=?, total_seconds=total_seconds+? WHERE user_id=? AND date_key=?")->execute([$now,$dur,$u['id'],$date]);
    }
}
function attendance_me(PDO $pdo, int $uid, ?string $date=null): array {
    $date=$date ?: today();
    $q=$pdo->prepare("SELECT ad.*, (SELECT login_at FROM attendance_sessions s WHERE s.attendance_day_id=ad.id ORDER BY login_at ASC LIMIT 1) login_at, (SELECT logout_at FROM attendance_sessions s WHERE s.attendance_day_id=ad.id AND s.logout_at IS NOT NULL ORDER BY logout_at DESC LIMIT 1) logout_at, (SELECT login_at FROM attendance_sessions s WHERE s.attendance_day_id=ad.id AND s.logout_at IS NULL ORDER BY login_at DESC LIMIT 1) active_login_at FROM attendance_days ad WHERE user_id=? AND date_key=?");
    $q->execute([$uid,$date]); $r=$q->fetch();
    if (!$r) return ['date'=>$date,'status'=>'absent','isLoggedIn'=>false,'totalSeconds'=>0,'savedSeconds'=>0];
    $active = $r['active_login_at'] ? seconds_between($r['active_login_at'], nowdt()) : 0;
    return ['date'=>$date,'status'=>'present','loginAt'=>$r['login_at'] ?: $r['first_login_at'],'logoutAt'=>$r['logout_at'] ?: $r['last_logout_at'],'activeLoginAt'=>$r['active_login_at'],'isLoggedIn'=>(bool)$r['active_login_at'],'totalSeconds'=>(int)$r['total_seconds']+$active,'savedSeconds'=>(int)$r['total_seconds']];
}
function attendance_rows(PDO $pdo, ?string $date=null): array {
    $date=$date ?: today();
    $q=$pdo->prepare("SELECT u.id user_id,u.full_name,u.username,u.avatar_color,ad.status,ad.first_login_at,ad.last_logout_at,ad.total_seconds,(SELECT login_at FROM attendance_sessions s WHERE s.user_id=u.id AND s.date_key=? ORDER BY login_at ASC LIMIT 1) login_at,(SELECT logout_at FROM attendance_sessions s WHERE s.user_id=u.id AND s.date_key=? AND s.logout_at IS NOT NULL ORDER BY logout_at DESC LIMIT 1) logout_at,(SELECT login_at FROM attendance_sessions s WHERE s.user_id=u.id AND s.date_key=? AND s.logout_at IS NULL ORDER BY login_at DESC LIMIT 1) active_login_at FROM users u LEFT JOIN attendance_days ad ON ad.user_id=u.id AND ad.date_key=? WHERE u.role='user' ORDER BY u.full_name");
    $q->execute([$date,$date,$date,$date]);
    $rows=[]; foreach($q->fetchAll() as $r){ $active=$r['active_login_at']?seconds_between($r['active_login_at'],nowdt()):0; $r['date']=$date; $r['status']=$r['status']?:'absent'; $r['total_seconds']=(int)($r['total_seconds']??0)+$active; $r['is_logged_in']=(bool)$r['active_login_at']; $rows[]=$r; }
    return $rows;
}
function export_rows(PDO $pdo, ?string $date=null): array {
    $date=$date ?: today(); $rows=attendance_rows($pdo,$date);
    $t=$pdo->prepare("SELECT assigned_to user_id,title,status FROM tasks WHERE deadline=? OR status!='completed' ORDER BY title"); $t->execute([$date]);
    $tasks=[]; foreach($t->fetchAll() as $r) $tasks[$r['user_id']][]=$r['title'].' ('.str_replace('_',' ',$r['status']).')';
    $c=$pdo->prepare("SELECT t.assigned_to user_id,t.title FROM task_updates tu JOIN tasks t ON t.id=tu.task_id WHERE tu.new_status='completed' AND DATE(tu.updated_at)=? ORDER BY t.title"); $c->execute([$date]);
    $done=[]; foreach($c->fetchAll() as $r) $done[$r['user_id']][]=$r['title'];
    return array_map(fn($r)=>['date'=>$date,'employeeName'=>$r['full_name'],'login'=>fmt_time($r['login_at']?:$r['first_login_at']),'logout'=>$r['is_logged_in']?'Active':fmt_time($r['logout_at']?:$r['last_logout_at']),'totalHours'=>fmt_seconds((int)$r['total_seconds']),'tasks'=>$tasks[$r['user_id']]??[],'completedTasks'=>$done[$r['user_id']]??[],'tasksCompleted'=>count($done[$r['user_id']]??[]),'status'=>$r['status']], $rows);
}
function make_pdf(array $lines): string {
    $content="BT /F1 10 Tf\n"; $y=800; foreach($lines as $line){ $line=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line); $content.="1 0 0 1 42 $y Tm ($line) Tj\n"; $y-=15; } $content.="ET";
    $objs=[]; $objs[]="<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>"; $objs[]="<< /Length ".strlen($content)." >>\nstream\n$content\nendstream"; $objs[]="<< /Type /Page /Parent 4 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 1 0 R >> >> /Contents 2 0 R >>"; $objs[]="<< /Type /Pages /Kids [3 0 R] /Count 1 >>"; $objs[]="<< /Type /Catalog /Pages 4 0 R >>";
    $pdf="%PDF-1.4\n"; $off=[0]; foreach($objs as $i=>$o){ $off[]=strlen($pdf); $pdf.=($i+1)." 0 obj\n$o\nendobj\n"; } $xref=strlen($pdf); $pdf.="xref\n0 6\n0000000000 65535 f \n"; for($i=1;$i<count($off);$i++) $pdf.=sprintf("%010d 00000 n \n",$off[$i]); return $pdf."trailer\n<< /Size 6 /Root 5 0 R >>\nstartxref\n$xref\n%%EOF";
}

$pdo = db();
$api = $_GET['api'] ?? '';
if (!$api && isset($_GET['php_api'])) {
    $path = trim((string)$_GET['php_api'], '/');
    $map = [
        'auth/login' => 'login',
        'auth/logout' => 'logout',
        'auth/me' => 'me',
        'attendance/me' => 'attendance_me',
        'attendance/summary' => 'attendance_summary',
        'users' => 'users',
        'users/notifications' => 'notifications',
        'users/notifications/read' => 'notifications_read',
        'users/me' => 'user_me',
        'tasks' => 'tasks',
        'tasks/reports/daily' => 'daily_report',
        'tasks/reports/daily/export-data' => 'export_data',
        'tasks/reports/daily/download.pdf' => 'download_pdf',
        'tasks/reports/daily/download.xlsx' => 'download_excel',
        'tasks/reports/weekly' => 'weekly_report',
        'tasks/reports/weekly/save' => 'weekly_save',
        'tasks/reports/daily/file-view' => 'daily_file_view',
    ];
    if (isset($map[$path])) {
        $api = $map[$path];
    } elseif (preg_match('#^tasks/(\d+)$#', $path, $m)) {
        $api = 'task/'.$m[1];
    } elseif (preg_match('#^users/(\d+)$#', $path, $m)) {
        $api = 'user/'.$m[1];
    } elseif (preg_match('#^tasks/reports/daily/(\d+)/file$#', $path, $m)) {
        $api = 'daily_file/'.$m[1];
    }
}
if ($api) {
    try {
        if ($api==='login' && $_SERVER['REQUEST_METHOD']==='POST') {
            $in=input(); $user=strtolower(clean($in['username']??'')); $pass=(string)($in['password']??'');
            if(!$user||!$pass) json_out(['error'=>'Username and password required'],400);
            $q=$pdo->prepare("SELECT * FROM users WHERE username=?"); $q->execute([$user]); $u=$q->fetch();
            if(!$u || !password_verify($pass,$u['password'])) json_out(['error'=>'Invalid credentials'],401);
            $sess=['id'=>(int)$u['id'],'username'=>$u['username'],'full_name'=>$u['full_name'],'role'=>$u['role'],'avatar_color'=>$u['avatar_color'],'avatar_url'=>$u['avatar_url']];
            $_SESSION['user']=$sess; $token=jwt_sign($sess); setcookie('auth_token',$token,time()+86400,'','',false,true);
            json_out(['success'=>true,'user'=>$sess,'token'=>$token,'attendance'=>start_attendance($pdo,$sess)]);
        }
        if ($api==='logout') { if($u=current_user()) end_attendance($pdo,$u); $_SESSION=[]; session_destroy(); setcookie('auth_token','',time()-3600); json_out(['success'=>true]); }
        if ($api==='me') json_out(require_user());
        if ($api==='attendance_me') { $u=require_user(); json_out(attendance_me($pdo,(int)$u['id'])); }
        if ($api==='attendance_summary') { $u=require_user(); $rows=attendance_rows($pdo); $total=array_sum(array_column($rows,'total_seconds')); $out=['date'=>today(),'totalEmployees'=>count($rows),'presentToday'=>count(array_filter($rows,fn($r)=>$r['status']==='present')),'absentToday'=>count(array_filter($rows,fn($r)=>$r['status']!=='present')),'totalSeconds'=>$total,'rows'=>$rows]; if($u['role']!=='admin'){unset($out['rows']);$out['me']=attendance_me($pdo,(int)$u['id']);} json_out($out); }
        if ($api==='users') { require_admin(); if($_SERVER['REQUEST_METHOD']==='GET') json_out($pdo->query("SELECT id,username,full_name,role,avatar_color,avatar_url,created_at FROM users WHERE role!='admin' ORDER BY full_name")->fetchAll()); $in=input(); if(empty($in['username'])||empty($in['password'])||empty($in['full_name'])) json_out(['error'=>'All fields required'],400); $pdo->prepare("INSERT INTO users(username,password,full_name,avatar_color) VALUES(?,?,?,?)")->execute([strtolower(clean($in['username'])),password_hash($in['password'],PASSWORD_DEFAULT),clean($in['full_name']),$in['avatar_color']??'#2563eb']); json_out(['success'=>true]); }
        if ($api==='user_me') { $u=require_user(); if($_SERVER['REQUEST_METHOD']==='PUT'){ $in=input(); if(empty($in['full_name'])) json_out(['error'=>'Full name required'],400); $pdo->prepare("UPDATE users SET full_name=?, avatar_color=? WHERE id=?")->execute([clean($in['full_name']),$in['avatar_color']??'#2563eb',$u['id']]); $q=$pdo->prepare("SELECT id,username,full_name,role,avatar_color,avatar_url FROM users WHERE id=?");$q->execute([$u['id']]); $_SESSION['user']=$q->fetch(); json_out($_SESSION['user']); } json_out($u); }
        if (substr($api,0,5)==='user/') { require_admin(); $id=(int)substr($api,5); if($_SERVER['REQUEST_METHOD']==='DELETE'){ $pdo->prepare("DELETE FROM attendance_sessions WHERE user_id=?")->execute([$id]); $pdo->prepare("DELETE FROM attendance_days WHERE user_id=?")->execute([$id]); $pdo->prepare("DELETE FROM daily_reports WHERE user_id=?")->execute([$id]); $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$id]); $pdo->prepare("DELETE FROM task_updates WHERE user_id=?")->execute([$id]); $pdo->prepare("DELETE FROM tasks WHERE assigned_to=? OR assigned_by=?")->execute([$id,$id]); $pdo->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$id]); json_out(['success'=>true]); } if($_SERVER['REQUEST_METHOD']==='PUT'){ $in=input(); if(empty($in['username'])||empty($in['full_name'])) json_out(['error'=>'username and full_name required'],400); if(!empty($in['password'])) $pdo->prepare("UPDATE users SET username=?,password=?,full_name=?,avatar_color=? WHERE id=?")->execute([strtolower(clean($in['username'])),password_hash($in['password'],PASSWORD_DEFAULT),clean($in['full_name']),$in['avatar_color']??'#2563eb',$id]); else $pdo->prepare("UPDATE users SET username=?,full_name=?,avatar_color=? WHERE id=?")->execute([strtolower(clean($in['username'])),clean($in['full_name']),$in['avatar_color']??'#2563eb',$id]); json_out(['success'=>true]); } json_out(['error'=>'Unsupported'],405); }
        if ($api==='notifications') { $u=require_user(); $q=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");$q->execute([$u['id']]); json_out($q->fetchAll()); }
        if ($api==='notifications_read') { $u=require_user(); $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$u['id']]); json_out(['success'=>true]); }
        if ($api==='tasks') {
            $u=require_user();
            if($_SERVER['REQUEST_METHOD']==='GET'){ $where=[];$p=[]; if($u['role']!=='admin'){$where[]='t.assigned_to=?';$p[]=$u['id'];} elseif(!empty($_GET['user_id'])){$where[]='t.assigned_to=?';$p[]=(int)$_GET['user_id'];} $f=$_GET['filter']??''; if(in_array($f,['pending','in_progress','completed'],true)){$where[]='t.status=?';$p[]=$f;} if($f==='today'){$where[]='t.deadline=?';$p[]=today();} if($f==='overdue'){$where[]="t.deadline<CURDATE() AND t.status!='completed'";} $sql="SELECT t.*,u.full_name assigned_to_name,u.avatar_color assigned_to_color,a.full_name assigned_by_name FROM tasks t JOIN users u ON u.id=t.assigned_to JOIN users a ON a.id=t.assigned_by".($where?' WHERE '.implode(' AND ',$where):'')." ORDER BY t.created_at DESC"; $q=$pdo->prepare($sql);$q->execute($p); json_out($q->fetchAll()); }
            if($u['role']!=='admin') json_out(['error'=>'Forbidden'],403); $in=input(); if(empty($in['title'])||empty($in['assigned_to'])) json_out(['error'=>'Title and assignee required'],400); $pdo->prepare("INSERT INTO tasks(title,description,assigned_to,assigned_by,priority,deadline) VALUES(?,?,?,?,?,?)")->execute([clean($in['title']),$in['description']??'',(int)$in['assigned_to'],$u['id'],$in['priority']??'medium',$in['deadline']?:null]); json_out(['success'=>true]);
        }
        if (substr($api,0,5)==='task/') { $u=require_user(); $id=(int)substr($api,5); $q=$pdo->prepare("SELECT * FROM tasks WHERE id=?");$q->execute([$id]);$task=$q->fetch(); if(!$task) json_out(['error'=>'Task not found'],404); if($u['role']!=='admin' && (int)$task['assigned_to']!==(int)$u['id']) json_out(['error'=>'Forbidden'],403); if($_SERVER['REQUEST_METHOD']==='PUT'){ $in=input(); $status=$in['status']??$task['status']; if($u['role']==='admin') $pdo->prepare("UPDATE tasks SET status=?,priority=COALESCE(?,priority),updated_at=NOW() WHERE id=?")->execute([$status,$in['priority']??null,$id]); else $pdo->prepare("UPDATE tasks SET status=?,updated_at=NOW() WHERE id=?")->execute([$status,$id]); if($status!==$task['status']) $pdo->prepare("INSERT INTO task_updates(task_id,user_id,old_status,new_status,note) VALUES(?,?,?,?,?)")->execute([$id,$u['id'],$task['status'],$status,$in['note']??'']); json_out(['success'=>true]); } json_out($task); }
        if ($api==='daily_report') {
            $u=require_user();
            if($_SERVER['REQUEST_METHOD']==='POST'){ $in=input(); $q=$pdo->prepare("SELECT id,title,status,description,deadline FROM tasks WHERE assigned_to=? AND (deadline=? OR status!='completed') ORDER BY deadline,title");$q->execute([$u['id'],today()]); $tasks=json_encode($q->fetchAll()); $pdo->prepare("INSERT INTO daily_reports(user_id,report_date,summary,incomplete_reason,tasks_json,file_name,file_data) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE summary=VALUES(summary),incomplete_reason=VALUES(incomplete_reason),tasks_json=VALUES(tasks_json),file_name=COALESCE(VALUES(file_name),file_name),file_data=COALESCE(VALUES(file_data),file_data),created_at=NOW()")->execute([$u['id'],today(),$in['summary']??'',$in['incomplete_reason']??'',$tasks,$in['file_name']??null,$in['file_data']??null]); json_out(['success'=>true]); }
            $q=$pdo->query("SELECT dr.*,u.full_name,u.avatar_color FROM daily_reports dr JOIN users u ON u.id=dr.user_id WHERE dr.report_date=CURDATE() ORDER BY u.full_name"); $subs=$q->fetchAll(); foreach($subs as &$s){$s['tasks']=json_decode($s['tasks_json']?:'[]',true); if(!empty($s['file_name'])&&!empty($s['file_data'])) $s['file_url']='?php_api=tasks/reports/daily/file-view&id='.$s['id']; unset($s['tasks_json'],$s['file_data']);} if($u['role']!=='admin') $subs=array_values(array_filter($subs,fn($s)=>(int)$s['user_id']===(int)$u['id'])); json_out(['submissions'=>$subs]);
        }
        if ($api==='daily_file_view') { $u=require_user(); $id=(int)($_GET['id']??0); $q=$pdo->prepare("SELECT * FROM daily_reports WHERE id=?");$q->execute([$id]);$r=$q->fetch(); if(!$r) { http_response_code(404); exit('File not found'); } if($u['role']!=='admin' && (int)$r['user_id']!==(int)$u['id']) { http_response_code(403); exit('Forbidden'); } $data=(string)$r['file_data']; $mime='application/octet-stream'; $raw=$data; if(preg_match('#^data:([^;]+);base64,(.+)$#',$data,$m)){ $mime=$m[1]; $raw=$m[2]; } header('Content-Type: '.$mime); header('Content-Disposition: inline; filename="'.basename((string)$r['file_name']).'"'); echo base64_decode($raw); exit; }
        if (substr($api,0,11)==='daily_file/') { require_admin(); $id=(int)substr($api,11); $pdo->prepare("UPDATE daily_reports SET file_name=NULL,file_data=NULL WHERE id=?")->execute([$id]); json_out(['success'=>true]); }
        if ($api==='weekly_report') { require_admin(); $report=$pdo->query("SELECT u.full_name,u.avatar_color,COUNT(t.id) total,SUM(t.status='completed') completed,SUM(t.status='pending') pending,SUM(t.status='in_progress') in_progress,SUM(t.deadline<CURDATE() AND t.status!='completed') overdue FROM users u LEFT JOIN tasks t ON t.assigned_to=u.id WHERE u.role='user' GROUP BY u.id ORDER BY u.full_name")->fetchAll(); $overall=$pdo->query("SELECT COUNT(*) total,SUM(status='completed') completed,SUM(status='pending') pending,SUM(status='in_progress') in_progress,SUM(deadline<CURDATE() AND status!='completed') overdue FROM tasks")->fetch(); $activity=$pdo->query("SELECT DATE(updated_at) date,COUNT(*) count,new_status status FROM task_updates WHERE updated_at>=DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(updated_at),new_status ORDER BY date")->fetchAll(); json_out(['report'=>$report,'overall'=>$overall,'dailyActivity'=>$activity]); }
        if ($api==='weekly_save') { require_admin(); json_out(['success'=>true]); }
        if ($api==='export_data') { require_admin(); $rows=export_rows($pdo); $totalSeconds=0; foreach($rows as $r){[$h,$m]=array_pad(array_map('intval',explode(':',$r['totalHours'])),2,0); $totalSeconds += ($h*3600)+($m*60);} json_out(['date'=>today(),'rows'=>$rows,'totals'=>['totalEmployees'=>count($rows),'present'=>count(array_filter($rows,fn($r)=>$r['status']==='present')),'absent'=>count(array_filter($rows,fn($r)=>$r['status']!=='present')),'totalHours'=>fmt_seconds($totalSeconds),'tasksCompleted'=>array_sum(array_column($rows,'tasksCompleted'))]]); }
        if ($api==='download_excel') { require_admin(); $rows=export_rows($pdo); header('Content-Type: application/vnd.ms-excel; charset=utf-8'); header('Content-Disposition: attachment; filename="today-work-report-'.today().'.xls"'); echo "<table border='1'><tr><th>Date</th><th>Employee Name</th><th>Login</th><th>Logout</th><th>Total Hours</th><th>Tasks Completed</th><th>Tasks</th></tr>"; foreach($rows as $r) echo "<tr><td>{$r['date']}</td><td>".htmlspecialchars($r['employeeName'])."</td><td>{$r['login']}</td><td>{$r['logout']}</td><td>{$r['totalHours']}</td><td>{$r['tasksCompleted']}</td><td>".htmlspecialchars(implode(', ',$r['completedTasks']))."</td></tr>"; echo "</table>"; exit; }
        if ($api==='download_pdf') { require_admin(); $rows=export_rows($pdo); $lines=['NJ Task Managing Portal - Today Work Report','Date: '.today(),'Generated: '.date('d M Y h:i:s A'),'']; foreach($rows as $i=>$r){$lines[]=($i+1).'. '.$r['employeeName'];$lines[]='   Login: '.$r['login'].' | Logout: '.$r['logout'].' | Total: '.$r['totalHours'].' | Completed: '.$r['tasksCompleted'];$lines[]='   Tasks: '.(implode('; ',$r['completedTasks']) ?: 'No completed tasks');$lines[]='';} header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="today-work-report-'.today().'.pdf"'); echo make_pdf($lines); exit; }
        json_out(['error'=>'Unknown API'],404);
    } catch(Throwable $e) { json_out(['error'=>'Server error','detail'=>$e->getMessage()],500); }
}
if (is_file(__DIR__.'/public/index.html')) {
    $html = file_get_contents(__DIR__.'/public/index.html');
    $bridge = <<<'HTML'
<script>
(function(){
  const nativeFetch = window.fetch.bind(window);
  window.fetch = function(resource, init) {
    const raw = typeof resource === 'string' ? resource : (resource && resource.url) || '';
    if (raw.startsWith('/api/')) {
      const from = new URL(raw, location.origin);
      const to = new URL(location.pathname, location.origin);
      to.searchParams.set('php_api', from.pathname.slice(5));
      from.searchParams.forEach((value, key) => to.searchParams.append(key, value));
      return nativeFetch(to.toString(), init);
    }
    return nativeFetch(resource, init);
  };
})();
</script>
HTML;
    $overrides = <<<'HTML'
<script>
window.addEventListener('load', function(){
  window.downloadDailyReportPdf = function(){ location.href = '?php_api=tasks/reports/daily/download.pdf'; };
  window.downloadDailyReportExcel = function(){ location.href = '?php_api=tasks/reports/daily/download.xlsx'; };
});
</script>
HTML;
    // Serve the frontend HTML unchanged to preserve original asset paths and behavior.
    echo $html;
    exit;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NJ Task Managing Portal</title>
<style>
:root{--bg:#05060a;--card:#111827dd;--text:#f8fafc;--muted:#94a3b8;--line:#334155;--pri:#ff7a18;--ok:#22c55e;--bad:#f43f5e;--blue:#38bdf8}body.light{--bg:#f8fafc;--card:#fff;--text:#0f172a;--muted:#64748b;--line:#e2e8f0;--pri:#2563eb}*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:radial-gradient(circle at 20% 0,#ff7a1830,transparent 30%),var(--bg);color:var(--text)}button,input,select,textarea{font:inherit}.hidden{display:none!important}.login{min-height:100vh;display:grid;place-items:center;padding:20px}.login-card,.card,.sidebar,.topbar{background:var(--card);border:1px solid var(--line);box-shadow:0 18px 54px #0004;border-radius:18px}.login-card{width:min(460px,100%);padding:32px}.brand{font-weight:900;font-size:22px;margin-bottom:20px}.form{display:grid;gap:12px}.form input,.form textarea,.form select,.search{width:100%;padding:12px 14px;border:1px solid var(--line);border-radius:12px;background:#0f172a22;color:var(--text)}.btn{border:0;border-radius:12px;padding:11px 15px;font-weight:800;cursor:pointer;background:linear-gradient(135deg,#ffb020,#ff7a18,#25f5a3);color:#07101c}.btn2{background:transparent;color:var(--text);border:1px solid var(--line)}.app{display:flex;min-height:100vh}.sidebar{position:fixed;inset:12px auto 12px 12px;width:250px;padding:18px;display:flex;flex-direction:column;gap:16px}.nav button{display:block;width:100%;text-align:left;margin:6px 0}.main{margin-left:274px;flex:1}.topbar{position:sticky;top:12px;margin:12px 12px 0;padding:12px 18px;display:flex;align-items:center;gap:10px;z-index:3}.title{font-size:20px;font-weight:900;margin-right:auto}.clock,.timer{font-weight:900;font-variant-numeric:tabular-nums;border:1px solid var(--line);border-radius:12px;padding:9px 12px}.page{display:none;padding:24px}.page.active{display:block}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}.card{padding:20px;margin-bottom:18px}.stat b{font-size:30px;display:block}.muted{color:var(--muted)}.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.tasks,.team{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px}.task{border-left:5px solid var(--pri)}.tag{display:inline-flex;padding:4px 9px;border-radius:999px;font-size:12px;font-weight:900;background:#334155;color:#e2e8f0}.completed{background:#14532d;color:#bbf7d0}.pending{background:#713f12;color:#fde68a}.in_progress{background:#075985;color:#bae6fd}table{width:100%;border-collapse:collapse;min-width:720px}th,td{text-align:left;padding:11px;border-bottom:1px solid var(--line);font-size:14px}.scroll{overflow:auto}.toast{position:fixed;right:20px;bottom:20px;background:#111827;color:white;padding:12px 16px;border-radius:12px;z-index:9}@media(max-width:850px){.sidebar{transform:translateX(-110%);transition:.25s}.sidebar.open{transform:none}.main{margin-left:0}.page{padding:14px}.topbar{top:0;margin:8px}.clock{display:none}.grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.timer{display:none}.grid{grid-template-columns:1fr}.row>*{width:100%}}
</style></head><body>
<div id="login" class="login"><div class="login-card"><div class="brand">NJ Task Managing Portal</div><div id="loginErr" class="muted"></div><div class="form"><input id="lu" placeholder="Username" autocomplete="username"><input id="lp" type="password" placeholder="Password" autocomplete="current-password"><button class="btn" onclick="login()">Sign In</button><div class="muted">Admin: admin / admin123</div></div></div></div>
<div id="app" class="app hidden"><aside id="side" class="sidebar"><div class="brand">NJ Portal</div><div><b id="uname"></b><div id="urole" class="muted"></div></div><nav id="nav" class="nav"></nav><button class="btn btn2" onclick="logout()">Sign Out</button></aside><main class="main"><header class="topbar"><button class="btn btn2" onclick="side.classList.toggle('open')">☰</button><div id="ptitle" class="title">Dashboard</div><div id="timer" class="timer">00:00:00</div><div id="tclock" class="clock">--</div><button class="btn btn2" onclick="toggleTheme()">Theme</button></header>
<section id="dashboard" class="page active"><div class="grid" id="stats"></div><div class="card"><div class="row"><h3>Attendance Today</h3><button class="btn btn2" onclick="loadAttendance()">Refresh</button></div><div id="attendance"></div></div><div class="card"><div class="row"><h3>Tasks</h3><input class="search" id="dashSearch" placeholder="Search tasks..." oninput="renderTasks(window.allTasks||[],'dashTasks')"></div><div class="tasks" id="dashTasks"></div></div></section>
<section id="tasks" class="page"><div class="row"><h2>My Tasks</h2><input class="search" id="taskSearch" placeholder="Search/filter..." oninput="loadTasks()"></div><div id="dailyForm" class="card"><h3>Submit Daily Report</h3><textarea id="summary" placeholder="Today's work summary"></textarea><textarea id="reason" placeholder="Pending / incomplete reason"></textarea><input type="file" id="file"><button class="btn" onclick="submitDaily()">Submit Daily Report</button></div><div class="tasks" id="taskList"></div></section>
<section id="assign" class="page"><div class="card"><h3>Assign Task</h3><div class="form"><input id="tt" placeholder="Task title"><textarea id="td" placeholder="Description"></textarea><select id="ta"></select><select id="tp"><option>low</option><option selected>medium</option><option>high</option></select><input id="tl" type="date"><button class="btn" onclick="createTask()">Assign</button></div></div><div class="tasks" id="adminTasks"></div></section>
<section id="team" class="page"><div class="card"><h3>Add Member</h3><div class="form"><input id="mn" placeholder="Full name"><input id="mu" placeholder="Username"><input id="mp" type="password" placeholder="Password"><button class="btn" onclick="addMember()">Add</button></div></div><div class="team" id="teamGrid"></div></section>
<section id="reports" class="page"><div class="card"><div class="row"><h2>Today Work Report</h2><button class="btn btn2" onclick="location='?api=download_pdf'">Download PDF</button><button class="btn btn2" onclick="location='?api=download_excel'">Download Excel</button><button class="btn btn2" onclick="downloadJpg()">Download JPG</button></div><div id="reportRows"></div></div></section>
</main></div>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script><script>
let user=null,allTasks=[],attendance=null,timerInt=null;const $=id=>document.getElementById(id);
async function api(a,opt={}){let r=await fetch('?api='+a,{credentials:'same-origin',headers:{'Content-Type':'application/json'},...opt});let t=await r.text();let d;try{d=JSON.parse(t)}catch{d=t}if(!r.ok)throw new Error(d.error||'Request failed');return d}
function toast(m){let t=document.createElement('div');t.className='toast';t.textContent=m;document.body.appendChild(t);setTimeout(()=>t.remove(),2500)}
async function login(){try{let d=await api('login',{method:'POST',body:JSON.stringify({username:$('lu').value,password:$('lp').value})});user=d.user;launch()}catch(e){$('loginErr').textContent=e.message}}
async function logout(){await api('logout',{method:'POST'}).catch(()=>{});clearInterval(timerInt);location.reload()}
async function check(){try{user=await api('me');launch()}catch{}}
function launch(){$('login').classList.add('hidden');$('app').classList.remove('hidden');$('uname').textContent=user.full_name;$('urole').textContent=user.role==='admin'?'Administrator':'Team Member';$('nav').innerHTML=(user.role==='admin'?['dashboard','assign','team','reports']:['dashboard','tasks']).map(p=>`<button class="btn btn2" onclick="show('${p}')">${p[0].toUpperCase()+p.slice(1)}</button>`).join('');startClock();loadAttendance();loadTasks();if(user.role==='admin'){loadUsers();loadReports()}else show('dashboard')}
function show(p){document.querySelectorAll('.page').forEach(x=>x.classList.remove('active'));$(p).classList.add('active');ptitle.textContent=p[0].toUpperCase()+p.slice(1);side.classList.remove('open');if(p==='dashboard')loadAttendance();if(p==='assign')loadTasks(true);if(p==='team')loadUsers();if(p==='reports')loadReports()}
function startClock(){setInterval(()=>tclock.textContent=new Date().toLocaleTimeString('ta-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true,timeZone:'Asia/Kolkata'}),1000)}
function dur(s){s=Math.max(0,Math.floor(s||0));return `${String(Math.floor(s/3600)).padStart(2,'0')}:${String(Math.floor(s%3600/60)).padStart(2,'0')}:${String(s%60).padStart(2,'0')}`}
async function loadAttendance(){let d=await api('attendance_summary');attendance=d;$('stats').innerHTML=user.role==='admin'?stat('Total Employees',d.totalEmployees)+stat('Present',d.presentToday)+stat('Absent',d.absentToday)+stat('Total Hours',dur(d.totalSeconds)):stat('My Hours',dur(d.me.totalSeconds))+stat('Status',d.me.isLoggedIn?'Logged In':d.me.status);if(user.role==='admin'){$('attendance').innerHTML=`<input class="search" placeholder="Search employees..." oninput="filterRows(this.value)"><div class="scroll"><table><thead><tr><th>Employee</th><th>Status</th><th>Login</th><th>Logout</th><th>Total</th></tr></thead><tbody id="atbody">${d.rows.map(r=>`<tr data-n="${esc(r.full_name).toLowerCase()}"><td>${esc(r.full_name)}</td><td><span class="tag ${r.is_logged_in?'in_progress':r.status}">${r.is_logged_in?'Logged in':r.status}</span></td><td>${fmt(r.login_at||r.first_login_at)}</td><td>${r.is_logged_in?'Active':fmt(r.logout_at||r.last_logout_at)}</td><td>${dur(r.total_seconds)}</td></tr>`).join('')}</tbody></table></div>`}else{$('attendance').innerHTML=`<b>${dur(d.me.totalSeconds)}</b> <span class="tag ${d.me.isLoggedIn?'in_progress':'completed'}">${d.me.isLoggedIn?'Logged in':d.me.status}</span>`;startTimer(d.me)}}
function stat(l,v){return `<div class="card stat"><b>${v}</b><span class="muted">${l}</span></div>`}
function startTimer(a){clearInterval(timerInt);let base=a.savedSeconds||0,st=a.activeLoginAt?new Date(a.activeLoginAt.replace(' ','T')).getTime():0;timerInt=setInterval(()=>timer.textContent=dur(base+(st?Math.floor((Date.now()-st)/1000):0)),1000)}
function filterRows(q){q=q.toLowerCase();document.querySelectorAll('#atbody tr').forEach(r=>r.classList.toggle('hidden',q&&!r.dataset.n.includes(q)))}
async function loadTasks(admin=false){let f=admin?'':'',d=await api('tasks'+f);allTasks=d;renderTasks(d,admin?'adminTasks':'taskList');renderTasks(d,'dashTasks');if(user.role==='admin')renderTasks(d,'adminTasks')}
function renderTasks(tasks,id){let q=($(id==='dashTasks'?'dashSearch':'taskSearch')?.value||'').toLowerCase();$(id).innerHTML=tasks.filter(t=>!q||[t.title,t.description,t.assigned_to_name].join(' ').toLowerCase().includes(q)).map(t=>`<div class="card task"><h3>${esc(t.title)}</h3><p class="muted">${esc(t.description||'')}</p><div class="row"><span class="tag ${t.status}">${t.status}</span><span class="tag">${t.priority}</span><span class="muted">${esc(t.assigned_to_name||'')}</span></div>${user.role!=='admin'?`<select onchange="updateTask(${t.id},this.value)"><option ${t.status==='pending'?'selected':''}>pending</option><option value="in_progress" ${t.status==='in_progress'?'selected':''}>in_progress</option><option ${t.status==='completed'?'selected':''}>completed</option></select>`:''}</div>`).join('')||'<div class="muted">No tasks found</div>'}
async function updateTask(id,status){await api('task/'+id,{method:'PUT',body:JSON.stringify({status})});toast('Updated');loadTasks()}
async function createTask(){await api('tasks',{method:'POST',body:JSON.stringify({title:tt.value,description:td.value,assigned_to:ta.value,priority:tp.value,deadline:tl.value})});toast('Task assigned');loadTasks(true)}
async function loadUsers(){let u=await api('users');ta.innerHTML=u.map(x=>`<option value="${x.id}">${esc(x.full_name)}</option>`).join('');teamGrid.innerHTML=u.map(x=>`<div class="card"><h3>${esc(x.full_name)}</h3><p class="muted">@${esc(x.username)}</p></div>`).join('')}
async function addMember(){await api('users',{method:'POST',body:JSON.stringify({full_name:mn.value,username:mu.value,password:mp.value})});toast('Member added');loadUsers()}
async function submitDaily(){let file=$('file').files[0];let payload={summary:summary.value,incomplete_reason:reason.value};if(file){payload.file_name=file.name;payload.file_data=await new Promise(r=>{let fr=new FileReader();fr.onload=()=>r(fr.result);fr.readAsDataURL(file)})}await api('daily_report',{method:'POST',body:JSON.stringify(payload)});toast('Daily report submitted')}
async function loadReports(){let d=await api('export_data');reportRows.innerHTML=`<div class="scroll"><table><tr><th>Name</th><th>Login</th><th>Logout</th><th>Total</th><th>Completed</th><th>Date</th></tr>${d.rows.map(r=>`<tr><td>${esc(r.employeeName)}</td><td>${r.login}</td><td>${r.logout}</td><td>${r.totalHours}</td><td>${r.tasksCompleted}</td><td>${r.date}</td></tr>`).join('')}</table></div>`}
async function downloadJpg(){let d=await api('export_data'),el=document.createElement('div');el.style.cssText='position:fixed;left:-10000px;top:0;width:1200px;background:#f8fafc;color:#0f172a;font-family:Arial;padding:36px';el.innerHTML=`<h1>Today Work Report</h1><h3>Date: ${d.date}</h3><table style="width:100%;border-collapse:collapse"><tr><th>Employee Name</th><th>Login Time</th><th>Logout Time</th><th>Total Hours</th><th>Tasks Completed</th><th>Date</th></tr>${d.rows.map(r=>`<tr><td>${esc(r.employeeName)}</td><td>${r.login}</td><td>${r.logout}</td><td>${r.totalHours}</td><td>${r.tasksCompleted}<br>${esc((r.completedTasks||[]).join(', '))}</td><td>${r.date}</td></tr>`).join('')}</table>`;document.body.appendChild(el);let c=await html2canvas(el,{scale:2,backgroundColor:'#f8fafc'});el.remove();c.toBlob(b=>{let a=document.createElement('a'),name=(d.rows[0]?.employeeName||'today-work-report').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');a.href=URL.createObjectURL(b);a.download=`${name}-${d.date}.jpg`;a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000)},'image/jpeg',.94)}
function fmt(v){return v?new Date(v.replace(' ','T')).toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true}):'-'}function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]))}function toggleTheme(){document.body.classList.toggle('light');localStorage.theme=document.body.classList.contains('light')?'light':'dark'}if(localStorage.theme==='light')document.body.classList.add('light');check();
</script></body></html>
