<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *"); // kalau frontend di subfolder lain
// DEBUG: aktifkan hanya sementara
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- DATABASE CREDENTIALS ---
// Ganti isi sesuai kredensial Hostinger-mu (atau biarkan seperti semula jika sudah benar)
$host = "localhost";
$user = "u759560335_akmal";   // <-- CEK LAGI DI HOSTINGER
$pass = "Alfa2008!?"; // <-- CEK LAGI PASSWORDNYA
$db   = "u759560335_kulinerAkmalDB"; // <-- CEK LAGI NAMA DATABASENYA

// --- koneksi
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"DB connect failed", "errno"=>$conn->connect_errno]);
    exit;
}

// --- If request body is JSON, populate $_POST so existing code works
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        foreach($json as $k=>$v) {
            // only set if not already present
            if (!isset($_POST[$k])) $_POST[$k] = $v;
        }
    }
}

// router
$action = $_GET['action'] ?? '';

// --- DEBUG/CHECK action: cek koneksi & tabel
if ($action === 'check') {
    $tables_ok = [];
    $needed = ['eduexpert_users','eduexpert_progress'];
    foreach($needed as $t){
        $res = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
        $tables_ok[$t] = ($res && $res->num_rows>0) ? true : false;
        if($res) $res->free();
    }
    // also echo received input for debugging
    echo json_encode([
        "status"=>"ok",
        "db_connected"=>true,
        "tables"=>$tables_ok,
        "received_get"=>$_GET,
        "received_post_keys"=>array_keys($_POST)
    ]);
    exit;
}

// --- ROUTES (register/login/save_progress/get_progress)
// register
if ($action == "register") {
    $fullname = $_POST["fullname"] ?? '';
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';
    $school   = $_POST["school"] ?? '';

    if ($fullname == "" || $username == "" || $password == "" || $school == "") {
        echo json_encode(["status"=>"error","message"=>"Form belum lengkap","received_post"=>$_POST]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO eduexpert_users (fullname, username, password, school) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $fullname, $username, $hashed, $school);

    if ($stmt->execute()) {
        echo json_encode(["status"=>"success", "message"=>"Registrasi berhasil"]);
    } else {
        echo json_encode(["status"=>"error", "message"=>"DB error","error"=>$stmt->error]);
    }
    exit;
}

// login
if ($action == "login") {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    if ($username === '' || $password === '') {
        echo json_encode(["status"=>"error","message"=>"Form belum lengkap","received_post"=>$_POST]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id, password, fullname FROM eduexpert_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows == 0) {
        echo json_encode(["status"=>"error","message"=>"User tidak ditemukan"]);
        exit;
    }
    $stmt->bind_result($id, $hashed, $fullname_db);
    $stmt->fetch();
    if (password_verify($password, $hashed)) {
        echo json_encode(["status"=>"success","user_id"=>$id,"fullname"=>$fullname_db]);
    } else {
        echo json_encode(["status"=>"error","message"=>"Password salah"]);
    }
    exit;
}

// save_progress
if ($action == "save_progress") {
    $user_id = intval($_POST["user_id"] ?? 0);
    $subject = $_POST["subject"] ?? "";
    $chapter = $_POST["chapter"] ?? "";
    $score   = intval($_POST["score"] ?? 0);

    if ($user_id <= 0 || $subject=="" || $chapter=="") {
        echo json_encode(["status"=>"error","message"=>"Form progress tidak lengkap","received_post"=>$_POST]);
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO eduexpert_progress (user_id, subject, chapter, score)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE score = VALUES(score), updated_at = CURRENT_TIMESTAMP
    ");
    // note: ON DUPLICATE KEY requires a unique key — see notes below
    $stmt->bind_param("issi", $user_id, $subject, $chapter, $score);

    if ($stmt->execute()) {
        echo json_encode(["status"=>"success"]);
    } else {
        echo json_encode(["status"=>"error","message"=>$stmt->error]);
    }
    exit;
}

// get_progress
if ($action == "get_progress") {
    $user_id = intval($_GET["user_id"] ?? 0);
    if ($user_id <= 0) {
        echo json_encode(["status"=>"error","message"=>"user_id invalid"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT subject, chapter, score, updated_at FROM eduexpert_progress WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $list = [];
    while ($row = $result->fetch_assoc()) $list[] = $row;
    echo json_encode(["status"=>"success","progress"=>$list]);
    exit;
}

// default
echo json_encode(["status"=>"error","message"=>"Action tidak valid"]);
exit;
?>
