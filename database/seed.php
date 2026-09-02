<?php
/** Seeds the demo corpus: subjects + topics, mapped to their strand source files. */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$pdo = db();

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE lessons');
$pdo->exec('TRUNCATE TABLE topics');
$pdo->exec('TRUNCATE TABLE subjects');
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$data = [
    [
        'code' => 'MATH',
        'name' => 'Mathematics',
        'strand' => 'Quadratic Equations and Expressions',
        'source' => 'strand-mathematics.txt',
        'topics' => [
            'Expanding and factorising quadratic expressions',
            'Solving quadratic equations by factorisation',
            'Completing the square',
            'The quadratic formula',
            'Worded problems and applications',
            'Graphical solution and review',
        ],
    ],
    [
        'code' => 'BIO',
        'name' => 'Biology',
        'strand' => 'Cellular Respiration',
        'source' => 'strand-biology.txt',
        'topics' => [
            'Releasing energy from food',
            'Aerobic respiration',
            'Anaerobic respiration',
            'Respiration and exercise',
        ],
    ],
];

$insertSubject = $pdo->prepare(
    'INSERT INTO subjects (code, name, strand, grade_level) VALUES (?, ?, ?, ?)'
);
$insertTopic = $pdo->prepare(
    'INSERT INTO topics (subject_id, name, strand, source_file) VALUES (?, ?, ?, ?)'
);

foreach ($data as $subject) {
    $insertSubject->execute([$subject['code'], $subject['name'], $subject['strand'], 'Grade 10']);
    $subjectId = (int) $pdo->lastInsertId();

    foreach ($subject['topics'] as $topic) {
        $insertTopic->execute([$subjectId, $topic, $subject['strand'], $subject['source']]);
    }
}

echo "Seeded subjects and topics successfully.\n";
