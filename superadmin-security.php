<?php
/** Superadmin: roles, permissions, and the audit log. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();

$logs = $pdo->query(
    'SELECT a.id, a.action, a.target_type, a.target_id, a.meta, a.created_at, u.name AS actor_name
       FROM audit_logs a
       LEFT JOIN users u ON u.id = a.user_id
      ORDER BY a.created_at DESC
      LIMIT 100'
)->fetchAll();

$pageTitle    = 'Security';
$activeNav    = 'admin-security';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '🔒';
$pageHeading  = 'Security';
$pageSubtitle = 'Roles, permissions, and a log of every privileged action.';
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'Security'],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel" style="margin-top:0;">
    <h2>Roles &amp; permissions</h2>
    <p class="muted">MwalimuPlus has two roles — there's no separate permissions system to configure beyond this.</p>
    <table class="table">
        <thead><tr><th>Role</th><th>Can</th></tr></thead>
        <tbody>
            <tr>
                <td><span class="badge" style="background:var(--surface-2); color:var(--ink);">Teacher</span></td>
                <td>Browse subjects/topics, generate and manage their own lessons and schemes of work.</td>
            </tr>
            <tr>
                <td><span class="badge badge-scheduled">Super admin</span></td>
                <td>Everything a teacher can, plus: create/approve/suspend/delete any teacher account, edit the curriculum catalogue, change platform/AI settings, and view analytics and this audit log. Held only by the dedicated <code>admin@mwalimuplus.com</code> login — never granted to a teacher account automatically.</td>
            </tr>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>Audit log</h2>
    <p class="muted">The last 100 privileged actions — logins, account changes, and role changes.</p>
    <?php if ($logs): ?>
        <table class="table">
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars(date('d M Y H:i', strtotime((string) $log['created_at']))) ?></td>
                    <td><?= htmlspecialchars($log['actor_name'] ?? 'system / anonymous') ?></td>
                    <td><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></td>
                    <td><?= $log['target_type'] !== '' ? htmlspecialchars($log['target_type']) . ' #' . (int) $log['target_id'] : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">No audit events recorded yet.</p>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
