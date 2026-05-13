<?php
// Export daftar ijin pending (approval) to Excel/CSV

require_once __DIR__ . "/includes/functions.php";
require_once __DIR__ . "/includes/excel.php";
require_login();
$u = current_user();
require_role(["GURU","KEPSEK"]);

$pending = [];
if ($u["role"] === "GURU") {
  $stmt = $mysqli->prepare("SELECT lr.leave_date, lr.reason, lr.description, su.full_name, su.nisn, c.name AS class_name, lr.status
    FROM leave_requests lr
    JOIN users su ON su.id=lr.user_id
    LEFT JOIN classes c ON c.id=su.class_id
    WHERE lr.status='PENDING' AND su.role='SISWA'
    ORDER BY lr.leave_date DESC, lr.id DESC");
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $pending[] = $row;
} else { // KEPSEK
  $stmt = $mysqli->prepare("SELECT lr.leave_date, lr.reason, lr.description, u.full_name, u.role, u.employee_no, u.nisn, c.name AS class_name, lr.status
    FROM leave_requests lr
    JOIN users u ON u.id=lr.user_id
    LEFT JOIN classes c ON c.id=u.class_id
    WHERE lr.status='PENDING' AND u.role IN ('SISWA','GURU','YAYASAN','KEPSEK')
    ORDER BY lr.leave_date DESC, lr.id DESC");
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $pending[] = $row;
}

if ($u["role"] === "GURU") {
  $headers = ["Tanggal","Nama","NISN","Kelas","Alasan","Keterangan","Status"]; 
  $rows = [];
  foreach ($pending as $p) {
    $rows[] = [$p["leave_date"], $p["full_name"], $p["nisn"], $p["class_name"], $p["reason"], $p["description"], $p["status"]];
  }
  excel_output_table("ijin_pending_siswa", $headers, $rows);
}

$headers = ["Tanggal","Nama","Role","No Pegawai","NISN","Kelas","Alasan","Keterangan","Status"]; 
$rows = [];
foreach ($pending as $p) {
  $rows[] = [
    $p["leave_date"],
    $p["full_name"],
    $p["role"] ?? "SISWA",
    $p["employee_no"] ?? "",
    $p["nisn"] ?? "",
    $p["class_name"] ?? "-",
    $p["reason"],
    $p["description"],
    $p["status"],
  ];
}
excel_output_table("ijin_pending", $headers, $rows);
