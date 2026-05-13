<?php
$title = "Template Import Users";
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$role = strtoupper(trim($_GET["role"] ?? "SISWA"));
$allowed = ["SISWA","GURU","KEPSEK","YAYASAN","ADMIN"];
if (!in_array($role, $allowed, true)) $role = "SISWA";

// Headers: gunakan nama kolom sederhana agar mudah dipakai ulang
$headers = [
  "full_name",
  "username",
  "password",
  "employee_no",
  "nisn",
  "class_name",
  "academic_year",
  "phone_wa",
  "address",
];

// Example row
$rows = [];
if ($role === "SISWA") {
  $rows[] = [
    "Budi Santoso",
    "budi.s",
    "123456",
    "",
    "1234567890",
    "X IPA 1",
    "2025/2026",
    "08123456789",
    "Jl. Contoh No. 1",
  ];
} else {
  $rows[] = [
    "Siti Aminah",
    "siti.a",
    "123456",
    "NIP001",
    "",
    "",
    "2025/2026",
    "08123456789",
    "Jl. Contoh No. 2",
  ];
}

$fname = "template_import_" . strtolower($role);
excel_output_table($fname, $headers, $rows);
