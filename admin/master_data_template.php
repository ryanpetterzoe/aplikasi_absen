<?php
// Download template for master data import

require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/excel.php";
require_login();
require_role("ADMIN");

$type = $_GET["type"] ?? "majors";
$allowed = ["majors","years","classes"];
if (!in_array($type, $allowed, true)) $type = "majors";

if ($type === "years") {
  $headers = ["name","is_active(1/0)"];
  $rows = [["2025/2026",1],["2024/2025",0]];
  excel_output_table("template_academic_years", $headers, $rows);
}

if ($type === "classes") {
  $headers = ["grade","name","major_name","homeroom_teacher","academic_year","is_active(1/0)"];
  $rows = [[10,"X IPA 1","IPA","Budi Santoso","2025/2026",1]];
  excel_output_table("template_classes", $headers, $rows);
}

// default majors
$headers = ["name"];
$rows = [["IPA"],["IPS"],["TKJ"]];
excel_output_table("template_majors", $headers, $rows);
