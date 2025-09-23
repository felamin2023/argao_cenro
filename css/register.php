<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="form-box">
            <h2>REGISTER</h2>
            <form method="POST" action="#">
                <label>USERNAME:</label>
                <input type="text" name="username" required>

                <label>EMAIL:</label>
                <input type="email" name="email" required>

                <label>MOBILE NUMBER:</label>
                <input type="text" name="mobile" required>

                <label>ROLE:</label>
                <input type="text" name="role" required>

                <label>PASSWORD:</label>
                <input type="password" name="password" required>

                <p>Already have an account? <a href="login.php">LOGIN</a></p>

                <button type="submit">REGISTER</button>
            </form>
        </div>
    </div>
</body>
</html>
