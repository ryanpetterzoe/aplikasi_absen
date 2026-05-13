<?php
require_once __DIR__ . "/../includes/functions.php";
require_login();
require_role("ADMIN");

$id = (int)($_POST["id"] ?? 0);
if ($id<=0) { flash_set("warning","Kelas tidak valid."); redirect("/admin/classes.php"); }

$mysqli->begin_transaction();
try {
  // kosongkan class_id siswa/guru yang masih terkait (umumnya siswa)
  $st1 = $mysqli->prepare("UPDATE users SET class_id=NULL WHERE class_id=?");
  $st1->bind_param("i", $id);
  $st1->execute();

  $st2 = $mysqli->prepare("DELETE FROM classes WHERE id=?");
  $st2->bind_param("i", $id);
  $st2->execute();

  $mysqli->commit();
  flash_set("success","Kelas berhasil dihapus.");
} catch (Throwable $e) {
  $mysqli->rollback();
  flash_set("danger","Gagal menghapus kelas: ".$e->getMessage());
}
redirect("/admin/classes.php");
