// Versioned frontend app (v3) - cache-bust copy of app.js

// Resolve the API root from the page's own location, so the app works both at
// the domain root and inside a subdirectory (e.g. https://host/franco-pay/).
const API_BASE = (function () {
  const path = window.location.pathname;
  const marker = path.lastIndexOf('/frontend/');
  const base = marker >= 0 ? path.slice(0, marker) : path.replace(/\/[^/]*$/, '');
  const candidate = (base === '/' ? '' : base) + '/api';
  try {
    const host = window.location.hostname || '';
    if (host.indexOf('railway.app') >= 0 || host.indexOf('vercel.app') >= 0) {
      return (base === '/' ? '' : base) + '/index.php/api';
    }
  } catch (e) {}
  return candidate;
})();

async function parseJson(res) {
  const body = (await res.text()).trim();
  if (!body) {
    if (res.status === 404) {
      throw new Error('API endpoint not found (404): ' + res.url
        + ' - check that the server rewrites unknown paths to index.php.');
    }
    throw new Error('The server returned an empty response (HTTP ' + res.status + ').');
  }
  try { return JSON.parse(body); } catch (err) { throw new Error('The server returned a non-JSON response (HTTP ' + res.status + '): ' + body.slice(0,200)); }
}

function apiFetch(path, opts = {}) {
  const token = localStorage.getItem('fp_token');
  const headers = opts.headers || {};
  headers['Content-Type'] = headers['Content-Type'] || 'application/json';
  if (token) headers['Authorization'] = 'Bearer ' + token;
  opts.headers = headers;
  return fetch(API_BASE + path, opts).then(async (res) => {
    if (!res.ok) {
      const text = (await res.text()).trim();
      const err = new Error('HTTP ' + res.status + ' ' + res.statusText + ': ' + (text || res.url));
      err.status = res.status;
      throw err;
    }
    return parseJson(res);
  }).catch(async (err) => {
    if (err && (err.status === 404 || /404/.test(String(err.message || ''))) && API_BASE.indexOf('/index.php') < 0) {
      try {
        const altBase = (API_BASE.replace(/^\//, '/index.php/'));
        const altRes = await fetch(altBase + path, opts);
        if (!altRes.ok) {
          const t = (await altRes.text()).trim();
          const e2 = new Error('Fallback HTTP ' + altRes.status + ' ' + altRes.statusText + ': ' + (t || altRes.url));
          e2.status = altRes.status;
          throw e2;
        }
        return parseJson(altRes);
      } catch (err2) {
        err2.apiBase = API_BASE;
        throw err2;
      }
    }
    err.apiBase = API_BASE;
    throw err;
  });
}

function showApiError(err) { console.error('API error:', err); const banner = document.getElementById('apiError'); if (banner) { banner.textContent = 'API error (' + API_BASE + '): ' + (err && err.message ? err.message : String(err)); banner.classList.remove('hidden'); } }

function hideApiError() { const banner = document.getElementById('apiError'); if (banner) { banner.classList.add('hidden'); banner.textContent = ''; } }

// Minimal behaviors (rest of app is intentionally omitted in this versioned copy)
window.fpDebug = { API_BASE };
