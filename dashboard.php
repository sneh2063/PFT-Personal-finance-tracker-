<?php
session_start();
include('connect/connection.php');

// Ensure you store the user's email in the session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['user_email'];

// SMTP configuration
$smtpHost = 'smtp.gmail.com'; // SMTP server
$smtpUser = '210490131017.sneh@gmail.com'; // SMTP username
$smtpPass = 'txra pvhh pzwf fpzu'; // SMTP password
$smtpPort = 587; // SMTP port
$smtpSecure = 'tls'; // Encryption type (tls/ssl)

// Include PHPMailer
use PHPMailer\PHPMailer\Exception;

require "Mail/phpmailer/PHPMailerAutoload.php"; // Ensure this path is correct

// Function to send email using PHPMailer
function sendEmail($to, $subject, $body) {
    global $smtpHost, $smtpUser, $smtpPass, $smtpPort, $smtpSecure;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port = $smtpPort;

        $mail->setFrom($smtpUser, 'Finance Dashboard');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        if ($mail->send()) {
            echo "";
        } else {
            echo "Failed to send email. Error: " . $mail->ErrorInfo;
        }
    } catch (Exception $e) {
        echo "Failed to send email. Error: " . $mail->ErrorInfo;
    }
}

// Fetch budget allocation
$budgetQuery = "SELECT category, amount AS budgeted, 
                (SELECT COALESCE(SUM(amount), 0) FROM transactions 
                 WHERE transactions.user_id = budgets.user_id 
                 AND transactions.category = budgets.category 
                 AND transactions.type = 'expense') AS spent 
                FROM budgets WHERE user_id = ?";
$stmt = $connect->prepare($budgetQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$budgets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check budget progress and send notification if necessary
foreach ($budgets as $budget) {
    $progress = ($budget['spent'] / $budget['budgeted']) * 100;
    $progressText = "";
    if ($progress >= 100) {
        $progressText = "100% of budget used - Over budget!";
    } elseif ($progress >= 70) {
        $progressText = "70% of budget used - Be cautious!";
    } elseif ($progress >= 50) {
        $progressText = "50% of budget used - Monitor spending!";
    } elseif ($progress >= 25) {
        $progressText = "25% of budget used - Off to a start!";
    } else {
        $progressText = "Less than 25% of budget used - Good control!";
    }
    
    $budgetNotifications[] = "Budget: " . $budget['category'] . " - " . $progressText;
}

// Fetch goals progress
$goalQuery = "SELECT 
                g.goal_id, 
                g.title, 
                g.target_amount, 
                COALESCE(SUM(t.amount), 0) AS saved_amount
              FROM goals g
              LEFT JOIN transactions t 
                ON g.goal_id = t.goal_id
                AND t.user_id = g.user_id
                AND t.type = 'income' 
              WHERE g.user_id = ? 
              GROUP BY g.goal_id, g.title, g.target_amount";

$stmt = $connect->prepare($goalQuery);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$goals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Check goal progress and send notification if necessary
foreach ($goals as $goal) {
    $title = $goal['title'];
    $target_amount = $goal['target_amount'];
    $saved_amount = $goal['saved_amount'];
    $progress = ($saved_amount / $target_amount) * 100;

    if ($progress >= 80) { // Notify if 80% of goal is achieved
        $subject = "Goal Progress: $title";
        $body = "You have saved $saved_amount out of your target $target_amount for $title. This is $progress% of your goal.";
        sendEmail($user_email, $subject, $body);
    }
}

// Calculate financial totals
$query = "SELECT 
            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
          FROM transactions WHERE user_id = ?";
$stmt = $connect->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$totals = $result->fetch_assoc();
$total_income = $totals['income'] ?? 0;
$total_expense = $totals['expense'] ?? 0;
$net_balance = $total_income - $total_expense;

// Fetch transactions with optional search/filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where = [];
$params = [$user_id];
if (!empty($search)) {
    $where[] = "(category LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filter !== 'all') {
    $where[] = "type = ?";
    $params[] = $filter;
}

$sql = "SELECT * FROM transactions WHERE user_id = ? " . (!empty($where) ? "AND " . implode(' AND ', $where) : "");
$sql .= " ORDER BY trans_date DESC, transaction_id DESC LIMIT 5"; // Limit to last 5 transactions

$stmt = $connect->prepare($sql);
$stmt->bind_param(str_repeat('s', count($params)), ...$params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
            color: #333;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Navbar Styles */
        .navbar {
            background-color: #2C3E50;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 10px 10px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar .navbar-brand {
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            font-size: 24px;
        }

        .nav-links {
            list-style: none;
            display: flex;
            gap: 20px;
            margin-left: auto;
        }

        .nav-links li {
            display: inline;
        }

        .nav-links a {
            text-decoration: none;
            color: white;
            transition: 0.3s;
            position: relative;
        }

        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: #1ABC9C;
            transform: scaleX(0);
            transition: transform 0.3s;
        }

        .nav-links a:hover::before {
            transform: scaleX(1);
        }

        /* Dashboard Container */
        .dashboard {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Card Design */
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 20px;
            background-color: white;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background-color: #2C3E50;
            color: white;
            border-bottom: none;
            padding: 20px;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-body {
            padding: 30px;
        }

        /* Summary Cards */
        .summary-card {
            text-align: center;
            padding: 20px;
        }

        .summary-card h5 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .summary-card p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        /* Chart Styles */
        .chart-container {
            height: 300px;
            position: relative;
        }

        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
        }

        /* Transactions Table */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            margin-bottom: 0;
        }

        .table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
        }

        .income {
            color: #28a745;
        }

        .expense {
            color: #dc3545;
        }

        /* Search Form */
        .search-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .search-form {
            display: flex;
            flex: 1;
            max-width: 600px;
        }

        .search-form input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 5px 0 0 5px;
            border: 1px solid #ddd;
            outline: none;
        }

        .search-form select {
            padding: 10px 15px;
            border-radius: 0;
            border: 1px solid #ddd;
            border-left: none;
            outline: none;
        }

        .search-form button {
            background-color: #E74C3C;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 0 5px 5px 0;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .search-form button:hover {
            background-color: #C0392B;
        }

        /* Export Button */
        .export-btn {
            display: inline-block;
            margin-top: 20px;
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .export-btn:hover {
            background-color: #5a6268;
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animated {
            animation: fadeIn 1s ease-in-out;
        }

        /* Background Animation */
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            background-color: rgba(26, 188, 156, 0.2);
            border-radius: 50%;
            animation: float 20s linear infinite;
        }

        @keyframes float {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(100vw, 100vh);
            }
        }
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="background-animation" id="backgroundAnimation">
        <!-- Particles will be generated by JavaScript -->
    </div>

    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span class="logo-icon">💰</span>
                Personal Finance Tracker
            </a>
            <ul class="nav-links">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="add_transaction.php">Transactions</a></li>
                <li><a href="process_budget.php">Budget</a></li>
                <li><a href="add_goal.php">Goals</a></li>
                <li><a href="profile.php">Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="dashboard container animated">
        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Net Balance</h5>
                        <p>$<?= number_format($net_balance, 2) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Income</h5>
                        <p>$<?= number_format($total_income, 2) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card summary-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Expenses</h5>
                        <p>$<?= number_format($total_expense, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Spending Chart and Trend Analysis Chart -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Category Spending</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Trend Analysis</h5>
                        <div class="form-group">
                            <label for="trendType">Select Trend:</label>
                            <select id="trendType" class="form-control">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly" selected>Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Goals Progress Graph and Budget Allocation Graph -->
        <div class="row" style="margin-top: -20px;">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Goals Progress</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="goalChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Budget Allocation</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="budgetChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Latest Transactions Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Latest Transactions</h5>
                        <div class="search-container">
                            <form method="get" class="search-form">
                                <input type="text" name="search" placeholder="Search..." value="<?= $search ?>">
                                <select name="filter">
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
                                    <option value="income" <?= $filter === 'income' ? 'selected' : '' ?>>Income</option>
                                    <option value="expense" <?= $filter === 'expense' ? 'selected' : '' ?>>Expense</option>
                                </select>
                                <button type="submit">Search</button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?= date("M d, Y", strtotime($transaction['trans_date'])) ?></td>
                                            <td><?= htmlspecialchars($transaction['category']) ?></td>
                                            <td class="<?= htmlspecialchars($transaction['type']) ?>"><?= ucfirst($transaction['type']) ?></td>
                                            <td>$<?= number_format($transaction['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($transaction['description']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <a href="export.php" class="export-btn">Export to CSV</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            loadCategoryChart();
            loadTrendChart('monthly'); // Default trend
            loadGoalChart();
            loadBudgetChart();

            document.getElementById('trendType').addEventListener('change', function() {
                loadTrendChart(this.value);
            });
        });

        async function loadCategoryChart() {
            const response = await fetch('api_transactions.php?type=expense&category=true');
            const data = await response.json();
            new Chart(document.getElementById('categoryChart'), {
                type: 'pie',
                data: {
                    labels: data.categories,
                    datasets: [ {
                        data: data.amounts,
                        backgroundColor: ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#4bc0c0']
                    } ]
                }
            });
        }

        let trendChart;
        async function loadTrendChart(trendType) {
            const response = await fetch(`api_transactions.php?trend=${trendType}`);
            const trendData = await response.json();
            if (trendChart) trendChart.destroy();
            trendChart = new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendData.map(d => d.label),
                    datasets: [
                        { label: 'Income', data: trendData.map(d => d.income), borderColor: '#36a2eb', fill: false },
                        { label: 'Expenses', data: trendData.map(d => d.expense), borderColor: '#ff6384', fill: false }
                    ]
                }
            });
        }

        async function loadGoalChart() {
            const goalData = <?= json_encode($goals) ?>;
            new Chart(document.getElementById('goalChart'), {
                type: 'bar',
                data: {
                    labels: goalData.map(g => g.title),
                    datasets: [
                        { 
                            label: 'Saved Amount', 
                            data: goalData.map(g => g.saved_amount), 
                            backgroundColor: '#36a2eb' 
                        },
                        { 
                            label: 'Target Amount', 
                            data: goalData.map(g => g.target_amount), 
                            backgroundColor: '#ff6384' 
                        }
                    ]
                }
            });
        }

        async function loadBudgetChart() {
            const budgetData = <?= json_encode($budgets) ?>;
            new Chart(document.getElementById('budgetChart'), {
                type: 'bar',
                data: {
                    labels: budgetData.map(b => b.category),
                    datasets: [
                        { 
                            label: 'Spent', 
                            data: budgetData.map(b => b.spent), 
                            backgroundColor: '#ff6384' 
                        },
                        { 
                            label: 'Budgeted', 
                            data: budgetData.map(b => b.budgeted), 
                            backgroundColor: '#36a2eb' 
                        }
                    ]
                }
            });
        }
    </script>

    <script>
        // Create background particles
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('backgroundAnimation');
            const particleCount = 100;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size between 1px and 9px
                const size = Math.random() * 8 + 1;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                
                // Random position
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                
                // Random animation duration between 15s and 25s
                particle.style.animationDuration = `${Math.random() * 10 + 15}s`;
                
                // Random animation delay
                particle.style.animationDelay = `${Math.random() * 10}s`;
                
                container.appendChild(particle);
            }
        });
    </script>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>
</body>
</html>