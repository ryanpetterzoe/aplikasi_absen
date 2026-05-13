<?php
// Export classes to Excel/CSV

require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$sql = "SELECT c.grade,c.name, m.name AS major_name, y.name AS year_name, u.full_name AS homeroom_name, c.is_active
        FROM classes c
        LEFT JOIN majors m ON m.id=c.major_id
        LEFT JOIN academic_years y ON y.id=c.academic_year_id
        LEFT JOIN users u ON u.id=c.homeroom_teacher_id
        ORDER BY c.grade ASC, c.name ASC";
$res = $mysqli->query($sql);
$classes = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

$headers = ["Tingkat","Nama Kelas","Jurusan","Wali Kelas","Tahun","Aktif(1/0)"];
$rows = [];
foreach ($classes as $c) {
  $rows[] = [
    (int)$c["grade"],
    $c["name"],
    $c["major_name"],
    $c["homeroom_name"],
    $c["year_name"],
    (int)($c["is_active"] ?? 1),
  ];
}

excel_output_table("data_kelas", $headers, $rows);
