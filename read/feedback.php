<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';

rr_ensure_store_layout();

if (rr_device_token() === '' && $_SERVER['REQUEST_METHOD'] !== 'POST' && (($_GET['bootstrap'] ?? '') !== '1')) {
    rr_render_device_bootstrap('feedback.php?bootstrap=1');
}

$deviceHash = rr_device_hash();
$profile = rr_load_profile($deviceHash);
$candidates = rr_load_candidates();
$today = rr_today();
$latestEntry = rr_history_entry_for_date($profile, rr_yesterday()) ?? rr_latest_history_entry($profile);
$suggestion = $latestEntry ? rr_find_candidate_by_id($candidates, (string) $latestEntry['candidate_id']) : null;
$message = null;
$error = null;
$existingFeedback = rr_feedback_entry_for_date($profile, $today);

if ($suggestion !== null) {
    $question = rr_question_for_today($profile, $suggestion);
    if ($existingFeedback !== null) {
        $question = rr_question_by_key($suggestion, (string) ($existingFeedback['question_key'] ?? '')) ?? $question;
        $message = 'You already answered today’s follow-up for this device. Come back tomorrow for the next question.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $answer = (string) ($_POST['answer'] ?? '');
        if (!array_key_exists($answer, $question['options'])) {
            $error = 'Please choose one of the available answers.';
        } else {
            $profile = rr_apply_feedback($profile, $suggestion, $question['key'], $answer);
            $profile['feedback'][$today] = [
                'date' => $today,
                'candidate_id' => $suggestion['id'],
                'question_key' => $question['key'],
                'answer' => $answer,
                'topics' => $suggestion['topics'],
                'recorded_at' => rr_now_iso(),
            ];
            rr_save_profile($profile);
            $existingFeedback = $profile['feedback'][$today];
            $message = 'Saved. Tomorrow’s ranking will reflect this answer.';
        }
    }
} else {
    $question = null;
}

ob_start();
?>
<section class="card">
    <p><a href="index.php">← Back to today’s recommendation</a></p>
    <h1>Daily follow-up</h1>
    <?php if ($suggestion === null || $question === null): ?>
        <p>No previous suggestion was found for this device yet. Visit <code>/read/</code> first.</p>
    <?php else: ?>
        <p class="muted">Referring to your latest suggestion: <?= rr_h($suggestion['title']) ?> (<?= rr_h($suggestion['source']) ?>)</p>
        <?php if ($message !== null): ?>
            <p><strong><?= rr_h($message) ?></strong></p>
        <?php endif; ?>
        <?php if ($error !== null): ?>
            <p><strong><?= rr_h($error) ?></strong></p>
        <?php endif; ?>
        <?php if ($existingFeedback === null): ?>
            <form method="post">
                <fieldset>
                    <legend><?= rr_h($question['prompt']) ?></legend>
                    <?php foreach ($question['options'] as $value => $label): ?>
                        <label class="option">
                            <input type="radio" name="answer" value="<?= rr_h($value) ?>">
                            <?= rr_h($label) ?>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <button type="submit">Save feedback</button>
            </form>
        <?php else: ?>
            <p class="muted">Saved answer: <?= rr_h((string) ($question['options'][$existingFeedback['answer']] ?? $existingFeedback['answer'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($suggestion['topics'])): ?>
            <p>
                <?php foreach (array_slice($suggestion['topics'], 0, 6) as $topic): ?>
                    <span class="pill"><?= rr_h((string) $topic) ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
</section>
<section class="card">
    <h2>Current preference snapshot</h2>
    <pre><?= rr_h(json_encode(rr_sanitize_profile_for_client($profile), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<?php
$body = (string) ob_get_clean();
rr_render_layout('Reader Feedback', $body);
