<?php
/**
 * Helper Excel Import/Export.
 *
 * - Prefer XLSX via PhpSpreadsheet if available (Composer vendor/autoload.php).
 * - Fallback to CSV if library not installed.
 */

function excel_bootstrap() {
  static $loaded = false;
  if ($loaded) return;
  $loaded = true;

  $vendor = __DIR__ . "/../vendor/autoload.php";
  if (file_exists($vendor)) {
    require_once $vendor;
  }
}

function excel_available() {
  excel_bootstrap();
  return class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet');
}

/**
 * Output an XLSX file (if available) otherwise CSV.
 *
 * @param string $filenameWithoutExt e.g. "rekap_absensi_2026-01-01"
 * @param string[] $headers
 * @param array<int, array<int, mixed>> $rows Row values (indexed array)
 */
function excel_output_table($filenameWithoutExt, $headers, $rows) {
  if (excel_available()) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $col = 1;
    foreach ($headers as $h) {
      $sheet->setCellValueByColumnAndRow($col, 1, $h);
      $col++;
    }
    $sheet->getStyle("1:1")->getFont()->setBold(true);

    // Rows
    $r = 2;
    foreach ($rows as $row) {
      $col = 1;
      foreach ($row as $val) {
        $sheet->setCellValueByColumnAndRow($col, $r, $val);
        $col++;
      }
      $r++;
    }

    // Autosize columns (best effort)
    for ($i = 1; $i <= count($headers); $i++) {
      $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filenameWithoutExt . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
  }

  // CSV fallback
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filenameWithoutExt . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, $headers);
  foreach ($rows as $row) fputcsv($out, $row);
  fclose($out);
  exit;
}

/**
 * Read uploaded Excel/CSV file into rows.
 * Returns list of associative rows keyed by header.
 *
 * Supports:
 * - XLSX/XLS/ODS via PhpSpreadsheet
 * - CSV via native fgetcsv
 */
function excel_read_assoc_rows($filepath) {
  $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
  $rows = [];

  if (in_array($ext, ['xlsx','xls','ods'], true) && excel_available()) {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filepath);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, true, true, true); // A,B,C...
    if (count($data) < 1) return [];

    // Determine header row (first non-empty row)
    $headerRow = null;
    foreach ($data as $idx => $row) {
      $has = false;
      foreach ($row as $v) {
        if (trim((string)$v) !== '') { $has = true; break; }
      }
      if ($has) { $headerRow = $idx; break; }
    }
    if ($headerRow === null) return [];

    $headers = [];
    foreach ($data[$headerRow] as $col => $h) {
      $h = trim((string)$h);
      if ($h !== '') $headers[$col] = $h;
    }

    foreach ($data as $idx => $row) {
      if ($idx <= $headerRow) continue;
      $assoc = [];
      $allEmpty = true;
      foreach ($headers as $col => $h) {
        $val = $row[$col] ?? '';
        if (is_string($val)) $val = trim($val);
        if (trim((string)$val) !== '') $allEmpty = false;
        $assoc[$h] = $val;
      }
      if (!$allEmpty) $rows[] = $assoc;
    }
    return $rows;
  }

  // CSV (or fallback)
  $fh = fopen($filepath, 'r');
  if (!$fh) return [];
  $headers = null;
  while (($cols = fgetcsv($fh)) !== false) {
    // Skip empty lines
    $nonEmpty = false;
    foreach ($cols as $c) {
      if (trim((string)$c) !== '') { $nonEmpty = true; break; }
    }
    if (!$nonEmpty) continue;

    if ($headers === null) {
      $headers = array_map(function($h){ return trim((string)$h); }, $cols);
      continue;
    }

    $assoc = [];
    $allEmpty = true;
    foreach ($headers as $i => $h) {
      if ($h === '') continue;
      $val = $cols[$i] ?? '';
      if (is_string($val)) $val = trim($val);
      if (trim((string)$val) !== '') $allEmpty = false;
      $assoc[$h] = $val;
    }
    if (!$allEmpty) $rows[] = $assoc;
  }
  fclose($fh);
  return $rows;
}
