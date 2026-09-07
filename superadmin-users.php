<?php
/**
 * Superadmin: manage teacher accounts — create, approve pending
 * self-registrations, suspend/reactivate, reset a password, promote/demote,
 * or delete. Every action is written to audit_logs.
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/require-superadmin.php';

$pdo = db();
$myId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Your session expired. Please try again.');
        header('Location: superadmin-users.php');
        exit;
    }

    $action = $_POST['action'] ?? '';
    $targetId = (int) ($_POST['user_id'] ?? 0);

    if ($action === 'create') {
        $name = clip(trim((string) ($_POST['name'] ?? '')), 100);
        $email = clip(trim((string) ($_POST['email'] ?? '')), 190);
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Enter a valid name and email.');
        } elseif (strlen($password) < 8) {
            set_flash('error', 'Use a password of at least 8 characters.');
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, 'teacher', 'active')"
                );
                $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                $newId = (int) $pdo->lastInsertId();
                audit_log($myId, 'teacher_created', 'user', $newId, ['name' => $name, 'email' => $email]);
                set_flash('success', "Teacher account created for {$name}.");
            } catch (PDOException $e) {
                set_flash('error', ($e->errorInfo[1] ?? null) === 1062
                    ? 'An account with that email already exists.'
                    : 'Could not create the account right now.');
            }
        }
    } elseif ($action === 'approve' && $targetId > 0) {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'")->execute([$targetId]);
        audit_log($myId, 'teacher_approved', 'user', $targetId);
        set_flash('success', 'Account approved — the teacher can now sign in.');
    } elseif ($action === 'suspend' && $targetId > 0) {
        if ($targetId === $myId) {
            set_flash('error', 'You cannot suspend your own account.');
        } else {
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$targetId]);
            audit_log($myId, 'teacher_suspended', 'user', $targetId);
            set_flash('success', 'Account suspended.');
        }
    } elseif ($action === 'reactivate' && $targetId > 0) {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$targetId]);
        audit_log($myId, 'teacher_reactivated', 'user', $targetId);
        set_flash('success', 'Account reactivated.');
    } elseif ($action === 'reset_password' && $targetId > 0) {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            set_flash('error', 'Use a password of at least 8 characters.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $targetId]);
            audit_log($myId, 'teacher_password_reset', 'user', $targetId);
            set_flash('success', 'Password reset. Share the new password with the teacher directly.');
        }
    } elseif ($action === 'promote' && $targetId > 0) {
        $pdo->prepare("UPDATE users SET role = 'superadmin' WHERE id = ?")->execute([$targetId]);
        audit_log($myId, 'role_promoted', 'user', $targetId);
        set_flash('success', 'Account promoted to super admin.');
    } elseif ($action === 'demote' && $targetId > 0) {
        if ($targetId === $myId) {
            set_flash('error', 'You cannot remove your own super admin access.');
        } else {
            $superadminCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin'")->fetchColumn();
            if ($superadminCount <= 1) {
                set_flash('error', 'At least one super admin must remain.');
            } else {
                $pdo->prepare("UPDATE users SET role = 'teacher' WHERE id = ?")->execute([$targetId]);
                audit_log($myId, 'role_demoted', 'user', $targetId);
                set_flash('success', 'Account moved back to teacher.');
            }
        }
    } elseif ($action === 'delete' && $targetId > 0) {
        if ($targetId === $myId) {
            set_flash('error', 'You cannot delete your own account from here.');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
            audit_log($myId, 'teacher_deleted', 'user', $targetId);
            set_flash('success', 'Account deleted.');
        }
    }

    header('Location: superadmin-users.php');
    exit;
}

$users = $pdo->query(
    "SELECT u.id, u.name, u.email, u.role, u.status, u.created_at,
            (SELECT COUNT(*) FROM lessons l WHERE l.user_id = u.id) AS lesson_count,
            (SELECT COUNT(*) FROM schemes s WHERE s.user_id = u.id) AS scheme_count
       FROM users u
      ORDER BY (u.status = 'pending') DESC, u.created_at DESC"
)->fetchAll();

$csrf = csrf_token();

$pageTitle    = 'Manage teachers';
$activeNav    = 'admin-teachers';
$showHeader   = true;
$adminShell   = true;
$pageIcon     = '👥';
$pageHeading  = 'Manage teachers';
$pageSubtitle = 'Create accounts directly, approve self-registrations, and control access.';
$pageActions  = '<button type="button" class="btn btn-primary" data-open-create-teacher>+ Create teacher</button>';
$breadcrumbs  = [
    ['label' => 'Super Admin', 'href' => 'superadmin.php'],
    ['label' => 'Manage teachers'],
];
require __DIR__ . '/includes/header.php';
?>
<section class="panel" style="margin-top:0;">
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Lessons</th>
                <th>Schemes</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['name']) ?><?= (int) $u['id'] === $myId ? ' <span class="muted">(you)</span>' : '' ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td>
                    <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="badge badge-scheduled">Super admin</span>
                    <?php else: ?>
                        <span class="badge" style="background:var(--surface-2); color:var(--ink);">Teacher</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($u['status'] === 'pending'): ?>
                        <span class="badge badge-needs_verification">Pending</span>
                    <?php elseif ($u['status'] === 'suspended'): ?>
                        <span class="badge badge-unknown">Suspended</span>
                    <?php else: ?>
                        <span class="badge badge-supported">Active</span>
                    <?php endif; ?>
                </td>
                <td><a href="superadmin-teacher-activity.php?id=<?= (int) $u['id'] ?>"><?= (int) $u['lesson_count'] ?></a></td>
                <td><a href="superadmin-teacher-activity.php?id=<?= (int) $u['id'] ?>"><?= (int) $u['scheme_count'] ?></a></td>
                <td><?= htmlspecialchars(date('d M Y', strtotime((string) $u['created_at']))) ?></td>
                <td>
                    <div class="table-actions">
                        <?php if ($u['status'] === 'pending'): ?>
                            <form method="post" action="superadmin-users.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-small btn-primary">Approve</button>
                            </form>
                        <?php elseif ($u['status'] === 'suspended'): ?>
                            <form method="post" action="superadmin-users.php" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="reactivate">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-small btn-outline">Reactivate</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="superadmin-users.php" onsubmit="return confirm('Suspend <?= htmlspecialchars(addslashes($u['name'])) ?>? They will not be able to sign in.');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="suspend">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-small btn-outline" <?= (int) $u['id'] === $myId ? 'disabled' : '' ?>>Suspend</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($u['role'] === 'superadmin'): ?>
                            <form method="post" action="superadmin-users.php" onsubmit="return confirm('Remove super admin access for <?= htmlspecialchars(addslashes($u['name'])) ?>?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="demote">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-small btn-outline" <?= (int) $u['id'] === $myId ? 'disabled' : '' ?>>Demote</button>
                            </form>
                        <?php else: ?>
                            <form method="post" action="superadmin-users.php" onsubmit="return confirm('Make <?= htmlspecialchars(addslashes($u['name'])) ?> a super admin?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                <input type="hidden" name="action" value="promote">
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn btn-small btn-outline">Promote</button>
                            </form>
                        <?php endif; ?>

                        <button type="button" class="btn btn-small btn-outline" data-open-reset-password="<?= (int) $u['id'] ?>" data-name="<?= htmlspecialchars($u['name']) ?>">Reset pwd</button>

                        <form method="post" action="superadmin-users.php" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This removes their lessons and schemes too.');" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button type="submit" class="btn btn-small btn-danger-ghost" <?= (int) $u['id'] === $myId ? 'disabled' : '' ?>>Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<!-- Create teacher modal -->
<div class="modal-backdrop" hidden data-create-teacher-backdrop></div>
<div class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="create-teacher-title" data-create-teacher-modal>
    <div class="modal-header">
        <h2 id="create-teacher-title">Create teacher account</h2>
        <button type="button" class="modal-close" aria-label="Close" data-close-create-teacher>&times;</button>
    </div>
    <div class="modal-body">
        <form method="post" action="superadmin-users.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <label for="new-teacher-name">Name</label>
            <input type="text" id="new-teacher-name" name="name" required>
            <label for="new-teacher-email">Email</label>
            <input type="email" id="new-teacher-email" name="email" required>
            <label for="new-teacher-password">Temporary password</label>
            <input type="text" id="new-teacher-password" name="password" minlength="8" required>
            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
    </div>
</div>

<!-- Reset password modal -->
<div class="modal-backdrop" hidden data-reset-password-backdrop></div>
<div class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="reset-password-title" data-reset-password-modal>
    <div class="modal-header">
        <h2 id="reset-password-title">Reset password for <span id="reset-password-name"></span></h2>
        <button type="button" class="modal-close" aria-label="Close" data-close-reset-password>&times;</button>
    </div>
    <div class="modal-body">
        <form method="post" action="superadmin-users.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="reset-password-user-id" value="">
            <label for="new-password">New password</label>
            <input type="text" id="new-password" name="new_password" minlength="8" required>
            <button type="submit" class="btn btn-primary btn-block">Reset password</button>
        </form>
    </div>
</div>

<script>
(function () {
    function open(modal, backdrop) {
        modal.hidden = false;
        backdrop.hidden = false;
        document.body.classList.add('modal-open');
    }
    function close(modal, backdrop) {
        modal.hidden = true;
        backdrop.hidden = true;
        document.body.classList.remove('modal-open');
    }

    var createModal = document.querySelector('[data-create-teacher-modal]');
    var createBackdrop = document.querySelector('[data-create-teacher-backdrop]');
    document.querySelector('[data-open-create-teacher]')?.addEventListener('click', function () { open(createModal, createBackdrop); });
    document.querySelector('[data-close-create-teacher]')?.addEventListener('click', function () { close(createModal, createBackdrop); });
    createBackdrop?.addEventListener('click', function () { close(createModal, createBackdrop); });

    var resetModal = document.querySelector('[data-reset-password-modal]');
    var resetBackdrop = document.querySelector('[data-reset-password-backdrop]');
    document.querySelectorAll('[data-open-reset-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('reset-password-user-id').value = btn.getAttribute('data-open-reset-password');
            document.getElementById('reset-password-name').textContent = btn.getAttribute('data-name');
            open(resetModal, resetBackdrop);
        });
    });
    document.querySelector('[data-close-reset-password]')?.addEventListener('click', function () { close(resetModal, resetBackdrop); });
    resetBackdrop?.addEventListener('click', function () { close(resetModal, resetBackdrop); });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
