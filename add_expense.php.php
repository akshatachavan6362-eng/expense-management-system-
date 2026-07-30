<?php
include 'includes/auth.php';
include 'includes/db_connect.php';

$message = '';

// Get user's expense categories
$stmt = $conn->prepare("SELECT * FROM categories WHERE (user_id = ? OR user_id IS NULL) AND type = 'expense'");
$stmt->execute([$_SESSION['user_id']]);
$categories = $stmt->fetchAll();

if(isset($_POST['add_expense'])) {
    $category_id = $_POST['category_id'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    
    $stmt = $conn->prepare("INSERT INTO transactions (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)");
    if($stmt->execute([$_SESSION['user_id'], $category_id, $amount, $description, $date])) {
        $message = '<div class="alert success">Expense added successfully!</div>';
    } else {
        $message = '<div class="alert error">Error adding expense!</div>';
    }
}

// Add new category if requested
if(isset($_POST['add_category'])) {
    $new_category = $_POST['new_category'];
    $stmt = $conn->prepare("INSERT INTO categories (name, type, user_id) VALUES (?, 'expense', ?)");
    $stmt->execute([$new_category, $_SESSION['user_id']]);
    header("Location: add_expense.php");
    exit;
}

include 'includes/header.php';
?>

<div class="header">
    <h1><i class="fas fa-minus-circle" style="color: #e74c3c;"></i> Add Expense</h1>
</div>

<?php echo $message; ?>

<div class="form-container">
    <form method="POST">
        <label>Select Category</label>
        <select name="category_id" required>
            <option value="">-- Select Category --</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
            <?php endforeach; ?>
        </select>
        
        <label>Or Add New Category</label>
        <div style="display: flex; gap: 10px;">
            <input type="text" name="new_category" placeholder="New category name">
            <button type="submit" name="add_category" style="width: auto; padding: 12px 20px;">Add</button>
        </div>
        
        <label>Amount ($)</label>
        <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
        
        <label>Description (Optional)</label>
        <input type="text" name="description" placeholder="Description">
        
        <label>