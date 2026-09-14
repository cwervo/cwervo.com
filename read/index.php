<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';

rr_ensure_store_layout();

if (rr_device_token() === '' && $_SERVER['REQUEST_METHOD'] !== 'POST' && (($_GET['bootstrap'] ?? '') !== '1')) {
    rr_render_device_bootstrap('index.php?bootstrap=1');
}

$deviceHash = rr_device_hash();
$profile = rr_load_profile($deviceHash);
$candidatesPayload = rr_candidates_payload();
$candidates = rr_dedupe_candidates($candidatesPayload['candidates']);
$today = rr_today();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_GET['action'] ?? '') === 'finalize')) {
    header('Content-Type: application/json; charset=utf-8');

    $existing = rr_history_entry_for_date($profile, $today);
    if (($existing['personalized'] ?? false) === true) {
        echo json_encode([
            'ok' => true,
            'selection' => $existing,
            'candidate' => rr_find_candidate_by_id($candidates, (string) ($existing['candidate_id'] ?? '')),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $selectedId = (string) ($_POST['selected_id'] ?? '');
    $allowedIds = [];
    foreach (($existing['top_candidates'] ?? []) as $candidateId) {
        $allowedIds[(string) $candidateId] = true;
    }
    if ($allowedIds === []) {
        foreach (rr_rank_candidates($candidates, $profile, [], 12) as $entry) {
            $allowedIds[(string) $entry['candidate']['id']] = true;
        }
    }

    $allowedCandidates = array_values(array_filter($candidates, static function (array $candidate) use ($allowedIds): bool {
        return isset($allowedIds[(string) ($candidate['id'] ?? '')]);
    }));

    $clientHintsRaw = json_decode((string) ($_POST['client_scores_json'] ?? '{}'), true);
    $clientHints = [];
    if (is_array($clientHintsRaw)) {
        foreach ($clientHintsRaw as $candidateId => $score) {
            if (!isset($allowedIds[(string) $candidateId]) || !is_numeric($score)) {
                continue;
            }
            $clientHints[(string) $candidateId] = max(-3.0, min(3.0, (float) $score));
        }
    }

    $ranked = rr_rank_candidates($allowedCandidates, $profile, $clientHints, 12);
    $rankedCandidatesById = [];
    foreach ($ranked as $entry) {
        $rankedCandidatesById[(string) $entry['candidate']['id']] = $entry;
    }

    $selectedEntry = $rankedCandidatesById[$selectedId] ?? null;
    $selectedCandidate = $selectedEntry['candidate'] ?? null;
    $selectedScores = $selectedEntry['scores'] ?? null;
    if ($selectedCandidate === null && $ranked !== []) {
        $selectedCandidate = $ranked[0]['candidate'];
        $selectedScores = $ranked[0]['scores'];
    }

    if ($selectedCandidate === null || $selectedScores === null) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'No candidate available.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $profile['history'][$today] = [
        'date' => $today,
        'candidate_id' => $selectedCandidate['id'],
        'title' => $selectedCandidate['title'],
        'url' => $selectedCandidate['url'],
        'source' => $selectedCandidate['source'],
        'topics' => $selectedCandidate['topics'],
        'personalized' => true,
        'selected_at' => rr_now_iso(),
        'scores' => $selectedScores,
        'explanation' => rr_choice_explanation($selectedCandidate, $selectedScores, $profile),
    ];
    rr_save_profile($profile);

    echo json_encode([
        'ok' => true,
        'selection' => $profile['history'][$today],
        'candidate' => $selectedCandidate,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$todayEntry = rr_history_entry_for_date($profile, $today);
$ranked = rr_rank_candidates($candidates, $profile, [], 12);

if ($todayEntry === null && $ranked !== []) {
    $selectedCandidate = $ranked[0]['candidate'];
    $selectedScores = $ranked[0]['scores'];
    $todayEntry = [
        'date' => $today,
        'candidate_id' => $selectedCandidate['id'],
        'title' => $selectedCandidate['title'],
        'url' => $selectedCandidate['url'],
        'source' => $selectedCandidate['source'],
        'topics' => $selectedCandidate['topics'],
        'personalized' => false,
        'selected_at' => rr_now_iso(),
        'scores' => $selectedScores,
        'explanation' => rr_choice_explanation($selectedCandidate, $selectedScores, $profile),
        'top_candidates' => array_map(static fn(array $entry): string => (string) $entry['candidate']['id'], $ranked),
    ];
    $profile['history'][$today] = $todayEntry;
    rr_save_profile($profile);
}

$selectedCandidate = $todayEntry ? rr_find_candidate_by_id($candidates, (string) $todayEntry['candidate_id']) : null;
$profileForClient = rr_sanitize_profile_for_client($profile);
$topCandidates = array_map(static fn(array $entry): array => ['candidate' => $entry['candidate'], 'scores' => $entry['scores']], $ranked);

ob_start();
?>
<section class="card">
    <p class="muted">Device-specific daily reading recommender</p>
    <h1>Today’s reading</h1>
    <?php if ($selectedCandidate === null): ?>
        <p>No cached candidates yet. Run <code>php read/fetch.php</code> first to build a reading pool.</p>
    <?php else: ?>
        <article id="suggestion-card">
            <h2><?= rr_h($selectedCandidate['title']) ?></h2>
            <p class="muted" id="suggestion-meta">
                <?= rr_h($selectedCandidate['source']) ?>
                <?php if (!empty($selectedCandidate['year'])): ?>· <?= rr_h((string) $selectedCandidate['year']) ?><?php endif; ?>
                <?php if (!empty($selectedCandidate['authors'])): ?>· <?= rr_h(implode(', ', array_slice($selectedCandidate['authors'], 0, 3))) ?><?php endif; ?>
            </p>
            <?php if (!empty($selectedCandidate['topics'])): ?>
                <p id="suggestion-topics">
                    <?php foreach (array_slice($selectedCandidate['topics'], 0, 5) as $topic): ?>
                        <span class="pill"><?= rr_h((string) $topic) ?></span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <p id="suggestion-summary"><?= rr_h($selectedCandidate['abstract'] !== '' ? mb_strimwidth($selectedCandidate['abstract'], 0, 520, '…', 'UTF-8') : 'No summary was available from the source; use the original link for details.') ?></p>
            <p><strong>Why this was chosen:</strong> <span id="why-copy"><?= rr_h((string) ($todayEntry['explanation'] ?? '')) ?></span></p>
            <p>
                <a class="button-link" href="<?= rr_h($selectedCandidate['url']) ?>" target="_blank" rel="noopener noreferrer">Open the source</a>
                <a class="button-link" href="feedback.php">Give today’s feedback</a>
            </p>
            <p class="muted" id="personalization-note">
                <?= !empty($todayEntry['personalized']) ? 'Locked in for today for this device.' : 'Using server baseline now; a local scorer will quietly personalize and lock the final pick for this device.' ?>
            </p>
        </article>
    <?php endif; ?>
</section>
<section class="card">
    <h2>How it works</h2>
    <ul>
        <li>Sources are fetched into local JSON files under <code>read/store/</code>.</li>
        <li>Your device gets one suggestion per UTC day, keyed by a privacy-conscious hash of browser signals plus an optional local device token.</li>
        <li>A tiny on-device scorer interface can rerank the top candidates without sending reading history to third-party services.</li>
    </ul>
    <p class="muted">Candidate pool last updated: <?= rr_h((string) ($candidatesPayload['generated_at'] ?? 'never')) ?></p>
</section>
<script>
window.READ_BOOTSTRAP = {
    todayEntry: <?= json_encode($todayEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    selectedCandidate: <?= json_encode($selectedCandidate, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    topCandidates: <?= json_encode($topCandidates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    profile: <?= json_encode($profileForClient, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script type="module" src="wasm/scorer-loader.js"></script>
<?php
$body = (string) ob_get_clean();
rr_render_layout('Daily Reader', $body);
