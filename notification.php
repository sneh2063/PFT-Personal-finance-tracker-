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
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Ensure this path is correct

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
            echo "Email sent successfully to $to.";
        } else {
            echo "Failed to send email. Error: " . $mail->ErrorInfo;
        }
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
    $category = $budget['category'];
    $budgeted = $budget['budgeted'];
    $spent = $budget['spent'];
    $progress = ($spent / $budgeted) * 100;

    if ($progress >= 10) { // Notify if 80% of budget is spent
        $subject = "Budget Alert: $category";
        $body = "You have spent $spent out of your budgeted $budgeted for $category. This is $progress% of your budget.";
        sendEmail($user_email, $subject, $body);
    }
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

    if ($progress >= 10) { // Notify if 80% of goal is achieved
        $subject = "Goal Progress: $title";
        $body = "You have saved $saved_amount out of your target $target_amount for $title. This is $progress% of your goal.";
        sendEmail($user_email, $subject, $body);
    }
}
?>