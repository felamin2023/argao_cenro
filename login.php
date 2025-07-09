<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="form-box">
            <h2>LOGIN</h2>
            <form method="POST" action="#">
                <label>USERNAME:</label>
                <input type="text" name="username" required>

                <label>PASSWORD:</label>
                <input type="password" name="password" required>

                <p>Don't have an account? <a href="register.php">REGISTER</a></p>

                <button type="submit">LOGIN</button>
            </form>
        </div>
    </div>
</body>
</html>
