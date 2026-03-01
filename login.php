<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $password = "1";   // 🔑 CHANGE THIS PASSWORD

    if ($_POST['password'] == $password) {
        $_SESSION['loggedin'] = true;
        header("Location: index.php");
        exit();
    } else {
        $error = "Wrong Password!";
    }
}
?>
<?php include "auth.php"; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex justify-content-center align-items-center vh-100">

<div class="card p-4 shadow" style="width:350px;">
    <h4 class="text-center mb-3">Dashboard Login</h4>

    <?php if ($error) echo "<div class='alert alert-danger'>$error</div>"; ?>

    <form method="post">
        <input type="password" name="password" class="form-control mb-3" placeholder="Enter Password" required>
        <button class="btn btn-primary w-100">Login</button>
    </form>
</div>

</body>
</html>