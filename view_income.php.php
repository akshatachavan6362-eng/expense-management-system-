<?php
include 'includes/auth.php';
include 'includes/db_connect.php';

// Handle Delete
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    header("Location: view_income.php");
    exit;
}

// Get all income transactions
$stmt = $conn->prepare("SELECT t.*, c.name as category_name 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND c.type = 'income' 
                        ORDER BY t.date DESC");
$stmt->execute([$_SESSION['user_id']]);
$incomes = $stmt->fetchAll();

// Calculate total
$total_income = array_sum(array_column($incomes, 'amount'));

include 'includes/header.php';
?>

<div class="header">
    <h1><i class="fas fa-arrow-up" style="color: #27ae60;"></i> View Income</h1>
    <a href="add_income.php" class="btn-add"><i class="fas fa-plus"></i> Add New Income</a>
</div>

<div class="table-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>All Income Transactions</h3>
        <p style="font-size: 18px;"><strong>Total Income: </strong><span style="color: #27ae60;">$<?php echo number_format($total_income, 2); ?></span></p>
    </div>
    
    <?php if(count($incomes) > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($incomes as $income): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($income['date'])); ?></td>
                <td><?php echo $income['category_name']; ?></td>
                <td><?php echo $income['description'] ?: 'N/A'; ?></td>
                <td style="color: #27ae60; font-weight: bold;">$<?php echo number_format($income['amount'], 2); ?></td>
                <td>
                    <a href="view_income.php?delete=<?php echo $income['id']; ?>" 
                       onclick="return confirm('Are you sure you want to delete this income?')"
                       style="color: #e74c3c;">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 40px; color: #7f8c8d;">
        <i class="fas fa-folder-open" style="font-size: 48px; margin-bottom: 15px;"></i>
        <p>No income records found. Start by adding your first income!</p>
        <a href="add_income.php" style="color: #3498db;">Add Income Now</a>
    </div>
    <?php endif; ?>
</div>

<style>
.btn-add {
    background-color: #27ae60;
    color: white;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    transition: 0.3s;
}
.btn-add:hover { background-color: #219a52; }
</style>

<?php include 'includes/footer.php'; ?>