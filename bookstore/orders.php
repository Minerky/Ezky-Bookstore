<?php
session_start();
require_once "db.php";
include_once "functions.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_order_title'])) {
        $title_to_confirm = trim($_POST['confirm_order_title']);

        if ($title_to_confirm) {
            // Get book id by title
            $stmt_book = $conn->prepare("SELECT id FROM books WHERE title = ?");
            $stmt_book->bind_param("s", $title_to_confirm);
            $stmt_book->execute();
            $result_book = $stmt_book->get_result();

            if ($result_book && $result_book->num_rows === 1) {
                $book = $result_book->fetch_assoc();
                $book_id = $book['id'];

                // Update transaction status to 'confirmed' for this user and book
                $stmt_update = $conn->prepare("UPDATE transactions SET status = 'confirmed' WHERE user_id = ? AND book_id = ?");
                $stmt_update->bind_param("ii", $user_id, $book_id);
                $stmt_update->execute();
                $stmt_update->close();

                // Delete transaction immediately after confirmation
                $stmt_delete = $conn->prepare("DELETE FROM transactions WHERE user_id = ? AND book_id = ?");
                $stmt_delete->bind_param("ii", $user_id, $book_id);
                $stmt_delete->execute();
                $stmt_delete->close();
            }
            $stmt_book->close();
        }
    }
    // Redirect to avoid form resubmission
    header("Location: orders.php");
    exit();
}

// Fetch orders for the logged-in user
$stmt_orders = $conn->prepare("
    SELECT b.title, b.image, t.quantity, t.status, t.purchase_date
    FROM transactions t
    JOIN books b ON t.book_id = b.id
    WHERE t.user_id = ?
    ORDER BY t.purchase_date DESC
");
$stmt_orders->bind_param("i", $user_id);
$stmt_orders->execute();
$result_orders = $stmt_orders->get_result();


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pesanan Saya - Rezky Bookstore</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #1a202c;
        }
        header {
            background-color: rgba(55, 65, 81, 0.9);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: #fbbf24;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        nav a {
            color: #fbbf24;
            font-weight: 600;
            margin-left: 1rem;
            transition: color 0.3s ease;
        }
        nav a:hover {
            color: #f59e0b;
        }
        .order-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3),
                        0 4px 6px -2px rgba(118, 75, 162, 0.2);
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            transition: background-color 0.3s ease;
            cursor: default;
        }
        .order-card:hover {
            background-color: #fef3c7; /* Tailwind amber-100 */
        }
        .order-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            border-radius: 0.5rem;
            background: #f3f4f6;
        }
        .order-info {
            flex-grow: 1;
        }
        .order-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.25rem;
        }
        .order-status {
            font-weight: 600;
            color: #10b981;
        }
        .order-date {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .btn-confirm-receipt {
            background-color: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .btn-confirm-receipt:hover {
            background-color: #dc2626;
        }
        footer {
            background-color: rgba(55, 65, 81, 0.9);
            color: #fbbf24;
            text-align: center;
            padding: 1rem;
            margin-top: 3rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <header class="flex justify-between items-center p-4 shadow-lg">
        <div class="logo">REZKY BOOKSTORE</div>
        <nav>
            <a href="user.php" class="hover:underline">Beranda</a>
            <a href="logout.php" class="hover:underline ml-4">Logout</a>
        </nav>
    </header>
    <main class="p-6 max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-6 text-white border-b-4 border-yellow-400 pb-2">Pesanan Saya</h1>
        <?php if ($result_orders && $result_orders->num_rows > 0): ?>
            <?php while ($order = $result_orders->fetch_assoc()): ?>
                <div class="order-card">
                    <img src="<?php echo htmlspecialchars($order['image']); ?>" alt="<?php echo htmlspecialchars($order['title']); ?>" class="order-image" />
                    <div class="order-info">
                        <div class="order-title"><?php echo htmlspecialchars($order['title']); ?></div>
                        <div class="order-status">Status: <?php echo htmlspecialchars($order['status']); ?></div>
                        <div class="order-date">Tanggal Pesanan: <?php echo htmlspecialchars($order['purchase_date']); ?></div>
                    <div>Jumlah: <?php echo htmlspecialchars($order['quantity']); ?></div>
                    <?php if (strtolower($order['status']) === 'delivered'): ?>
<form method="POST" action="orders.php" class="mt-2">
    <input type="hidden" name="confirm_order_title" value="<?php echo htmlspecialchars($order['title']); ?>" />
    <button type="submit" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700" onclick="return confirm('Konfirmasi terima buku ini? Pesanan akan langsung dihapus setelah konfirmasi.');">Konfirmasi Terima</button>
</form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endwhile; ?>
        <?php else: ?>
            <p class="text-white text-center py-8">Anda belum melakukan pemesanan.</p>
        <?php endif; ?>
    </main>
    <footer>
        &copy; <?php echo date('Y'); ?> Rezky Bookstore. All rights reserved.
    </footer>
</body>
</html>
<?php
$stmt_orders->close();
?>
