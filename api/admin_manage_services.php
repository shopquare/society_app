<?php
// api/admin_manage_services.php
// Admin & Committee Management of Local Service Categories, Providers & Reports

require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/audit_logger.php';

initApi(['GET', 'POST']);
requireApiPermission('services.view');

$societyId = (int)$_SESSION['society_id'];
$actorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $cats = $conn->query("SELECT * FROM sms_service_categories ORDER BY display_order ASC, name ASC")->fetchAll();

        $provs = $conn->prepare(
            "SELECT sp.*, sc.name AS category_name, CONCAT(p.first_name, ' ', p.last_name) AS submitted_by_name
             FROM sms_service_providers sp
             INNER JOIN sms_service_categories sc ON sp.category_id = sc.id
             LEFT JOIN sms_user_profiles p ON sp.submitted_by = p.user_id
             WHERE sp.society_id = :sid
             ORDER BY FIELD(sp.approval_status, 'Pending', 'Approved', 'Rejected'), sp.is_featured DESC, sp.created_at DESC"
        );
        $provs->execute([':sid' => $societyId]);

        $reps = $conn->prepare(
            "SELECT sr.*, sp.business_name, CONCAT(p.first_name, ' ', p.last_name) AS reporter_name
             FROM sms_service_reports sr
             INNER JOIN sms_service_providers sp ON sr.provider_id = sp.id
             LEFT JOIN sms_user_profiles p ON sr.reported_by = p.user_id
             WHERE sr.society_id = :sid
             ORDER BY FIELD(sr.status, 'Open', 'Reviewed', 'Resolved', 'Dismissed'), sr.created_at DESC LIMIT 80"
        );
        $reps->execute([':sid' => $societyId]);

        jsonSuccess(['categories' => $cats, 'providers' => $provs->fetchAll(), 'reports' => $reps->fetchAll()]);
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to load admin service management data.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireApiPermission('services.manage');
    $data = getJsonInput();
    requireValidCsrfToken($data['csrf_token'] ?? '');
    $action = trim((string)($data['action'] ?? ''));

    try {
        if ($action === 'save_provider') {
            $id = (int)($data['id'] ?? 0);
            $catId = (int)($data['category_id'] ?? 0);
            $name = trim((string)($data['business_name'] ?? ''));
            $cp = trim((string)($data['contact_person'] ?? ''));
            $phone = normalizeIndianPhoneNumber((string)($data['phone_number'] ?? ''));
            $wa = trim((string)($data['whatsapp_number'] ?? ''));
            $area = trim((string)($data['service_area'] ?? ''));
            $desc = trim((string)($data['description'] ?? ''));
            $hours = trim((string)($data['timing_hours'] ?? '8:00 AM - 8:00 PM'));
            $emerg = !empty($data['is_emergency_service']) ? 1 : 0;
            $ver = !empty($data['is_verified']) ? 1 : 0;
            $feat = !empty($data['is_featured']) ? 1 : 0;
            $st = trim((string)($data['approval_status'] ?? 'Approved'));
            if (!in_array($st, ['Pending', 'Approved', 'Rejected'], true)) $st = 'Approved';
            if ($catId <= 0 || $name === '' || $phone === '') jsonError('Category, name, and valid phone required.', 400);

            if ($id > 0) {
                $conn->prepare(
                    "UPDATE sms_service_providers SET category_id=:cid, business_name=:bn, contact_person=:cp, phone_number=:ph, whatsapp_number=:wa, service_area=:sa, description=:ds, timing_hours=:th, is_emergency_service=:em, is_verified=:ver, is_featured=:ft, approval_status=:st WHERE id=:id AND society_id=:sid"
                )->execute([':cid' => $catId, ':bn' => $name, ':cp' => $cp ?: null, ':ph' => $phone, ':wa' => $wa ?: $phone, ':sa' => $area ?: null, ':ds' => $desc ?: null, ':th' => $hours, ':em' => $emerg, ':ver' => $ver, ':ft' => $feat, ':st' => $st, ':id' => $id, ':sid' => $societyId]);
            } else {
                $conn->prepare(
                    "INSERT INTO sms_service_providers (society_id, category_id, submitted_by, business_name, contact_person, phone_number, whatsapp_number, service_area, description, timing_hours, is_emergency_service, is_verified, is_featured, approval_status, verified_by, verified_at)
                     VALUES (:sid, :cid, :by, :bn, :cp, :ph, :wa, :sa, :ds, :th, :em, :ver, :ft, :st, :vby, NOW())"
                )->execute([':sid' => $societyId, ':cid' => $catId, ':by' => $actorId, ':bn' => $name, ':cp' => $cp ?: null, ':ph' => $phone, ':wa' => $wa ?: $phone, ':sa' => $area ?: null, ':ds' => $desc ?: null, ':th' => $hours, ':em' => $emerg, ':ver' => $ver, ':ft' => $feat, ':st' => $st, ':vby' => $ver ? $actorId : null]);
            }
            logAudit($conn, $societyId, $actorId, 'SAVE_SERVICE_PROVIDER', 'service_provider', $id ?: (int)$conn->lastInsertId(), ['name' => $name]);
            jsonSuccess(['message' => 'Service provider saved cleanly.']);
        } elseif ($action === 'moderate_provider') {
            $id = (int)($data['id'] ?? 0);
            $op = trim((string)($data['operation'] ?? ''));
            if ($id <= 0) jsonError('Provider ID required.', 400);

            if ($op === 'approve') {
                $conn->prepare("UPDATE sms_service_providers SET approval_status='Approved', is_active=1 WHERE id=:id AND society_id=:sid")->execute([':id' => $id, ':sid' => $societyId]);
            } elseif ($op === 'reject') {
                $conn->prepare("UPDATE sms_service_providers SET approval_status='Rejected', is_active=0 WHERE id=:id AND society_id=:sid")->execute([':id' => $id, ':sid' => $societyId]);
            } elseif ($op === 'toggle_verified') {
                $conn->prepare("UPDATE sms_service_providers SET is_verified = 1 - is_verified, verified_by=:by, verified_at=NOW() WHERE id=:id AND society_id=:sid")->execute([':by' => $actorId, ':id' => $id, ':sid' => $societyId]);
            } elseif ($op === 'toggle_featured') {
                $conn->prepare("UPDATE sms_service_providers SET is_featured = 1 - is_featured WHERE id=:id AND society_id=:sid")->execute([':id' => $id, ':sid' => $societyId]);
            } elseif ($op === 'toggle_active') {
                $conn->prepare("UPDATE sms_service_providers SET is_active = 1 - is_active WHERE id=:id AND society_id=:sid")->execute([':id' => $id, ':sid' => $societyId]);
            } else {
                jsonError('Invalid moderation operation.', 400);
            }
            logAudit($conn, $societyId, $actorId, 'MODERATE_SERVICE', 'service_provider', $id, ['op' => $op]);
            jsonSuccess(['message' => 'Service provider status updated.']);
        } elseif ($action === 'resolve_report') {
            $rid = (int)($data['report_id'] ?? 0);
            $st = trim((string)($data['status'] ?? 'Resolved'));
            $notes = trim((string)($data['resolution_notes'] ?? ''));
            $deactivate = !empty($data['deactivate_provider']);
            if ($rid <= 0 || !in_array($st, ['Reviewed', 'Resolved', 'Dismissed'], true)) jsonError('Invalid report payload.', 400);

            $conn->prepare("UPDATE sms_service_reports SET status=:st, reviewed_by=:by, resolution_notes=:rn WHERE id=:id AND society_id=:sid")
                ->execute([':st' => $st, ':by' => $actorId, ':rn' => $notes ?: null, ':id' => $rid, ':sid' => $societyId]);
            if ($deactivate) {
                $conn->prepare("UPDATE sms_service_providers sp INNER JOIN sms_service_reports sr ON sp.id = sr.provider_id SET sp.is_active = 0 WHERE sr.id = :rid AND sp.society_id = :sid")
                    ->execute([':rid' => $rid, ':sid' => $societyId]);
            }
            jsonSuccess(['message' => 'Report updated cleanly.']);
        } elseif ($action === 'save_category') {
            $name = trim((string)($data['name'] ?? ''));
            $icon = trim((string)($data['icon'] ?? '🔧'));
            if ($name === '') jsonError('Category name required.', 400);
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $conn->prepare("INSERT IGNORE INTO sms_service_categories (name, slug, icon, display_order) VALUES (:n, :s, :i, 99)")
                ->execute([':n' => $name, ':s' => $slug, ':i' => $icon]);
            jsonSuccess(['message' => 'Service category created.'], 201);
        } else {
            jsonError('Invalid action.', 400);
        }
    } catch (Throwable $e) {
        logAndFail($e, 'Unable to update service management record.');
    }
}
