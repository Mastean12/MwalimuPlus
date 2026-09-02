<?php
/**
 * Renders a lesson's content. Shared by lesson.php (signed-in) and
 * browse-lesson.php (public). Expects $lesson (row) and $payload (decoded
 * JSON, or null) and $isUnknown (bool) to already be set by the caller.
 */

declare(strict_types=1);
?>
<p>
    <span class="badge badge-<?= strtolower($lesson['status']) ?>"><?= htmlspecialchars($lesson['status']) ?></span>
</p>
<p class="disclosure">This is an AI assistant. It can be wrong. The teacher makes the final decision.</p>

<?php if ($isUnknown || $payload === null): ?>
    <section class="panel panel-sijui">
        <h2>Not supported by the curriculum source</h2>
        <p><?= htmlspecialchars($payload['sijui'] ?? 'I can\'t answer that from the curriculum design I have. Check the KICD office or the Ministry of Education for guidance.') ?></p>
    </section>
<?php else: ?>
    <?php $citations = $payload['citations'] ?? []; ?>
    <?php if ($citations !== []): ?>
        <p class="citations">Sources: <?= htmlspecialchars(implode(', ', $citations)) ?></p>
    <?php endif; ?>

    <section class="panel">
        <h2>Learning objectives</h2>
        <?php if (!empty($payload['objectives'])): ?>
            <ul>
                <?php foreach ($payload['objectives'] as $objective): ?>
                    <li><?= htmlspecialchars($objective) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">None listed.</p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Prerequisites</h2>
        <?php if (!empty($payload['prerequisites'])): ?>
            <ul>
                <?php foreach ($payload['prerequisites'] as $prerequisite): ?>
                    <li><?= htmlspecialchars($prerequisite) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="muted">None listed.</p>
        <?php endif; ?>
    </section>

    <?php if (!empty($payload['teacher_explanation'])): ?>
        <section class="panel">
            <h2>Teacher explanation</h2>
            <div class="prose"><?= nl2br(htmlspecialchars($payload['teacher_explanation'])) ?></div>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['examples'])): ?>
        <section class="panel">
            <h2>Examples</h2>
            <?php if (is_array($payload['examples'])): ?>
                <ol>
                    <?php foreach ($payload['examples'] as $example): ?>
                        <li><?= htmlspecialchars(is_array($example) ? json_encode($example) : $example) ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['examples'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['board_plan'])): ?>
        <section class="panel">
            <h2>Board plan</h2>
            <pre class="board-plan"><?= htmlspecialchars($payload['board_plan']) ?></pre>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['teacher_questions'])): ?>
        <section class="panel">
            <h2>Suggested teacher questions</h2>
            <?php if (is_array($payload['teacher_questions'])): ?>
                <ul>
                    <?php foreach ($payload['teacher_questions'] as $question): ?>
                        <li><?= htmlspecialchars(is_array($question) ? json_encode($question) : $question) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['teacher_questions'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['common_misconceptions'])): ?>
        <section class="panel">
            <h2>Common misconceptions</h2>
            <?php if (is_array($payload['common_misconceptions'])): ?>
                <ul>
                    <?php foreach ($payload['common_misconceptions'] as $item): ?>
                        <li><?= htmlspecialchars(is_array($item) ? json_encode($item) : $item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['common_misconceptions'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['quick_check'])): ?>
        <section class="panel">
            <h2>Quick check</h2>
            <?php if (is_array($payload['quick_check'])): ?>
                <ul>
                    <?php foreach ($payload['quick_check'] as $item): ?>
                        <li><?= htmlspecialchars(is_array($item) ? json_encode($item) : $item) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="prose"><?= nl2br(htmlspecialchars($payload['quick_check'])) ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($payload['teacher_notes'])): ?>
        <section class="panel">
            <h2>Teacher notes</h2>
            <div class="prose"><?= nl2br(htmlspecialchars($payload['teacher_notes'])) ?></div>
        </section>
    <?php endif; ?>
<?php endif; ?>
