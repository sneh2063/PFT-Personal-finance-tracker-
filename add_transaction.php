<?php
session_start();
include('connect/connection.php');

// Check if session is set and user_id is available
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];

// Fetch goals for the dropdown (only if they have a target amount)
$goalQuery = "SELECT goal_id, title FROM goals WHERE user_id = ?";
$goalStmt = $connect->prepare($goalQuery);
$goalStmt->bind_param("i", $user_id);
$goalStmt->execute();
$goalResult = $goalStmt->get_result();
$goals = $goalResult->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $category = trim($_POST['category']);
    $type = strtolower($_POST['type']);
    $date = $_POST['date'];
    $description = trim($_POST['description'] ?? '');
    $goal_id = isset($_POST['goal_id']) ? (int)$_POST['goal_id'] : null;
    $submitType = $_POST['submit'];

    // Basic validation
    if (!$amount || $category === '') {
        $errors[] = "Amount and category are required";
    }

    if (empty($errors)) {
        $connect->begin_transaction();
        try {
            // Insert transaction
            $stmt = $connect->prepare("
                INSERT INTO transactions 
                    (user_id, amount, category, type, trans_date, description, goal_id)
                VALUES 
                    (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("idssssi", $user_id, $amount, $category, $type, $date, $description, $goal_id);
            $stmt->execute();

            // If income is linked to a goal, update the goal's progress
            if ($type === 'income' && $goal_id !== null) {
                $stmt = $connect->prepare("
                    UPDATE goals 
                    SET current_amount = current_amount + ?
                    WHERE goal_id = ?
                ");
                $stmt->bind_param("di", $amount, $goal_id);
                $stmt->execute();
            }

            $connect->commit();

            if ($submitType === "save_add_another") {
                header("Location: add_transaction.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } catch (mysqli_sql_exception $e) {
            $connect->rollback();
            $errors[] = "Failed to add transaction: " . $e->getMessage();
        }
    }

    $_SESSION['errors'] = $errors;
    header("Location: add_transaction.php");
    exit();
}

// Fetch last 5 transactions
$query = "SELECT amount, category, type, trans_date, description FROM transactions WHERE user_id = ? ORDER BY trans_date DESC LIMIT 5";
$stmt = $connect->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - Personal Finance Tracker</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Navbar Styles */
        .navbar {
            background-color: #2C3E50;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 10px 10px;
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

        /* Main Content */
        .main-content {
            padding: 30px 20px;
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

        /* Form Styles */
        .form-group {
            margin-bottom: 30px;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: border-color 0.3s ease;
            font-size: 16px;
            color: #333;
            background-color: white;
        }

        .form-control:focus {
            border-color: #1ABC9C;
            box-shadow: none;
        }

        .btn-primary {
            background-color: #E74C3C;
            border-color: #E74C3C;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-weight: bold;
            padding: 12px 25px;
            font-size: 16px;
        }

        .btn-primary:hover {
            background-color: #C0392B;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-weight: bold;
            padding: 12px 25px;
            font-size: 16px;
        }

        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px;
        }

        /* Table Styles */
        .table {
            margin-top: 20px;
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

        /* Dropdown improvements */
        #goal_section {
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        #goal_id {
            font-size: 16px;
            color: #333;
            background-color: white;
            z-index: 100;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Dashboard button */
        .dashboard-btn {
            margin-bottom: 20px;
            text-align: center;
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
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </nav>

    <div class="main-content animated">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header text-center">
                            <h3>Add Transaction</h3>
                        </div>
                        <div class="card-body">
                            <!-- Display errors if any -->
                            <?php if (isset($_SESSION['errors']) && count($_SESSION['errors']) > 0): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($_SESSION['errors'] as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php unset($_SESSION['errors']); ?>
                            <?php endif; ?>

                            <form method="post" action="add_transaction.php">
                                <div class="form-group">
                                    <label for="amount">Amount</label>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" required>
                                </div>
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" required>
                                </div>
                                <div class="form-group">
                                    <label for="type">Type</label>
                                    <select class="form-control" id="type" name="type" required onchange="toggleGoalDropdown()">
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>

                                <!-- Goal selection (only visible for income) -->
                                <div class="form-group" id="goal_section" style="display: none;">
                                    <label for="goal_id">Apply to Goal</label>
                                    <select class="form-control" id="goal_id" name="goal_id">
                                        <option value="">-- Select Goal --</option>
                                        <?php foreach ($goals as $goal): ?>
                                            <option value="<?= $goal['goal_id'] ?>"><?= htmlspecialchars($goal['title']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="date">Date</label>
                                    <input type="date" class="form-control" id="date" name="date" required>
                                </div>
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description"></textarea>
                                </div>

                                <!-- Buttons for submitting -->
                                <div class="text-center">
                                    <button type="submit" name="submit" value="save" class="btn btn-primary">Add Transaction</button>
                                    <button type="submit" name="submit" value="save_add_another" class="btn btn-success">Save & Add Another</button>
                                </div>
                            </form>

                            <!-- Dashboard Button -->
                            <div class="dashboard-btn">
                                <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                            </div>
                        </div>
                    </div>

                    <!-- Last 5 Transactions Table -->
                    <div class="card mt-4">
                        <div class="card-header text-center">
                            <h4>Last 5 Transactions</h4>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Amount</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($transactions) > 0): ?>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($transaction['amount']) ?></td>
                                                <td><?= htmlspecialchars($transaction['category']) ?></td>
                                                <td class="<?= $transaction['type'] ?>"><?= ucfirst(htmlspecialchars($transaction['type'])) ?></td>
                                                <td><?= htmlspecialchars($transaction['trans_date']) ?></td>
                                                <td><?= htmlspecialchars($transaction['description']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No transactions available</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleGoalDropdown() {
            const type = document.getElementById('type').value;
            const goalSection = document.getElementById('goal_section');
            goalSection.style.display = (type === 'income') ? 'block' : 'none';
        }

        // Create background particles
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('backgroundAnimation');
            const particleCount = 300;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');
                
                // Random size between 1px and 9px
                const size = Math.random() * 10 + 1;
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>