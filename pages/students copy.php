<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../includes/db.php'; // adjust path as needed
include_once('../includes/phpqrcode/qrlib.php'); // Make sure this path is correct

// Initialize search and sorting
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'lastname';
$order = (isset($_GET['order']) && $_GET['order'] === 'desc') ? 'desc' : 'asc';
$allowed_sorts = ['student_id', 'lastname', 'firstname', 'middlename', 'course', 'year_level'];
if (!in_array($sort, $allowed_sorts)) $sort = 'lastname';

// Pagination
$results_per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$start_from = ($page - 1) * $results_per_page;

// Count total results
$count_sql = "SELECT COUNT(*) FROM students WHERE lastname LIKE ? OR firstname LIKE ? OR student_id LIKE ?";
$stmt = $conn->prepare($count_sql);
$search_param = "%$search%";
$stmt->bind_param("sss", $search_param, $search_param, $search_param);
$stmt->execute();
$stmt->bind_result($total_results);
$stmt->fetch();
$stmt->close();

$total_pages = ceil($total_results / $results_per_page);

// Fetch paginated, sorted results
$sql = "SELECT * FROM students WHERE lastname LIKE ? OR firstname LIKE ? OR student_id LIKE ? ORDER BY $sort $order LIMIT ?, ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssii", $search_param, $search_param, $search_param, $start_from, $results_per_page);
$stmt->execute();
$result = $stmt->get_result();

// Helper for toggling order
function toggleOrder($currentOrder) {
    return $currentOrder === 'asc' ? 'desc' : 'asc';
}
?>
<?php require_once '../includes/header.php'; ?>
<head>
    <title>Students List</title>
    <style>
        /* ...existing styles... */
        /* Modal styles */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1000; 
            left: 0; top: 0; width: 100%; height: 100%; 
            overflow: auto; background: rgba(0,0,0,0.4);
        }
        .modal-content {
            background: #fff; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 500px; border-radius: 8px;
        }
        .close {
            color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;
        }
        .close:hover { color: #000; }
        .form-group { margin-bottom: 10px; }
        .form-group label { display: block; margin-bottom: 3px; }
        .form-group input, .form-group select { width: 100%; padding: 6px; }
        .btn-primary { background: #007bff; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; }
        .btn-primary:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h2>Students</h2>
    <button id="openModalBtn" class="btn-primary" style="margin-bottom:10px;">Add</button>
    <form method="get" style="margin-bottom:10px;">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or ID">
        <button type="submit">Search</button>
    </form>
    <p><strong><?php echo $total_results; ?></strong> results found.</p>
    <table>
        <tr>
            <th><a href="?sort=student_id&order=<?php echo toggleOrder($order); ?>&search=<?php echo urlencode($search); ?>">ID</a></th>
            <th><a href="?sort=lastname&order=<?php echo toggleOrder($order); ?>&search=<?php echo urlencode($search); ?>">Last Name</a></th>
            <th><a href="?sort=firstname&order=<?php echo toggleOrder($order); ?>&search=<?php echo urlencode($search); ?>">First Name</a></th>
            <th><a href="?sort=middlename&order=<?php echo toggleOrder($order); ?>&search=<?php echo urlencode($search); ?>">Middle Name</a></th>
            <th>Type</th>
            <th>Details</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['student_id']); ?></td>
            <td><?php echo htmlspecialchars($row['lastname']); ?></td>
            <td><?php echo htmlspecialchars($row['firstname']); ?></td>
            <td><?php echo htmlspecialchars($row['middlename']); ?></td>
            <td><?php echo htmlspecialchars($row['type']); ?></td>
            <td>
                <?php
                switch ($row['type']) {
                    case 'Admin':
                        echo 'Department: ' . htmlspecialchars($row['department']);
                        break;
                    case 'Faculty':
                        echo 'Program: ' . htmlspecialchars($row['program']);
                        break;
                    case 'College':
                        echo 'Course: ' . htmlspecialchars($row['course']) . '<br>Year Level: ' . htmlspecialchars($row['year_level']);
                        break;
                    case 'BasicEd':
                        echo 'Grade Level: ' . htmlspecialchars($row['grade_level']);
                        break;
                    default:
                        echo '-';
                }
                ?>
            </td>
            <td>
                <button class="btn-primary" style="padding:2px 8px;font-size:13px;" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                |
                <button class="btn-secondary" style="padding:2px 8px;font-size:13px;"><a href="student_profile.php?student_id=<?php echo urlencode($row['student_id']); ?>" style="text-decoration:none;color:white;">Profile</a></button>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <div class="pagination" style="margin-top:10px;">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>&order=<?php echo $order; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <span class="close" id="closeModalBtn">&times;</span>
            <h3>Add Student</h3>
            <form method="post" action="">
                <div class="form-group">
                    <label for="student_id">School ID <small>(Format: AY2025-00000)</small></label>
                    <input type="text" name="student_id" id="student_id" pattern="AY\d{4}-\d{5}" title="Format: AY0000-00000" required>
                </div>
                <div class="form-group">
                    <label for="lastname">Last Name</label>
                    <input type="text" name="lastname" id="lastname" required>
                </div>
                <div class="form-group">
                    <label for="firstname">First Name</label>
                    <input type="text" name="firstname" id="firstname" required>
                </div>
                <div class="form-group">
                    <label for="middlename">Middle Name</label>
                    <input type="text" name="middlename" id="middlename">
                </div>
                <div class="form-group">
                    <label for="type"><strong>User Type</strong></label>
                    <select id="type" name="type" required onchange="showFields()" class="form-control">
                        <option value="">-- User Type --</option>
                        <option value="Admin">Admin</option>
                        <option value="Faculty">Faculty</option>
                        <option value="College">College</option>
                        <option value="BasicEd">Basic Education</option>
                    </select>
                </div>
                <div id="admin_fields" style="display:none; margin-top:10px;">
                    <label for="admin_dept">Department</label>
                    <input type="text" name="admin_dept" id="admin_dept" class="form-control">
                </div>
                <div id="faculty_fields" style="display:none; margin-top:10px;">
                    <label for="faculty_dept">Department / Program</label>
                    <input type="text" name="faculty_dept" id="faculty_dept" class="form-control">
                </div>
                <div id="college_fields" style="display:none; margin-top:10px;">
                    <label for="course">Course</label>
                    <select name="course" id="course">
                        <option value="">Select Course</option>
                        <option value="BEED-GEN">BEED-GEN</option>
                        <option value="BSA">BSA</option>
                        <option value="BSAIS">BSAIS</option>
                        <option value="BSBA-MM">BSBA-MM</option>
                        <option value="BSCRIM">BSCRIM</option>
                        <option value="BSED-ENG">BSED-ENG</option>
                        <option value="BSED-FIL">BSED-FIL</option>
                        <option value="BSED-MATH">BSED-MATH</option>
                        <option value="BSHM">BSHM</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSPSY">BSPSY</option>
                        <option value="BSTM">BSTM</option>
                        <option value="BPED">BPED</option>
                    </select>
                    <label for="college_year" style="margin-top:5px;">Year Level</label>
                    <select name="college_year" id="college_year" class="form-control">
                        <option value="">-- Year Level --</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div id="basiced_fields" style="display:none; margin-top:10px;">
                    <label for="grade_level">Grade Level</label>
                    <select name="grade_level" id="grade_level" class="form-control">
                        <option value="">-- Grade Level --</option>
                        <?php for($i=1;$i<=12;$i++): ?>
                            <option value="Grade <?= $i ?>">Grade <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" name="add_student" class="btn-primary">Register</button>
            </form>
            <?php
            // Handle Add Student POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id']) && !isset($_POST['edit_student_id'])) {
                $student_id = trim($_POST['student_id']);
                $type = trim($_POST['type']);
                $lastname = trim($_POST['lastname']);
                $firstname = trim($_POST['firstname']);
                $middlename = trim($_POST['middlename']);
                $department = isset($_POST['admin_dept']) ? trim($_POST['admin_dept']) : (isset($_POST['faculty_dept']) ? trim($_POST['faculty_dept']) : null);
                $program = isset($_POST['faculty_dept']) ? trim($_POST['faculty_dept']) : null;
                $course = isset($_POST['course']) ? trim($_POST['course']) : null;
                $year_level = isset($_POST['college_year']) ? intval($_POST['college_year']) : (isset($_POST['year_level']) ? intval($_POST['year_level']) : null);
                $grade_level = isset($_POST['grade_level']) ? trim($_POST['grade_level']) : null;

                // Validate Student ID format
                if (!preg_match('/^AY\d{4}-\d{5}$/', $student_id)) {
                    echo "<p style='color:red;'>ID must be in the format AY0000-00000.</p>";
                } else {
                    // Check for duplicate student_id
                    $check = $conn->prepare("SELECT student_id FROM students WHERE student_id=?");
                    $check->bind_param("s", $student_id);
                    $check->execute();
                    $check->store_result();
                    if ($check->num_rows > 0) {
                        echo "<p style='color:red;'>ID already exists.</p>";
                    } else {
                        $stmt_add = $conn->prepare("INSERT INTO students (student_id, type, lastname, firstname, middlename, department, program, course, year_level, grade_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_add->bind_param("ssssssssss", $student_id, $type, $lastname, $firstname, $middlename, $department, $program, $course, $year_level, $grade_level);
                        if ($stmt_add->execute()) {
                            // Generate QR code after successful insert
                            $qr_data = $student_id; // Use the student_id as QR data

                            // Ensure directory exists
                            $qr_dir = '../assets/qrcodes/';
                            if (!is_dir($qr_dir)) {
                                mkdir($qr_dir, 0777, true);
                            }

                            // Save as just the filename for DB, but full path for file
                            $qr_filename = $student_id . '.png';
                            $qr_file = $qr_dir . $qr_filename;

                            // Generate QR code
                            QRcode::png($qr_data, $qr_file, QR_ECLEVEL_L, 6);

                            // Save relative path to DB (for web access)
                            $qr_path_db = 'assets/qrcodes/' . $qr_filename;
                            $stmt_addqrpath = $conn->prepare("UPDATE students SET qr_path=? WHERE student_id=?");
                            $stmt_addqrpath->bind_param("ss", $qr_path_db, $student_id);
                            $stmt_addqrpath->execute();
                            $stmt_addqrpath->close();

                            echo "<script>window.location.href=window.location.pathname+'?success=1';</script>";
                        } else {
                            echo "<p style='color:red;'>Error adding student.</p>";
                        }
                        $stmt_add->close();
                    }
                    $check->close();
                }
            }
            ?>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editStudentModal" class="modal">
        <div class="modal-content" style="width:500px;">
            <span class="close" id="closeEditModalBtn">&times;</span>
            <h3>Edit Student</h3>
            <form method="post" id="editStudentForm" autocomplete="off">
                <input type="hidden" name="edit_student_id" id="edit_student_id">
                <div class="form-group">
                    <label for="edit_lastname">Last Name</label>
                    <input type="text" name="lastname" id="edit_lastname" required>
                </div>
                <div class="form-group">
                    <label for="edit_firstname">First Name</label>
                    <input type="text" name="firstname" id="edit_firstname" required>
                </div>
                <div class="form-group">
                    <label for="edit_middlename">Middle Name</label>
                    <input type="text" name="middlename" id="edit_middlename">
                </div>
                <div class="form-group">
                    <label for="edit_course">Course</label>
                    <select name="course" id="edit_course" required>
                        <option value="">Select Course</option>
                        <option value="BEED-GEN">BEED-GEN</option>
                        <option value="BSA">BSA</option>
                        <option value="BSAIS">BSAIS</option>
                        <option value="BSBA-MM">BSBA-MM</option>
                        <option value="BSCRIM">BSCRIM</option>
                        <option value="BSED-ENG">BSED-ENG</option>
                        <option value="BSED-FIL">BSED-FIL</option>
                        <option value="BSED-MATH">BSED-MATH</option>
                        <option value="BSHM">BSHM</option>
                        <option value="BSIT">BSIT</option>
                        <option value="BSPSY">BSPSY</option>
                        <option value="BSTM">BSTM</option>
                        <option value="BPED">BPED</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_year_level">Year Level</label>
                    <select name="year_level" id="edit_year_level" required>
                        <option value="">Select Year</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                        <option value="4">4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_type"><strong>User Type</strong></label>
                    <select id="edit_type" name="edit_type" required onchange="showEditFields()" class="form-control">
                        <option value="">-- User Type --</option>
                        <option value="Admin">Admin</option>
                        <option value="Faculty">Faculty</option>
                        <option value="College">College</option>
                        <option value="BasicEd">Basic Education</option>
                    </select>
                </div>
                <div id="edit_admin_fields" style="display:none; margin-top:10px;">
                    <label for="edit_admin_dept">Department</label>
                    <input type="text" name="edit_admin_dept" id="edit_admin_dept" class="form-control">
                </div>
                <div id="edit_faculty_fields" style="display:none; margin-top:10px;">
                    <label for="edit_faculty_dept">Department / Program</label>
                    <input type="text" name="edit_faculty_dept" id="edit_faculty_dept" class="form-control">
                </div>
                <div id="edit_basiced_fields" style="display:none; margin-top:10px;">
                    <label for="edit_grade_level">Grade Level</label>
                    <select name="edit_grade_level" id="edit_grade_level" class="form-control">
                        <option value="">-- Grade Level --</option>
                        <?php for($i=1;$i<=12;$i++): ?>
                            <option value="Grade <?= $i ?>">Grade <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
            <?php
            // Handle Edit Student POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_student_id'])) {
                $student_id = trim($_POST['edit_student_id']);
                $type = trim($_POST['edit_type']);
                $lastname = trim($_POST['lastname']);
                $firstname = trim($_POST['firstname']);
                $middlename = trim($_POST['middlename']);
                $department = isset($_POST['edit_admin_dept']) ? trim($_POST['edit_admin_dept']) : (isset($_POST['edit_faculty_dept']) ? trim($_POST['edit_faculty_dept']) : null);
                $program = isset($_POST['edit_faculty_dept']) ? trim($_POST['edit_faculty_dept']) : null;
                $course = isset($_POST['course']) ? trim($_POST['course']) : null;
                $year_level = isset($_POST['year_level']) ? intval($_POST['year_level']) : null;
                $grade_level = isset($_POST['edit_grade_level']) ? trim($_POST['edit_grade_level']) : null;

                $stmt_edit = $conn->prepare("UPDATE students SET type=?, lastname=?, firstname=?, middlename=?, department=?, program=?, course=?, year_level=?, grade_level=? WHERE student_id=?");
                $stmt_edit->bind_param("ssssssssss", $type, $lastname, $firstname, $middlename, $department, $program, $course, $year_level, $grade_level, $student_id);
                if ($stmt_edit->execute()) {
                    echo "<script>window.location.href=window.location.pathname+'?updated=1';</script>";
                } else {
                    echo "<p style='color:red;'>Error updating student.</p>";
                }
                $stmt_edit->close();
            }
            ?>
        </div>
    </div>

    <script>
        // Modal open/close logic for Add
        const addModal = document.getElementById('addStudentModal');
        const openBtn = document.getElementById('openModalBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        openBtn.onclick = () => { addModal.style.display = 'block'; }
        closeBtn.onclick = () => { addModal.style.display = 'none'; }
        window.onclick = (e) => {
            if (e.target == addModal) addModal.style.display = 'none';
            if (e.target == editModal) editModal.style.display = 'none';
        }
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id']) && !isset($_POST['edit_student_id'])): ?>
            addModal.style.display = 'block';
        <?php endif; ?>

        // Modal open/close logic for Edit
        const editModal = document.getElementById('editStudentModal');
        const closeEditBtn = document.getElementById('closeEditModalBtn');
        closeEditBtn.onclick = () => { editModal.style.display = 'none'; }

        // Fill and open edit modal
        function openEditModal(student) {
            document.getElementById('edit_student_id').value = student.student_id;
            document.getElementById('edit_type').value = student.type;
            document.getElementById('edit_lastname').value = student.lastname;
            document.getElementById('edit_firstname').value = student.firstname;
            document.getElementById('edit_middlename').value = student.middlename;
            document.getElementById('edit_admin_dept').value = student.department || '';
            document.getElementById('edit_faculty_dept').value = student.program || '';
            document.getElementById('edit_course').value = student.course || '';
            document.getElementById('edit_year_level').value = student.year_level || '';
            document.getElementById('edit_grade_level').value = student.grade_level || '';
            showEditFields();
            editModal.style.display = 'block';
        }

        // Show/hide fields based on student type
        function showFields() {
            var type = document.getElementById('type').value;
            document.getElementById('admin_fields').style.display = (type === 'admin') ? 'block' : 'none';
            document.getElementById('faculty_fields').style.display = (type === 'faculty') ? 'block' : 'none';
            document.getElementById('college_fields').style.display = (type === 'college') ? 'block' : 'none';
            document.getElementById('basiced_fields').style.display = (type === 'basiced') ? 'block' : 'none';
        }

        // Show/hide fields based on student type in edit modal
        function showEditFields() {
            var type = document.getElementById('edit_type').value;
            document.getElementById('edit_admin_fields').style.display = (type === 'Admin') ? 'block' : 'none';
            document.getElementById('edit_faculty_fields').style.display = (type === 'Faculty') ? 'block' : 'none';
            document.getElementById('edit_college_fields').style.display = (type === 'College') ? 'block' : 'none';
            document.getElementById('edit_basiced_fields').style.display = (type === 'BasicEd') ? 'block' : 'none';
        }
    </script>
    <!-- ...existing code... -->
<?php
include '../includes/footer.php';
$stmt->close();
$conn->close();
?>
