<?php
session_start();
include('connect/connection.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Ensure PHPMailer is installed via Composer

// Check database connection
if ($connect->connect_error) {
    die("Database Connection Failed: " . $connect->connect_error);
}

// 🔹 Process Recurring Transactions
$stmt = $connect->prepare("
    SELECT * FROM recurring_transactions 
    WHERE next_date <= CURDATE() AND (end_date IS NULL OR end_date >= CURDATE())
");

if (!$stmt) {
    die("Prepare Statement Failed: " . $connect->error);
}

$stmt->execute();
$result = $stmt->get_result();
$recurring = $result->fetch_all(MYSQLI_ASSOC);

foreach ($recurring as $rt) {
    try {
        $connect->begin_transaction();

        // Insert new transaction
        $stmt = $connect->prepare("
            INSERT INTO transactions 
                (user_id, amount, type, category, trans_date, description)
            VALUES 
                (?, ?, 'expense', 'Recurring Payment', ?, '')
        ");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connect->error);
        }

        $trans_date = date('Y-m-d');
        $stmt->bind_param("ids", $rt['user_id'], $rt['amount'], $trans_date);
        
        if (!$stmt->execute()) {
            throw new Exception("Transaction Insert Failed: " . $stmt->error);
        }

        // Update next recurring transaction date
        $next_date = date('Y-m-d', strtotime('+1 ' . $rt['interval'], strtotime($rt['next_date'])));
        $stmt = $connect->prepare("UPDATE recurring_transactions SET next_date = ? WHERE recurring_id = ?");
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $connect->error);
        }

        $stmt->bind_param("si", $next_date, $rt['recurring_id']);
        if (!$stmt->execute()) {
            throw new Exception("Update Next Date Failed: " . $stmt->error);
        }

        $connect->commit();
    } catch (Exception $e) {
        $connect->rollback();
        error_log("Transaction Error: " . $e->getMessage());
        echo "Error processing recurring transactions: " . $e->getMessage();
    }
}

// 🔹 Check for Budget Notifications
$stmt = $connect->prepare("
    SELECT 
        u.user_id, u.email, u.name, 
        b.category, b.amount AS budget, 
        COALESCE(SUM(t.amount), 0) AS spent
    FROM budgets b
    INNER JOIN users u ON u.user_id = b.user_id
    LEFT JOIN transactions t ON 
        t.user_id = u.user_id 
        AND t.category = b.category 
        AND t.type = 'expense' 
        AND MONTH(t.trans_date) = MONTH(CURDATE())
    WHERE b.period = 'monthly' 
    GROUP BY b.budget_id
    HAVING (spent / budget) >= 0.9
");

if (!$stmt) {
    die("Prepare Statement Failed: " . $connect->error);
}

$stmt->execute();
$result = $stmt->get_result();
$budget_alerts = $result->fetch_all(MYSQLI_ASSOC);

foreach ($budget_alerts as $alert) {
    $user_id = $alert['user_id'];
    $email = $alert['email'];
    $name = $alert['name'];
    $category = $alert['category'];
    $budget = $alert['budget'];
    $spent = $alert['spent'];

    // Insert notification into DB
    $stmt = $connect->prepare("
        INSERT INTO notifications 
            (user_id, message, type, notification_date, budget_id)
        VALUES 
            (?, CONCAT('Budget alert: Your ', ?, ' budget is 90% spent'), 'warning', CURDATE(), ?)
    ");

    if (!$stmt) {
        error_log("Notification Insert Failed: " . $connect->error);
        continue;
    }

    $stmt->bind_param("isi", $user_id, $category, $budget);
    if (!$stmt->execute()) {
        error_log("Notification Insert Failed: " . $stmt->error);
        continue;
    }

    // 🔹 Send Email Notification
    $mail = new PHPMailer(true);
    try {
        $mail->SMTPDebug = 2;  // Enable SMTP debugging (0 = off, 1 = commands, 2 = detailed)
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com';  // Replace with your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = '210490131017.sneh@gmail,com'; // Replace with your email
        $mail->Password = 'txra pvhh pzwf fpzu'; // Replace with your email password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your_email@example.com', 'Budget Tracker');
        $mail->addAddress($email, $name);
        $mail->Subject = "Budget Alert - $category";
        $mail->Body = "
            Dear $name, 

            Your budget for category '$category' is reaching its limit.
            Budget: $$budget
            Spent: $$spent
            
            Please review your expenses.

            Regards,
            Budget Tracker Team
        ";

        if (!$mail->send()) {
            throw new Exception("Email sending failed: " . $mail->ErrorInfo);
        }
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        echo "Error sending email: " . $e->getMessage();
    }
}
?>
