<?php
// Export detail absensi pegawai (guru/kepsek/yayasan) to Excel/CSV

require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/excel.php";
require_login();
require_role(["ADMIN","KEPSEK","YAYASAN"]);

$target_id = (int)($_GET["id"] ?? 0);
$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

if ($target_id <= 0) { http_response_code(400); echo "ID tidak valid"; exit; }

$stmt = $mysqli->prepare("SELECT id,full_name,employee_no,role FROM users WHERE id=? AND role IN ('GURU','KEPSEK','YAYASAN') LIMIT 1");
$stmt->bind_param("i", $target_id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
if (!$target) { http_response_code(404); echo "Pegawai tidak ditemukan"; exit; }

$stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC");
$stmt->bind_param("iss", $target_id, $from, $to);
$stmt->execute();
$att = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$att_by_date = [];
foreach ($att as $a) $att_by_date[$a["att_date"]] = $a;

$stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, description, status, decision_note FROM leave_requests\n  WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date ASC");
$stmt->bind_param("iss", $target_id, $from, $to);
$stmt->execute();
$leave_by_date = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $leave_by_date[$row["leave_date"]] = $row;

$ay_id = get_active_academic_year_id($mysqli);
$workday_set = array_flip(list_workday_dates($mysqli, $from, $to, $ay_id));
$dates = date_range_inclusive($from, $to);

$headers = ["Tanggal","Status","Masuk","Status Masuk","Pulang","Status Pulang","Ijin","Keterangan","Libur"];
$out = [];

foreach ($dates as $d) {
  $a = $att_by_date[$d] ?? null;
  $l = $leave_by_date[$d] ?? null;
  $holiday = is_holiday($mysqli, $d, $ay_id);
  $is_workday = isset($workday_set[$d]) ? 1 : 0;

  $status = "OFF";
  if ($holiday) $status = "LIBUR";
  elseif ($is_workday) {
    if ($l) {
      if (($l["status"] ?? "") === "APPROVED") $status = "IJIN";
      elseif (($l["status"] ?? "") === "PENDING") $status = "IJIN (PENDING)";
      elseif (($l["status"] ?? "") === "REJECTED") $status = "TANPA KETERANGAN";
      else $status = "IJIN";
    } elseif ($a) $status = "HADIR";
    else $status = "TANPA KETERANGAN";
  }

  $masuk = $a && $a["checkin_at"] ? date("H:i", strtotime($a["checkin_at"])) : "-";
  $pulang = $a && $a["checkout_at"] ? date("H:i", strtotime($a["checkout_at"])) : "-";
  $status_in = $a && !empty($a["status_in"]) ? att_code_label($a["status_in"]) : "-";
  $status_out = $a && !empty($a["status_out"]) ? att_code_label($a["status_out"]) : "-";

  $ijin = $l ? ($l["reason"] ?? "-") : "-";
  $ket = $l ? ($l["description"] ?? "") : (string)($a["note_out"] ?? "");
  if ($l && !empty($l["decision_note"])) $ket = trim($ket . " | Catatan: " . $l["decision_note"]);

  $out[] = [$d, $status, $masuk, $status_in, $pulang, $status_out, $ijin, $ket, $holiday ? $holiday : "-"];
}

$fn = "detail_staff_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $target["full_name"]) . "_{$from}_{$to}";
excel_output_table($fn, $headers, $out);
