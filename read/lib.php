<?php

declare(strict_types=1);

const READ_STORE_DIR = __DIR__ . '/store';
const READ_DEVICE_DIR = READ_STORE_DIR . '/devices';
const READ_CANDIDATES_FILE = READ_STORE_DIR . '/candidates.json';
const READ_HTTP_USER_AGENT = 'cwervo-read-bot/1.0 (+https://github.com/cwervo/cwervo.com)';

function rr_ensure_store_layout(): void
{
    foreach ([READ_STORE_DIR, READ_DEVICE_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    if (!file_exists(READ_CANDIDATES_FILE)) {
        rr_write_json(READ_CANDIDATES_FILE, [
            'generated_at' => null,
            'sources' => [],
            'candidates' => [],
        ]);
    }
}

function rr_now_iso(): string
{
    return gmdate('c');
}

function rr_today(): string
{
    return gmdate('Y-m-d');
}

function rr_yesterday(): string
{
    return gmdate('Y-m-d', strtotime('-1 day'));
}

function rr_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rr_read_json(string $path, mixed $default): mixed
{
    if (!is_file($path)) {
        return $default;
    }

    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
}

function rr_write_json(string $path, mixed $data): bool
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($encoded === false) {
        return false;
    }

    $tmpPath = tempnam($dir, basename($path) . '.tmp-');
    if ($tmpPath === false) {
        return false;
    }

    if (@file_put_contents($tmpPath, $encoded . PHP_EOL, LOCK_EX) === false) {
        @unlink($tmpPath);
        return false;
    }

    return @rename($tmpPath, $path);
}

function rr_http_get(string $url, int $timeout = 10, array $headers = []): array
{
    $defaultHeaders = [
        'User-Agent: ' . READ_HTTP_USER_AGENT,
        'Accept: application/json, application/xml, text/xml;q=0.9, text/plain;q=0.8, */*;q=0.5',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => max(3, min($timeout, 6)),
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
            CURLOPT_USERAGENT => READ_HTTP_USER_AGENT,
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        if ($body !== false || $status > 0) {
            return [
                'ok' => $body !== false && $status >= 200 && $status < 300,
                'status' => $status,
                'body' => $body === false ? '' : $body,
                'headers' => [],
                'error' => $error,
                'url' => $url,
            ];
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'header' => implode("\r\n", array_merge($defaultHeaders, $headers)),
            'ignore_errors' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $meta = $http_response_header ?? [];
    $status = 0;
    if (!empty($meta[0]) && preg_match('/\s(\d{3})\s/', $meta[0], $matches)) {
        $status = (int) $matches[1];
    }

    return [
        'ok' => $body !== false && $status >= 200 && $status < 300,
        'status' => $status,
        'body' => $body === false ? '' : $body,
        'headers' => $meta,
        'error' => $body === false ? 'request_failed' : null,
        'url' => $url,
    ];
}

function rr_openalex_abstract(array $result): string
{
    if (!empty($result['abstract']) && is_string($result['abstract'])) {
        return rr_clean_text($result['abstract']);
    }

    $inverted = $result['abstract_inverted_index'] ?? null;
    if (!is_array($inverted)) {
        return '';
    }

    $tokens = [];
    foreach ($inverted as $word => $positions) {
        foreach ((array) $positions as $position) {
            $tokens[(int) $position] = (string) $word;
        }
    }
    ksort($tokens);

    return rr_clean_text(implode(' ', $tokens));
}

function rr_clean_text(?string $text): string
{
    $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', trim($text ?? ''));
    return $text ?? '';
}

function rr_slugify_title(string $title): string
{
    $title = mb_strtolower(rr_clean_text($title), 'UTF-8');
    $title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title) ?? '';
    return trim($title);
}

function rr_extract_year(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }

    if (is_array($value)) {
        foreach ($value as $entry) {
            $year = rr_extract_year($entry);
            if ($year !== null) {
                return $year;
            }
        }
        return null;
    }

    if (preg_match('/(19|20)\d{2}/', (string) $value, $matches)) {
        return (int) $matches[0];
    }

    return null;
}

function rr_year_freshness(?int $year): float
{
    if ($year === null) {
        return 0.0;
    }

    $age = max(0, (int) gmdate('Y') - $year);
    return max(0.0, 3.0 - min(3.0, $age * 0.2));
}

function rr_normalize_topics(mixed $topics): array
{
    if (!is_array($topics)) {
        $topics = $topics === null ? [] : [$topics];
    }

    $normalized = [];
    foreach ($topics as $topic) {
        $clean = rr_clean_text(is_array($topic) ? (string) ($topic['display_name'] ?? $topic['name'] ?? '') : (string) $topic);
        if ($clean !== '') {
            $normalized[] = $clean;
        }
    }

    return array_values(array_unique($normalized));
}

function rr_normalize_candidate(array $candidate): array
{
    $year = rr_extract_year($candidate['year'] ?? null);
    $topics = rr_normalize_topics($candidate['topics'] ?? []);
    $authors = [];

    foreach (($candidate['authors'] ?? []) as $author) {
        $name = rr_clean_text((string) $author);
        if ($name !== '') {
            $authors[] = $name;
        }
    }

    $title = rr_clean_text((string) ($candidate['title'] ?? 'Untitled'));
    $abstract = rr_clean_text((string) ($candidate['abstract'] ?? ''));
    $url = trim((string) ($candidate['url'] ?? ''));

    return [
        'id' => (string) ($candidate['id'] ?? sha1(($candidate['source'] ?? 'unknown') . '|' . $title . '|' . $url)),
        'source' => rr_clean_text((string) ($candidate['source'] ?? 'unknown')),
        'title' => $title,
        'authors' => array_values(array_unique($authors)),
        'year' => $year,
        'abstract' => $abstract,
        'url' => $url,
        'type' => rr_clean_text((string) ($candidate['type'] ?? 'work')),
        'topics' => $topics,
        'score_base' => round((float) ($candidate['score_base'] ?? 0.0), 3),
        'fetched_at' => (string) ($candidate['fetched_at'] ?? rr_now_iso()),
    ];
}

function rr_similarity_ratio(string $left, string $right): float
{
    if ($left === '' || $right === '') {
        return 0.0;
    }

    similar_text($left, $right, $percent);
    return $percent;
}

function rr_dedupe_candidates(array $candidates): array
{
    $deduped = [];
    $seenUrls = [];
    $seenTitles = [];

    usort($candidates, static function (array $a, array $b): int {
        return ($b['score_base'] ?? 0) <=> ($a['score_base'] ?? 0);
    });

    foreach ($candidates as $candidate) {
        $normalized = rr_normalize_candidate($candidate);
        $urlKey = strtolower(preg_replace('/#.*$/', '', $normalized['url']) ?? '');
        $titleKey = rr_slugify_title($normalized['title']);

        if ($urlKey !== '' && isset($seenUrls[$urlKey])) {
            continue;
        }

        $isDuplicate = false;
        foreach ($seenTitles as $existingTitle) {
            if ($titleKey !== '' && rr_similarity_ratio($titleKey, $existingTitle) >= 94.0) {
                $isDuplicate = true;
                break;
            }
        }

        if ($isDuplicate) {
            continue;
        }

        if ($urlKey !== '') {
            $seenUrls[$urlKey] = true;
        }
        if ($titleKey !== '') {
            $seenTitles[] = $titleKey;
        }

        $deduped[] = $normalized;
    }

    return array_values($deduped);
}

function rr_candidates_payload(): array
{
    rr_ensure_store_layout();
    $payload = rr_read_json(READ_CANDIDATES_FILE, []);
    if (!is_array($payload)) {
        $payload = [];
    }

    if (array_is_list($payload)) {
        $payload = [
            'generated_at' => null,
            'sources' => [],
            'candidates' => $payload,
        ];
    }

    $payload['candidates'] = is_array($payload['candidates'] ?? null) ? $payload['candidates'] : [];
    $payload['sources'] = is_array($payload['sources'] ?? null) ? $payload['sources'] : [];

    return $payload;
}

function rr_load_candidates(): array
{
    $payload = rr_candidates_payload();
    return rr_dedupe_candidates($payload['candidates']);
}

function rr_device_token(): string
{
    $token = $_COOKIE['cw_read_device'] ?? $_POST['device_token'] ?? $_GET['device_token'] ?? '';
    $token = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $token) ?? '';
    return substr($token, 0, 128);
}

function rr_device_hash(): string
{
    $stableSignals = [
        rr_device_token(),
        $_SERVER['HTTP_USER_AGENT'] ?? '',
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
    ];

    return hash('sha256', implode('|', $stableSignals));
}

function rr_render_device_bootstrap(string $returnPath = 'index.php'): never
{
    ob_start();
    ?>
    <section class="card">
        <h1>Preparing your daily reader…</h1>
        <p class="muted">We’re creating a local device token so your recommendation can stay consistent for the day.</p>
    </section>
    <script>
    (function () {
        const key = 'cw_read_device_token';
        let token = '';
        try {
            token = window.localStorage.getItem(key) || '';
            if (!token) {
                token = (window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'device-' + Date.now())
                    .replace(/[^a-zA-Z0-9_-]/g, '')
                    .slice(0, 64);
                window.localStorage.setItem(key, token);
            }
        } catch (error) {
            token = 'device-' + Date.now();
        }
        document.cookie = 'cw_read_device=' + token + '; path=/; max-age=31536000; SameSite=Lax';
        window.location.replace(<?= json_encode($returnPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>);
    })();
    </script>
    <?php
    rr_render_layout('Preparing Reader', (string) ob_get_clean());
    exit;
}

function rr_device_profile_path(string $deviceHash): string
{
    return READ_DEVICE_DIR . '/' . $deviceHash . '.json';
}

function rr_csrf_token(): string
{
    $cookieName = 'cw_read_csrf';
    $token = $_COOKIE[$cookieName] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = bin2hex(random_bytes(32));
        setcookie($cookieName, $token, [
            'expires' => time() + 86400 * 30,
            'path' => '/read/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => false,
            'samesite' => 'Strict',
        ]);
        $_COOKIE[$cookieName] = $token;
    }

    return $token;
}

function rr_verify_csrf_token(?string $token): bool
{
    $cookieToken = $_COOKIE['cw_read_csrf'] ?? '';
    return is_string($token)
        && is_string($cookieToken)
        && $token !== ''
        && $cookieToken !== ''
        && hash_equals($cookieToken, $token);
}

function rr_default_profile(string $deviceHash): array
{
    return [
        'device_hash' => $deviceHash,
        'created_at' => rr_now_iso(),
        'updated_at' => rr_now_iso(),
        'history' => [],
        'feedback' => [],
        'topic_weights' => [],
        'preferences' => [
            'technicality' => 0.0,
            'repeat_source_affinity' => 0.0,
        ],
    ];
}

function rr_load_profile(string $deviceHash): array
{
    rr_ensure_store_layout();
    $profile = rr_read_json(rr_device_profile_path($deviceHash), rr_default_profile($deviceHash));
    if (!is_array($profile)) {
        $profile = rr_default_profile($deviceHash);
    }

    $profile['history'] = is_array($profile['history'] ?? null) ? $profile['history'] : [];
    $profile['feedback'] = is_array($profile['feedback'] ?? null) ? $profile['feedback'] : [];
    $profile['topic_weights'] = is_array($profile['topic_weights'] ?? null) ? $profile['topic_weights'] : [];
    $profile['preferences'] = is_array($profile['preferences'] ?? null) ? $profile['preferences'] : rr_default_profile($deviceHash)['preferences'];

    return $profile;
}

function rr_save_profile(array $profile): bool
{
    $profile['updated_at'] = rr_now_iso();
    return rr_write_json(rr_device_profile_path((string) $profile['device_hash']), $profile);
}

function rr_find_candidate_by_id(array $candidates, string $id): ?array
{
    foreach ($candidates as $candidate) {
        if ((string) ($candidate['id'] ?? '') === $id) {
            return $candidate;
        }
    }

    return null;
}

function rr_latest_history_entry(array $profile): ?array
{
    $history = $profile['history'] ?? [];
    if ($history === []) {
        return null;
    }

    krsort($history);
    $latest = reset($history);
    return is_array($latest) ? $latest : null;
}

function rr_history_entry_for_date(array $profile, string $date): ?array
{
    $entry = $profile['history'][$date] ?? null;
    return is_array($entry) ? $entry : null;
}

function rr_feedback_entry_for_date(array $profile, string $date): ?array
{
    $entry = $profile['feedback'][$date] ?? null;
    return is_array($entry) ? $entry : null;
}

function rr_topic_weight(array $profile, string $topic): float
{
    return (float) ($profile['topic_weights'][mb_strtolower($topic, 'UTF-8')] ?? 0.0);
}

function rr_score_candidate(array $candidate, array $profile, array $clientHints = []): array
{
    $baseline = (float) ($candidate['score_base'] ?? 0.0) + rr_year_freshness($candidate['year'] ?? null);

    $topicBoost = 0.0;
    foreach (($candidate['topics'] ?? []) as $topic) {
        $topicBoost += rr_topic_weight($profile, (string) $topic);
    }

    $technicalityPref = (float) ($profile['preferences']['technicality'] ?? 0.0);
    $abstractLength = strlen((string) ($candidate['abstract'] ?? ''));
    $technicalityScore = 0.0;
    if ($abstractLength > 600) {
        $technicalityScore = $technicalityPref > 0 ? 0.5 : -0.3;
    } elseif ($abstractLength > 0) {
        $technicalityScore = $technicalityPref < 0 ? 0.3 : 0.1;
    }

    $sourceAffinity = 0.0;
    $latestHistory = rr_latest_history_entry($profile);
    if ($latestHistory !== null && (($latestHistory['source'] ?? null) === ($candidate['source'] ?? null))) {
        $sourceAffinity = (float) ($profile['preferences']['repeat_source_affinity'] ?? 0.0);
    }

    $clientScore = 0.0;
    if ($clientHints !== []) {
        $clientScore = (float) ($clientHints[$candidate['id']] ?? 0.0);
    }

    $total = $baseline + $topicBoost + $technicalityScore + $sourceAffinity + $clientScore;

    return [
        'total' => round($total, 3),
        'baseline' => round($baseline, 3),
        'topic_boost' => round($topicBoost, 3),
        'technicality' => round($technicalityScore, 3),
        'source_affinity' => round($sourceAffinity, 3),
        'client' => round($clientScore, 3),
    ];
}

function rr_rank_candidates(array $candidates, array $profile, array $clientHints = [], int $limit = 25): array
{
    $scored = [];
    foreach ($candidates as $candidate) {
        $scores = rr_score_candidate($candidate, $profile, $clientHints);
        $scored[] = [
            'candidate' => $candidate,
            'scores' => $scores,
        ];
    }

    usort($scored, static function (array $left, array $right): int {
        return ($right['scores']['total'] ?? 0) <=> ($left['scores']['total'] ?? 0);
    });

    return array_slice($scored, 0, $limit);
}

function rr_choice_explanation(array $candidate, array $scores, array $profile): string
{
    $reasons = [];
    if (($scores['baseline'] ?? 0) > 0) {
        $reasons[] = 'strong baseline relevance';
    }
    if (($scores['topic_boost'] ?? 0) > 0 && !empty($candidate['topics'])) {
        $reasons[] = 'matches your recent interest in ' . implode(', ', array_slice($candidate['topics'], 0, 2));
    }
    if (($scores['client'] ?? 0) > 0) {
        $reasons[] = 'boosted by your on-device reranker';
    }
    if (($scores['source_affinity'] ?? 0) > 0) {
        $reasons[] = 'continues a source style you recently liked';
    } elseif (($scores['source_affinity'] ?? 0) < 0) {
        $reasons[] = 'pushes away from a source style you recently disliked';
    }
    if (($scores['technicality'] ?? 0) !== 0.0) {
        $reasons[] = (($profile['preferences']['technicality'] ?? 0) > 0 ? 'leans technical' : 'keeps a lighter reading load');
    }

    if ($reasons === []) {
        $reasons[] = 'selected from today’s best available candidates';
    }

    return ucfirst(implode('; ', $reasons)) . '.';
}

function rr_sanitize_profile_for_client(array $profile): array
{
    return [
        'topic_weights' => $profile['topic_weights'] ?? [],
        'preferences' => $profile['preferences'] ?? [],
        'history_count' => count($profile['history'] ?? []),
    ];
}

function rr_question_for_today(array $profile, array $suggestion): array
{
    $topics = $suggestion['topics'] ?? [];
    $primaryTopic = $topics[0] ?? 'this kind of reading';
    $questions = rr_question_bank($suggestion, $primaryTopic);

    $count = count($profile['feedback'] ?? []);
    return $questions[$count % count($questions)];
}

function rr_question_bank(array $suggestion, string $primaryTopic): array
{
    return [
        [
            'key' => 'technicality',
            'prompt' => 'Was “' . $suggestion['title'] . '” too technical, too basic, or about right?',
            'options' => [
                'too_technical' => 'Too technical',
                'about_right' => 'About right',
                'too_basic' => 'Too basic',
            ],
        ],
        [
            'key' => 'more_like_this',
            'prompt' => 'Do you want more like “' . $suggestion['title'] . '” tomorrow?',
            'options' => [
                'yes_more' => 'Yes, more like this',
                'neutral' => 'Neutral',
                'less_like_this' => 'Less like this',
            ],
        ],
        [
            'key' => 'topic_focus',
            'prompt' => 'Should tomorrow lean more toward ' . $primaryTopic . ' or away from it?',
            'options' => [
                'more_topic' => 'More of this topic',
                'mixed' => 'Keep it mixed',
                'less_topic' => 'Less of this topic',
            ],
        ],
    ];
}

function rr_question_by_key(array $suggestion, string $questionKey): ?array
{
    $questions = rr_question_bank($suggestion, $suggestion['topics'][0] ?? 'this kind of reading');
    foreach ($questions as $question) {
        if (($question['key'] ?? null) === $questionKey) {
            return $question;
        }
    }

    return null;
}

function rr_adjust_topic_weights(array &$profile, array $topics, float $delta): void
{
    foreach ($topics as $topic) {
        $key = mb_strtolower((string) $topic, 'UTF-8');
        $profile['topic_weights'][$key] = round(((float) ($profile['topic_weights'][$key] ?? 0.0)) + $delta, 3);
    }
}

function rr_apply_feedback(array $profile, array $suggestion, string $questionKey, string $answer): array
{
    $topics = $suggestion['topics'] ?? [];

    switch ($questionKey) {
        case 'technicality':
            if ($answer === 'too_technical') {
                $profile['preferences']['technicality'] = round(((float) ($profile['preferences']['technicality'] ?? 0.0)) - 0.5, 3);
            } elseif ($answer === 'too_basic') {
                $profile['preferences']['technicality'] = round(((float) ($profile['preferences']['technicality'] ?? 0.0)) + 0.5, 3);
            }
            break;

        case 'more_like_this':
            if ($answer === 'yes_more') {
                rr_adjust_topic_weights($profile, $topics, 0.6);
                $profile['preferences']['repeat_source_affinity'] = round(((float) ($profile['preferences']['repeat_source_affinity'] ?? 0.0)) + 0.2, 3);
            } elseif ($answer === 'less_like_this') {
                rr_adjust_topic_weights($profile, $topics, -0.6);
                $profile['preferences']['repeat_source_affinity'] = round(((float) ($profile['preferences']['repeat_source_affinity'] ?? 0.0)) - 0.2, 3);
            }
            break;

        case 'topic_focus':
            if ($answer === 'more_topic') {
                rr_adjust_topic_weights($profile, $topics, 0.8);
            } elseif ($answer === 'less_topic') {
                rr_adjust_topic_weights($profile, $topics, -0.8);
            }
            break;
    }

    return $profile;
}

function rr_render_layout(string $title, string $bodyHtml, string $extraHead = ''): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . rr_h($title) . '</title>';
    echo '<style>
        :root { color-scheme: light dark; }
        body { font-family: system-ui, sans-serif; margin: 0; padding: 2rem 1rem; background: #0b1020; color: #e8eefc; }
        main { max-width: 54rem; margin: 0 auto; }
        a { color: #93c5fd; }
        .card { background: rgba(17, 24, 39, 0.92); border: 1px solid rgba(148, 163, 184, 0.25); border-radius: 1rem; padding: 1.25rem; margin-bottom: 1rem; }
        .muted { color: #b6c2de; }
        .pill { display: inline-block; margin: 0 .35rem .35rem 0; padding: .2rem .55rem; border-radius: 999px; background: rgba(96, 165, 250, .16); }
        form button, .button-link { background: #2563eb; color: #fff; border: 0; border-radius: .7rem; padding: .7rem 1rem; cursor: pointer; text-decoration: none; display: inline-block; }
        fieldset { border: 0; padding: 0; margin: 1rem 0; }
        label.option { display: block; margin: .55rem 0; padding: .75rem .9rem; border: 1px solid rgba(148, 163, 184, 0.25); border-radius: .75rem; }
        code, pre { overflow-x: auto; }
    </style>';
    echo $extraHead;
    echo '</head><body><main>' . $bodyHtml . '</main></body></html>';
}
