<?php
// api/admin_reports.php
// Automated Compliance, Security, Handover, Financial, Overdue & Attendance Reports (JSON, CSV & Pure-PHP PDF)

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/pdf_generator.php';

initApi(['GET']);
requireApiPermission('reports.view');

$societyId = (int)$_SESSION['society_id'];
$report = trim((string)($_GET['report'] ?? 'security_activity'));
$format = trim((string)($_GET['format'] ?? 'json'));
$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));

function rptCsvSafe($val): string {
    $s = (string)($val ?? '');
    if (preg_match('/^[=+\-@\t\r]/', $s)) return "'" . $s;
    return $s;
}

try {
    $socName = (string)($conn->query("SELECT society_name FROM sms_societies WHERE id = {$societyId}")->fetchColumn() ?: 'SecureGate Society');
    $title = 'Society Report';
    $headers = [];
    $rows = [];

    if ($report === 'security_activity') {
        $title = "Security & Gate Activity ({$from} to {$to})";
        $headers = ['Visitor', 'Category', 'Unit', 'Gate ID', 'Status', 'Entry Time'];
        $stmt = $conn->prepare(
            "SELECT visitor_name, visitor_type, unit_number, COALESCE(CAST(gate_id AS CHAR), 'Main'), status, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
             FROM sms_visitors
             WHERE society_id = :sid AND DATE(created_at) BETWEEN :f AND :t
             ORDER BY created_at DESC LIMIT 200"
        );
        $stmt->execute([':sid' => $societyId, ':f' => $from, ':t' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'gate_handovers') {
        $title = "Gate Shift Handover Compliance ({$from} to {$to})";
        $headers = ['Gate ID', 'Shift', 'Outgoing Guard', 'Incoming Guard', 'Status', 'Emergency?', 'Handed Over At'];
        $stmt = $conn->prepare(
            "SELECT h.gate_id, h.shift_name, CONCAT(po.first_name,' ',po.last_name), COALESCE(CONCAT(pi.first_name,' ',pi.last_name), 'Pending'),
                    h.status, IF(h.is_emergency_handover=1,'YES','NO'), DATE_FORMAT(h.created_at, '%Y-%m-%d %H:%i')
             FROM sms_gate_handovers h
             LEFT JOIN sms_user_profiles po ON h.outgoing_guard_id = po.user_id
             LEFT JOIN sms_user_profiles pi ON h.incoming_guard_id = pi.user_id
             WHERE h.society_id = :sid AND DATE(h.created_at) BETWEEN :f AND :t
             ORDER BY h.created_at DESC LIMIT 150"
        );
        $stmt->execute([':sid' => $societyId, ':f' => $from, ':t' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'financial_collection') {
        $title = "Maintenance Financial Collection Report";
        $headers = ['Invoice #', 'Unit', 'Title', 'Amount', 'Penalty', 'Paid', 'Balance', 'Status'];
        $stmt = $conn->prepare(
            "SELECT COALESCE(invoice_number, CONCAT('INV-', id)), COALESCE(unit_number, 'N/A'), title, amount, penalty_amount, paid_amount, COALESCE(balance_amount, 0), status
             FROM sms_invoices
             WHERE society_id = :sid
             ORDER BY due_date DESC LIMIT 250"
        );
        $stmt->execute([':sid' => $societyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'overdue_payments') {
        $title = "Overdue & Unpaid Dues Escalation Report";
        $headers = ['Invoice #', 'Resident', 'Unit', 'Balance Due', 'Due Date', 'Status'];
        $stmt = $conn->prepare(
            "SELECT COALESCE(i.invoice_number, CONCAT('INV-', i.id)), CONCAT(p.first_name,' ',p.last_name),
                    COALESCE(i.unit_number, uu.unit_number, 'N/A'), COALESCE(i.balance_amount, i.amount), i.due_date, i.status
             FROM sms_invoices i
             INNER JOIN sms_user_profiles p ON i.resident_id = p.user_id
             LEFT JOIN sms_user_units uu ON i.resident_id = uu.user_id AND uu.is_current_occupant = 1
             WHERE i.society_id = :sid AND i.status IN ('Unpaid','Partially_Paid','Overdue')
             ORDER BY i.due_date ASC LIMIT 200"
        );
        $stmt->execute([':sid' => $societyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } elseif ($report === 'staff_attendance') {
        $title = "Security & Society Staff Attendance Log ({$from} to {$to})";
        $headers = ['Type', 'Staff Name', 'Shift', 'Status', 'Check In', 'Check Out'];
        $stmt = $conn->prepare(
            "SELECT staff_type, staff_name, shift_name, status, DATE_FORMAT(check_in_at, '%Y-%m-%d %H:%i'), COALESCE(DATE_FORMAT(check_out_at, '%Y-%m-%d %H:%i'), 'Active')
             FROM sms_staff_attendance
             WHERE society_id = :sid AND DATE(check_in_at) BETWEEN :f AND :t
             ORDER BY check_in_at DESC LIMIT 200"
        );
        $stmt->execute([':sid' => $societyId, ':f' => $from, ':t' => $to]);
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
    } else {
        jsonError('Unknown report type requested.', 400);
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $report . '_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_map('rptCsvSafe', $row));
        }
        fclose($out);
        exit;
    }

    if ($format === 'pdf') {
        $lines = [
            "Society: {$socName}",
            "Generated At: " . date('d M Y, h:i A'),
            "Total Records: " . count($rows),
            "---",
            implode(' | ', $headers),
            "---"
        ];
        foreach (array_slice($rows, 0, 45) as $row) {
            $lines[] = implode(' | ', array_map(fn($c) => substr((string)$c, 0, 26), $row));
        }
        generateSimplePdf($title, $lines, $report . '_' . date('Ymd') . '.pdf');
    }

    jsonSuccess(['title' => $title, 'headers' => $headers, 'rows' => $rows]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to generate requested report.');
}
