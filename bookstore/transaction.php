<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['purchase_book_id'])) {
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $book_id = intval($_POST['purchase_book_id']);
        $purchase_date = date('Y-m-d H:i:s');

        // Remove check for previous purchase to allow multiple purchases of the same book
        // $stmt_check = $conn->prepare("SELECT COUNT(*) FROM transactions WHERE user_id = ? AND book_id = ?");
        // $stmt_check->bind_param("ii", $user_id, $book_id);
        // $stmt_check->execute();
        // $stmt_check->bind_result($count);
        // $stmt_check->fetch();
        // $stmt_check->close();

        // if ($count > 0) {
        //     // Already purchased
        //     header("Location: user.php?purchase=already");
        //     exit();
        // }

        // Insert transaction record with address, payment_method, and phone
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';

        $status = 'Pending';
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, book_id, purchase_date, address, payment_method, status, quantity, whatsapp_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }
        $stmt->bind_param("iissssis", $user_id, $book_id, $purchase_date, $address, $payment_method, $status, $quantity, $phone);
        if ($stmt->execute()) {
            $stmt->close();
            header("Location: user.php?purchase=success");
            exit();
        } else {
            $stmt->close();
            header("Location: user.php?purchase=error");
            exit();
        }
    } else {
        // User not logged in properly, redirect to login
        header("Location: login.php");
        exit();
    }
} else {
    // Invalid request method or missing data
    header("Location: user.php");
    exit();
}
?>
