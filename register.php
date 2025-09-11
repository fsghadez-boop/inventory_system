<?php
require 'config/db.php';
$err = "";
if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    // Duplicate?
    $stmt = $db->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows) $err = "Username exists!";
    else {
        $passhash = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
        $stmt2->bind_param("ss", $username, $passhash);
        $stmt2->execute();
        header("Location: login.php");
        exit;
    }
}
?>
<!doctype html>
<html><head>
<title>Register</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"></head>
<body>
<div class="container pt-5">
    <h3>User Registration</h3>
    <?php if($err): ?><div class="alert alert-danger"><?= $err ?></div><?php endif; ?>
    <form method="post" style="max-width:350px;">
        <input class="form-control mb-2" name="username" placeholder="Username" required>
        <input class="form-control mb-2" name="password" type="password" placeholder="Password" required>
        <button class="btn btn-primary">Register</button>
        <a href="login.php" class="btn btn-secondary ml-2">Back</a>
    </form>
</div>
</body>
</html>
