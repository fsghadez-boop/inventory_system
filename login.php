<?php
require 'config/db.php';
$err = "";
if ($_POST) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $stmt = $db->prepare("SELECT * FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result && password_verify($password, $result['password'])) {
        // Start the session and set user details
        session_start();
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['role'] = $result['role'];

        // Log the login action
        $user_id = $result['id'];
        $sql = "INSERT INTO audit_logs (performed_by, action, log_details, created_at) 
                VALUES (?, 'login', 'User logged in', NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bind_param("i", $user_id); // Bind user ID
        $stmt->execute();

        // Redirect based on user role
        if ($result['role'] == 'admin') {
            header('Location: admin/dashboard.php');
        } else {
            header('Location: user/dashboard.php');
        }
        exit;
    } else {
        $err = "Invalid login credentials";
    }
}
?>
<!doctype html>
<html>
<head>
    <title>Inventory System Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center" style="margin-top:100px;">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title">Login</h4>
                    <?php if ($err): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input class="form-control mb-3" name="username" placeholder="Username" required>
                        <input class="form-control mb-3" name="password" type="password" placeholder="Password" required>
                        <button class="btn btn-primary btn-block">Login</button>
                    </form>
                    <!-- Optionally enable registration
                    <hr>
                    <p><a href="register.php">Register</a></p>
                    -->
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
