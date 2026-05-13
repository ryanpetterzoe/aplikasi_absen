<?php
// Export Log Absensi (user sendiri) to Excel/CSV

require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/excel.php";
require_login();

$u = current_user();
$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");

// attendance
$stmt = $mysqli->prepare("SELECT * FROM attendance WHERE user_id=? AND att_date BETWEEN ? AND ? ORDER BY att_date ASC");
$stmt->bind_param("iss", $u["id"], $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$att_by_date = [];
while ($row = $res->fetch_assoc()) $att_by_date[$row["att_date"]] = $row;

// leave
$stmt = $mysqli->prepare("SELECT DATE(leave_date) AS leave_date, reason, description, status, decision_note FROM leave_requests\n  WHERE user_id=? AND DATE(leave_date) BETWEEN ? AND ? ORDER BY leave_date ASC");
$stmt->bind_param("iss", $u["id"], $from, $to);
$stmt->execute();
$res = $stmt->get_result();
$leave_by_date = [];
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

$fn = "log_absensi_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $u["username"]) . "_{$from}_{$to}";
excel_output_table($fn, $headers, $out);
