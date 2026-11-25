<?php
// /society_app/api/admin_get_stats.php

require_once __DIR__ . '/../includes/api_bootstrap.php';

initApi(['GET']);
requireApiAuth([1, 2]);

$societyId = (int)$_SESSION['society_id'];

try {
    // 1. Active Residents Count
    $stmtResidents = $conn->prepare("SELECT COUNT(*) FROM sms_users WHERE society_id = :society_id AND role_id = 3 AND status = 'active'");
    $stmtResidents->execute([':society_id' => $societyId]);
    $activeResidents = (int)$stmtResidents->fetchColumn();

    // 2. Guards Count
    $stmtGuards = $conn->prepare("SELECT COUNT(*) FROM sms_users WHERE society_id = :society_id AND role_id = 4 AND status = 'active'");
    $stmtGuards->execute([':society_id' => $societyId]);
    $activeGuards = (int)$stmtGuards->fetchColumn();

    // 3. Pending Registration Approvals
    $stmtPending = $conn->prepare("SELECT COUNT(*) FROM sms_users WHERE society_id = :society_id AND status = 'pending'");
    $stmtPending->execute([':society_id' => $societyId]);
    $pendingApprovals = (int)$stmtPending->fetchColumn();

    // 4. Open Complaints Count
    $stmtComplaints = $conn->prepare("SELECT COUNT(*) FROM sms_complaints WHERE society_id = :society_id AND status IN ('Open', 'In Progress')");
    $stmtComplaints->execute([':society_id' => $societyId]);
    $openComplaints = (int)$stmtComplaints->fetchColumn();

    // 5. Emergency Alerts (Past 7 Days & Active)
    $stmtEmergencies = $conn->prepare("SELECT COUNT(*) FROM sms_emergency_alerts WHERE society_id = :society_id AND created_at >= NOW() - INTERVAL 7 DAY");
    $stmtEmergencies->execute([':society_id' => $societyId]);
    $recentEmergencies = (int)$stmtEmergencies->fetchColumn();

    // 6. Financial Overview
    $stmtFin = $conn->prepare(
        "SELECT 
            COALESCE(SUM(amount), 0) AS total_invoiced,
            COALESCE(SUM(CASE WHEN status = 'Paid' THEN amount ELSE 0 END), 0) AS total_collected,
            COALESCE(SUM(CASE WHEN status IN ('Unpaid', 'Overdue') THEN amount ELSE 0 END), 0) AS total_pending
         FROM sms_invoices
         WHERE society_id = :society_id"
    );
    $stmtFin->execute([':society_id' => $societyId]);
    $finData = $stmtFin->fetch();

    $totalInvoiced = (float)($finData['total_invoiced'] ?? 0);
    $totalCollected = (float)($finData['total_collected'] ?? 0);
    $totalPending = (float)($finData['total_pending'] ?? 0);
    $collectionRate = $totalInvoiced > 0 ? round(($totalCollected / $totalInvoiced) * 100, 1) : 0;

    // 7. Recent Emergency Alerts List
    $stmtAlertList = $conn->prepare(
        "SELECT a.id, a.unit_number, a.alert_type, a.status, a.notes, a.created_at, a.acknowledged_at,
                CONCAT(p.first_name, ' ', p.last_name) AS resident_name, p.phone_number
         FROM sms_emergency_alerts a
         INNER JOIN sms_user_profiles p ON a.resident_id = p.user_id
         WHERE a.society_id = :society_id
         ORDER BY a.created_at DESC
         LIMIT 10"
    );
    $stmtAlertList->execute([':society_id' => $societyId]);
    $emergencyLogs = $stmtAlertList->fetchAll();

    jsonSuccess([
        'stats' => [
            'active_residents' => $activeResidents,
            'active_guards' => $activeGuards,
            'pending_approvals' => $pendingApprovals,
            'open_complaints' => $openComplaints,
            'recent_emergencies' => $recentEmergencies,
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalCollected,
            'total_pending' => $totalPending,
            'collection_rate' => $collectionRate
        ],
        'emergency_logs' => $emergencyLogs
    ]);
} catch (Throwable $e) {
    logAndFail($e, 'Unable to load society statistics.');
}
