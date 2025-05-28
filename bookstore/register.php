<?php
session_start();
require_once "db.php";
require_once "functions.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST["username"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Username already taken.";
        } else {
            $stmt->close();
            // Insert new user with role 'user' without hashing password
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
            $stmt->bind_param("ss", $username, $password);
            if ($stmt->execute()) {
                $success = "Registration successful. You can now <a href='login.php' class='text-blue-600 underline'>login</a>.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Register - Bookstore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
</head>
<body class="bg-gray-100 font-['Roboto'] flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Register for Rezky Bookstore</h1>
        <?php if ($error): ?>
            <div class="bg-red-100 text-red-700 p-3 rounded mb-4"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?php echo $success; ?></div>
        <?php endif; ?>
        <form method="POST" action="register.php" class="space-y-4">
            <div>
                <label for="username" class="block mb-1 font-semibold">Username</label>
                <input type="text" id="username" name="username" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
                <label for="password" class="block mb-1 font-semibold">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    <button type="button" id="togglePassword" class="absolute right-2 top-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                        Show
                    </button>
                </div>
            </div>
            <div>
                <label for="confirm_password" class="block mb-1 font-semibold">Confirm Password</label>
                <div class="relative">
                    <input type="password" id="confirm_password" name="confirm_password" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    <button type="button" id="toggleConfirmPassword" class="absolute right-2 top-2 text-gray-600 hover:text-gray-900 focus:outline-none">
                        Show
                    </button>
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition">Register</button>
        </form>
        <p class="mt-4 text-center text-gray-600">Sudah punya akun? <a href="login.php" class="text-blue-600 underline">Login disini</a>.</p>
    </div>
    <script src="login_password_toggle.js"></script>
</body>
</html>
