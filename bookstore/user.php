<?php
session_start();
require_once "db.php";
include_once "functions.php";

$purchased_books = [];

// Redirect admin users to admin.php
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin.php");
    exit();
}

// Fetch notifications for the logged-in user
$notifications = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Fetch unread notifications
    $stmt_notif = $conn->prepare("SELECT id, message, created_at FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
    $stmt_notif->bind_param("i", $user_id);
    $stmt_notif->execute();
    $result_notif = $stmt_notif->get_result();
    while ($notif = $result_notif->fetch_assoc()) {
        $notifications[] = $notif;
    }
    $stmt_notif->close();

    // Populate purchased_books with book IDs the user has purchased
    $stmt_purchased = $conn->prepare("SELECT book_id FROM transactions WHERE user_id = ?");
    $stmt_purchased->bind_param("i", $user_id);
    $stmt_purchased->execute();
    $result_purchased = $stmt_purchased->get_result();
    $purchased_books = [];
    while ($row = $result_purchased->fetch_assoc()) {
        $purchased_books[] = $row['book_id'];
    }
    $stmt_purchased->close();
}

$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
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
    <title>Rezky Bookstore - User Dashboard</title>
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
            background-color: rgba(55, 65, 81, 0.9); /* Tailwind slate-700 with opacity */
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .logo {
            font-weight: 700;
            font-size: 1.5rem;
            color: #fbbf24; /* amber-400 */
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
            color: #f59e0b; /* amber-500 */
        }
        .book-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3), 0 4px 6px -2px rgba(118, 75, 162, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }
        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 25px -5px rgba(102, 126, 234, 0.5), 0 10px 10px -5px rgba(118, 75, 162, 0.4);
        }
        .book-image {
            height: 200px;
            object-fit: contain;
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            background: #f3f4f6; /* Tailwind gray-100 */
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
            color: #374151; /* Tailwind gray-700 */
            margin-bottom: 0.25rem;
        }
        .book-author {
            color: #6b7280; /* Tailwind gray-500 */
            margin-bottom: 0.5rem;
        }
        .book-price {
            font-weight: 700;
            color: #10b981; /* Tailwind emerald-500 */
            font-size: 1.125rem;
        }
        .purchase-button {
            background-color: #6366f1; /* Tailwind indigo-500 */
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.3s ease;
            width: 100%;
            margin-top: 1rem;
        }
        .purchase-button:hover {
            background-color: #4f46e5; /* Tailwind indigo-600 */
        }
        /* Modal styles */
        .modal-bg {
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 50;
        }
        .modal-content {
            background-color: white;
            border-radius: 1rem;
            padding: 2rem;
            max-width: 28rem;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        .modal-header {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #374151;
        }
        .modal-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #4b5563;
        }
        .modal-input, .modal-textarea, .modal-select {
            width: 100%;
            padding: 0.5rem;
            border-radius: 0.5rem;
            border: 1px solid #d1d5db;
            margin-bottom: 1rem;
            font-size: 1rem;
            color: #374151;
        }
        .modal-textarea {
            resize: vertical;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        .btn-cancel {
            background-color: #e5e7eb;
            color: #374151;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-cancel:hover {
            background-color: #d1d5db;
        }
        .btn-confirm {
            background-color: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn-confirm:hover {
            background-color: #059669;
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
        <div class="logo"> REZKY BOOKSTORE</div>
        <nav>
            <a href="orders.php" class="hover:underline">Cek Pesanan</a>
            <a href="logout.php" class="hover:underline ml-4">Logout</a>
        </nav>
    </header>
    <main class="p-6 max-w-7xl mx-auto">
        <p class="mb-6 text-xl font-semibold text-white">Selamat datang, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        <?php if (isset($_GET['purchase']) && $_GET['purchase'] === 'success'): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded shadow-lg max-w-md mx-auto text-center">
                <h3 class="text-lg font-semibold mb-2">Transaksi Berhasil!</h3>
                <p>Terima kasih atas pembelian Anda.</p>
            </div>
        <?php elseif (isset($_GET['purchase']) && $_GET['purchase'] === 'already'): ?>
            <!-- Removed the warning message about already purchased book to allow multiple purchases -->
            <!-- No message displayed -->
        <?php elseif (isset($_GET['purchase']) && $_GET['purchase'] === 'error'): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded shadow-lg max-w-md mx-auto text-center">
                <h3 class="text-lg font-semibold mb-2">Kesalahan</h3>
                <p>Terjadi kesalahan saat memproses transaksi. Silakan coba lagi.</p>
            </div>
        <?php endif; ?>
        <h2 class="text-3xl font-bold mb-6 text-white border-b-4 border-yellow-400 pb-2">Books List</h2>

        <form method="GET" action="user.php" class="mb-6 flex flex-col sm:flex-row gap-4 max-w-md">
            <input type="text" name="search" placeholder="Search books by title or author" value="<?php echo htmlspecialchars($search); ?>" class="border border-gray-300 rounded px-4 py-2 flex-grow" />
            <button type="submit" class="bg-yellow-400 text-gray-900 px-6 py-3 rounded-lg hover:bg-yellow-500 transition font-semibold">Search</button>
        </form>

        <?php if ($result && $result->num_rows > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-8">
            <?php while ($row = $result->fetch_assoc()): ?>
            <div class="book-card">
                <img src="<?php echo htmlspecialchars($row['image']); ?>" alt="<?php echo htmlspecialchars($row['title']); ?>" class="book-image" />
                <div class="book-info">
                    <div>
                        <h3 class="book-title"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p class="book-author">Author: <?php echo htmlspecialchars($row['author']); ?></p>
                        <p class="book-price">Rp<?php echo rtrim(rtrim(number_format($row['price'], 2), '0'), '.'); ?></p>
                    </div>
<button type="button" class="purchase-button mt-4 open-purchase-modal" data-book-id="<?php echo $row['id']; ?>">Beli</button>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <p class="text-center py-8 text-gray-300 font-semibold">No books found.</p>
        <?php endif; ?>
</main>

<!-- Purchase Modal -->
<div id="purchase-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="modal-content relative">
        <button id="close-modal" class="absolute top-2 right-2 text-gray-600 hover:text-gray-900 text-2xl font-bold">&times;</button>
        <h3 class="modal-header">Form Pembelian</h3>
        <form method="POST" action="transaction.php" id="modal-purchase-form">
            <input type="hidden" name="purchase_book_id" id="modal-purchase-book-id" value="" />
            <label for="modal-address" class="modal-label">Alamat</label>
            <textarea name="address" id="modal-address" class="modal-textarea" rows="3" required></textarea>

            <label for="modal-payment-method" class="modal-label">Metode Pembayaran</label>
            <select name="payment_method" id="modal-payment-method" class="modal-select" required>
                <option value="credit_card">Kartu Kredit</option>
                <option value="bank_transfer">Transfer Bank</option>
                <option value="cash_on_delivery">Bayar di Tempat</option>
            </select>

            <label for="modal-phone" class="modal-label">Nomor WhatsApp</label>
            <input type="text" name="phone" id="modal-phone" class="modal-input" placeholder="08123456789" required />

            <label for="modal-quantity" class="modal-label">Jumlah Beli</label>
            <input type="number" name="quantity" id="modal-quantity" min="1" class="modal-input" value="1" required />

            <button type="submit" class="purchase-button mt-4 w-full">Submit</button>
        </form>
    </div>
</div>

<footer>
    &copy; <?php echo date('Y'); ?> Rezky Bookstore. All rights reserved.
</footer>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const purchaseModal = document.getElementById('purchase-modal');
    const openModalButtons = document.querySelectorAll('.open-purchase-modal');
    const closeModalButton = document.getElementById('close-modal');
    const modalPurchaseForm = document.getElementById('modal-purchase-form');

    openModalButtons.forEach(button => {
        button.addEventListener('click', () => {
            const bookId = button.getAttribute('data-book-id');
            document.getElementById('modal-purchase-book-id').value = bookId;
            purchaseModal.classList.remove('hidden');
        });
    });

    closeModalButton.addEventListener('click', () => {
        purchaseModal.classList.add('hidden');
        modalPurchaseForm.reset();
    });

    modalPurchaseForm.addEventListener('submit', function(event) {
        const addressInput = this.querySelector('textarea[name="address"]');
        const paymentSelect = this.querySelector('select[name="payment_method"]');
        const whatsappInput = this.querySelector('input[name="phone"]');
        const quantityInput = this.querySelector('input[name="quantity"]');

        const address = addressInput.value.trim();
        const phone = whatsappInput.value.trim();
        const quantity = quantityInput ? quantityInput.value : '1';

        if (!address) {
            alert('Alamat harus diisi.');
            event.preventDefault();
            return;
        }
        if (!phone) {
            alert('Nomor WhatsApp harus diisi.');
            event.preventDefault();
            return;
        }
        if (!quantity || isNaN(quantity) || quantity <= 0) {
            alert('Jumlah beli harus diisi dengan angka lebih dari 0.');
            event.preventDefault();
            return;
        }

        if (!confirm('Apakah Anda yakin ingin melakukan pembelian ini?')) {
            event.preventDefault();
            return;
        }

        // Disable purchase button and change text
        const submitButton = this.querySelector('button.purchase-button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Memproses...';
        }
    });
});
</script>
</body>
</html>
</script>
</body>
</html>
