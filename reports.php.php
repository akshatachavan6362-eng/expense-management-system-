<?php
include 'includes/auth.php';
include 'includes/db_connect.php';

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Get income/expense summary for date range
$stmt = $conn->prepare("SELECT c.type, SUM(t.amount) as total 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND t.date BETWEEN ? AND ?
                        GROUP BY c.type");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$summary = $stmt->fetchAll();

$income_total = 0;
$expense_total = 0;

foreach($summary as $s) {
    if($s['type'] == 'income') $income_total = $s['total'];
    if($s['type'] == 'expense') $expense_total = $s['total'];
}

$balance = $income_total - $expense_total;

// Get category-wise breakdown
$stmt = $conn->prepare("SELECT c.name, c.type, SUM(t.amount) as total 
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND t.date BETWEEN ? AND ?
                        GROUP BY c.id 
                        ORDER BY total DESC");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$category_breakdown = $stmt->fetchAll();

// Get daily transactions for line chart
$stmt = $conn->prepare("SELECT t.date, 
                        SUM(CASE WHEN c.type = 'income' THEN t.amount ELSE 0 END) as income,
                        SUM(CASE WHEN c.type = 'expense' THEN t.amount ELSE 0 END) as expense
                        FROM transactions t 
                        JOIN categories c ON t.category_id = c.id 
                        WHERE t.user_id = ? AND t.date BETWEEN ? AND ?
                        GROUP BY t.date 
                        ORDER BY t.date ASC");
$stmt->execute([$_SESSION['user_id'], $start_date, $end_date]);
$daily_data = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="header">
    <h1><i class="fas fa-chart-pie"></i> Financial Reports</h1>
</div>

<!-- Date Filter -->
<div class="form-container" style="margin-bottom: 30px;">
    <form method="GET" style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
        <div style="flex: 1;">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" style="margin-bottom: 0;">
        </div>
        <div style="flex: 1;">
            <label>End Date</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" style="margin-bottom: 0;">
        </div>
        <div style="flex: 0;">
            <button type="submit" style="height: 42px;">Generate Report</button>
        </div>
        <div style="flex: 0;">
            <a href="reports.php" class="btn-add" style="height: 42px; display: flex; align-items: center; text-decoration: none;">Reset</a>
        </div>
    </form>
</div>

<!-- Summary Cards -->
<div class="card-container">
    <div class="card income">
        <h3><i class="fas fa-arrow-up"></i> Total Income</h3>
        <p>$<?php echo number_format($income_total, 2); ?></p>
    </div>
    <div class="card expense">
        <h3><i class="fas fa-arrow-down"></i> Total Expenses</h3>
        <p>$<?php echo number_format($expense_total, 2); ?></p>
    </div>
    <div class="card">
        <h3><i class="fas fa-balance-scale"></i> Net Balance</h3>
        <p style="color: <?php echo $balance >= 0 ? '#27ae60' : '#e74c3c'; ?>">
            $<?php echo number_format($balance, 2); ?>
        </p>
    </div>
</div>

<!-- Charts -->
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
    <div class="form-container">
        <h3>Daily Income vs Expense</h3>
        <canvas id="dailyChart"></canvas>
    </div>
    <div class="form-container">
        <h3>Category Breakdown</h3>
        <canvas id="categoryChart"></canvas>
    </div>
</div>

<!-- Category Table -->
<div class="table-container">
    <h3>Category-wise Breakdown</h3>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Percentage</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_amount = $income_total + $expense_total;
            foreach($category_breakdown as $cat): 
                $percentage = $total_amount > 0 ? ($cat['total'] / $total_amount) * 100 : 0;
            ?>
            <tr>
                <td><?php echo $cat['name']; ?></td>
                <td>
                    <span style="color: <?php echo $cat['type'] == 'income' ? '#27ae60' : '#e74c3c'; ?>">
                        <?php echo ucfirst($cat['type']); ?>
                    </span>
                </td>
                <td style="font-weight: bold;">$<?php echo number_format($cat['total'], 2); ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div style="width: 100px; height: 8px; background: #ecf0f1; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $percentage; ?>%; height: 100%; background: <?php echo $cat['type'] == 'income' ? '#27ae60' : '#e74c3c'; ?>;"></div>
                        </div>
                        <span><?php echo number_format($percentage, 1); ?>%</span>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Export Options -->
<div style="margin-top: 30px; display: flex; gap: 15px;">
    <button onclick="window.print()" style="width: auto; padding: 12px 30px;">
        <i class="fas fa-print"></i> Print Report
    </button>
    <a href="export_report.php?start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
       style="background: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">
        <i class="fas fa-file-excel"></i> Export to Excel
    </a>
</div>

<!-- Chart Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Line Chart
    const dates = <?php echo json_encode(array_column($daily_data, 'date')); ?>;
    const incomeData = <?php echo json_encode(array_column($daily_data, 'income')); ?>;
    const expenseData = <?php echo json_encode(array_column($daily_data, 'expense')); ?>;
    
    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: dates.map(d => new Date(d).toLocaleDateString('en-US', {month: 'short', day: 'numeric'})),
            datasets: [
                {
                    label: 'Income',
                    data: incomeData,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Expense',
                    data: expenseData,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    fill: true,
                    tension: 0.4
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
    
    // Category Pie Chart
    new Chart(document.getElementById('categoryChart'), {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($category_breakdown, 'name')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($category_breakdown, 'total')); ?>,
                backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#9b59b6', '#1abc9c', '#2ecc71', '#e67e22', '#34495e']
            }]
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>