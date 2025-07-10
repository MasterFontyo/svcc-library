<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = $_POST['title'];
                $description = $_POST['description'];
                $event_date = $_POST['event_date'];
                $event_time = $_POST['event_time'];
                
                $stmt = $conn->prepare("INSERT INTO library_events (title, description, event_date, event_time, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssi", $title, $description, $event_date, $event_time, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $success = "Event added successfully!";
                } else {
                    $error = "Error adding event: " . $conn->error;
                }
                break;
                
            case 'edit':
                $event_id = $_POST['event_id'];
                $title = $_POST['title'];
                $description = $_POST['description'];
                $event_date = $_POST['event_date'];
                $event_time = $_POST['event_time'];
                
                $stmt = $conn->prepare("UPDATE library_events SET title = ?, description = ?, event_date = ?, event_time = ? WHERE event_id = ?");
                $stmt->bind_param("ssssi", $title, $description, $event_date, $event_time, $event_id);
                
                if ($stmt->execute()) {
                    $success = "Event updated successfully!";
                } else {
                    $error = "Error updating event: " . $conn->error;
                }
                break;
                
            case 'delete':
                $event_id = $_POST['event_id'];
                
                $stmt = $conn->prepare("DELETE FROM library_events WHERE event_id = ?");
                $stmt->bind_param("i", $event_id);
                
                if ($stmt->execute()) {
                    $success = "Event deleted successfully!";
                } else {
                    $error = "Error deleting event: " . $conn->error;
                }
                break;
        }
    }
}

// Get current month and year
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Calculate previous and next month/year
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get events for current month
$events_query = $conn->prepare("
    SELECT * FROM library_events 
    WHERE YEAR(event_date) = ? AND MONTH(event_date) = ?
    ORDER BY event_date, event_time
");
$events_query->bind_param("ii", $current_year, $current_month);
$events_query->execute();
$events = $events_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Group events by day
$events_by_day = [];
foreach ($events as $event) {
    $day = date('j', strtotime($event['event_date']));
    if (!isset($events_by_day[$day])) {
        $events_by_day[$day] = [];
    }
    $events_by_day[$day][] = $event;
}

// Calendar calculations
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day);
$day_of_week = date('w', $first_day);
$month_name = date('F Y', $first_day);

?>

<style>
/* Calendar Styles */
.calendar-container {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: #e9ecef;
}

.calendar-day-header {
  background: #800000;
  color: #fff;
  padding: 12px 8px;
  text-align: center;
  font-weight: 600;
  font-size: 14px;
}

.calendar-day {
  background: #fff;
  min-height: 120px;
  padding: 8px;
  position: relative;
  cursor: pointer;
  transition: background-color 0.2s;
  border: 1px solid transparent;
}

.calendar-day:hover:not(.empty) {
  background: #f8f9fa;
}

.calendar-day.empty {
  background: #f8f9fa;
  cursor: default;
}

.calendar-day.today {
  background: #e3f2fd;
  border: 2px solid #007bff;
  font-weight: bold;
}

.calendar-day.has-events {
  background: #fff3cd;
}

.calendar-day.today.has-events {
  background: #cce5ff;
}

.day-number {
  font-weight: 600;
  font-size: 16px;
  margin-bottom: 4px;
  color: #333;
}

.event-item {
  background: #800000;
  color: #fff;
  padding: 2px 4px;
  margin-bottom: 2px;
  border-radius: 3px;
  font-size: 10px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.event-item:hover {
  background: #a83232;
}

.event-title {
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.event-time {
  font-size: 9px;
  opacity: 0.9;
}

/* Responsive Calendar */
@media (max-width: 768px) {
  .calendar-day {
    min-height: 80px;
    padding: 4px;
  }
  
  .day-number {
    font-size: 14px;
  }
  
  .event-item {
    font-size: 9px;
    padding: 1px 3px;
  }
  
  .calendar-day-header {
    padding: 8px 4px;
    font-size: 12px;
  }
}

@media (max-width: 576px) {
  .calendar-day {
    min-height: 60px;
    padding: 2px;
  }
  
  .day-number {
    font-size: 12px;
  }
  
  .event-item {
    font-size: 8px;
    padding: 1px 2px;
  }
  
  .event-title {
    max-width: 40px;
  }
  
  .event-time {
    display: none;
  }
}

/* Modal fixes */
.modal {
  z-index: 1055 !important;
}

.modal-backdrop {
  z-index: 1050 !important;
}

.modal-dialog {
  z-index: 1056 !important;
}

/* Fix for modal centering */
.modal-dialog-centered {
  display: flex;
  align-items: center;
  min-height: calc(100vh - 60px);
}

/* Ensure form inputs are visible */
.modal-body .form-control {
  z-index: 1060 !important;
  position: relative;
}
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-calendar3"></i> Library Events Calendar</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    <i class="bi bi-plus-circle"></i> Add Event
                </button>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Calendar Navigation -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                        <h4 class="mb-0"><?= $month_name ?></h4>
                        <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>" class="btn btn-outline-primary btn-sm">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
                
                <!-- Calendar Grid -->
                <div class="card-body p-0">
                    <div class="calendar-container">
                        <div class="calendar-grid">
                            <!-- Day headers -->
                            <div class="calendar-day-header">Sun</div>
                            <div class="calendar-day-header">Mon</div>
                            <div class="calendar-day-header">Tue</div>
                            <div class="calendar-day-header">Wed</div>
                            <div class="calendar-day-header">Thu</div>
                            <div class="calendar-day-header">Fri</div>
                            <div class="calendar-day-header">Sat</div>

                            <?php
                            // Empty cells for days before the first day of the month
                            for ($i = 0; $i < $day_of_week; $i++) {
                                echo '<div class="calendar-day empty"></div>';
                            }

                            // Days of the month
                            for ($day = 1; $day <= $days_in_month; $day++) {
                                $today_class = (date('Y-m-d') == date('Y-m-d', mktime(0, 0, 0, $current_month, $day, $current_year))) ? 'today' : '';
                                $has_events_class = isset($events_by_day[$day]) ? 'has-events' : '';
                                
                                echo '<div class="calendar-day ' . $today_class . ' ' . $has_events_class . '" data-day="' . $day . '">';
                                echo '<div class="day-number">' . $day . '</div>';
                                
                                if (isset($events_by_day[$day])) {
                                    foreach ($events_by_day[$day] as $event) {
                                        $time_display = $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'All Day';
                                        echo '<div class="event-item" data-event-id="' . $event['event_id'] . '">';
                                        echo '<div class="event-title">' . htmlspecialchars($event['title']) . '</div>';
                                        echo '<div class="event-time">' . $time_display . '</div>';
                                        echo '</div>';
                                    }
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events List -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-list-ul"></i> Events for <?= $month_name ?></h5>
                </div>
                <div class="card-body">
                    <?php if (empty($events)): ?>
                        <p class="text-muted text-center py-4">No events scheduled for this month.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Event</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($event['event_date'])) ?></td>
                                            <td><?= $event['event_time'] ? date('g:i A', strtotime($event['event_time'])) : 'All Day' ?></td>
                                            <td><strong><?= htmlspecialchars($event['title']) ?></strong></td>
                                            <td><?= htmlspecialchars($event['description']) ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-event-btn" 
                                                        data-event='<?= json_encode($event) ?>'>
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Are you sure you want to delete this event?')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addEventModalLabel">Add New Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_time" class="form-label">Time (Optional)</label>
                                <input type="time" class="form-control" id="event_time" name="event_time">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Event Modal -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editEventModalLabel">Edit Event</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="event_id" id="edit_event_id">
                    
                    <div class="mb-3">
                        <label for="edit_title" class="form-label">Event Title</label>
                        <input type="text" class="form-control" id="edit_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_event_date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="edit_event_date" name="event_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_event_time" class="form-label">Time (Optional)</label>
                                <input type="time" class="form-control" id="edit_event_time" name="event_time">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default date to today when modal opens
    const addModal = document.getElementById('addEventModal');
    const editModal = document.getElementById('editEventModal');
    
    // Set default date when add modal is shown
    addModal.addEventListener('show.bs.modal', function() {
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('event_date').value = today;
    });
    
    // Handle edit event button clicks
    document.querySelectorAll('.edit-event-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            try {
                const eventData = JSON.parse(this.getAttribute('data-event'));
                
                document.getElementById('edit_event_id').value = eventData.event_id;
                document.getElementById('edit_title').value = eventData.title;
                document.getElementById('edit_description').value = eventData.description || '';
                document.getElementById('edit_event_date').value = eventData.event_date;
                document.getElementById('edit_event_time').value = eventData.event_time || '';
                
                const editModalInstance = new bootstrap.Modal(editModal);
                editModalInstance.show();
            } catch (error) {
                console.error('Error opening edit modal:', error);
            }
        });
    });

    // Handle calendar day clicks
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.addEventListener('click', function(e) {
            e.preventDefault();
            if (!this.classList.contains('empty')) {
                try {
                    const dayNumber = this.getAttribute('data-day');
                    const currentDate = new Date(<?= $current_year ?>, <?= $current_month - 1 ?>, dayNumber);
                    const dateString = currentDate.toISOString().split('T')[0];
                    
                    document.getElementById('event_date').value = dateString;
                    const addModalInstance = new bootstrap.Modal(addModal);
                    addModalInstance.show();
                } catch (error) {
                    console.error('Error opening add modal:', error);
                }
            }
        });
    });

    // Clear form when modal is hidden
    addModal.addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        if (form) {
            form.reset();
        }
    });
    
    editModal.addEventListener('hidden.bs.modal', function() {
        const form = this.querySelector('form');
        if (form) {
            form.reset();
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
