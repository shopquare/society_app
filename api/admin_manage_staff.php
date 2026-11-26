<?php
// api/admin_manage_staff.php
// Society Staff, Daily Help & Shift Attendance Management API

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiPermission('guards.manage');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $guards = $conn->prepare(
            "SELECT u.id, u.email, u.status, CONCAT(p.first_name, ' ', p.last_name) AS full_name, p.phone_number
             FROM sms_users u
             INNER JOIN sms_user_profiles p ON u.id = p.user_id
             WHERE u.society_id = :sid AND u.role_id = 4
             ORDER BY u.status ASC, full_name ASC"
        );
        $guards->execute([':sid' => $societyId]);

        $daily = $conn->prepare("SELECT * FROM sms_daily_staff WHERE society_id = :sid ORDER BY status ASC, staff_name ASC");
        $daily->execute([':sid' => $societyId]);

        $att = $conn->prepare(
            "SELECT a.*, g.gate_name
             FROM sms_staff_attendance a
             LEFT JOIN sms_gates g ON a.gate_id = g.id
             WHERE a.society_id = :sid
             ORDER BY a.check_in_at DESC LIMIT 80"
        );
        $att->execute([':sid' => $societyId]);

        jsonSuccess([
            'guards' => $guards->fetchAll(),
            'daily_staff' => $daily->fetchAll(),
            'attendance' => $att->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load staff & attendance.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'add_daily_staff') {
            $name = trim((string)($data['staff_name'] ?? ''));
            $role = trim((string)($data['role'] ?? 'Housekeeping'));
            $phone = normalizeIndianPhoneNumber((string)($data['phone_number'] ?? ''));
            if ($name === '' || $phone === '') jsonError('Staff name and valid 10-digit phone number required.', 400);

            $passCode = 'STF-' . strtoupper(bin2hex(random_bytes(3)));
            $conn->prepare(
                "INSERT INTO sms_daily_staff (society_id, staff_name, role, phone_number, staff_pass_code, status) VALUES (:sid, :sn, :rl, :ph, :pc, 'Active')"
            )->execute([':sid' => $societyId, ':sn' => $name, ':rl' => $role, ':ph' => $phone, ':pc' => $passCode]);

            $conn->prepare(
                "INSERT INTO sms_domestic_staff (society_id, staff_name, staff_type, phone_number, pass_code, is_verified) VALUES (:sid, :sn, :rl, :ph, :pc, 1)"
            )->execute([':sid' => $societyId, ':sn' => $name, ':rl' => $role, ':ph' => $phone, ':pc' => $passCode]);

            logAudit($conn, $societyId, $actorId, 'ADD_DAILY_STAFF', 'daily_staff', null, ['name' => $name, 'role' => $role]);
            jsonSuccess(['message' => "Staff member added with gate pass {$passCode}.", 'pass_code' => $passCode], 201);
        } elseif ($action === 'toggle_staff_status') {
            $id = (int)($data['staff_id'] ?? 0);
            if ($id <= 0) jsonError('Staff ID required.', 400);
            $conn->prepare("UPDATE sms_daily_staff SET status = IF(status='Active','Inactive','Active') WHERE id = :id AND society_id = :sid")
                ->execute([':id' => $id, ':sid' => $societyId]);
            jsonSuccess(['message' => 'Staff pass status updated.']);
        } elseif ($action === 'log_attendance') {
            $staffType = trim((string)($data['staff_type'] ?? 'Guard'));
            if (!in_array($staffType, ['Guard', 'Daily_Staff', 'Society_Staff'], true)) $staffType = 'Guard';
            $staffId = (int)($data['staff_id'] ?? 0);
            $staffName = trim((string)($data['staff_name'] ?? ''));
            $shift = trim((string)($data['shift_name'] ?? 'Day Shift'));
            $mode = trim((string)($data['mode'] ?? 'check_in'));

            if ($mode === 'check_out' && !empty($data['attendance_id'])) {
                $aid = (int)$data['attendance_id'];
                $conn->prepare("UPDATE sms_staff_attendance SET status = 'Checked_Out', check_out_at = NOW() WHERE id = :id AND society_id = :sid")
                    ->execute([':id' => $aid, ':sid' => $societyId]);
                jsonSuccess(['message' => 'Staff shift check-out logged.']);
            } else {
                if ($staffName === '') jsonError('Staff name required for attendance.', 400);
                $conn->prepare(
                    "INSERT INTO sms_staff_attendance (society_id, staff_type, staff_id, staff_name, shift_name, status, check_in_at, recorded_by)
                     VALUES (:sid, :st, :stid, :sn, :sh, 'On_Duty', NOW(), :by)"
                )->execute([':sid' => $societyId, ':st' => $staffType, ':stid' => $staffId, ':sn' => $staffName, ':sh' => $shift, ':by' => $actorId]);
                jsonSuccess(['message' => 'Attendance check-in recorded.'], 201);
            }
        } else {
            jsonError('Invalid staff action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update staff or attendance record.');
    }
}
