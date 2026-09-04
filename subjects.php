<?php
/** Subjects: the full KICD curriculum catalogue, searchable and filterable. */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth-check.php';

$pdo = db();
$userId = (int) $_SESSION['user_id'];

$q = trim((string) ($_GET['q'] ?? ''));

$sql = 'SELECT id, code, name, strand, grade_level FROM subjects';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE name LIKE ? OR code LIKE ? OR strand LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY name';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$subjects = $stmt->fetchAll();

$topicCounts = $pdo->query('SELECT subject_id, COUNT(*) AS n FROM topics GROUP BY subject_id')
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$lessonCountStmt = $pdo->prepare('SELECT subject_id, COUNT(*) AS n FROM lessons WHERE user_id = ? GROUP BY subject_id');
$lessonCountStmt->execute([$userId]);
$lessonCounts = $lessonCountStmt->fetchAll(PDO::FETCH_KEY_PAIR);

function get_subject_icon(string $code, string $name): string {
    $c = strtoupper($code);
    $n = strtolower($name);
    if (str_contains($n, 'math') || $c === 'MATH') return '📐';
    if (str_contains($n, 'chem') || $c === 'CHEM') return '🧪';
    if (str_contains($n, 'bio') || $c === 'BIO') return '🧬';
    if (str_contains($n, 'phys') || $c === 'PHYS') return '⚡';
    if (str_contains($n, 'eng') || str_contains($n, 'liter') || $c === 'ENG') return '📖';
    if (str_contains($n, 'kisw') || $c === 'KISW') return '🗣️';
    if (str_contains($n, 'hist') || $c === 'HIST') return '📜';
    if (str_contains($n, 'geog') || $c === 'GEOG') return '🌐';
    if (str_contains($n, 'comp') || str_contains($n, 'ict') || $c === 'COMP') return '💻';
    if (str_contains($n, 'art') || str_contains($n, 'craft')) return '🎨';
    if (str_contains($n, 'mus') || str_contains($n, 'sound')) return '🎵';
    if (str_contains($n, 'agri')) return '🌱';
    if (str_contains($n, 'bus') || str_contains($n, 'econ')) return '📊';
    return '📚';
}

function get_subject_gradient(string $code): string {
    $c = strtoupper($code);
    if (in_array($c, ['MATH', 'PHYS', 'COMP'], true)) return 'linear-gradient(90deg, #3b82f6, #6366f1)';
    if (in_array($c, ['CHEM', 'BIO', 'AGRI'], true)) return 'linear-gradient(90deg, #10b981, #059669)';
    if (in_array($c, ['ENG', 'KISW', 'LIT'], true)) return 'linear-gradient(90deg, #8b5cf6, #ec4899)';
    if (in_array($c, ['HIST', 'GEOG', 'CRE'], true)) return 'linear-gradient(90deg, #f59e0b, #d97706)';
    return 'linear-gradient(90deg, #10b981, #3b82f6)';
}

$pageTitle    = 'Subjects Hub';
$activeNav    = 'subjects';
$showHeader   = true;
$pageIcon     = '📚';
$pageHeading  = 'Subjects Hub';
$pageSubtitle = 'Browse and manage the KICD curriculum framework.';
$csrf         = csrf_token();
$pageActions  = '<button class="btn btn-primary" id="open-add-subject-top-btn" style="border-radius:999px;">＋ Add subject</button>';
require __DIR__ . '/includes/header.php';
?>
<section class="panel" style="padding: 1.5rem;">
    <!-- KPI Command Grid -->
    <div class="subjects-kpi-grid">
        <div class="subject-kpi-card">
            <div class="kpi-icon">📚</div>
            <div class="kpi-info">
                <span class="kpi-val"><?= count($subjects) ?></span>
                <span class="kpi-lbl">KICD Subjects</span>
            </div>
        </div>
        <div class="subject-kpi-card">
            <div class="kpi-icon">📑</div>
            <div class="kpi-info">
                <span class="kpi-val"><?= array_sum($topicCounts) ?></span>
                <span class="kpi-lbl">Curriculum Topics</span>
            </div>
        </div>
        <div class="subject-kpi-card">
            <div class="kpi-icon">📝</div>
            <div class="kpi-info">
                <span class="kpi-val"><?= array_sum($lessonCounts) ?></span>
                <span class="kpi-lbl">Generated Lessons</span>
            </div>
        </div>
        <div class="subject-kpi-card">
            <div class="kpi-icon">🎓</div>
            <div class="kpi-info">
                <span class="kpi-val"><?= count(array_unique(array_column($subjects, 'grade_level'))) ?: 1 ?> Grades</span>
                <span class="kpi-lbl">CBC Framework</span>
            </div>
        </div>
    </div>

    <!-- Hero Search & Grade Filter Bar -->
    <div class="subjects-hero-bar">
        <form method="get" action="subjects.php" class="subjects-search-wrapper" id="subjects-search-form">
            <span aria-hidden="true" style="color: var(--muted); font-size: 1.1rem;">🔍</span>
            <input type="search" id="subject-search-input" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search subjects by name, code or strand…">
            <?php if ($q !== ''): ?>
                <a href="subjects.php" style="color: var(--muted); text-decoration: none; font-size: 0.9rem; padding: 0.2rem 0.5rem;" title="Clear search">✕</a>
            <?php endif; ?>
        </form>

        <div class="grade-filter-pills" id="grade-filter-container">
            <button type="button" class="grade-pill active" data-grade="all">All Grades</button>
            <?php
                $uniqueGrades = array_filter(array_unique(array_column($subjects, 'grade_level')));
                sort($uniqueGrades);
                foreach ($uniqueGrades as $g):
            ?>
                <button type="button" class="grade-pill" data-grade="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></button>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-primary" id="open-add-subject-modal" style="border-radius: 999px; margin: 0;">＋ Add Subject</button>
    </div>

    <!-- Subjects Redesign Cards Grid -->
    <?php if ($subjects): ?>
        <div class="subjects-redesign-grid" id="subjects-grid">
            <?php foreach ($subjects as $subject): ?>
                <?php
                    $topicN = (int) ($topicCounts[$subject['id']] ?? 0);
                    $lessonN = (int) ($lessonCounts[$subject['id']] ?? 0);
                    $icon = get_subject_icon($subject['code'], $subject['name']);
                    $gradient = get_subject_gradient($subject['code']);
                ?>
                <a class="subject-card-redesign" href="subject.php?id=<?= (int) $subject['id'] ?>"
                   data-name="<?= htmlspecialchars(strtolower($subject['name'])) ?>"
                   data-code="<?= htmlspecialchars(strtolower($subject['code'])) ?>"
                   data-strand="<?= htmlspecialchars(strtolower($subject['strand'])) ?>"
                   data-grade="<?= htmlspecialchars($subject['grade_level']) ?>"
                   style="--accent-gradient: <?= $gradient ?>;">
                    <div class="subject-card-top">
                        <div class="subject-icon-box"><?= $icon ?></div>
                        <span class="subject-code-tag"><?= htmlspecialchars($subject['code']) ?></span>
                    </div>
                    <div class="subject-card-body">
                        <h3><?= htmlspecialchars($subject['name']) ?></h3>
                        <div class="subject-strand-text"><?= htmlspecialchars($subject['strand']) ?></div>
                    </div>
                    <div class="subject-card-footer">
                        <div class="subject-pills-group">
                            <span class="subject-mini-pill" title="Topics">📑 <?= $topicN ?></span>
                            <span class="subject-mini-pill" title="Lessons">📝 <?= $lessonN ?></span>
                            <span class="subject-mini-pill" style="opacity: 0.85;"><?= htmlspecialchars($subject['grade_level']) ?></span>
                        </div>
                        <span class="subject-explore-link">Explore &rarr;</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <p id="no-subjects-match" class="muted" style="display: none; margin-top: 2rem; text-align: center;">No subjects match your active search or filter.</p>
    <?php else: ?>
        <p class="muted" style="text-align: center; margin: 3rem 0;"><?= $q !== '' ? 'No subjects match your search.' : 'No subjects available yet.' ?></p>
    <?php endif; ?>
</section>

<!-- Premium Multi-Step Add Subject Modal -->
<div class="modal-backdrop" id="add-subject-modal" style="display: none;">
    <div class="modal premium-modal" style="max-width: 500px; width: 90%;">
        <div class="modal-header">
            <h2>Add a Subject</h2>
            <button class="modal-close" id="close-add-subject-modal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="step-indicator">
                <div class="step-dot active" data-step="1">1</div>
                <div class="step-dot" data-step="2">2</div>
                <div class="step-dot" data-step="3">3</div>
            </div>
            
            <form id="add-subject-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                
                <!-- Step 1: Basic Details -->
                <div class="modal-step active" id="step-1">
                    <h3 style="margin-top:0;">Basic Details</h3>
                    <p class="muted" style="margin-bottom: 1.5rem;">Let's start with the subject code and name.</p>
                    <label>Code
                        <input type="text" name="code" maxlength="10" required placeholder="e.g. CHEM" style="width: 100%;">
                    </label>
                    <label style="margin-top: 1rem;">Name
                        <input type="text" name="name" maxlength="100" required placeholder="e.g. Chemistry" style="width: 100%;">
                    </label>
                </div>
                
                <!-- Step 2: Curriculum Context -->
                <div class="modal-step" id="step-2">
                    <h3 style="margin-top:0;">Curriculum Context</h3>
                    <p class="muted" style="margin-bottom: 1.5rem;">What is the primary strand and grade level?</p>
                    <label>Strand
                        <input type="text" name="strand" maxlength="190" required placeholder="e.g. Matter and Materials" style="width: 100%;">
                    </label>
                    <label style="margin-top: 1rem;">Grade level
                        <input type="text" name="grade_level" maxlength="20" value="Grade 10" required style="width: 100%;">
                    </label>
                </div>
                
                <!-- Step 3: Resources -->
                <div class="modal-step" id="step-3">
                    <h3 style="margin-top:0;">Resources (Optional)</h3>
                    <p class="muted" style="margin-bottom: 1.5rem;">Upload the KICD curriculum design PDF. This grounds the AI generation.</p>
                    <label>Curriculum PDF (10 MB max)
                        <input type="file" name="pdf" accept="application/pdf" style="width: 100%;">
                    </label>
                    <div id="add-subject-result" style="margin-top: 1rem;"></div>
                </div>
            </form>
        </div>
        <div class="modal-actions" style="justify-content: space-between;">
            <button type="button" class="btn btn-outline" id="modal-btn-prev" style="visibility: hidden;">Back</button>
            <button type="button" class="btn btn-primary" id="modal-btn-next">Next Step</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('add-subject-modal');
    if (modal) {
        modal.style.display = 'none';
    }
    var btnOpen = document.getElementById('open-add-subject-modal');
    var btnOpenTop = document.getElementById('open-add-subject-top-btn');
    var btnClose = document.getElementById('close-add-subject-modal');
    
    var currentStep = 1;
    var totalSteps = 3;
    var btnPrev = document.getElementById('modal-btn-prev');
    var btnNext = document.getElementById('modal-btn-next');
    var form = document.getElementById('add-subject-form');
    var resultDiv = document.getElementById('add-subject-result');
    
    function updateSteps() {
        document.querySelectorAll('.modal-step').forEach(function(el, index) {
            if (index + 1 === currentStep) {
                el.classList.add('active');
            } else {
                el.classList.remove('active');
            }
        });
        
        document.querySelectorAll('.step-dot').forEach(function(el, index) {
            var stepNum = index + 1;
            el.classList.remove('active', 'completed');
            if (stepNum < currentStep) {
                el.classList.add('completed');
            } else if (stepNum === currentStep) {
                el.classList.add('active');
            }
        });
        
        btnPrev.style.visibility = (currentStep === 1) ? 'hidden' : 'visible';
        btnNext.textContent = (currentStep === totalSteps) ? 'Save Subject' : 'Next Step';
    }
    
    function openModal() {
        if (modal) {
            modal.style.display = 'flex';
            document.body.classList.add('modal-open');
        }
    }

    if (btnOpen) btnOpen.addEventListener('click', openModal);
    if (btnOpenTop) btnOpenTop.addEventListener('click', openModal);
    
    function closeModal() {
        if (modal) modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        currentStep = 1;
        form.reset();
        resultDiv.innerHTML = '';
        updateSteps();
    }
    
    if (btnClose) btnClose.addEventListener('click', closeModal);
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal();
        });
    }
    
    if (btnPrev) {
        btnPrev.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                updateSteps();
            }
        });
    }
    
    if (btnNext) {
        btnNext.addEventListener('click', function() {
            var currentStepDiv = document.getElementById('step-' + currentStep);
            var inputs = currentStepDiv.querySelectorAll('input[required]');
            var valid = true;
            inputs.forEach(function(input) {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    valid = false;
                }
            });
            
            if (!valid) return;
            
            if (currentStep < totalSteps) {
                currentStep++;
                updateSteps();
            } else {
                btnNext.disabled = true;
                btnNext.textContent = 'Saving...';
                btnPrev.disabled = true;
                
                var formData = new FormData(form);
                formData.set('action', 'add_subject');
                
                fetch('api/curriculum.php', { method: 'POST', body: formData })
                    .then(function(res) { return res.json().catch(function() { return { success: false, error: 'Unexpected response.' }; }); })
                    .then(function(data) {
                        if (!data || !data.success) {
                            resultDiv.innerHTML = '<div class="alert alert-error">' + (data.error || 'Could not add the subject.') + '</div>';
                            btnNext.disabled = false;
                            btnNext.textContent = 'Save Subject';
                            btnPrev.disabled = false;
                        } else {
                            window.location.reload();
                        }
                    })
                    .catch(function() {
                        resultDiv.innerHTML = '<div class="alert alert-error">Network error.</div>';
                        btnNext.disabled = false;
                        btnNext.textContent = 'Save Subject';
                        btnPrev.disabled = false;
                    });
            }
        });
    }

    /* Client-side Instant Filter Logic for Search & Grade Pills */
    var searchInput = document.getElementById('subject-search-input');
    var gradePills = document.querySelectorAll('.grade-pill');
    var cards = document.querySelectorAll('.subject-card-redesign');
    var noMatchMsg = document.getElementById('no-subjects-match');
    var activeGrade = 'all';

    function filterSubjects() {
        var query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        var visibleCount = 0;

        cards.forEach(function(card) {
            var name = card.getAttribute('data-name') || '';
            var code = card.getAttribute('data-code') || '';
            var strand = card.getAttribute('data-strand') || '';
            var grade = card.getAttribute('data-grade') || '';

            var matchesSearch = !query || name.includes(query) || code.includes(query) || strand.includes(query);
            var matchesGrade = activeGrade === 'all' || grade.toLowerCase() === activeGrade.toLowerCase();

            if (matchesSearch && matchesGrade) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noMatchMsg) {
            noMatchMsg.style.display = (visibleCount === 0 && cards.length > 0) ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterSubjects);
    }

    gradePills.forEach(function(pill) {
        pill.addEventListener('click', function() {
            gradePills.forEach(function(p) { p.classList.remove('active'); });
            pill.classList.add('active');
            activeGrade = pill.getAttribute('data-grade') || 'all';
            filterSubjects();
        });
    });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
