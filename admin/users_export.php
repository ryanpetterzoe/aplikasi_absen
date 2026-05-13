<?php
// Export users by role to Excel/CSV

require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$role = $_GET["role"] ?? "SISWA";
$allowed = ["SISWA","GURU","KEPSEK","YAYASAN","ADMIN"];
if (!in_array($role, $allowed, true)) $role = "SISWA";

$stmt = $mysqli->prepare("SELECT u.id,u.role,u.status,u.full_name,u.username,u.employee_no,u.nisn,
    c.name AS class_name, ay.name AS academic_year, u.created_at
  FROM users u
  LEFT JOIN classes c ON c.id=u.class_id
  LEFT JOIN academic_years ay ON ay.id=u.academic_year_id
  WHERE u.role=?
  ORDER BY u.created_at DESC");
$stmt->bind_param("s", $role);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;

$headers = ["Role","Status","Nama","Username","NIP","NISN","Kelas","Tahun Pelajaran","Dibuat"];
$out = [];
foreach ($rows as $r) {
  $out[] = [
    $r["role"],
    $r["status"],
    $r["full_name"],
    $r["username"],
    $r["employee_no"],
    $r["nisn"],
    $r["class_name"],
    $r["academic_year"],
    $r["created_at"],
  ];
}

excel_output_table("users_{$role}", $headers, $out);
