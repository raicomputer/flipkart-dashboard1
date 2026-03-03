<?php

$host = "sql12.freesqldatabase.com";
$user = "sql12818488";
$password = "AiNkWRH4Q5";
$database = "sql12818488";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

?>