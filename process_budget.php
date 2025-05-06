<?php
session_start();
include('connect/connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category']);
    $amount = (float)$_POST['amount'];
    $period = $_POST['period'] ?? 'monthly';

    if (empty($category) || $amount <= 0 || !in_array($period, ['monthly', 'weekly'])) {
        $errors[] = "Invalid budget data. Please fill in all fields correctly.";
    } else {
        try {
            $stmt = $connect->prepare("
                INSERT INTO budgets (user_id, category, amount, period)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("isds", $user_id, $category, $amount, $period);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $_SESSION['success'] = "Budget added successfully!";
            } else {
                $errors[] = "Failed to add budget.";
            }
            header("Location: dashboard.php");
            exit();
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

$_SESSION['errors'] = $errors;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Budget - Personal Finance Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
            color: #333;
            margin: 0;
            padding: 0;
            position: relative;
            overflow: hidden;
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

        /* Navigation */
        .navbar {
            background-color: #2C3E50;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-radius: 0 0 10px 10px;
            position: relative;
            z-index: 10;
        }

        .navbar .navbar-brand {
            color: white;
            font-weight: bold;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
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
            position: relative;
            z-index: 10;
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
        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: border-color 0.3s ease;
            padding: 15px;
            font-size: 16px;
            color: #333;
        }

        .form-control:focus {
            border-color: #1ABC9C;
            box-shadow: none;
        }

        .btn-primary {
            background-color: #2C3E50;
            border-color: #2C3E50;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-weight: bold;
            padding: 15px 30px;
            font-size: 16px;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #1a2530;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-outline-secondary {
            border-color: #2C3E50;
            color: #2C3E50;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-weight: bold;
            padding: 12px 25px;
            font-size: 16px;
        }

        .btn-outline-secondary:hover {
            background-color: #2C3E50;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px;
        }

        .dashboard-btn {
            margin-top: 20px;
            text-align: center;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 10px;
        }

        .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: border-color 0.3s ease;
            padding: 15px;
            font-size: 16px;
            color: #333;
        }

        .form-select:focus {
            border-color: #1ABC9C;
            box-shadow: none;
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

    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header text-center">
                            <h3>Add Budget</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($_SESSION['errors'])): ?>
                                <div class="alert alert-danger">
                                    <?php foreach ($_SESSION['errors'] as $error) {
                                        echo "<p>$error</p>";
                                    }
                                    unset($_SESSION['errors']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($_SESSION['success'])): ?>
                                <div class="alert alert-success">
                                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                </div>
                            <?php endif; ?>

                            <form action="" method="POST">
                                <div class="mb-4">
                                    <label for="category" class="form-label">Category</label>
                                    <input type="text" class="form-control" id="category" name="category" placeholder="Enter category name" required>
                                </div>

                                <div class="mb-4">
                                    <label for="amount" class="form-label">Budget Amount</label>
                                    <input type="number" step="0.01" class="form-control" id="amount" name="amount" placeholder="Enter budget amount" required>
                                </div>

                                <div class="mb-4">
                                    <label for="period" class="form-label">Budget Period</label>
                                    <select class="form-select" id="period" name="period" required>
                                        <option value="monthly">Monthly</option>
                                        <option value="weekly">Weekly</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">Add Budget</button>
                            </form>

                            <div class="dashboard-btn">
                                <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>