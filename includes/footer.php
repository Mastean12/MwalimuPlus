<?php
/** Shared page footer: closes <main>, loads shared JS + offline service worker. */

declare(strict_types=1);
?>
</main>
<footer class="site-footer">
    <p>Curriculum source: official KICD strand designs (demo corpus). AI output can be wrong — the teacher makes the final decision.</p>
</footer>
<script src="assets/js/app.js"></script>
<script>
    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        navigator.serviceWorker.register('offline/service-worker.js').catch(function () {});
    }
</script>
</body>
</html>
