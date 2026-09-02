<?php
/**
 * Profile content: account summary + account-details/change-password forms.
 * Shared by profile.php (full page) and its AJAX response (modal content).
 * Expects $user, $lessonCount, $csrfToken to be set by the caller.
 */

declare(strict_types=1);
?>
<section class="panel profile-summary">
    <span class="user-avatar user-avatar-lg" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?></span>
    <div>
        <h2><?= htmlspecialchars($user['name']) ?></h2>
        <p class="muted"><?= htmlspecialchars($user['email']) ?></p>
        <p class="muted">Member since <?= htmlspecialchars(date('j F Y', strtotime($user['created_at']))) ?> · <?= $lessonCount ?> lesson<?= $lessonCount === 1 ? '' : 's' ?> generated</p>
    </div>
</section>

<section class="panel">
    <h2>Account details</h2>
    <form method="post" action="profile.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="form_action" value="update_profile">

        <label for="name">Name</label>
        <input type="text" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required autocomplete="name">

        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required autocomplete="email">

        <button type="submit" class="btn btn-primary">Save changes</button>
    </form>
</section>

<section class="panel">
    <h2>Change password</h2>
    <form method="post" action="profile.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="form_action" value="change_password">

        <div class="password-field">
            <label for="current_password">Current password</label>
            <div class="password-input-wrap">
                <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
                <button type="button" class="password-toggle" aria-pressed="false" aria-label="Show password" data-toggle-password="current_password">Show</button>
            </div>
        </div>

        <div class="password-field">
            <label for="password">New password</label>
            <div class="password-input-wrap">
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" aria-pressed="false" aria-label="Show password" data-toggle-password="password">Show</button>
            </div>
            <div class="strength-meter" aria-hidden="true">
                <span class="strength-bar" data-strength-bar></span>
            </div>
            <p class="field-hint" data-strength-label>At least 8 characters.</p>
        </div>

        <div class="password-field">
            <label for="confirm">Confirm new password</label>
            <div class="password-input-wrap">
                <input type="password" id="confirm" name="confirm" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" aria-pressed="false" aria-label="Show password" data-toggle-password="confirm">Show</button>
            </div>
            <p class="field-hint match-hint" data-match-hint role="status"></p>
        </div>

        <button type="submit" class="btn btn-primary">Change password</button>
    </form>
</section>
