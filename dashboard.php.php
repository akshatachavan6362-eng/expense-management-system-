<?php
include 'includes/auth.php';
include 'includes/db_connect.php';

// Get User Statistics
$user_id = $_SESSION['user_id'];

// Total Income
$stmt = $conn->prepare("SELECT COALESCE(SUM(t.amount), 0) as total_income 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND c.type = 'income'");
$stmt->execute([$user_id]);
$total_income = $stmt->fetch()['total_income'];

// Total Expense
$stmt = $conn->prepare("SELECT COALESCE(SUM(t.amount), 0) as total_expense 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND c.type = 'expense'");
$stmt->execute([$user_id]);
$total_expense = $stmt->fetch()['total_expense'];

// Balance
$balance = $total_income - $total_expense;

// Recent Transactions
$stmt = $conn->prepare("SELECT t.*, c.name as category_name, c.type 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? 
                        ORDER BY t.date DESC LIMIT 5");
$stmt->execute([$user_id]);
$recent_transactions = $stmt->fetchAll();

// Monthly Income/Expense Data for Chart
$stmt = $conn->prepare("SELECT MONTH(t.date) as month, 
                        SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END) as income,
                        SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END) as expense
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND YEAR(t.date) = YEAR(CURDATE())
                        GROUP BY MONTH(t.date)");
$stmt->execute([$user_id]);
$monthly_data = $stmt->fetchAll();

// Category-wise Expense Data for Pie Chart
$stmt = $conn->prepare("SELECT c.name, SUM(t.amount) as total 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND c.type = 'expense'
                        GROUP BY c.id ORDER BY total DESC LIMIT 5");
$stmt->execute([$user_id]);
$category_expense = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="header">
    <h1>Welcome back, <?php echo $_SESSION['user_name']; ?>!</h1>
    <p><?php echo date('l, F d, Y'); ?></p>
</div>

<!-- Statistics Cards -->
<div class="card-container">
    <div class="card income">
        <h3><i class="fas fa-arrow-up"></i> Total Income</h3>
        <p>$<?php echo number_format($total_income, 2); ?></p>
    </div>
    <div class="card expense">
        <h3><i class="fas fa-arrow-down"></i> Total Expenses</h3>
        <p>$<?php echo number_format($total_expense, 2); ?></p>
    </div>
    <div class="card">
        <h3><i class="fas fa-wallet"></i> Current Balance</h3>
        <p style="color: <?php echo $balance >= 0 ? '#27ae60' : '#e74c3c'; ?>">
            $<?php echo number_format($balance, 2); ?>
        </p>
    </div>
</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
    <div class="form-container">
        <h3>Monthly Income vs Expense</h3>
        <canvas id="barChart"></canvas>
    </div>
    <div class="form-container">
        <h3>Expenses by Category</h3>
        <canvas id="pieChart"></canvas>
    </div>
</div>

<!-- Recent Transactions -->
<div class="table-container">
    <h3><i class="fas fa-history"></i> Recent Transactions</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Type</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($recent_transactions as $t): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($t['date'])); ?></td>
                <td><?php echo $t['category_name']; ?></td>
                <td><?php echo $t['description'] ?: 'N/A'; ?></td>
                <td>
                    <span style="color: <?php echo $t['type'] == 'income' ? '#27ae60' : '#e74c3c'; ?>">
                        <?php echo ucfirst($t['type']); ?>
                    </span>
                </td>
                <td style="font-weight: bold; color: <?php echo $t['type'] == 'income' ? '#27ae60' : '#e74c3c'; ?>">
                    <?php echo $t['type'] == 'income' ? '+' : '-'; ?>$<?php echo number_format($t['amount'], 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top: 15px;">
        <a href="view_income.php" style="margin-right: 15px;"><i class="fas fa-eye"></i> View All Income</a>
        <a href="view_expense.php"><i class="fas fa-eye"></i> View All Expenses</a>
    </div>
</div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bar Chart - Monthly Data
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const incomeData = new Array(12).fill(0);
    const expenseData = new Array(12).fill(0);
    
    <?php foreach($monthly_data as $data): ?>
    incomeData[<?php echo $data['month'] - 1; ?>] = <?php echo $data['income']; ?>;
    expenseData[<?php echo $data['month'] - 1; ?>] = <?php echo $data['expense']; ?>;
    <?php endforeach; ?>

    new Chart(document.getElementById('barChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    backgroundColor: '#27ae60'
                },
                {
                    label: 'Expense',
                    data: expenseData,
                    backgroundColor: '#e74c3c'
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Pie Chart - Category Expenses
    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($category_expense, 'name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($category_expense, 'total')); ?>,
                backgroundColor: ['#e74c3c', '#3498db', '#f39c12', '#9b59b6', '#1abc9c']
            }]
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>