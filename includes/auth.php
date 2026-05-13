<?php
require_once __DIR__ . "/functions.php";

function login_user($user_row) {
  $_SESSION["user"] = [
    "id" => (int)$user_row["id"],
    "role" => $user_row["role"],
    "status" => $user_row["status"],
    "username" => $user_row["username"],
    "full_name" => $user_row["full_name"],
    "class_id" => $user_row["class_id"],
    "academic_year_id" => $user_row["academic_year_id"],
    "must_change_password" => (int)$user_row["must_change_password"],
    "is_alumni" => (int)$user_row["is_alumni"]
  ];
}
?>
