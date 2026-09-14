const bootstrap = window.READ_BOOTSTRAP || {};

function ensureDeviceToken() {
  const storageKey = 'cw_read_device_token';
  let token = '';
  try {
    token = window.localStorage.getItem(storageKey) || '';
    if (!token) {
      token = (crypto.randomUUID ? crypto.randomUUID() : `device-${Date.now()}`)
        .replace(/[^a-zA-Z0-9_-]/g, '')
        .slice(0, 64);
      window.localStorage.setItem(storageKey, token);
    }

    function summarizeCandidate(candidate) {
      const abstract = String(candidate.abstract || '').trim();
      return abstract ? `${abstract.slice(0, 520)}${abstract.length > 520 ? '…' : ''}` : 'No summary was available from the source; use the original link for details.';
    }
  } catch (error) {
    token = `fallback-${Date.now()}`;
  }

  document.cookie = `cw_read_device=${token}; path=/; max-age=31536000; SameSite=Lax`;
  return token;
}

function fallbackScorer(profile) {
  return {
    scoreCandidate(candidate) {
      const weights = profile.topic_weights || {};
      const preferences = profile.preferences || {};
      const topics = Array.isArray(candidate.topics) ? candidate.topics : [];
      let topicBoost = 0;
      for (const topic of topics) {
        const key = String(topic).toLowerCase();
        topicBoost += Number(weights[key] || 0);
      }

      const abstract = String(candidate.abstract || '');
      const wantsTechnical = Number(preferences.technicality || 0);
      const technicalityBoost = abstract.length > 600 ? wantsTechnical * 0.35 : (wantsTechnical < 0 ? 0.15 : 0.05);
      const freshnessBoost = candidate.year ? Math.max(0, 2 - (new Date().getUTCFullYear() - candidate.year) * 0.1) : 0;
      return Number((topicBoost + technicalityBoost + freshnessBoost).toFixed(3));
    }
  };
}

async function loadScorer(profile) {
  // TODO: Replace this stub with a tiny compiled WASM scorer if/when the hosting
  // target can reliably serve `application/wasm`. The interface is already wired.
  try {
    const response = await fetch('wasm/scorer.wasm', { cache: 'no-store' });
    if (!response.ok) {
      throw new Error(`WASM unavailable: ${response.status}`);
    }
    const bytes = await response.arrayBuffer();
    const wasm = await WebAssembly.instantiate(bytes, {});
    if (typeof wasm.instance.exports.score_candidate === 'function') {
      return {
        scoreCandidate(candidate) {
          const topicCount = Array.isArray(candidate.topics) ? candidate.topics.length : 0;
          const year = Number(candidate.year || 0);
          const base = Number(candidate.score_base || 0);
          return Number(wasm.instance.exports.score_candidate(base, topicCount, year).toFixed(3));
        }
      };
    }
  } catch (error) {
    // fall through to deterministic JS fallback.
  }
  return fallbackScorer(profile);
}

async function personalizeSelection() {
  if (!bootstrap.todayEntry || bootstrap.todayEntry.personalized || !Array.isArray(bootstrap.topCandidates) || bootstrap.topCandidates.length === 0) {
    return;
  }

  ensureDeviceToken();
  const scorer = await loadScorer(bootstrap.profile || {});
  const scored = bootstrap.topCandidates.map((entry) => {
    const bonus = scorer.scoreCandidate(entry.candidate);
    return {
      candidate: entry.candidate,
      score: bonus,
      total: Number((Number(entry.scores.total || 0) + bonus).toFixed(3))
    };
  }).sort((a, b) => b.total - a.total);

  const selected = scored[0];
  if (!selected) {
    return;
  }

  const clientScores = Object.fromEntries(scored.map((entry) => [entry.candidate.id, entry.score]));
  const formData = new URLSearchParams();
  formData.set('selected_id', selected.candidate.id);
  formData.set('client_scores_json', JSON.stringify(clientScores));
  formData.set('device_token', ensureDeviceToken());

  try {
    const response = await fetch('index.php?action=finalize', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: formData.toString()
    });
    if (!response.ok) {
      return;
    }

    const payload = await response.json();
    if (!payload.ok || !payload.candidate) {
      return;
    }

    const card = document.getElementById('suggestion-card');
    if (!card) {
      return;
    }

    const link = card.querySelector('a.button-link');
    if (link) {
      link.href = payload.candidate.url;
    }
    const title = card.querySelector('h2');
    if (title) {
      title.textContent = payload.candidate.title;
    }
    const meta = document.getElementById('suggestion-meta');
    if (meta) {
      const authors = Array.isArray(payload.candidate.authors) && payload.candidate.authors.length
        ? ` · ${payload.candidate.authors.slice(0, 3).join(', ')}`
        : '';
      meta.textContent = `${payload.candidate.source}${payload.candidate.year ? ` · ${payload.candidate.year}` : ''}${authors}`;
    }
    const topics = document.getElementById('suggestion-topics');
    if (topics) {
      topics.innerHTML = '';
      for (const topic of (payload.candidate.topics || []).slice(0, 5)) {
        const span = document.createElement('span');
        span.className = 'pill';
        span.textContent = topic;
        topics.appendChild(span);
      }
    }
    const summary = document.getElementById('suggestion-summary');
    if (summary) {
      summary.textContent = summarizeCandidate(payload.candidate);
    }
    const why = document.getElementById('why-copy');
    if (why) {
      why.textContent = payload.selection.explanation;
    }
    const note = document.getElementById('personalization-note');
    if (note) {
      note.textContent = 'Locked in for today for this device after local reranking.';
    }
  } catch (error) {
    // Keep the server baseline selection if personalization fails.
  }
}

personalizeSelection();
