<?php
session_start();
$message = "";

// ✅ Helper functions for cookies
function setCookieSafe($name, $value, $days = 0) {
    $options = [
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => false,
        'samesite' => 'Lax'
    ];
    if ($days > 0) {
        $options['expires'] = time() + ($days * 24 * 60 * 60);
    }
    setcookie($name, $value, $options);
}

function clearCookieSafe($name) {
    setcookie($name, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
}

// ---------------- SIGN UP ----------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    $firstname = htmlspecialchars($_POST['fname']);
    $lastname = htmlspecialchars($_POST['lname']);
    $username = htmlspecialchars($_POST['uname']);
    $email = htmlspecialchars($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if username exists
    if (file_exists("users.txt")) {
        $users = file("users.txt", FILE_IGNORE_NEW_LINES);
        foreach ($users as $user) {
            if (strpos($user, "Username: $username") !== false) {
                $message = "Username already exists.";
                goto skip_signup;
            }
        }
    }

    $data = "First Name: $firstname | Last Name: $lastname | Username: $username | Email: $email | Password: $password\n";

    $file = fopen("users.txt", "a");
    if ($file) {
        fwrite($file, $data);
        fclose($file);
        $message = "Sign-up successful. You can now log in.";
    } else {
        $message = "Error: Unable to write to file.";
    }
}
skip_signup:

// ---------------- LOGIN ----------------
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $login_username = trim($_POST['signin_uname']);
    $login_password = $_POST['signin_password'];
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';

    if (file_exists("users.txt")) {
        $users = file("users.txt", FILE_IGNORE_NEW_LINES);
        foreach ($users as $user) {
            $parts = explode("|", $user);
            $uname = trim(str_replace("Username:", "", $parts[2]));
            $pass = trim(str_replace("Password:", "", $parts[4]));

            if ($uname === $login_username && password_verify($login_password, $pass)) {
                $_SESSION['is_authenticated'] = true;
                $_SESSION['username'] = $login_username;
                $_SESSION['fname'] = trim(str_replace("First Name:", "", $parts[0]));

                // ✅ Session cookies
                ini_set('session.cookie_httponly', '1');
                if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                    ini_set('session.cookie_secure', '1');
                }

                // ✅ Custom cookies
                setCookieSafe('session_active', '1');
                setCookieSafe('session_started_at', (string)time());

                // ✅ Remember username cookie (if checked)
                if ($remember) {
                    setCookieSafe('remember_username', $login_username, 30);
                } else {
                    clearCookieSafe('remember_username');
                }

                header("Location: /vsphp/public/index.html");
                exit;
            }
        }
    }
    $message = "Invalid username or password.";
}

// ---------------- LOGOUT ----------------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();

    // Clear cookies
    clearCookieSafe('remember_username');
    clearCookieSafe('session_active');
    clearCookieSafe('session_started_at');

    header("Location: /vsphp/auth/sign.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sign In / Sign Up</title>
  <style>
    body {
      margin: 0;
      font-family: Arial, Helvetica, sans-serif;
      background-image: linear-gradient(rgb(0, 0, 0), rgb(28, 28, 27), rgb(47, 47, 45), rgb(70, 70, 67));
      color: white;
    }
    img.logo { display: block; margin: 20px auto; }
    h1 { text-align: center; font-size: 40px; margin-bottom: 30px; }
    .container { display: flex; justify-content: center; gap: 40px; padding: 40px 20px; flex-wrap: wrap; }
    .form-box {
      background-color: rgba(255, 255, 255, 0.05);
      border-radius: 20px;
      padding: 30px;
      width: 350px;
      box-shadow: 0px 0px 20px rgba(255, 255, 255, 0.3);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .form-box:hover { transform: scale(1.05); box-shadow: 0 0 40px rgba(255, 255, 255, 0.4); }
    label { font-weight: bold; }
    input[type="text"], input[type="email"], input[type="password"] {
      width: 100%; padding: 8px; margin: 6px 0 12px; border: none; border-radius: 4px;
    }
    input[type="submit"] {
      padding: 10px 20px; font-weight: bold; border: none; border-radius: 8px;
      background-color: #444; color: white; cursor: pointer; transition: background-color 0.3s ease;
    }
    input[type="submit"]:hover { background-color: #666; }
    .center { text-align: center; }
    a { color: white; text-decoration: none; }
    a:hover { text-decoration: underline; }
    footer { margin-top: 50px; padding: 20px; background-color: rgb(20, 20, 20); font-size: 0.9em; text-align: center; }
    .message { text-align: center; margin: 20px 0; font-weight: bold; }

    /* ✅ Custom “Remember Me” checkbox styling */
    .remember-label {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: normal;
      cursor: pointer;
      user-select: none;
    }

    input[type="checkbox"] {
      appearance: none;
      width: 18px;
      height: 18px;
      border: 2px solid white;
      border-radius: 4px;
      background-color: transparent;
      cursor: pointer;
      transition: all 0.2s ease-in-out;
      position: relative;
    }

    input[type="checkbox"]:hover {
      box-shadow: 0 0 6px rgba(255, 255, 255, 0.4);
    }

    input[type="checkbox"]:checked {
      background-color: white;
      box-shadow: 0 0 8px rgba(255, 255, 255, 0.6);
    }

    input[type="checkbox"]:checked::after {
      content: "✔";
      position: absolute;
      top: 0;
      left: 3px;
      color: black;
      font-size: 13px;
      font-weight: bold;
    }
  </style>
</head>
<body>
  <img src="logo.png" alt="logo" height="100" class="logo" />
  <h1>SIGN IN / SIGN UP</h1>

  <?php if ($message) echo "<p class='message'>$message</p>"; ?>

  <?php if (!isset($_SESSION['username'])): ?>
  <div class="container">
    <div class="form-box">
      <h2>Sign Up</h2>
      <form action="" method="post">
        <label for="fname">First name:</label><br>
        <input type="text" id="fname" name="fname" required><br>

        <label for="lname">Last name:</label><br>
        <input type="text" id="lname" name="lname" required><br>

        <label for="signup-username">Username:</label><br>
        <input type="text" id="signup-username" name="uname" required><br>

        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br>

        <label for="signup-password">Password:</label><br>
        <input type="password" id="signup-password" name="password" required><br>

        <input type="submit" name="signup" value="Sign up">
      </form>
    </div>

    <div class="form-box">
      <h2>Sign In</h2>
      <form action="" method="post">
        <label for="signin-username">Username:</label><br>
        <input type="text" id="signin-username" name="signin_uname" required><br>

        <label for="signin-password">Password:</label><br>
        <input type="password" id="signin-password" name="signin_password" required><br>

        <!-- ✅ Styled Remember Me checkbox -->
        <label class="remember-label"><input type="checkbox" name="remember" value="1"> Remember Me</label><br><br>

        <input type="submit" name="login" value="Sign in">
      </form>
    </div>
  </div>
  <?php else: ?>
  <div class="container">
    <div class="form-box">
      <h2>Welcome, <?php echo htmlspecialchars($_SESSION['fname']); ?></h2>
      <p>This is your protected dashboard. Only logged-in users can see this content.</p>
      <a href="?logout=true">Logout</a>
    </div>
  </div>
  <?php endif; ?>

  <p class="center"><u><a href="../public/index.html">Go Back To Home</a></u></p>

  <footer>
    <p>© 2025 MOONFEST. All rights reserved.</p>
  </footer>
</body>
</html>

