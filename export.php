<?php
session_start(); // Start session to track user login status
include('connect/connection.php'); // Ensure this file sets up the database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Session expired. Please log in again.");
}

$user_id = $_SESSION['user_id'];

// Use MySQLi instead of PDO
$connect = new mysqli("localhost", "root", "", "pft");

// Check connection
if ($connect->connect_error) {
    die("Database connection failed: " . $connect->connect_error);
}

// ✅ Fetch the user's email from the database
$user_stmt = $connect->prepare("SELECT email FROM login WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data) {
    die("Error: User not found.");
}

$user_email = $user_data['email']; // ✅ Use this email for sending the report

// ✅ Fetch transactions from the database
$stmt = $connect->prepare("SELECT * FROM transactions WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$transactions = $result->fetch_all(MYSQLI_ASSOC);

if (empty($transactions)) {
    die("No transactions found.");
}

// ✅ Generate CSV file
$filename = 'transactions_' . time() . '.csv';
$file_path = __DIR__ . '/' . $filename;

$fp = fopen($file_path, 'w');
fputcsv($fp, array_keys($transactions[0]));

foreach ($transactions as $row) {
    fputcsv($fp, $row);
}

fclose($fp);

// ✅ Ensure the file exists before sending
if (!file_exists($file_path)) {
    die("Error: CSV file not found.");
}

// ✅ Send Email using PHPMailer
require "Mail/phpmailer/PHPMailerAutoload.php";
$mail = new PHPMailer;
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com'; // Your SMTP server
$mail->SMTPAuth = true;
$mail->Username = '210490131017.sneh@gmail.com'; // Your email
$mail->Password = 'txra pvhh pzwf fpzu'; // Use an "App Password"
$mail->SMTPSecure = 'tls';
$mail->Port = 587;

$mail->setFrom('no-reply@example.com', 'Transaction Report');
$mail->addAddress($user_email); // ✅ Send email to the logged-in user
$mail->Subject = "Your Transactions Report";
$mail->Body = "Please find attached your transactions report.";
$mail->addAttachment($file_path);

if ($mail->send()) {
    echo "Email sent successfully to $user_email.";
} else {
    echo "Failed to send email. Error: " . $mail->ErrorInfo;
}

// ✅ Delete the temporary file
unlink($file_path);

// ✅ Close the database connections
$stmt->close();
$user_stmt->close();
$connect->close();
?>
