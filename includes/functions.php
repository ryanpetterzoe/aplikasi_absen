<?php
require_once __DIR__ . "/config.php";

function get_school_profile($mysqli) {
  $row = $mysqli->query("SELECT * FROM school_profile WHERE id=1")->fetch_assoc();
  if (!$row) {
    $row = ["school_name"=>"Sekolah","school_address"=>"","school_phone"=>"","logo_path"=>""];
  }
  return $row;
}


function e($str) { return htmlspecialchars($str ?? "", ENT_QUOTES, "UTF-8"); }

function role_label($role) {
  $role = strtoupper(trim($role ?? ""));
  switch ($role) {
    case "KEPSEK": return "Kepala Sekolah";
    case "GURU": return "Tenaga Pendidik / Guru";
    case "YAYASAN": return "Yayasan";
    case "SISWA": return "Siswa";
    case "ADMIN": return "Admin";
    default: return $role ?: "-";
  }
}

function att_code_label($code) {
  $code = strtoupper(trim($code ?? ""));
  switch ($code) {
    case "ONTIME": return "Tepat Waktu";
    case "LATE": return "Terlambat";
    case "EARLY": return "Lebih Awal";
    case "TELAT": return "Terlambat";
    case "TEPAT WAKTU": return "Tepat Waktu";
    case "PULANG LEBIH AWAL": return "Pulang Lebih Awal";
    default: return $code ?: "-";
  }
}


function redirect($path) {
  global $BASE_URL;
  $base = rtrim((string)$BASE_URL, "/");
  if ($base === "/") $base = "";
  $p = "/" . ltrim((string)$path, "/");
  header("Location: " . $base . $p);
  exit;
}

function current_user() {
  return $_SESSION["user"] ?? null;
}

function require_login() {
  if (!current_user()) redirect("/login.php");
}

function require_role($roles) {
  $u = current_user();
  if (!$u) redirect("/login.php");
  if (is_string($roles)) $roles = [$roles];
  if (!in_array($u["role"], $roles, true)) {
    http_response_code(403);
    echo "<h3>Akses ditolak.</h3>";
    exit;
  }
}

function flash_set($type, $msg) {
  $_SESSION["flash"] = ["type"=>$type, "msg"=>$msg];
}
function flash_get() {
  $f = $_SESSION["flash"] ?? null;
  unset($_SESSION["flash"]);
  return $f;
}

function get_active_academic_year_id($mysqli) {
  $res = $mysqli->query("SELECT id FROM academic_years WHERE is_active=1 LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) return (int)$row["id"];
  return null;
}

function get_work_rules($mysqli) {
  $year_id = get_active_academic_year_id($mysqli);
  if (!$year_id) return null;
  $stmt = $mysqli->prepare("SELECT * FROM work_rules WHERE academic_year_id=? ORDER BY id DESC LIMIT 1");
  $stmt->bind_param("i", $year_id);
  $stmt->execute();
  $r = $stmt->get_result();
  return $r->fetch_assoc();
}


function get_holiday_for_date($mysqli, $date) {
  $year_id = get_active_academic_year_id($mysqli);
  if (!$year_id) return null;
  // libur berlaku jika academic_year_id NULL (global) atau sesuai tahun aktif
  $stmt = $mysqli->prepare("SELECT * FROM holidays WHERE holiday_date=? AND (academic_year_id IS NULL OR academic_year_id=?) ORDER BY academic_year_id DESC LIMIT 1");
  $stmt->bind_param("si", $date, $year_id);
  $stmt->execute();
  return $stmt->get_result()->fetch_assoc();
}

function is_holiday($mysqli, $date, $academic_year_id=null) {
  $h = get_holiday_for_date($mysqli, $date);
  return $h["name"] ?? null;
}


function ensure_default_work_schedule($mysqli, $year_id, $base_rules) {
  // buat default jika belum ada jadwal untuk tahun ini
  $stmt = $mysqli->prepare("SELECT COUNT(*) c FROM work_schedule WHERE academic_year_id=?");
  $stmt->bind_param("i", $year_id);
  $stmt->execute();
  $c = (int)($stmt->get_result()->fetch_assoc()["c"] ?? 0);
  if ($c > 0) return;

  $checkin = $base_rules["checkin_time"] ?? "07:00:00";
  $checkout = $base_rules["checkout_time"] ?? "15:00:00";

  // default: Senin-Jumat kerja, Sabtu-Minggu libur
  for ($d=1; $d<=7; $d++) {
    $is_workday = ($d <= 5) ? 1 : 0;
    $st = $mysqli->prepare("INSERT INTO work_schedule (academic_year_id, day_of_week, is_workday, checkin_time, checkout_time) VALUES (?,?,?,?,?)");
    $st->bind_param("iiiss", $year_id, $d, $is_workday, $checkin, $checkout);
    $st->execute();
  }
}

function get_work_schedule_for_date($mysqli, $date) {
  $year_id = get_active_academic_year_id($mysqli);
  if (!$year_id) return null;

  $base = get_work_rules($mysqli);
  if (!$base) {
    // fallback base rules jika belum diset
    $base = ["checkin_time"=>"07:00:00","checkout_time"=>"15:00:00","late_tolerance_min"=>10];
  }

  ensure_default_work_schedule($mysqli, $year_id, $base);

  $holiday = get_holiday_for_date($mysqli, $date);
  if ($holiday) {
    return [
      "is_workday" => 0,
      "is_holiday" => 1,
      "holiday_name" => $holiday["name"],
      "checkin_time" => $base["checkin_time"],
      "checkout_time" => $base["checkout_time"],
      "late_tolerance_min" => (int)$base["late_tolerance_min"],
    ];
  }

  $dow = (int)date("N", strtotime($date)); // 1..7
  $stmt = $mysqli->prepare("SELECT * FROM work_schedule WHERE academic_year_id=? AND day_of_week=? LIMIT 1");
  $stmt->bind_param("ii", $year_id, $dow);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();

  if (!$row) {
    // fallback jika tidak ada row
    $row = [
      "is_workday" => ($dow <= 5) ? 1 : 0,
      "checkin_time" => $base["checkin_time"],
      "checkout_time" => $base["checkout_time"],
    ];
  }

  return [
    "is_workday" => (int)$row["is_workday"],
    "is_holiday" => 0,
    "holiday_name" => null,
    "checkin_time" => $row["checkin_time"] ?? $base["checkin_time"],
    "checkout_time" => $row["checkout_time"] ?? $base["checkout_time"],
    "late_tolerance_min" => (int)$base["late_tolerance_min"],
  ];
}

function ensure_upload_dir($dir) {
  if (!is_dir($dir)) mkdir($dir, 0777, true);
}

function save_base64_jpeg($base64, $filepath) {
  // base64 format: data:image/jpeg;base64,....
  if (!preg_match('/^data:image\/(jpeg|jpg);base64,/', $base64)) return false;
  $data = substr($base64, strpos($base64, ',') + 1);
  $bin = base64_decode($data);
  if ($bin === false) return false;
  file_put_contents($filepath, $bin);
  return true;
}

function has_leave_today($mysqli, $user_id, $date) {
  // Pending/Approved dianggap ijin aktif (blok absen). Rejected dianggap tidak ijin.
  $stmt = $mysqli->prepare("SELECT id FROM leave_requests WHERE user_id=? AND leave_date=? AND status IN ('PENDING','APPROVED') LIMIT 1");
  $stmt->bind_param("is", $user_id, $date);
  $stmt->execute();
  $r = $stmt->get_result();
  return (bool)$r->fetch_assoc();
}

function attendance_today($mysqli, $user_id, $date) {
  $stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date=? LIMIT 1");
  $stmt->bind_param("is", $user_id, $date);
  $stmt->execute();
  $r = $stmt->get_result();
  return $r->fetch_assoc();
}

function calc_status_in($rules, $checkin_dt) {
  if (!$rules) return "ONTIME";
  $checkin_time = date("H:i:s", strtotime($checkin_dt));
  $deadline = date("H:i:s", strtotime($rules["checkin_time"] . " + " . (int)$rules["late_tolerance_min"] . " minutes"));
  return ($checkin_time > $deadline) ? "LATE" : "ONTIME";
}

function calc_status_out($rules, $checkout_dt) {
  if (!$rules) return ["NORMAL", null];
  $checkout_time = date("H:i:s", strtotime($checkout_dt));
  $limit = $rules["checkout_time"];
  if ($checkout_time < $limit) return ["EARLY", "Pulang lebih awal"];
  return ["NORMAL", null];
}

function stmt_bind_params($stmt, $types, $params) {
  // bind_param membutuhkan reference; gunakan call_user_func_array
  $refs = [];
  $refs[] = &$types;
  foreach ($params as $k => $v) {
    $refs[] = &$params[$k];
  }
  call_user_func_array([$stmt, 'bind_param'], $refs);
}


function save_uploaded_image($file, $destDir, $baseNameNoExt) {
  // $file: $_FILES['...']
  if (empty($file["name"])) return [null, null];
  $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
  if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) {
    return [null, "Format foto harus jpg/png/webp."];
  }
  ensure_upload_dir($destDir);
  $fname = $baseNameNoExt . "." . $ext;
  $dest = rtrim($destDir, "/") . "/" . $fname;
  if (!move_uploaded_file($file["tmp_name"], $dest)) {
    return [null, "Gagal upload foto."];
  }
  return [$fname, null];
}


function list_workday_dates($mysqli, $from, $to, $academic_year_id) {
  // return array of YYYY-MM-DD that are workdays (based on work_schedule) and not holidays
  $from_dt = new DateTime($from);
  $to_dt = new DateTime($to);
  if ($from_dt > $to_dt) return [];

  $sched = [];
  if ($academic_year_id) {
    $st = $mysqli->prepare("SELECT day_of_week,is_workday FROM work_schedule WHERE academic_year_id=?");
    $st->bind_param("i", $academic_year_id);
    $st->execute();
    $rs = $st->get_result();
    while ($row = $rs->fetch_assoc()) {
      $sched[(int)$row["day_of_week"]] = (int)$row["is_workday"];
    }
  }
  // fallback: Senin-Jumat workday
  for ($d=1;$d<=7;$d++) {
    if (!isset($sched[$d])) $sched[$d] = ($d <= 5) ? 1 : 0;
  }

  $hol = [];
  $sqlh = "SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN ? AND ? AND (academic_year_id IS NULL" . ($academic_year_id ? " OR academic_year_id=?" : "") . ")";
  if ($academic_year_id) {
    $st = $mysqli->prepare($sqlh);
    $st->bind_param("ssi", $from, $to, $academic_year_id);
  } else {
    $st = $mysqli->prepare($sqlh);
    $st->bind_param("ss", $from, $to);
  }
  $st->execute();
  $rs = $st->get_result();
  while ($row = $rs->fetch_assoc()) $hol[$row["holiday_date"]] = true;

  $out = [];
  $cur = clone $from_dt;
  while ($cur <= $to_dt) {
    $ds = $cur->format("Y-m-d");
    $dow = (int)$cur->format("N"); // 1..7
    if (($sched[$dow] ?? 0) === 1 && empty($hol[$ds])) $out[] = $ds;
    $cur->modify("+1 day");
  }
  return $out;
}

function count_absent_without_excuse($workdays, $present_days, $leave_approved) {
  $w = count($workdays);
  $abs = $w - (int)$present_days - (int)$leave_approved;
  if ($abs < 0) $abs = 0;
  return $abs;
}



function add_notification($mysqli, $user_id, $title, $message) {
  $stmt = $mysqli->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)");
  $stmt->bind_param("iss", $user_id, $title, $message);
  $stmt->execute();
}

function get_unread_notifications($mysqli, $user_id, $limit=5) {
  $stmt = $mysqli->prepare("SELECT id,title,message,created_at FROM notifications WHERE user_id=? AND is_read=0 ORDER BY id DESC LIMIT ?");
  $stmt->bind_param("ii", $user_id, $limit);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = [];
  while ($row = $res->fetch_assoc()) $rows[] = $row;
  return $rows;
}

function count_unread_notifications($mysqli, $user_id) {
  $stmt = $mysqli->prepare("SELECT COUNT(*) c FROM notifications WHERE user_id=? AND is_read=0");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return (int)($row["c"] ?? 0);
}

function mark_all_notifications_read($mysqli, $user_id) {
  $stmt = $mysqli->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
}

function date_range_inclusive($from, $to) {
  $out = [];
  $d1 = new DateTime($from);
  $d2 = new DateTime($to);
  if ($d1 > $d2) return $out;
  while ($d1 <= $d2) {
    $out[] = $d1->format("Y-m-d");
    $d1->modify("+1 day");
  }
  return $out;
}


function indonesian_date($ymd) {
  // input: YYYY-MM-DD or any string parsable by strtotime
  if (empty($ymd)) return "-";
  $ts = strtotime($ymd);
  if ($ts === false) return $ymd;
  $bulan = ["Januari","Februari","Maret","April","Mei","Juni","Juli","Agustus","September","Oktober","November","Desember"];
  $d = (int)date("j", $ts);
  $m = (int)date("n", $ts);
  $y = date("Y", $ts);
  return $d . " " . $bulan[$m-1] . " " . $y;
}

function indonesian_datetime($dt) {
  if (empty($dt)) return "-";
  $ts = strtotime($dt);
  if ($ts === false) return $dt;
  return indonesian_date(date("Y-m-d", $ts)) . " " . date("H:i", $ts) . " WIB";
}


// --- App Settings ---
function get_app_setting($key, $default = null) {
  global $mysqli;
  static $cache = null;
  if ($cache === null) {
    $cache = [];
    $res = $mysqli->query("SELECT `key`,`value` FROM app_settings");
    if ($res) {
      while ($row = $res->fetch_assoc()) $cache[$row["key"]] = $row["value"];
    }
  }
  return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_app_setting($key, $value) {
  global $mysqli;
  $stmt = $mysqli->prepare("INSERT INTO app_settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)");
  $stmt->bind_param("ss", $key, $value);
  $stmt->execute();
}

?>