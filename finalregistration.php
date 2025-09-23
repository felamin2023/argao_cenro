<?php
session_start();
if (!isset($_SESSION['google_email'])) {
    header("Location: superregister.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Complete Profile</title>
</head>

<body>
    <h2>Complete Your Registration</h2>
    <form action="/denr/superadmin/backend/admin/save_profile.php" method="POST">
        <input type="hidden" name="email" value="<?= htmlspecialchars($_SESSION['google_email']) ?>">

        <label>First Name:</label>
        <input type="text" name="first_name" value="<?= htmlspecialchars($_SESSION['first_name'] ?? '') ?>" required><br>

        <label>Last Name:</label>
        <input type="text" name="last_name" value="<?= htmlspecialchars($_SESSION['last_name'] ?? '') ?>" required><br>

        <label>Username:</label>
        <input type="text" name="username" required><br>

        <label>Department:</label>
        <select name="department" required>
            <option value="">Select Department</option>
            <option>Wildlife</option>
            <option>Seedling</option>
            <option>Tree Cutting</option>
            <option>Marine</option>
        </select><br>

        <label>Phone:</label>
        <input type="text" name="phone"><br>

        <label>Age:</label>
        <input type="number" name="age"><br>

        <label>Password (just for DB requirement):</label>
        <input type="password" name="password" required><br>

        <input type="hidden" name="role" value="Admin">
        <button type="submit">Submit</button>
    </form>
</body>

</html>