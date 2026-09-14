<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';

rr_ensure_store_layout();

function fetch_openalex(): array
{
    $url = 'https://api.openalex.org/works?search=' . rawurlencode('Eric Betzig microscopy optics imaging') . '&per-page=12&sort=cited_by_count:desc';
    $response = rr_http_get($url, 12);
    $items = [];

    if ($response['ok']) {
        $payload = json_decode($response['body'], true);
        foreach (($payload['results'] ?? []) as $result) {
            $items[] = [
                'id' => 'openalex:' . ($result['id'] ?? sha1(json_encode($result))),
                'source' => 'OpenAlex',
                'title' => $result['display_name'] ?? 'Untitled',
                'authors' => array_map(static fn(array $author): string => (string) ($author['author']['display_name'] ?? ''), $result['authorships'] ?? []),
                'year' => $result['publication_year'] ?? null,
                'abstract' => rr_openalex_abstract($result),
                'url' => $result['primary_location']['landing_page_url'] ?? $result['doi'] ?? $result['id'] ?? '',
                'type' => $result['type'] ?? 'article',
                'topics' => array_map(static fn(array $topic): string => (string) ($topic['display_name'] ?? ''), $result['concepts'] ?? []),
                'score_base' => min(8.0, ((float) ($result['cited_by_count'] ?? 0) / 50.0) + 1.5),
                'fetched_at' => rr_now_iso(),
            ];
        }
    }

    return [
        'meta' => [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'count' => count($items),
            'url' => $url,
        ],
        'items' => $items,
    ];
}

function fetch_crossref(): array
{
    $query = http_build_query([
        'query' => 'Eric Betzig microscopy optics',
        'rows' => 12,
        'filter' => 'from-pub-date:2000-01-01',
        'select' => 'DOI,title,author,published-print,published-online,issued,abstract,URL,type,subject,is-referenced-by-count',
    ]);
    $url = 'https://api.crossref.org/works?' . $query;
    $response = rr_http_get($url, 12);
    $items = [];

    if ($response['ok']) {
        $payload = json_decode($response['body'], true);
        foreach (($payload['message']['items'] ?? []) as $result) {
            $authors = [];
            foreach (($result['author'] ?? []) as $author) {
                $authors[] = trim((string) (($author['given'] ?? '') . ' ' . ($author['family'] ?? '')));
            }

            $items[] = [
                'id' => 'crossref:' . ($result['DOI'] ?? sha1(json_encode($result))),
                'source' => 'Crossref',
                'title' => $result['title'][0] ?? 'Untitled',
                'authors' => $authors,
                'year' => rr_extract_year($result['issued']['date-parts'][0] ?? $result['published-print']['date-parts'][0] ?? $result['published-online']['date-parts'][0] ?? null),
                'abstract' => rr_clean_text((string) ($result['abstract'] ?? '')),
                'url' => $result['URL'] ?? (($result['DOI'] ?? '') !== '' ? 'https://doi.org/' . $result['DOI'] : ''),
                'type' => $result['type'] ?? 'article',
                'topics' => $result['subject'] ?? [],
                'score_base' => min(7.0, ((float) ($result['is-referenced-by-count'] ?? 0) / 40.0) + 1.0),
                'fetched_at' => rr_now_iso(),
            ];
        }
    }

    return [
        'meta' => [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'count' => count($items),
            'url' => $url,
        ],
        'items' => $items,
    ];
}

function fetch_arxiv(): array
{
    $query = rawurlencode('(all:"Eric Betzig" OR all:microscopy OR all:optics OR all:imaging)');
    $url = 'https://export.arxiv.org/api/query?search_query=' . $query . '&start=0&max_results=12&sortBy=lastUpdatedDate&sortOrder=descending';
    $response = rr_http_get($url, 12);
    $items = [];

    if ($response['ok']) {
        $xml = @simplexml_load_string($response['body']);
        if ($xml instanceof SimpleXMLElement) {
            foreach ($xml->entry as $entry) {
                $authors = [];
                foreach ($entry->author as $author) {
                    $authors[] = (string) $author->name;
                }

                $categories = [];
                foreach ($entry->category as $category) {
                    $term = (string) ($category['term'] ?? '');
                    if ($term !== '') {
                        $categories[] = $term;
                    }
                }

                $items[] = [
                    'id' => 'arxiv:' . sha1((string) $entry->id),
                    'source' => 'arXiv',
                    'title' => (string) $entry->title,
                    'authors' => $authors,
                    'year' => rr_extract_year((string) $entry->published),
                    'abstract' => (string) $entry->summary,
                    'url' => (string) $entry->id,
                    'type' => 'preprint',
                    'topics' => $categories,
                    'score_base' => 2.4,
                    'fetched_at' => rr_now_iso(),
                ];
            }
        }
    }

    return [
        'meta' => [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'count' => count($items),
            'url' => $url,
        ],
        'items' => $items,
    ];
}

function fetch_internet_archive(): array
{
    $query = '(title:("Eric Betzig") OR subject:(microscopy) OR subject:(optics) OR description:(imaging))';
    $params = http_build_query([
        'q' => $query,
        'rows' => 12,
        'page' => 1,
        'output' => 'json',
    ]);
    $params .= '&' . implode('&', array_map(
        static fn(string $field): string => 'fl[]=' . rawurlencode($field),
        ['identifier', 'title', 'creator', 'year', 'mediatype', 'description', 'subject', 'downloads']
    ));
    $params .= '&sort[]=' . rawurlencode('downloads desc');
    $url = 'https://archive.org/advancedsearch.php?' . $params;
    $response = rr_http_get($url, 12);
    $items = [];

    if ($response['ok']) {
        $payload = json_decode($response['body'], true);
        foreach (($payload['response']['docs'] ?? []) as $result) {
            $identifier = (string) ($result['identifier'] ?? '');
            $creator = $result['creator'] ?? [];
            $subjects = $result['subject'] ?? [];
            $items[] = [
                'id' => 'archive:' . $identifier,
                'source' => 'Internet Archive',
                'title' => $result['title'] ?? 'Untitled',
                'authors' => is_array($creator) ? $creator : [$creator],
                'year' => rr_extract_year($result['year'] ?? null),
                'abstract' => is_array($result['description'] ?? null) ? implode(' ', $result['description']) : (string) ($result['description'] ?? ''),
                'url' => $identifier !== '' ? 'https://archive.org/details/' . rawurlencode($identifier) : 'https://archive.org',
                'type' => $result['mediatype'] ?? 'archive',
                'topics' => is_array($subjects) ? $subjects : [$subjects],
                'score_base' => min(5.5, ((float) ($result['downloads'] ?? 0) / 1000.0) + 1.2),
                'fetched_at' => rr_now_iso(),
            ];
        }
    }

    return [
        'meta' => [
            'ok' => $response['ok'],
            'status' => $response['status'],
            'count' => count($items),
            'url' => $url,
        ],
        'items' => $items,
    ];
}

$sources = [
    'openalex' => fetch_openalex(),
    'crossref' => fetch_crossref(),
    'arxiv' => fetch_arxiv(),
    'internet_archive' => fetch_internet_archive(),
];

$previousPayload = rr_candidates_payload();
$candidates = [];
foreach ($sources as $source) {
    $candidates = array_merge($candidates, $source['items']);
}

$dedupedCandidates = rr_dedupe_candidates($candidates);
$retainedPrevious = false;
if ($dedupedCandidates === [] && !empty($previousPayload['candidates'])) {
    $dedupedCandidates = rr_dedupe_candidates($previousPayload['candidates']);
    $retainedPrevious = true;
}

$payload = [
    'generated_at' => rr_now_iso(),
    'sources' => array_map(static fn(array $source): array => $source['meta'], $sources),
    'candidates' => $dedupedCandidates,
    'retained_previous_candidates' => $retainedPrevious,
];

rr_write_json(READ_CANDIDATES_FILE, $payload);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

echo json_encode([
    'ok' => count($payload['candidates']) > 0,
    'generated_at' => $payload['generated_at'],
    'source_status' => $payload['sources'],
    'candidate_count' => count($payload['candidates']),
    'retained_previous_candidates' => $payload['retained_previous_candidates'],
    'output_file' => READ_CANDIDATES_FILE,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
