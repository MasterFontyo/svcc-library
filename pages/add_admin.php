<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    if (empty($username) || empty($password) || empty($confirm)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if username exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username=?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'Admin';
            $stmt_insert = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt_insert->bind_param("sss", $username, $hash, $role);
            if ($stmt_insert->execute()) {
                $success = "Admin account created successfully.";
            } else {
                $error = "Failed to create admin account.";
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}
?>

<h2>Add Admin User</h2>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post" style="max-width:400px;">
    <div class="form-group">
        <label for="username">Username *</label>
        <input type="text" name="username" id="username" required autocomplete="off">
    </div>
    <div class="form-group">
        <label for="password">Password *</label>
        <input type="password" name="password" id="password" required autocomplete="new-password">
    </div>
    <div class="form-group">
        <label for="confirm_password">Confirm Password *</label>
        <input type="password" name="confirm_password" id="confirm_password" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn-primary">Add Admin</button>
</form>

<?php require_once '../includes/footer.php'; ?>