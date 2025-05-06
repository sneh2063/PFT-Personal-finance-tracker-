<?php
session_start();
include('connect/connection.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$queryType = $_GET['type'] ?? '';
$category = $_GET['category'] ?? false;
$trend = $_GET['trend'] ?? false;

header('Content-Type: application/json');

// Fetch category-wise expense data
if ($queryType === 'expense' && $category) {
    $stmt = $connect->prepare("
        SELECT category, SUM(amount) AS total
        FROM transactions
        WHERE user_id = ? AND type = 'expense' AND category != ''
        GROUP BY category
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [
        'categories' => [],
        'amounts' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $data['categories'][] = $row['category'];
        $data['amounts'][] = (float) $row['total'];
    }

    echo json_encode($data);
    exit;
}

// Fetch financial trends (Daily, Weekly, Monthly)
if ($trend) {
    if ($trend === 'daily') {
        $stmt = $connect->prepare("
            SELECT DATE(trans_date) AS label,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
            FROM transactions
            WHERE user_id = ?
            GROUP BY label
            ORDER BY label DESC
            LIMIT 30
        ");
    } elseif ($trend === 'weekly') {
        $stmt = $connect->prepare("
            SELECT DATE_FORMAT(trans_date, '%Y-%u') AS label,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
            FROM transactions
            WHERE user_id = ?
            GROUP BY label
            ORDER BY label DESC
            LIMIT 12
        ");
    } elseif ($trend === 'monthly') {
        $stmt = $connect->prepare("
            SELECT DATE_FORMAT(trans_date, '%Y-%m') AS label,
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expense
            FROM transactions
            WHERE user_id = ?
            GROUP BY label
            ORDER BY label DESC
            LIMIT 12
        ");
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid trend type']);
        exit;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $trendData = [];

    while ($row = $result->fetch_assoc()) {
        $trendData[] = [
            'label' => $row['label'],
            'income' => (float) $row['income'],
            'expense' => (float) $row['expense']
        ];
    }

    echo json_encode($trendData);
    exit;
}

// Invalid Request
http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
?>
