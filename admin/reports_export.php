<?php
$title = "Export Rekap Absensi";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

// Parameter sama seperti reports.php
$type = $_GET["type"] ?? "daily"; // daily/weekly/semester/yearly
$date = $_GET["date"] ?? date("Y-m-d");
$year = $_GET["year"] ?? date("Y");
$class_id = $_GET["class_id"] ?? "";
$major_id = $_GET["major_id"] ?? "";

$range_from = $range_to = null;
if ($type === "daily") {
  $range_from = $date;
  $range_to = $date;
} elseif ($type === "weekly") {
  $ts = strtotime($date);
  $monday = date("Y-m-d", strtotime("monday this week", $ts));
  $sunday = date("Y-m-d", strtotime("sunday this week", $ts));
  $range_from = $monday; $range_to = $sunday;
} elseif ($type === "semester") {
  $m = (int)date("n", strtotime($date));
  if ($m <= 6) { $range_from = date("Y-01-01", strtotime($date)); $range_to = date("Y-06-30", strtotime($date)); }
  else { $range_from = date("Y-07-01", strtotime($date)); $range_to = date("Y-12-31", strtotime($date)); }
} else { // yearly
  $range_from = $year . "-01-01";
  $range_to = $year . "-12-31";
}

$where = "";
$params = [$range_from, $range_to, $range_from, $range_to];
$types = "ssss";
if ($class_id !== "") { $where .= " AND u.class_id=?"; $types .= "i"; $params[] = (int)$class_id; }
if ($major_id !== "") { $where .= " AND c.major_id=?"; $types .= "i"; $params[] = (int)$major_id; }

$sql = "SELECT u.id,u.full_name,u.role,c.name AS class_name,m.name AS major_name,
  COUNT(a.id) present_days,
  SUM(CASE WHEN a.status_in='LATE' THEN 1 ELSE 0 END) late_count,
  SUM(CASE WHEN a.status_out='EARLY' THEN 1 ELSE 0 END) early_count,
  (SELECT COUNT(*) FROM leave_requests lr WHERE lr.user_id=u.id AND lr.status='APPROVED' AND DATE(lr.leave_date) BETWEEN ? AND ?) AS leave_count
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN majors m ON m.id=c.major_id
  LEFT JOIN attendance a ON a.user_id=u.id AND a.att_date BETWEEN ? AND ?
  WHERE u.status='ACTIVE' AND u.role IN ('SISWA','GURU','KEPSEK','YAYASAN') $where
  GROUP BY u.id
  ORDER BY u.role, c.name, u.full_name";

$stmt = $mysqli->prepare($sql);
stmt_bind_params($stmt, $types, $params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
$ay_id = get_active_academic_year_id($mysqli);
$workdays = list_workday_dates($mysqli, $range_from, $range_to, $ay_id);

while ($r = $res->fetch_assoc()) {
  $absent_count = count_absent_without_excuse($workdays, (int)$r["present_days"], (int)$r["leave_count"]);
  $rows[] = [
    $r["full_name"],
    role_label($r["role"]),
    $r["class_name"],
    $r["major_name"],
    (int)$r["present_days"],
    (int)$r["late_count"],
    (int)$r["leave_count"],
    (int)$absent_count,
    (int)$r["early_count"],
  ];
}

$headers = [
  "Nama",
  "Role",
  "Kelas",
  "Jurusan",
  "Hadir",
  "Telat",
  "Ijin",
  "Tanpa Keterangan",
  "Pulang Awal",
];

$fname = "rekap_absensi_{$range_from}_sd_{$range_to}";
excel_output_table($fname, $headers, $rows);
