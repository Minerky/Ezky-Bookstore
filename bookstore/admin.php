

<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "db.php";
include_once "functions.php";

if (!is_logged_in() || !is_admin()) {
    header("Location: login.php");
    exit();
}



// Handle create, update, delete operations
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Create new book
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $title = trim($_POST['title']);
        $image = trim($_POST['image']);
        $author = trim($_POST['author']);
        $price = floatval($_POST['price']);

        if ($title && $author && $price > 0) {
            $stmt = $conn->prepare("INSERT INTO books (title, image, author, price) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssd", $title, $image, $author, $price);
            $stmt->execute();
            $stmt->close();
            header("Location: admin.php");
            exit();
        }
    }

    // Clear sales report notifications and delete all transactions
    if (isset($_POST['clear_report']) && $_POST['clear_report'] == '1') {
        // Delete all records from transactions table
        $conn->query("DELETE FROM transactions");
        unset($_SESSION['admin_notifications']);
        header("Location: admin.php");
        exit();
    }

    // Update book
    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        $id = intval($_POST['id']);
        $title = trim($_POST['title']);
        $image = trim($_POST['image']);
        $author = trim($_POST['author']);
        $price = floatval($_POST['price']);

        if ($id > 0 && $title && $author && $price > 0) {
            $stmt = $conn->prepare("UPDATE books SET title = ?, image = ?, author = ?, price = ? WHERE id = ?");
            $stmt->bind_param("sssdi", $title, $image, $author, $price, $id);
            $stmt->execute();
            $stmt->close();
            header("Location: admin.php");
            exit();
        }
    }

    // Update order status
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        $transaction_id = intval($_POST['transaction_id']);
        $new_status = trim($_POST['new_status']);
        // Normalize status value: lowercase then ucfirst
        $new_status = ucfirst(strtolower($new_status));

        if ($transaction_id > 0 && $new_status) {
            // Update the status in transactions table
            $stmt_update = $conn->prepare("UPDATE transactions SET status = ? WHERE id = ?");
            $stmt_update->bind_param("si", $new_status, $transaction_id);
            $stmt_update->execute();
            $stmt_update->close();

            // Fetch user_id and book title for the transaction
            $stmt_info = $conn->prepare("SELECT user_id, b.title FROM transactions t JOIN books b ON t.book_id = b.id WHERE t.id = ?");
            $stmt_info->bind_param("i", $transaction_id);
            $stmt_info->execute();
            $result_info = $stmt_info->get_result();
            if ($result_info && $result_info->num_rows === 1) {
                $row_info = $result_info->fetch_assoc();
                $user_id = $row_info['user_id'];
                $book_title = $row_info['title'];

                // Prepare notification message
                $message = "Your order for '{$book_title}' has been updated to '{$new_status}'.";

                // Insert notification into notifications table
                $stmt_notif = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $stmt_notif->bind_param("is", $user_id, $message);
                $stmt_notif->execute();
                $stmt_notif->close();
            }
            $stmt_info->close();

            header("Location: admin.php");
            exit();
        }
    }
}

// Handle delete operation via GET
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: admin.php");
        exit();
    }
}

$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// New code to handle edit mode and fetch book data
$edit_mode = false;
$edit_book = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    if ($edit_id > 0) {
        $stmt_edit = $conn->prepare("SELECT * FROM books WHERE id = ?");
        $stmt_edit->bind_param("i", $edit_id);
        $stmt_edit->execute();
        $edit_result = $stmt_edit->get_result();
        if ($edit_result && $edit_result->num_rows === 1) {
            $edit_book = $edit_result->fetch_assoc();
            $edit_mode = true;
        }
        $stmt_edit->close();
    }
}

if ($search !== '') {
    $search_param = '%' . $search . '%';
    $stmt = $conn->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ?");
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT * FROM books";
    $result = $conn->query($sql);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Rezky Bookstore - Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto&display=swap" rel="stylesheet" />
    <style>
        /* Custom styles for modern bookstore look */
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
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            padding: 1.5rem;
            max-width: 28rem;
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
            display: block;
        }
        .form-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            color: #374151;
            transition: border-color 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }
        .btn-submit {
            background-color: #6366f1;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #4f46e5;
        }
        .book-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            height: 400px;
        }
        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(102, 126, 234, 0.5), 0 10px 10px -5px rgba(118, 75, 162, 0.4);
        }
        .book-image {
            width: 100%;
            height: 12rem;
            object-fit: contain;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            margin-bottom: 1rem;
            background: #f3f4f6;
        }
        .book-info {
            padding: 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .book-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .book-author {
            color: #6b7280;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .book-price {
            font-weight: 700;
            color: #10b981;
            font-size: 1.125rem;
        }
        .action-links a {
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .action-links a.edit {
            color: #3b82f6;
        }
        .action-links a.edit:hover {
            color: #2563eb;
        }
        .action-links a.delete {
            color: #ef4444;
        }
        .action-links a.delete:hover {
            color: #dc2626;
        }
        .sales-report {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
            margin-top: 2rem;
        }
        .sales-report h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            border-bottom: 2px solid #fbbf24;
            padding-bottom: 0.5rem;
            color: #374151;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-clear {
            color: #ef4444;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            transition: color 0.3s ease;
        }
        .btn-clear:hover {
            color: #dc2626;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            color: #4b5563;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 0.5rem 0.75rem;
            text-align: left;
        }
        th {
            background-color: #fbbf24;
            color: #1f2937;
        }
        select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            color: #374151;
            transition: border-color 0.3s ease;
        }
        select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
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
    <style>
        /* Custom styles for modern bookstore look */
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
        .form-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            padding: 1.5rem;
            max-width: 28rem;
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
            display: block;
        }
        .form-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            color: #374151;
            transition: border-color 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
        }
        .btn-submit {
            background-color: #6366f1;
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.3s ease;
        }
        .btn-submit:hover {
            background-color: #4f46e5;
        }
        .book-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            height: 400px;
        }
        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(102, 126, 234, 0.5), 0 10px 10px -5px rgba(118, 75, 162, 0.4);
        }
        .book-image {
            width: 100%;
            height: 12rem;
            object-fit: contain;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            margin-bottom: 1rem;
            background: #f3f4f6;
        }
        .book-info {
            padding: 1rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .book-title {
            font-size: 1.125rem;
            font-weight: 700;
            color: #374151;
            margin-bottom: 0.25rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .book-author {
            color: #6b7280;
            margin-bottom: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .book-price {
            font-weight: 700;
            color: #10b981;
            font-size: 1.125rem;
        }
        .action-links a {
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .action-links a.edit {
            color: #3b82f6;
        }
        .action-links a.edit:hover {
            color: #2563eb;
        }
        .action-links a.delete {
            color: #ef4444;
        }
        .action-links a.delete:hover {
            color: #dc2626;
        }
        .sales-report {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            padding: 1.5rem;
            max-height: 600px;
            overflow-y: auto;
            margin-top: 2rem;
        }
        .sales-report h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            border-bottom: 2px solid #fbbf24;
            padding-bottom: 0.5rem;
            color: #374151;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-clear {
            color: #ef4444;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            transition: color 0.3s ease;
        }
        .btn-clear:hover {
            color: #dc2626;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            color: #4b5563;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 0.5rem 0.75rem;
            text-align: left;
        }
        th {
            background-color: #fbbf24;
            color: #1f2937;
        }
        select {
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            color: #374151;
            transition: border-color 0.3s ease;
        }
        select:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.3);
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
    <script>
        // Add hover effect on table rows
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', () => {
                    row.style.backgroundColor = '#fef3c7'; // Tailwind amber-100
                });
                row.addEventListener('mouseleave', () => {
                    row.style.backgroundColor = '';
                });
            });
        });
    </script>
</head>
<body>
    <header class="flex justify-between items-center p-4 shadow-lg">
        <div class="logo">REZKY BOOKSTORE</div>
        <nav>
            <a href="logout.php" class="hover:underline ml-4">Logout</a>
        </nav>
    </header>
    <main class="p-6 max-w-7xl mx-auto">
        <p class="mb-6 text-xl font-semibold text-white">Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <h2 class="text-3xl font-bold mb-6 text-white border-b-4 border-yellow-400 pb-2">Books List</h2>

        <form method="GET" action="admin.php" class="mb-6 flex flex-col sm:flex-row gap-4 max-w-md">
            <input type="text" name="search" placeholder="Search books by title or author" value="<?php echo htmlspecialchars($search); ?>" class="form-input flex-grow" />
            <button type="submit" class="bg-yellow-400 text-gray-900 px-6 py-3 rounded-lg hover:bg-yellow-500 transition font-semibold">Search</button>
        </form>

        <div class="flex gap-8 mb-8">
            <!-- Book input form -->
            <div class="form-container flex-shrink-0">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'create'; ?>" />
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_book['id']); ?>" />
                    <?php endif; ?>
                    <label for="title" class="form-label">Title</label>
                    <input type="text" id="title" name="title" required class="form-input" value="<?php echo $edit_mode ? htmlspecialchars($edit_book['title']) : ''; ?>" />
                    <label for="image" class="form-label">Image URL</label>
                    <input type="text" id="image" name="image" class="form-input" value="<?php echo $edit_mode ? htmlspecialchars($edit_book['image']) : ''; ?>" />
                    <label for="author" class="form-label">Author</label>
                    <input type="text" id="author" name="author" required class="form-input" value="<?php echo $edit_mode ? htmlspecialchars($edit_book['author']) : ''; ?>" />
                    <label for="price" class="form-label">Price</label>
                    <input type="number" step="0.01" id="price" name="price" required class="form-input" value="<?php echo $edit_mode ? htmlspecialchars($edit_book['price']) : ''; ?>" />
                    <button type="submit" class="btn-submit"><?php echo $edit_mode ? 'Update Book' : 'Add Book'; ?></button>
                </form>
            </div>
            <!-- Book list -->
            <div class="flex-grow overflow-auto max-h-[600px]">
                <?php if ($result && $result->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-8">
                    <?php while ($row = $result->fetch_assoc()) { ?>
                    <div class="book-card">
                        <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" class="book-image" />
                        <div class="book-info">
                            <div>
                                <h3 class="book-title" title="<?php echo htmlspecialchars($row['title']); ?>"><?php echo htmlspecialchars($row['title']); ?></h3>
                                <p class="book-author" title="<?php echo htmlspecialchars($row['author']); ?>">Author: <?php echo htmlspecialchars($row['author']); ?></p>
<p class="book-price">Rp<?php echo rtrim(rtrim(number_format($row['price'], 2), '0'), '.'); ?></p>
                            </div>
                            <div class="mt-4 flex justify-between action-links">
                                <a href="admin.php?edit=<?php echo $row['id']; ?>" class="edit">Edit</a>
                                <a href="admin.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this book?');" class="delete">Delete</a>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <?php else: ?>
                <p class="text-center py-8 text-gray-500 font-semibold">No books found.</p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Sales report -->
        <div class="sales-report">
            <h2>
                Laporan Penjualan Buku
                <form method="POST" action="admin.php" onsubmit="return confirm('Yakin ingin menghapus laporan penjualan?');" class="inline">
                    <input type="hidden" name="clear_report" value="1" />
                    <button type="submit" class="btn-clear">Clear</button>
                </form>
            </h2>
            <form method="GET" action="admin.php" class="mb-4">
                <input type="text" name="sales_search" placeholder="Cari laporan penjualan..." value="<?php echo isset($_GET['sales_search']) ? htmlspecialchars($_GET['sales_search']) : ''; ?>" class="form-input" />
                <button type="submit" class="btn-submit mt-2">Cari</button>
            </form>
            <?php
            $sales_search = '';
            if (isset($_GET['sales_search'])) {
                $sales_search = trim($_GET['sales_search']);
            }
            if ($sales_search !== '') {
                $search_param = '%' . $sales_search . '%';
                $sales_sql = "SELECT t.id, t.purchase_date, u.username, b.title, t.status, t.quantity, t.address, t.whatsapp_number FROM transactions t
                              JOIN users u ON t.user_id = u.id
                              JOIN books b ON t.book_id = b.id
                              WHERE u.username LIKE ? OR b.title LIKE ? OR t.purchase_date LIKE ?
                              ORDER BY t.purchase_date DESC";
                $stmt_sales = $conn->prepare($sales_sql);
                $stmt_sales->bind_param("sss", $search_param, $search_param, $search_param);
                $stmt_sales->execute();
                $sales_result = $stmt_sales->get_result();
            } else {
                $sales_sql = "SELECT t.id, t.purchase_date, u.username, b.title, t.status, t.quantity, t.address, t.whatsapp_number FROM transactions t
                              JOIN users u ON t.user_id = u.id
                              JOIN books b ON t.book_id = b.id
                              ORDER BY t.purchase_date DESC";
                $sales_result = $conn->query($sales_sql);
            }
            ?>
            <?php if ($sales_result && $sales_result->num_rows > 0): ?>
                <p class="text-sm text-gray-600 mb-2">Total records: <?php echo $sales_result->num_rows; ?></p>
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal Pembelian</th>
                            <th>Username</th>
                            <th>Judul Buku</th>
                            <th>Status Pesanan</th>
                            <th>Jumlah Beli</th>
                            <th>Alamat</th>
                            <th>Nomor HP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sale = $sales_result->fetch_assoc()) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sale['purchase_date']); ?></td>
                            <td><?php echo htmlspecialchars($sale['username']); ?></td>
                            <td><?php echo htmlspecialchars($sale['title']); ?></td>
                            <td>
                                <form method="POST" action="admin.php" class="inline">
                                    <input type="hidden" name="action" value="update_status" />
                                    <input type="hidden" name="transaction_id" value="<?php echo htmlspecialchars($sale['id']); ?>" />
                                    <select name="new_status" onchange="this.form.submit()">
                                        <?php
                                        $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                                        foreach ($statuses as $status_option) {
                                            $selected = ($sale['status'] === $status_option) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($status_option) . "\" $selected>" . htmlspecialchars($status_option) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </form>
                            </td>
                            <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($sale['address']); ?></td>
                            <td><?php echo htmlspecialchars($sale['whatsapp_number']); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500">Belum ada laporan penjualan.</p>
            <?php endif; ?>
        </div>
    </main>
    <footer>
        &copy; <?php echo date('Y'); ?> Rezky Bookstore. All rights reserved.
    </footer>
</body>
