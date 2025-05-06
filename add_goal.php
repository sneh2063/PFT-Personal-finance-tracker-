<?php
session_start();
include('connect/connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $target = filter_var($_POST['target'], FILTER_VALIDATE_FLOAT);
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Validate input
    if (empty($title) || $target === false || $target <= 0 || empty($start_date) || empty($end_date)) {
        $errors[] = "Title, valid target amount, start date, and end date are required.";
    } elseif (strtotime($start_date) > strtotime($end_date)) {
        $errors[] = "Start date cannot be later than the end date.";
    }

    if (empty($errors)) {
        $stmt = $connect->prepare("
            INSERT INTO goals 
                (user_id, title, target_amount, start_date, end_date)
            VALUES 
                (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isdsd", $user_id, $title, $target, $start_date, $end_date);

        if ($stmt->execute()) {
            $success = "Goal added successfully!";
        } else {
            $errors[] = "Failed to add goal: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Financial Goal - Personal Finance Tracker</title>
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
            margin-bottom: 20px;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #ddd;
            transition: border-color 0.3s ease;
            padding: 12px 15px;
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

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            border-radius: 30px;
            transition: all 0.3s ease;
            font-weight: bold;
            padding: 12px 25px;
            font-size: 16px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
            padding: 15px;
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
                            <h3>Add Financial Goal</h3>
                        </div>
                        <div class="card-body">
                            <!-- Display Errors -->
                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?= htmlspecialchars($error) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <!-- Success Message -->
                            <?php if (!empty($success)): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="form-group">
                                    <label for="title">Goal Title</label>
                                    <input type="text" id="title" name="title" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="target">Target Amount ($)</label>
                                    <input type="number" step="0.01" id="target" name="target" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="start_date">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                                </div>

                                <div class="form-group">
                                    <label for="end_date">End Date</label>
                                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary">Add Goal</button>
                                    <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                                </div>
                            </form>
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

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>