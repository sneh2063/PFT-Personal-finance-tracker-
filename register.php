<?php
session_start();
include('connect/connection.php');

if (isset($_POST["register"])) {
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $gender = $_POST["gender"];
    $occupation = $_POST["occupation"];
    $email = $_POST["email"];
    $contact = $_POST["contact"];
    $password = $_POST["password"];

    // Check if email already exists
    $check_query = $connect->prepare("SELECT * FROM login WHERE email = ?");
    $check_query->bind_param("s", $email);
    $check_query->execute();
    $result = $check_query->get_result();
    
    if (!empty($email) && !empty($password)) {
        if ($result->num_rows > 0) {
            echo "<script>alert('User with this email already exists!');</script>";
        } else {
            // Secure password hashing
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $connect->prepare("INSERT INTO login (first_name, last_name, gender, occupation, email, contact, password, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sssssss", $first_name, $last_name, $gender, $occupation, $email, $contact, $password_hash);

            if ($stmt->execute()) {
                // OTP Generation & Email Sending
                $otp = rand(100000, 999999);
                $_SESSION['otp'] = $otp;
                $_SESSION['mail'] = $email;

                require "Mail/phpmailer/PHPMailerAutoload.php";
                $mail = new PHPMailer;

                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->Port = 587;
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = 'tls';

                $mail->Username = '210490131017.sneh@gmail.com';
                $mail->Password = 'txra pvhh pzwf fpzu';

                $mail->setFrom('210490131017.sneh@gmail.com', 'OTP Verification');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = "Your Verification Code";
                $mail->Body = "<p>Dear user,</p><h3>Your OTP code is <b>$otp</b></h3><br><p>Best regards,</p><b>Your Website</b>";

                if (!$mail->send()) {
                    echo "<script>alert('Register Failed, Invalid Email');</script>";
                } else {
                    echo "<script>alert('Registered Successfully, OTP sent to $email'); window.location.replace('verification.php');</script>";
                }
            } else {
                echo "<script>alert('Registration failed!');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register - Personal Finance Tracker</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

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
            0% { transform: translate(0, 0); }
            100% { transform: translate(100vw, 100vh); }
        }

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

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: none;
            margin-top: 80px;
            background-color: white;
            position: relative;
            z-index: 1;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .card-header {
            background-color: #2C3E50;
            color: white;
            border-bottom: none;
            padding: 20px;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            position: relative;
            z-index: 1;
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

        .form-group label {
            font-weight: 500;
            color: #333;
        }

        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 140 140' width='16' height='16' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill='%23333' d='M70 90L35 50h70z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 12px;
            padding-right: 2rem;
            min-height: 45px;
        }

        .form-text {
            font-size: 0.875em;
            margin-top: 0.25rem;
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

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animated {
            animation: fadeIn 1s ease-in-out;
        }
    </style>
</head>
<body>
    <div class="background-animation" id="backgroundAnimation"></div>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <span class="logo-icon">💰</span>
                Personal Finance Tracker
            </a>
            <ul class="nav-links">
                <li><a href="index.html">Home</a></li>
                <li><a href="index.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </div>
    </nav>

    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card animated">
                    <div class="card-header text-center">
                        <h4>Register</h4>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>

                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>

                            <div class="form-group">
                                <label>Gender</label>
                                <select class="form-control" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Occupation</label>
                                <input type="text" class="form-control" name="occupation" required>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>

                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" class="form-control" name="contact" required>
                            </div>

                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" class="form-control" name="password" id="password" required>
                                <small id="passwordHelpBlock" class="form-text text-muted">
                                    Must be at least 8 characters long.
                                </small>
                            </div>

                            <button type="submit" name="register" class="btn btn-primary btn-block">Register</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('backgroundAnimation');
            const particleCount = 300;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.classList.add('particle');

                const size = Math.random() * 10 + 1;
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;

                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;

                particle.style.animationDuration = `${Math.random() * 10 + 15}s`;
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
