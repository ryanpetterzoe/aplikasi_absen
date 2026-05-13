<?php
// Export Rekap Absensi Guru to Excel/CSV

require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/excel.php";
require_login();
require_role(["ADMIN","KEPSEK","YAYASAN"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");
$teacher_id = $_GET["teacher_id"] ?? "";

$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);
$workday_set = array_flip($workdays);

if ($teacher_id !== "") {
  $tid = (int)$teacher_id;
  $t = $mysqli->prepare("SELECT id,full_name,employee_no,role FROM users WHERE id=? AND role IN ('GURU','KEPSEK','YAYASAN') LIMIT 1");
  $t->bind_param("i", $tid);
  $t->execute();
  $teacher = $t->get_result()->fetch_assoc();
  if (!$teacher) { http_response_code(404); echo "Guru tidak ditemukan."; exit; }

  $stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC");
  $stmt->bind_param("iss", $tid, $from, $to);
  $stmt->execute();
  $att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  $att_by_date = [];
  foreach ($att as $a) $att_by_date[$a["att_date"]] = $a;

  $stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, status FROM leave_requests
    WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date ASC");
  $stmt->bind_param("iss", $tid, $from, $to);
  $stmt->execute();
  $leave = [];
  $res = $stmt->get_result();
  while ($r = $res->fetch_assoc()) $leave[$r["leave_date"]] = $r;

  $dates = date_range_inclusive($from, $to);
  $headers = ["Tanggal","Status","Masuk","Pulang","Keterangan"];
  $out = [];
  foreach ($dates as $d) {
    $a = $att_by_date[$d] ?? null;
    $l = $leave[$d] ?? null;
    $holiday = is_holiday($mysqli, $d, $ay_id);
    $is_workday = isset($workday_set[$d]) ? 1 : 0;

    $status = "OFF";
    if ($holiday) $status = "LIBUR";
    elseif ($is_workday) {
      if ($l && ($l["status"] === "APPROVED")) $status = "IJIN";
      elseif ($l && ($l["status"] === "PENDING")) $status = "IJIN (PENDING)";
      elseif ($l && ($l["status"] === "REJECTED")) $status = "TANPA KETERANGAN";
      elseif ($a) $status = "HADIR";
      else $status = "TANPA KETERANGAN";
    }

    $masuk = $a && $a["checkin_at"] ? date("H:i", strtotime($a["checkin_at"])) : "-";
    $pulang = $a && $a["checkout_at"] ? date("H:i", strtotime($a["checkout_at"])) : "-";
    $ket = "";
    if ($holiday) $ket = "Libur: {$holiday}";
    elseif ($l) $ket = "Ijin ({$l['status']}): {$l['reason']}";
    elseif ($a) {
      $ketParts = [];
      if (!empty($a["status_in"])) $ketParts[] = "Masuk: " . att_code_label($a["status_in"]);
      if (!empty($a["status_out"])) $ketParts[] = "Pulang: " . att_code_label($a["status_out"]);
      if (!empty($a["note_out"])) $ketParts[] = "Catatan: " . $a["note_out"];
      $ket = implode(". ", $ketParts);
    }

    $out[] = [$d, $status, $masuk, $pulang, $ket];
  }

  $fn = "detail_guru_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $teacher["full_name"]) . "_{$from}_{$to}";
  excel_output_table($fn, $headers, $out);
}

// Summary semua guru (role GURU)
$sql = "SELECT u.id,u.full_name,u.employee_no,
  COUNT(DISTINCT a.att_date) AS present_days,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  (SELECT COUNT(*) FROM leave_requests lr
     WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.status='ACTIVE' AND u.role='GURU'
  GROUP BY u.id
  ORDER BY u.full_name";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ssss", $from, $to, $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $r["absent_count"] = count_absent_without_excuse($workdays, (int)$r["present_days"], (int)$r["leave_count"]);
  $rows[] = $r;
}

$headers = ["Nama","No Pegawai","Hadir","Telat","Ijin","Tanpa Ket."];
$out = [];
foreach ($rows as $r) {
  $out[] = [$r["full_name"], $r["employee_no"], (int)$r["present_days"], (int)$r["late_count"], (int)$r["leave_count"], (int)$r["absent_count"]];
}
excel_output_table("rekap_guru_{$from}_{$to}", $headers, $out);
