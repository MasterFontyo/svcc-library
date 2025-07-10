<?php
include '../includes/header.php';
include '../includes/db.php';

// --- Sorting ---
$allowed_sorts = ['title', 'author', 'publisher', 'year_published', 'available_copies', 'total_copies', 'isbn', 'copyright'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sorts) ? $_GET['sort'] : 'title';
$order = (isset($_GET['order']) && $_GET['order'] === 'desc') ? 'desc' : 'asc';

// --- Pagination ---
$results_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start_from = ($page - 1) * $results_per_page;

// --- Search ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = "%" . $conn->real_escape_string($search) . "%";

// --- Count total results ---
$count_sql = "SELECT COUNT(*) FROM books WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?";
$stmt = $conn->prepare($count_sql);
$stmt->bind_param("sss", $filter, $filter, $filter);
$stmt->execute();
$stmt->bind_result($total_results);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_results / $results_per_page);

// --- Fetch paginated, sorted results ---
$sql = "SELECT book_id, isbn, copyright, ctrl_number, title, author, publisher, year_published, status, total_copies, available_copies 
        FROM books 
        WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?
        ORDER BY $sort $order
        LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $filter, $filter, $filter, $start_from, $results_per_page);
$stmt->execute();
$result = $stmt->get_result();

// Helper for toggling order
function toggleOrder($currentOrder) {
    return $currentOrder === 'asc' ? 'desc' : 'asc';
}
?>

<style>
    th a,
    th a:visited,
    th a:active {
        color: rgb(255, 255, 255) !important;
        text-decoration: none;
        cursor: pointer;
        padding: 0.5em 0.2em;
        border-radius: 2px;
        font-weight: bold;
    }
    th a:hover {
        background: #f5eaea !important;
        color: #a83232;
    }
</style>

<h1>Books Inventory</h1>

<div style="margin-bottom: 1rem;">
  <form method="get" action="" style="display: flex; gap: 10px;">
    <input type="text" name="search" placeholder="Search by Title, Author, or ISBN" value="<?= htmlspecialchars($search) ?>" style="flex: 1;" />
    <button type="submit" class="btn">Search</button>
    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addBookModal').style.display='block'">Add Book</button>
  </form>
</div>

<p><strong><?= $total_results ?></strong> results found.</p>

<table>
    <thead>
        <tr>
            <th><a href="?sort=isbn&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">ISBN</a></th>
            <th>Control Number</th>
            <th><a href="?sort=title&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Title</a></th>
            <th><a href="?sort=author&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Author</a></th>
            <th><a href="?sort=publisher&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Publisher</a></th>
            <th><a href="?sort=year_published&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Year</a></th>
            <th><a href="?sort=copyright&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Copyright</a></th>
            <th>
                <a href="?sort=available_copies&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Available</a> /
                <a href="?sort=total_copies&order=<?= toggleOrder($order) ?>&search=<?= urlencode($search) ?>">Total</a>
            </th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['isbn']) ?></td>
            <td><?= htmlspecialchars($row['ctrl_number']) ?></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['author']) ?></td>
            <td><?= htmlspecialchars($row['publisher']) ?></td>
            <td><?= htmlspecialchars($row['year_published']) ?></td>
            <td><?= htmlspecialchars($row['copyright']) ?></td>
            <td><?= $row['available_copies'] ?> / <?= $row['total_copies'] ?></td>
            <td>
                <?php
                    if ($row['available_copies'] == 0) {
                        echo "<span style='color:red;'>Out of Stock</span>";
                    } else {
                        echo "<span style='color:green;'>Available</span>";
                    }
                ?>
            </td>
            <td style="display: flex; gap: 5px;">
                <button class="btn btn-secondary" onclick='openEditBookModal(<?= json_encode($row) ?>)'>Edit</button>
                <a href="book_profile.php?book_id=<?= $row['book_id'] ?>" class="btn btn-primary">View Profile</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<div class="pagination">
  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <?php if ($i == $page): ?>
      <span class="active"><?= $i ?></span>
    <?php else: ?>
      <a href="?page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</div>

<!-- Add Book Modal -->
<div id="addBookModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" id="closeAddBookModalBtn" style="cursor:pointer;">&times;</span>
    <h2>Add Book</h2>
    <form method="post" id="addBookForm" autocomplete="off">
      <div class="form-group">
        <label for="book_isbn">ISBN</label>
        <input type="text" name="book_isbn" id="book_isbn">
      </div>
      <div class="form-group">
        <label for="book_ctrl_number">Control Number</label>
        <input type="text" name="book_ctrl_number" id="book_ctrl_number">
      </div>
      <div class="form-group">
        <label for="book_title">Title *</label>
        <input type="text" name="book_title" id="book_title" required>
      </div>
      <div class="form-group">
        <label for="book_author">Author</label>
        <input type="text" name="book_author" id="book_author">
      </div>
      <div class="form-group">
        <label for="book_publisher">Publisher</label>
        <input type="text" name="book_publisher" id="book_publisher">
      </div>
      <div class="form-group">
        <label for="book_year">Year Published</label>
        <input type="number" name="book_year" id="book_year" min="1000" max="9999">
      </div>
      <div class="form-group">
        <label for="book_copyright">Copyright Year</label>
        <input type="number" name="book_copyright" id="book_copyright" min="1000" max="9999">
      </div>
      <div class="form-group">
        <label for="book_total">Total Copies</label>
        <input type="number" name="book_total" id="book_total" min="1" value="1" required>
      </div>
      <div class="form-group">
        <label for="book_available">Available Copies</label>
        <input type="number" name="book_available" id="book_available" min="0" value="1" required>
      </div>
      <button type="submit" class="btn-primary">Add Book</button>
    </form>
  </div>
</div>

<!-- Edit Book Modal -->
<div id="editBookModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" id="closeEditBookModalBtn" style="cursor:pointer;">&times;</span>
    <h2>Edit Book</h2>
    <form method="post" id="editBookForm" autocomplete="off">
      <input type="hidden" name="edit_book_id" id="edit_book_id">
      <div class="form-group">
        <label for="edit_book_isbn">ISBN</label>
        <input type="text" name="edit_book_isbn" id="edit_book_isbn">
      </div>
      <div class="form-group">
        <label for="edit_book_ctrl_number">Control Number</label>
        <input type="text" name="edit_book_ctrl_number" id="edit_book_ctrl_number">
      </div>
      <div class="form-group">
        <label for="edit_book_title">Title *</label>
        <input type="text" name="edit_book_title" id="edit_book_title" required>
      </div>
      <div class="form-group">
        <label for="edit_book_author">Author</label>
        <input type="text" name="edit_book_author" id="edit_book_author">
      </div>
      <div class="form-group">
        <label for="edit_book_publisher">Publisher</label>
        <input type="text" name="edit_book_publisher" id="edit_book_publisher">
      </div>
      <div class="form-group">
        <label for="edit_book_year">Year Published</label>
        <input type="number" name="edit_book_year" id="edit_book_year" min="1000" max="9999">
      </div>
      <div class="form-group">
        <label for="edit_book_copyright">Copyright Year</label>
        <input type="number" name="edit_book_copyright" id="edit_book_copyright" min="1000" max="9999">
      </div>
      <div class="form-group">
        <label for="edit_book_total">Total Copies</label>
        <input type="number" name="edit_book_total" id="edit_book_total" min="1" required>
      </div>
      <div class="form-group">
        <label for="edit_book_available">Available Copies</label>
        <input type="number" name="edit_book_available" id="edit_book_available" min="0" required>
      </div>
      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Close Add Book Modal
  var closeAddBtn = document.getElementById('closeAddBookModalBtn');
  if (closeAddBtn) {
    closeAddBtn.onclick = function() {
      document.getElementById('addBookModal').style.display = 'none';
    };
  }

  // Close Edit Book Modal
  var closeEditBtn = document.getElementById('closeEditBookModalBtn');
  if (closeEditBtn) {
    closeEditBtn.onclick = function() {
      document.getElementById('editBookModal').style.display = 'none';
    };
  }

  // Close modal when clicking outside modal content
  window.onclick = function(event) {
    var editModal = document.getElementById('editBookModal');
    if (event.target == editModal) {
      editModal.style.display = 'none';
    }
    var addModal = document.getElementById('addBookModal');
    if (event.target == addModal) {
      addModal.style.display = 'none';
    }
  };
});

// Show Edit Book Modal and populate fields
function openEditBookModal(book) {
  document.getElementById('editBookModal').style.display = 'block';
  document.getElementById('edit_book_id').value = book.book_id;
  document.getElementById('edit_book_isbn').value = book.isbn || '';
  document.getElementById('edit_book_ctrl_number').value = book.ctrl_number || '';
  document.getElementById('edit_book_title').value = book.title;
  document.getElementById('edit_book_author').value = book.author || '';
  document.getElementById('edit_book_publisher').value = book.publisher || '';
  document.getElementById('edit_book_year').value = book.year_published || '';
  document.getElementById('edit_book_copyright').value = book.copyright || '';
  document.getElementById('edit_book_total').value = book.total_copies;
  document.getElementById('edit_book_available').value = book.available_copies;
}
</script>

<?php
// Handle Add Book POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_title']) && !isset($_POST['edit_book_id'])) {
  $isbn = trim($_POST['book_isbn']);
  $ctrl_number = trim($_POST['book_ctrl_number']);
  $title = trim($_POST['book_title']);
  $author = trim($_POST['book_author']);
  $publisher = trim($_POST['book_publisher']);
  $year = !empty($_POST['book_year']) ? intval($_POST['book_year']) : null;
  $copyright = !empty($_POST['book_copyright']) ? intval($_POST['book_copyright']) : null;
  $total = intval($_POST['book_total']);
  $available = intval($_POST['book_available']);
  $status = ($available > 0) ? 'Available' : 'Borrowed';

  $stmt = $conn->prepare("INSERT INTO books (isbn, ctrl_number, title, author, publisher, year_published, copyright, status, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->bind_param("sssssiisii", $isbn, $ctrl_number, $title, $author, $publisher, $year, $copyright, $status, $total, $available);
  if ($stmt->execute()) {
    echo "<script>window.location.href=window.location.pathname+'?book_added=1';</script>";
  } else {
    echo "<p style='color:red;'>Error adding book.</p>";
  }
  $stmt->close();
}

// Handle Edit Book POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book_id'])) {
  $book_id = intval($_POST['edit_book_id']);
  $isbn = trim($_POST['edit_book_isbn']);
  $ctrl_number = trim($_POST['edit_book_ctrl_number']);
  $title = trim($_POST['edit_book_title']);
  $author = trim($_POST['edit_book_author']);
  $publisher = trim($_POST['edit_book_publisher']);
  $year = !empty($_POST['edit_book_year']) ? intval($_POST['edit_book_year']) : null;
  $copyright = !empty($_POST['edit_book_copyright']) ? intval($_POST['edit_book_copyright']) : null;
  $total = intval($_POST['edit_book_total']);
  $available = intval($_POST['edit_book_available']);
  $status = ($available > 0) ? 'Available' : 'Borrowed';

  $stmt = $conn->prepare("UPDATE books SET isbn=?, ctrl_number=?, title=?, author=?, publisher=?, year_published=?, copyright=?, status=?, total_copies=?, available_copies=? WHERE book_id=?");
  $stmt->bind_param("sssssiisiii", $isbn, $ctrl_number, $title, $author, $publisher, $year, $copyright, $status, $total, $available, $book_id);
  if ($stmt->execute()) {
    echo "<script>window.location.href=window.location.pathname+'?book_updated=1';</script>";
    exit;
  } else {
    echo "<p style='color:red;'>Error updating book.</p>";
  }
  $stmt->close();
}
?>

<?php include '../includes/footer.php'; ?>
