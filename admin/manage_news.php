<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login_new.php");
    exit();
}

// Handle Add News
if (isset($_POST['add_news'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $image_url = $_POST['image_url'] ?? null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $created_by = $_SESSION['admin'];
    $created_at = date('Y-m-d H:i:s');

    // Create news table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS news (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        category VARCHAR(100),
        image_url VARCHAR(500),
        is_featured TINYINT(1) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 1,
        created_by VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->query($create_table_sql);

    $sql = "INSERT INTO news (title, content, category, image_url, is_featured, is_active, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiiis", $title, $content, $category, $image_url, $is_featured, $is_active, $created_by, $created_at);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "News added successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error adding news: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_news.php");
    exit();
}

// Handle Edit News
if (isset($_POST['edit_news'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $image_url = $_POST['image_url'] ?? null;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $sql = "UPDATE news SET title=?, content=?, category=?, image_url=?, is_featured=?, is_active=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssii", $title, $content, $category, $image_url, $is_featured, $is_active, $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "News updated successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error updating news: " . $stmt->error;
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_news.php");
    exit();
}

// Handle Delete News
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $sql = "DELETE FROM news WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "News deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Error deleting news!";
        $_SESSION['message_type'] = "danger";
    }
    header("Location: manage_news.php");
    exit();
}

// Create news table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    category VARCHAR(100),
    image_url VARCHAR(500),
    is_featured TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_by VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
$conn->query($create_table_sql);

// Fetch all news
$sql_news = "SELECT * FROM news ORDER BY created_at DESC";
$result_news = $conn->query($sql_news);
$news_list = [];
if ($result_news) {
    while ($row = $result_news->fetch_assoc()) {
        $news_list[] = $row;
    }
}

// Get news for editing if edit ID is provided
$edit_news = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $sql = "SELECT * FROM news WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_news = $result->fetch_assoc();
}

require_once __DIR__ . '/../includes/theme_loader.php';
$active_theme = loadActiveTheme($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News - NIELIT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php injectThemeCSS($active_theme); ?>
    <style>
        :root {
            --primary: #1a56db;
            --secondary: #0f172a;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0284c7;
        }
        body { background: #f8fafc; }
        .sidebar-nav { background: var(--secondary); }
        .news-card {
            border: 1px solid rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
        }
        .news-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .news-card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
            color: white;
            padding: 1.2rem;
        }
        .news-card-body {
            padding: 1.5rem;
        }
        .featured-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-active {
            background: rgba(16,185,129,0.2);
            color: #10b981;
        }
        .status-inactive {
            background: rgba(239,68,68,0.2);
            color: #ef4444;
        }
        .form-modal {
            border-radius: 15px;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);
            color: white;
            border: none;
        }
        .btn-custom {
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(26,86,219,0.25);
        }
        .section-title {
            font-weight: 800;
            color: var(--secondary);
            margin-bottom: 2rem;
            font-size: 1.8rem;
        }
        .action-btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 sidebar-nav p-4">
                <div class="mb-4">
                    <h5 class="text-white fw-bold">
                        <i class="fas fa-newspaper me-2"></i>News Management
                    </h5>
                </div>
                <ul class="list-unstyled">
                    <li class="mb-3">
                        <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="text-white text-decoration-none">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="<?php echo APP_URL; ?>/admin/manage_news.php" class="text-warning text-decoration-none fw-bold">
                            <i class="fas fa-newspaper me-2"></i>Manage News
                        </a>
                    </li>
                    <li class="mb-3">
                        <a href="<?php echo APP_URL; ?>/admin/manage_announcements.php" class="text-white text-decoration-none">
                            <i class="fas fa-bell me-2"></i>Announcements
                        </a>
                    </li>
                    <li class="mt-5">
                        <a href="<?php echo APP_URL; ?>/admin/logout.php" class="text-white text-decoration-none">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 p-5">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="section-title mb-2">News Management</h1>
                        <p class="text-muted mb-0">Create and manage news articles for the homepage</p>
                    </div>
                    <button class="btn btn-primary btn-custom" data-bs-toggle="modal" data-bs-target="#newsModal">
                        <i class="fas fa-plus me-2"></i>Add New News
                    </button>
                </div>

                <!-- Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?php echo $_SESSION['message_type']; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $_SESSION['message_type'] == 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo $_SESSION['message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- News Cards Grid -->
                <div class="row g-4">
                    <?php if (!empty($news_list)): ?>
                        <?php foreach ($news_list as $news): ?>
                            <div class="col-lg-6 col-md-12">
                                <div class="news-card position-relative">
                                    <?php if ($news['is_featured']): ?>
                                        <span class="featured-badge">
                                            <i class="fas fa-star me-1"></i>Featured
                                        </span>
                                    <?php endif; ?>
                                    <div class="news-card-header">
                                        <h5 class="mb-2 text-truncate"><?php echo htmlspecialchars($news['title']); ?></h5>
                                        <small class="d-block">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M d, Y', strtotime($news['created_at'])); ?>
                                        </small>
                                    </div>
                                    <div class="news-card-body">
                                        <p class="text-muted small mb-3" style="max-height: 80px; overflow: hidden;">
                                            <?php echo mb_substr(strip_tags($news['content']), 0, 150) . '...'; ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="status-badge <?php echo $news['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i>
                                                    <?php echo $news['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                                <?php if ($news['category']): ?>
                                                    <span class="badge bg-info ms-2"><?php echo htmlspecialchars($news['category']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-3 pt-3 border-top">
                                            <a href="?edit=<?php echo $news['id']; ?>" class="btn btn-sm btn-outline-primary action-btn me-2">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="?delete=<?php echo $news['id']; ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-outline-danger action-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center py-5">
                                <i class="fas fa-newspaper fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">No news articles yet. Create your first news article!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit News Modal -->
    <div class="modal fade" id="newsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content form-modal">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-pen-fancy me-2"></i>
                        <?php echo $edit_news ? 'Edit News Article' : 'Add New Article'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <?php if ($edit_news): ?>
                            <input type="hidden" name="id" value="<?php echo $edit_news['id']; ?>">
                        <?php endif; ?>

                        <div class="mb-4">
                            <label class="form-label fw-600">Article Title *</label>
                            <input type="text" class="form-control" name="title" required 
                                   value="<?php echo $edit_news ? htmlspecialchars($edit_news['title']) : ''; ?>"
                                   placeholder="Enter news article title">
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-600">Category</label>
                                <select class="form-select" name="category">
                                    <option value="">Select Category</option>
                                    <option value="Announcement" <?php echo $edit_news && $edit_news['category'] == 'Announcement' ? 'selected' : ''; ?>>Announcement</option>
                                    <option value="Achievement" <?php echo $edit_news && $edit_news['category'] == 'Achievement' ? 'selected' : ''; ?>>Achievement</option>
                                    <option value="Event" <?php echo $edit_news && $edit_news['category'] == 'Event' ? 'selected' : ''; ?>>Event</option>
                                    <option value="Update" <?php echo $edit_news && $edit_news['category'] == 'Update' ? 'selected' : ''; ?>>Update</option>
                                    <option value="Other" <?php echo $edit_news && $edit_news['category'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-600">Image URL</label>
                                <input type="url" class="form-control" name="image_url" 
                                       value="<?php echo $edit_news ? htmlspecialchars($edit_news['image_url'] ?? '') : ''; ?>"
                                       placeholder="https://example.com/image.jpg">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-600">Article Content *</label>
                            <textarea class="form-control" name="content" rows="8" required 
                                      placeholder="Enter detailed news content..."><?php echo $edit_news ? htmlspecialchars($edit_news['content']) : ''; ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" id="is_featured"
                                           <?php echo $edit_news && $edit_news['is_featured'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">
                                        <i class="fas fa-star text-warning me-1"></i>Mark as Featured
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked
                                           <?php echo !$edit_news || $edit_news['is_active'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">
                                        <i class="fas fa-eye text-success me-1"></i>Active (Visible on site)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="<?php echo $edit_news ? 'edit_news' : 'add_news'; ?>" class="btn btn-primary btn-custom">
                            <i class="fas fa-save me-2"></i><?php echo $edit_news ? 'Update' : 'Save'; ?> Article
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-open modal if editing
        <?php if ($edit_news): ?>
            var newsModal = new bootstrap.Modal(document.getElementById('newsModal'));
            newsModal.show();
        <?php endif; ?>
    </script>
</body>
</html>
