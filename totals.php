<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Loan.php';
include_once '../models/Payment.php';

$database = new Database();
$db = $database->getConnection();
$loan = new Loan($db);
$payment = new Payment($db);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit;
}

$type = $_GET['type'] ?? 'all';

switch ($type) {
    case 'overview':
        $overall = $loan->getOverallTotals();
        $payments = $payment->getTotalPayments();

        $collection_rate = $overall['total_loan_amount'] > 0
            ? round(($overall['total_paid_amount'] / $overall['total_loan_amount']) * 100, 2)
            : 0;

        echo json_encode([
            "loan_totals" => $overall,
            "payment_totals" => $payments,
            "summary" => [
                "total_loans" => $overall['total_loans'],
                "total_loan_amount" => number_format($overall['total_loan_amount'], 2),
                "total_remaining" => number_format($overall['total_remaining_amount'], 2),
                "total_paid" => number_format($overall['total_paid_amount'], 2),
                "total_payments_count" => $payments['total_payments'],
                "total_payments_amount" => number_format($payments['total_amount'], 2),
                "collection_rate" => $collection_rate
            ]
        ]);
        break;

    case 'by_type':
        $stmt = $loan->getTotalsByType();
        $result = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = [
                "loan_type" => $row['loan_type'],
                "total_loans" => $row['total_loans'],
                "total_amount" => number_format($row['total_amount'], 2),
                "total_remaining" => number_format($row['total_remaining'], 2),
                "total_paid" => number_format($row['total_paid'], 2)
            ];
        }

        echo json_encode(["totals_by_type" => $result]);
        break;

    case 'by_status':
        $stmt = $loan->getTotalsByStatus();
        $result = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = [
                "status" => $row['status'],
                "total_loans" => $row['total_loans'],
                "total_amount" => number_format($row['total_amount'], 2),
                "total_remaining" => number_format($row['total_remaining'], 2)
            ];
        }

        echo json_encode(["totals_by_status" => $result]);
        break;

    case 'monthly':
        $loan_stmt = $loan->getMonthlyTotals();
        $payment_stmt = $payment->getMonthlyPaymentTotals();

        $monthly_loans = [];
        while ($row = $loan_stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthly_loans[] = [
                "month" => $row['month'],
                "total_loans" => $row['total_loans'],
                "total_amount" => number_format($row['total_amount'], 2)
            ];
        }

        $monthly_payments = [];
        while ($row = $payment_stmt->fetch(PDO::FETCH_ASSOC)) {
            $monthly_payments[] = [
                "month" => $row['month'],
                "total_payments" => $row['total_payments'],
                "total_amount" => number_format($row['total_amount'], 2)
            ];
        }

        echo json_encode([
            "monthly_loans" => $monthly_loans,
            "monthly_payments" => $monthly_payments
        ]);
        break;

    case 'user':
        if (!isset($_GET['user_id'])) {
            http_response_code(400);
            echo json_encode(["message" => "User ID is required."]);
            break;
        }

        $user_totals = $loan->getUserTotals($_GET['user_id']);
        echo json_encode([
            "user_id" => $_GET['user_id'],
            "totals" => [
                "total_loans" => $user_totals['total_loans'],
                "total_loan_amount" => number_format($user_totals['total_loan_amount'], 2),
                "total_remaining" => number_format($user_totals['total_remaining_amount'], 2),
                "total_paid" => number_format($user_totals['total_paid_amount'], 2),
                "total_monthly_installments" => number_format($user_totals['total_monthly_installments'], 2)
            ]
        ]);
        break;

    case 'dashboard':
        $overall = $loan->getOverallTotals();
        $by_type_data = [];

        $stmt = $loan->getTotalsByType();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $by_type_data[$row['loan_type']] = $row['total_amount'];
        }

        echo json_encode([
            "personal_loans" => number_format($by_type_data['personal'] ?? 0, 0),
            "corporate_loans" => number_format($by_type_data['corporate'] ?? 0, 0),
            "business_loans" => number_format($by_type_data['business'] ?? 0, 0),
            "custom_loans" => "Choose Money",
            "total_active_loans" => $overall['total_loans'],
            "total_loan_amount" => number_format($overall['total_loan_amount'], 0),
            "total_remaining" => number_format($overall['total_remaining_amount'], 0),
            "total_monthly_installments" => number_format($overall['total_monthly_installments'], 0)
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid type specified."]);
        break;
}
