<?php
$host = "sql12.freesqldatabase.com";
$user = "sql12818488";
$password = "AiNkWRH4Q5";
$database = "sql12818488";

$conn = mysqli_connect($host, $user, $password, $database);

if (!$conn) {
    die("❌ Connection Failed: " . mysqli_connect_error());
}

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>

