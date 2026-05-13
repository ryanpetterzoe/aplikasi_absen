<?php
// DB Backup & Restore helpers (tanpa shell / mysqldump)
// Aman untuk shared-hosting.

function dbtools_is_gzip_file(string $path): bool {
  $fh = @fopen($path, 'rb');
  if (!$fh) return false;
  $sig = fread($fh, 2);
  fclose($fh);
  return $sig === "\x1f\x8b";
}

function dbtools_list_tables(mysqli $db): array {
  $tables = [];
  $res = $db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE' OR Table_type='VIEW'");
  if ($res) {
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
      if (!empty($row[0])) $tables[] = $row[0];
    }
  }
  // fallback
  if (!$tables) {
    $res2 = $db->query("SHOW TABLES");
    if ($res2) {
      while ($row = $res2->fetch_array(MYSQLI_NUM)) {
        if (!empty($row[0])) $tables[] = $row[0];
      }
    }
  }
  sort($tables);
  return $tables;
}

function dbtools_dump(mysqli $db, array $options = []): void {
  $includeData = ($options['include_data'] ?? true) ? true : false;
  $gzip = ($options['gzip'] ?? false) ? true : false;
  $filenameBase = $options['filename_base'] ?? ('backup_' . date('Ymd_His'));

  $tables = dbtools_list_tables($db);
  $header = "-- Absensi Sekolah DB Backup\n";
  $header .= "-- Generated: " . date('c') . "\n";
  $header .= "-- Server: " . ($db->server_info ?? '') . "\n\n";
  $header .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
  $header .= "SET time_zone = '+07:00';\n";
  $header .= "SET foreign_key_checks = 0;\n";
  $header .= "SET names utf8mb4;\n\n";

  if ($gzip) {
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.sql.gz"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $out = gzopen('php://output', 'wb9');
    gzwrite($out, $header);
  } else {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filenameBase . '.sql"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $header;
    $out = null;
  }

  $write = function(string $s) use ($gzip, &$out) {
    if ($gzip) {
      gzwrite($out, $s);
    } else {
      echo $s;
    }
  };

  foreach ($tables as $table) {
    $tblEsc = str_replace('`', '``', $table);
    $write("-- ----------------------------\n");
    $write("-- Table: `{$tblEsc}`\n");
    $write("-- ----------------------------\n");

    $res = $db->query("SHOW CREATE TABLE `{$tblEsc}`");
    if ($res && ($row = $res->fetch_assoc())) {
      $create = $row['Create Table'] ?? $row['Create View'] ?? null;
      if ($create) {
        $write("DROP TABLE IF EXISTS `{$tblEsc}`;\n");
        $write($create . ";\n\n");
      }
    }

    if ($includeData) {
      // export data
      $result = $db->query("SELECT * FROM `{$tblEsc}`", MYSQLI_USE_RESULT);
      if ($result) {
        $cols = [];
        $fields = $result->fetch_fields();
        foreach ($fields as $f) $cols[] = '`' . str_replace('`', '``', $f->name) . '`';
        $colList = implode(',', $cols);

        $chunk = [];
        $chunkSize = 200;

        while ($row = $result->fetch_assoc()) {
          $vals = [];
          foreach ($fields as $f) {
            $v = $row[$f->name];
            if ($v === null) {
              $vals[] = 'NULL';
            } else {
              // selalu quote (aman untuk NISN/telepon leading zero)
              $vals[] = "'" . $db->real_escape_string((string)$v) . "'";
            }
          }
          $chunk[] = '(' . implode(',', $vals) . ')';
          if (count($chunk) >= $chunkSize) {
            $write("INSERT INTO `{$tblEsc}` ({$colList}) VALUES\n" . implode(",\n", $chunk) . ";\n");
            $chunk = [];
          }
        }
        if ($chunk) {
          $write("INSERT INTO `{$tblEsc}` ({$colList}) VALUES\n" . implode(",\n", $chunk) . ";\n");
        }
        $write("\n");
        $result->free_result();
      }
    }
  }

  $write("SET foreign_key_checks = 1;\n");

  if ($gzip && $out) {
    gzclose($out);
  }
}

function dbtools_import(mysqli $db, string $filepath): array {
  $isGz = dbtools_is_gzip_file($filepath) || (strtolower(pathinfo($filepath, PATHINFO_EXTENSION)) === 'gz');
  $handle = $isGz ? @gzopen($filepath, 'rb') : @fopen($filepath, 'rb');
  if (!$handle) {
    return ['ok'=>false, 'error'=>'Gagal membuka file backup.'];
  }

  @$db->query("SET foreign_key_checks = 0");
  @$db->query("SET names utf8mb4");

  $buffer = '';
  $inString = false;
  $stringChar = '';
  $escaped = false;
  $inBlockComment = false;
  $executed = 0;

  $readLine = function() use (&$handle, $isGz) {
    return $isGz ? gzgets($handle) : fgets($handle);
  };

  while (($line = $readLine()) !== false) {
    // normalisasi line endings
    $line = str_replace("\r\n", "\n", $line);
    $line = str_replace("\r", "\n", $line);

    // skip komentar single line (jika tidak sedang di string)
    $trim = ltrim($line);
    if (!$inString && !$inBlockComment) {
      if ($trim === '' || $trim === "\n") continue;
      if (strpos($trim, '--') === 0) continue;
      if (strpos($trim, '#') === 0) continue;
    }

    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
      $ch = $line[$i];
      $next = ($i + 1 < $len) ? $line[$i + 1] : '';

      // block comment /* ... */
      if (!$inString) {
        if (!$inBlockComment && $ch === '/' && $next === '*') {
          $inBlockComment = true;
          $i++; // skip '*'
          continue;
        }
        if ($inBlockComment) {
          if ($ch === '*' && $next === '/') {
            $inBlockComment = false;
            $i++; // skip '/'
          }
          continue;
        }
      }

      // string handling
      if ($inString) {
        $buffer .= $ch;
        if ($escaped) {
          $escaped = false;
          continue;
        }
        if ($ch === "\\") {
          $escaped = true;
          continue;
        }
        if ($ch === $stringChar) {
          $inString = false;
          $stringChar = '';
        }
        continue;
      }

      if ($ch === "'" || $ch === '"') {
        $inString = true;
        $stringChar = $ch;
        $buffer .= $ch;
        continue;
      }

      // statement end
      if ($ch === ';') {
        $buffer .= $ch;
        $sql = trim($buffer);
        $buffer = '';
        if ($sql !== '') {
          try {
            // gunakan multi_query untuk statement yang mungkin panjang
            if (!$db->multi_query($sql)) {
              $err = $db->error;
              return ['ok'=>false, 'error'=>'Restore gagal: ' . $err, 'executed'=>$executed];
            }
            do { $db->store_result(); } while ($db->more_results() && $db->next_result());
            $executed++;
          } catch (Throwable $t) {
            return ['ok'=>false, 'error'=>'Restore gagal: ' . $t->getMessage(), 'executed'=>$executed];
          }
        }
        continue;
      }

      $buffer .= $ch;
    }
  }

  // flush last buffer
  $sql = trim($buffer);
  if ($sql !== '') {
    try {
      if (!$db->multi_query($sql)) {
        $err = $db->error;
        return ['ok'=>false, 'error'=>'Restore gagal: ' . $err, 'executed'=>$executed];
      }
      do { $db->store_result(); } while ($db->more_results() && $db->next_result());
      $executed++;
    } catch (Throwable $t) {
      return ['ok'=>false, 'error'=>'Restore gagal: ' . $t->getMessage(), 'executed'=>$executed];
    }
  }

  @$db->query("SET foreign_key_checks = 1");
  if ($isGz) gzclose($handle); else fclose($handle);
  return ['ok'=>true, 'executed'=>$executed];
}
