<?php
/** Shared page footer: closes the shell, loads shared JS + offline service worker. */

declare(strict_types=1);
?>
</main>
<footer class="site-footer">
    <p>Curriculum source: official KICD strand designs (demo corpus). AI output can be wrong — the teacher makes the final decision.</p>
</footer>
<?php if (!empty($showHeader)): ?>
    </div><!-- .app-main -->
</div><!-- .app -->

<div class="modal-backdrop" hidden data-profile-modal-backdrop></div>
<div class="modal" hidden role="dialog" aria-modal="true" aria-labelledby="profile-modal-title" data-profile-modal>
    <div class="modal-header">
        <h2 id="profile-modal-title">Your profile</h2>
        <button type="button" class="modal-close" aria-label="Close" data-profile-modal-close>&times;</button>
    </div>
    <div class="modal-body" data-profile-modal-body></div>
</div>
<?php endif; ?>
<script src="assets/js/app.js"></script>
<script>
    if ('serviceWorker' in navigator) {
        var host = location.hostname;
        var isLocalDev = host === 'localhost' || host === '127.0.0.1' || host === '[::1]';
        if (location.protocol === 'https:' || isLocalDev) {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        }
    }
</script>
</body>
</html>
