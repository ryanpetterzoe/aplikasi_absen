<?php
// Export Rekap Absensi Siswa to Excel/CSV

require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/excel.php";
require_login();
require_role(["ADMIN","GURU","KEPSEK","YAYASAN"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"] ?? date("Y-m-d");
$group = $_GET["group"] ?? "student"; // student/class/major
$class_id = $_GET["class_id"] ?? "";
$major_id = $_GET["major_id"] ?? "";

$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $from, $to, $ay_id);

$where = "";
$params = [$from,$to,$from,$to];
$types = "ssss";

if ($class_id !== "") { $where .= " AND u.class_id=?"; $params[] = (int)$class_id; $types .= "i"; }
if ($major_id !== "") { $where .= " AND c.major_id=?"; $params[] = (int)$major_id; $types .= "i"; }

$sql = "SELECT u.id,u.full_name,u.nisn,
  c.name AS class_name,
  m.name AS major_name,
  COUNT(DISTINCT a.att_date) AS present_days,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) AS late_count,
  (SELECT COUNT(*) FROM leave_requests lr
     WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN majors m ON m.id=c.major_id
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.role='SISWA' AND u.status='ACTIVE' AND u.is_alumni=0 $where
  GROUP BY u.id
  ORDER BY c.name,u.full_name";

$stmt = $mysqli->prepare($sql);
stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
  $r["absent_count"] = count_absent_without_excuse($workdays, (int)$r["present_days"], (int)$r["leave_count"]);
  $rows[] = $r;
}

function agg_init() {
  return ["count_students"=>0,"present_days"=>0,"late_count"=>0,"leave_count"=>0,"absent_count"=>0];
}

$filename = "rekap_siswa_{$group}_{$from}_{$to}";

if ($group === "class" || $group === "major") {
  $agg = [];
  foreach ($rows as $r) {
    $k = ($group === "class") ? ($r["class_name"] ?: "-") : ($r["major_name"] ?: "-");
    if (!isset($agg[$k])) $agg[$k] = agg_init();
    $agg[$k]["count_students"] += 1;
    $agg[$k]["present_days"] += (int)$r["present_days"];
    $agg[$k]["late_count"] += (int)$r["late_count"];
    $agg[$k]["leave_count"] += (int)$r["leave_count"];
    $agg[$k]["absent_count"] += (int)$r["absent_count"];
  }

  $headers = [
    ($group === "class" ? "Kelas" : "Jurusan"),
    "Jumlah Siswa",
    "Hadir",
    "Telat",
    "Ijin",
    "Tanpa Ket.",
  ];
  $out = [];
  foreach ($agg as $k => $a) {
    $out[] = [$k, $a["count_students"], $a["present_days"], $a["late_count"], $a["leave_count"], $a["absent_count"]];
  }
  excel_output_table($filename, $headers, $out);
}

// default: student
$headers = [
  "Nama",
  "NISN",
  "Kelas",
  "Jurusan",
  "Hadir",
  "Telat",
  "Ijin",
  "Tanpa Ket.",
];
$out = [];
foreach ($rows as $r) {
  $out[] = [
    $r["full_name"],
    $r["nisn"],
    $r["class_name"],
    $r["major_name"],
    (int)$r["present_days"],
    (int)$r["late_count"],
    (int)$r["leave_count"],
    (int)$r["absent_count"],
  ];
}
excel_output_table($filename, $headers, $out);
