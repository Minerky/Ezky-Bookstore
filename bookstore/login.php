<?php
session_start();
require_once "db.php";
require_once "functions.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = sanitize_input($_POST["username"]);
    $password = $_POST["password"];

    // Query untuk mencari user berdasarkan username
    $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $stored_password = $user["password"];

        // Compare password as plaintext
        if ($password === $stored_password) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            // Redirect berdasarkan role
            if ($user["role"] === "admin") {
                header("Location: admin.php");
                exit();
            } elseif ($user["role"] === "user") {
                header("Location: user.php");
                exit();
            } else {
                header("Location: login.php");
                exit();
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login - Bookstore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
</head>
<body class="relative font-['Roboto'] flex items-center justify-center min-h-screen bg-gradient-to-br from-indigo-100 via-purple-100 to-pink-100">
    <div class="relative bg-white p-8 rounded-xl shadow-lg w-full max-w-md hover:shadow-2xl transition-shadow duration-300 z-10">
        <h1 class="text-3xl font-extrabold mb-6 text-center text-gray-900">Login to Rezky Bookstore</h1>
        <?php if ($error): ?>
            <div class="bg-red-200 border border-red-400 text-red-800 p-4 rounded mb-6 font-semibold shadow-md transition duration-300 ease-in-out"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php" class="space-y-6">
            <div>
                <label for="username" class="block mb-2 font-semibold text-gray-800">Username</label>
                <input type="text" id="username" name="username" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-4 focus:ring-indigo-500 focus:border-indigo-500 transition" />
            </div>
            <div>
                <label for="password" class="block mb-2 font-semibold text-gray-800">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-4 focus:ring-indigo-500 focus:border-indigo-500 transition" />
                    <button type="button" id="togglePassword" class="absolute right-3 top-3 text-gray-600 hover:text-gray-900 focus:outline-none transition">Show</button>
                </div>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-3 rounded-lg hover:bg-indigo-700 transition duration-300 font-semibold shadow-md hover:shadow-lg">Login</button>
        </form>
        <p class="mt-6 text-center text-gray-600">Belum punya akun? <a href="register.php" class="text-indigo-600 underline hover:text-indigo-800">Daftar disini</a>.</p>
    </div>
    <script src="login_password_toggle.js"></script>
</body>
</html>
