<?php
// api/admin_moderation_center.php
// Unified Admin Content Moderation Queue & Resident/Tenant Move-In/Out KYC Console

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/notification_service.php';

initApi(['GET', 'POST']);
requireApiPermission('community.moderate');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $mktReports = $conn->prepare(
            "SELECT r.id, r.listing_id, r.reason, r.status, r.created_at,
                    l.title AS listing_title, l.category, l.status AS listing_status, l.unit_number,
                    CONCAT(p.first_name, ' ', p.last_name) AS reporter_name
             FROM sms_marketplace_reports r
             INNER JOIN sms_marketplace_listings l ON r.listing_id = l.id
             LEFT JOIN sms_user_profiles p ON r.reporter_id = p.user_id
             WHERE r.society_id = :sid
             ORDER BY FIELD(r.status, 'Pending', 'Reviewed', 'Dismissed'), r.created_at DESC LIMIT 60"
        );
        $mktReports->execute([':sid' => $societyId]);

        $discReports = $conn->prepare(
            "SELECT dr.id, dr.discussion_id, dr.reason, dr.status, dr.created_at,
                    d.title AS discussion_title, d.status AS discussion_status,
                    CONCAT(p.first_name, ' ', p.last_name) AS reporter_name
             FROM sms_discussion_reports dr
             INNER JOIN sms_discussions d ON dr.discussion_id = d.id
             LEFT JOIN sms_user_profiles p ON dr.reported_by = p.user_id
             WHERE dr.society_id = :sid
             ORDER BY FIELD(dr.status, 'Open', 'Reviewed', 'Dismissed'), dr.created_at DESC LIMIT 60"
        );
        $discReports->execute([':sid' => $societyId]);

        $svcReports = $conn->prepare(
            "SELECT sr.id, sr.provider_id, sr.reason, sr.details, sr.status, sr.created_at,
                    sp.business_name, sp.is_active AS provider_active,
                    CONCAT(p.first_name, ' ', p.last_name) AS reporter_name
             FROM sms_service_reports sr
             INNER JOIN sms_service_providers sp ON sr.provider_id = sp.id
             LEFT JOIN sms_user_profiles p ON sr.reported_by = p.user_id
             WHERE sr.society_id = :sid
             ORDER BY FIELD(sr.status, 'Open', 'Reviewed', 'Resolved', 'Dismissed'), sr.created_at DESC LIMIT 60"
        );
        $svcReports->execute([':sid' => $societyId]);

        $tenancies = $conn->prepare(
            "SELECT uu.id, uu.user_id, uu.unit_number, uu.occupancy_type, uu.move_in_date, uu.move_out_date,
                    uu.is_current_occupant, uu.verification_status, uu.lease_end_date, uu.kyc_notes,
                    CONCAT(p.first_name, ' ', p.last_name) AS resident_name, p.phone_number, u.email, u.status AS user_status
             FROM sms_user_units uu
             INNER JOIN sms_users u ON uu.user_id = u.id
             INNER JOIN sms_user_profiles p ON u.id = p.user_id
             WHERE uu.society_id = :sid
             ORDER BY FIELD(uu.verification_status, 'Pending', 'Expired', 'Verified', 'Moved_Out'), uu.occupancy_type DESC, uu.unit_number ASC LIMIT 120"
        );
        $tenancies->execute([':sid' => $societyId]);

        jsonSuccess([
            'marketplace_reports' => $mktReports->fetchAll(),
            'discussion_reports' => $discReports->fetchAll(),
            'service_reports' => $svcReports->fetchAll(),
            'tenancies' => $tenancies->fetchAll()
        ]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load moderation center data.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'moderate_marketplace') {
            $rid = (int)($data['report_id'] ?? 0);
            $op = trim((string)($data['operation'] ?? 'dismiss'));
            if ($rid <= 0) jsonError('Report ID required.', 400);

            if ($op === 'archive_listing') {
                $conn->prepare(
                    "UPDATE sms_marketplace_listings l
                     INNER JOIN sms_marketplace_reports r ON l.id = r.listing_id
                     SET l.status = 'Archived', r.status = 'Reviewed'
                     WHERE r.id = :rid AND r.society_id = :sid"
                )->execute([':rid' => $rid, ':sid' => $societyId]);
            } else {
                $conn->prepare("UPDATE sms_marketplace_reports SET status = 'Dismissed' WHERE id = :rid AND society_id = :sid")
                    ->execute([':rid' => $rid, ':sid' => $societyId]);
            }
            logAudit($conn, $societyId, $actorId, 'MODERATE_MARKETPLACE', 'marketplace_report', $rid, ['op' => $op]);
            jsonSuccess(['message' => 'Marketplace report processed.']);
        } elseif ($action === 'moderate_discussion_report') {
            $rid = (int)($data['report_id'] ?? 0);
            $op = trim((string)($data['operation'] ?? 'dismiss'));
            if ($rid <= 0) jsonError('Report ID required.', 400);

            if ($op === 'remove_post') {
                $conn->prepare(
                    "UPDATE sms_discussions d
                     INNER JOIN sms_discussion_reports r ON d.id = r.discussion_id
                     SET d.status = 'Removed', r.status = 'Reviewed', r.reviewed_by = :by
                     WHERE r.id = :rid AND r.society_id = :sid"
                )->execute([':by' => $actorId, ':rid' => $rid, ':sid' => $societyId]);
            } else {
                $conn->prepare("UPDATE sms_discussion_reports SET status = 'Dismissed', reviewed_by = :by WHERE id = :rid AND society_id = :sid")
                    ->execute([':by' => $actorId, ':rid' => $rid, ':sid' => $societyId]);
            }
            logAudit($conn, $societyId, $actorId, 'MODERATE_DISCUSSION', 'discussion_report', $rid, ['op' => $op]);
            jsonSuccess(['message' => 'Discussion report handled.']);
        } elseif ($action === 'moderate_service_report') {
            $rid = (int)($data['report_id'] ?? 0);
            $op = trim((string)($data['operation'] ?? 'resolve'));
            if ($rid <= 0) jsonError('Report ID required.', 400);

            if ($op === 'disable_provider') {
                $conn->prepare(
                    "UPDATE sms_service_providers sp
                     INNER JOIN sms_service_reports sr ON sp.id = sr.provider_id
                     SET sp.is_active = 0, sr.status = 'Resolved', sr.reviewed_by = :by
                     WHERE sr.id = :rid AND sr.society_id = :sid"
                )->execute([':by' => $actorId, ':rid' => $rid, ':sid' => $societyId]);
            } else {
                $conn->prepare("UPDATE sms_service_reports SET status = 'Dismissed', reviewed_by = :by WHERE id = :rid AND society_id = :sid")
                    ->execute([':by' => $actorId, ':rid' => $rid, ':sid' => $societyId]);
            }
            logAudit($conn, $societyId, $actorId, 'MODERATE_SERVICE_REPORT', 'service_report', $rid, ['op' => $op]);
            jsonSuccess(['message' => 'Service report resolved.']);
        } elseif ($action === 'update_tenancy_verification') {
            requireApiPermission('residents.manage');
            $unitLinkId = (int)($data['unit_link_id'] ?? 0);
            $verStatus = trim((string)($data['verification_status'] ?? 'Verified'));
            $leaseEnd = trim((string)($data['lease_end_date'] ?? ''));
            $moveOut = trim((string)($data['move_out_date'] ?? ''));
            $notes = trim((string)($data['kyc_notes'] ?? ''));
            if ($unitLinkId <= 0 || !in_array($verStatus, ['Pending', 'Verified', 'Expired', 'Moved_Out'], true)) {
                jsonError('Invalid unit occupancy verification payload.', 400);
            }

            $isCurrent = ($verStatus === 'Moved_Out') ? 0 : 1;
            $conn->prepare(
                "UPDATE sms_user_units
                 SET verification_status = :vs, lease_end_date = :le, move_out_date = :mo, is_current_occupant = :cur, kyc_notes = :kn
                 WHERE id = :id AND society_id = :sid"
            )->execute([
                ':vs' => $verStatus, ':le' => $leaseEnd ?: null, ':mo' => $moveOut ?: null,
                ':cur' => $isCurrent, ':kn' => $notes ?: null, ':id' => $unitLinkId, ':sid' => $societyId
            ]);
            logAudit($conn, $societyId, $actorId, 'UPDATE_TENANCY_KYC', 'user_unit', $unitLinkId, ['verification_status' => $verStatus]);
            jsonSuccess(['message' => 'Occupancy verification updated cleanly.']);
        } else {
            jsonError('Invalid moderation action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update moderation record.');
    }
}
