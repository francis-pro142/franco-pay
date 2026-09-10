// Minimal frontend app for FRANCO PAY

// Resolve the API root from the page's own location, so the app works both at
// the domain root and inside a subdirectory (e.g. https://host/franco-pay/).
// A hardcoded '/api' breaks in the latter case: the request lands outside the
// app and the web server answers with its own 404 page.
const API_BASE = (function () {
  const path = window.location.pathname;
  const marker = path.lastIndexOf('/frontend/');
  const base = marker >= 0 ? path.slice(0, marker) : path.replace(/\/[^/]*$/, '');
  return (base === '/' ? '' : base) + '/api';
})();

// Read the body once and turn non-JSON replies (server 404 pages, PHP fatals,
// empty bodies) into a readable Error rather than a bare
// "Unexpected end of JSON input" from Response.json().
async function parseJson(res) {
  const body = (await res.text()).trim();

  if (!body) {
    if (res.status === 404) {
      throw new Error('API endpoint not found (404): ' + res.url
        + ' - check that the server rewrites unknown paths to index.php.');
    }
    throw new Error('The server returned an empty response (HTTP ' + res.status + ').');
  }

  try {
    return JSON.parse(body);
  } catch (err) {
    throw new Error('The server returned a non-JSON response (HTTP ' + res.status + '): '
      + body.slice(0, 200));
  }
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
      throw new Error('HTTP ' + res.status + ' ' + res.statusText + ': ' + (text || res.url));
    }
    return parseJson(res);
  }).catch((err) => {
    err.apiBase = API_BASE;
    throw err;
  });
}

function showApiError(err) {
  console.error('API error:', err);
  const banner = document.getElementById('apiError');
  if (banner) {
    banner.textContent = 'API error (' + API_BASE + '): ' + (err && err.message ? err.message : String(err));
    banner.classList.remove('hidden');
  } else {
    const wb = document.getElementById('welcomeBanner');
    if (wb) wb.textContent = 'Unable to load data from the API.';
  }
}

function hideApiError() {
  const banner = document.getElementById('apiError');
  if (banner) {
    banner.classList.add('hidden');
    banner.textContent = '';
  }
}

function go(path) { window.location.href = path; }
function uuid() { return (crypto && crypto.randomUUID) ? crypto.randomUUID() : Math.random().toString(16).slice(2) + Date.now().toString(16); }
function formatMoney(value) {
  const amount = Number(value || 0);
  return new Intl.NumberFormat('en-GH', { style: 'currency', currency: 'GHS', minimumFractionDigits: 2 }).format(amount);
}
function getGreetingFromServerTime(isoString) {
  const serverDate = new Date(isoString || Date.now());
  const hour = serverDate.getHours();
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

function validateEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
}

function validatePhone(value) {
  return /^\+?[0-9\-()\s]{7,20}$/.test(String(value || '').trim());
}

function validatePassword(value) {
  return String(value || '').length >= 8;
}

const loginForm = document.getElementById('loginForm');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const identifier = (loginForm.email.value || '').trim();
    const password = loginForm.password.value;

    if (!identifier || !password || !validatePassword(password)) {
      alert('Please enter a valid email/phone and a password with at least 8 characters.');
      return;
    }

    const data = {
      email: identifier,
      phone: identifier,
      password: password
    };
    try {
      const res = await fetch(API_BASE + '/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(parseJson);

      if (res.token) {
        localStorage.setItem('fp_token', res.token);
        go('dashboard.html');
      } else {
        alert(res.error || 'Login failed');
      }
    } catch (err) {
      console.error(err);
      alert('Login failed: ' + err.message);
    }
  });
}

function showWelcomeBonusModal() {
  const modal = document.getElementById('bonusModal');
  if (!modal) return;
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
}

function hideWelcomeBonusModal() {
  const modal = document.getElementById('bonusModal');
  if (!modal) return;
  modal.classList.add('hidden');
  modal.setAttribute('aria-hidden', 'true');
}

async function claimWelcomeBonus() {
  const token = localStorage.getItem('fp_token');
  if (!token) return;

  let res;
  try {
    res = await fetch(API_BASE + '/wallet/claim-bonus', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
      }
    }).then(parseJson);
  } catch (err) {
    console.error(err);
    alert('Unable to accept bonus right now: ' + err.message);
    return false;
  }

  if (res.status === 'claimed' || res.balance !== undefined) {
    hideWelcomeBonusModal();
    const walletBalance = document.getElementById('walletBalance');
    if (walletBalance) walletBalance.textContent = formatMoney(res.balance ?? 0);
    const banner = document.getElementById('welcomeBanner');
    if (banner) banner.textContent = `Bonus accepted: ${formatMoney(res.balance ?? 0)} is now available in your wallet.`;
    return true;
  }

  alert(res.error || 'Unable to accept bonus right now.');
  return false;
}

const regForm = document.getElementById('registerForm');
if (regForm) {
  regForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const emailValue = (regForm.email.value || '').trim();
    const phoneValue = (regForm.phone.value || '').trim();
    const passwordValue = regForm.password.value;
    const fullName = (regForm.full_name.value || '').trim();

    if (!fullName || (!emailValue && !phoneValue) || !validatePassword(passwordValue)) {
      alert('Please provide a full name, a valid email or phone number, and a password of at least 8 characters.');
      return;
    }

    if (emailValue && !validateEmail(emailValue)) {
      alert('The email address is invalid.');
      return;
    }

    if (phoneValue && !validatePhone(phoneValue)) {
      alert('The phone number is invalid.');
      return;
    }

    const data = {
      full_name: fullName,
      email: emailValue,
      phone: phoneValue,
      password: passwordValue
    };

    try {
      const res = await fetch(API_BASE + '/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      }).then(parseJson);

      if (res.wallet_number) {
        alert('Account created. Your first-time welcome bonus of GHC 100,000 has been added.');
        go('login.html');
      } else {
        alert(res.error || 'Registration failed');
      }
    } catch (err) {
      console.error(err);
      alert('Registration failed: ' + err.message);
    }
  });
}

let currentWalletState = { balance: 0, wallet_number: '' };
let pendingRecipientConfirmation = null;

if (document.location.pathname.endsWith('/send.html')) {
  (async () => {
    try {
      const token = localStorage.getItem('fp_token');
      if (!token) {
        go('login.html');
        return;
      }

      const wallet = await apiFetch('/wallet');
      currentWalletState = {
        balance: Number(wallet.balance ?? 0),
        wallet_number: wallet.wallet_number || ''
      };

      const balanceEl = document.getElementById('availableBalance');
      if (balanceEl) balanceEl.textContent = formatMoney(currentWalletState.balance);
    } catch (err) {
      console.error(err);
      const result = document.getElementById('result');
      if (result) result.textContent = 'Unable to load wallet details.';
    }
  })();
}

function showRecipientReview(recipientDetails) {
  const modal = document.getElementById('recipientModal');
  const nameEl = document.getElementById('reviewName');
  const walletEl = document.getElementById('reviewWallet');
  if (!modal || !nameEl || !walletEl) return;

  nameEl.textContent = recipientDetails.full_name;
  walletEl.textContent = `${recipientDetails.wallet_number} • ${formatMoney(recipientDetails.amount)}`;
  modal.classList.remove('hidden');
  modal.setAttribute('aria-hidden', 'false');
  pendingRecipientConfirmation = recipientDetails;
}

function clearRecipientReview() {
  const modal = document.getElementById('recipientModal');
  if (modal) {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }
  pendingRecipientConfirmation = null;
}

if (document.location.pathname.endsWith('/dashboard.html')) {
  const sidebarNav = Array.from(document.querySelectorAll('.side-nav a'));
  sidebarNav.forEach((item) => {
    item.addEventListener('click', () => {
      sidebarNav.forEach((navItem) => navItem.classList.toggle('active', navItem === item));
      const nav = document.querySelector('.sidebar');
      if (nav) nav.classList.remove('mobile-open');
    });
  });

  const menuToggle = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');
  if (menuToggle && sidebar) {
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('mobile-open');
    });
  }

  const renderTransactions = (transactions = []) => {
    const container = document.getElementById('transactions');
    if (!container) return;

    if (!transactions.length) {
      container.innerHTML = '<div class="empty-state">No transactions yet. Your welcome bonus is ready to use.</div>';
      return;
    }

    const rows = transactions.map((t) => {
      const label = t.status || 'PENDING';
      const badge = label === 'SUCCESS' ? 'success' : 'neutral';
      return `
        <div class="transaction-row">
          <div>
            <strong>${t.transaction_reference || '—'}</strong>
            <small>${new Date(t.created_at || Date.now()).toLocaleString()}</small>
          </div>
          <div class="amount-block">
            <span class="amount ${Number(t.amount) > 0 ? 'credit' : 'debit'}">${formatMoney(t.amount)}</span>
            <span class="status-badge ${badge}">${label}</span>
          </div>
        </div>
      `;
    }).join('');

    container.innerHTML = rows;
  };

  const loadDashboard = async () => {
    try {
      hideApiError();
      const token = localStorage.getItem('fp_token');
      if (!token) {
        go('login.html');
        return;
      }

      const wallet = await apiFetch('/wallet');
      const balance = Number(wallet.balance ?? 0);
      const walletNumber = wallet.wallet_number || '—';
      const currency = wallet.currency || 'GHS';
      const userName = wallet.full_name || 'Account Holder';
      const greeting = getGreetingFromServerTime(wallet.server_time);
      const bonusEligible = !!wallet.bonus_eligible;

      const greetingEl = document.getElementById('greetingText');
      if (greetingEl) greetingEl.textContent = greeting;

      const welcomeTitleEl = document.getElementById('welcomeTitle');
      if (welcomeTitleEl) welcomeTitleEl.textContent = `${userName}`;

      const accountNameEl = document.getElementById('accountName');
      if (accountNameEl) accountNameEl.textContent = userName;

      const accountNameSmallEl = document.getElementById('accountNameSmall');
      if (accountNameSmallEl) accountNameSmallEl.textContent = userName;

      const timestampEl = document.getElementById('serverTimestamp');
      if (timestampEl && wallet.server_time) {
        const serverDate = new Date(wallet.server_time);
        timestampEl.textContent = serverDate.toLocaleString('en-GH', {
          dateStyle: 'medium',
          timeStyle: 'short'
        });
      }

      const balanceEl = document.getElementById('walletBalance');
      if (balanceEl) balanceEl.textContent = formatMoney(balance);

      const walletNumberEl = document.getElementById('walletNumber');
      if (walletNumberEl) walletNumberEl.textContent = `${walletNumber} • ${currency}`;

      const walletCodeEl = document.getElementById('walletCode');
      if (walletCodeEl) walletCodeEl.textContent = walletNumber;

      const walletNumberDetailEl = document.getElementById('walletNumberDetail');
      if (walletNumberDetailEl) walletNumberDetailEl.textContent = walletNumber;

      const banner = document.getElementById('welcomeBanner');
      if (banner) {
        banner.textContent = bonusEligible
          ? 'A first-time welcome bonus is available and waiting for your approval.'
          : balance >= 100000
            ? `Welcome bonus: ${formatMoney(100000)} has been added to ${userName}'s account.`
            : 'Your wallet is active and ready for your next transfer.';
      }

      if (bonusEligible) {
        showWelcomeBonusModal();
      } else {
        hideWelcomeBonusModal();
      }

      const tx = await apiFetch('/transactions');
      const txCountEl = document.getElementById('txCount');
      if (txCountEl) txCountEl.textContent = String(tx.transactions ? tx.transactions.length : 0);

      renderTransactions(tx.transactions || []);
    } catch (err) {
      console.error(err);
      showApiError(err);
      const container = document.getElementById('transactions');
      if (container) container.innerHTML = '<div class="empty-state">Unable to load your dashboard right now.</div>';
    }
  };

  loadDashboard();

  const refreshBtn = document.getElementById('refreshWallet');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', loadDashboard);
  }

  const acceptBonusBtn = document.getElementById('acceptBonusBtn');
  if (acceptBonusBtn) {
    acceptBonusBtn.addEventListener('click', async () => {
      const credited = await claimWelcomeBonus();
      if (credited) {
        await loadDashboard();
      }
    });
  }

  const dismissBonusBtn = document.getElementById('dismissBonusBtn');
  if (dismissBonusBtn) {
    dismissBonusBtn.addEventListener('click', () => {
      hideWelcomeBonusModal();
    });
  }

  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      const token = localStorage.getItem('fp_token');
      if (token) {
        try {
          await fetch(API_BASE + '/auth/logout', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': 'Bearer ' + token
            }
          });
        } catch (err) {
          console.warn('Logout API call failed.', err);
        }
      }
      localStorage.removeItem('fp_token');
      go('login.html');
    });
  }
}

// Admin audit page
if (document.location.pathname.endsWith('/admin.html')) {
  const auditContainer = document.getElementById('auditContainer');
  const refreshBtn = document.getElementById('refreshAudit');
  const logoutBtn = document.getElementById('logoutBtn');
  const searchInput = document.getElementById('auditSearch');
  const pageSizeSelect = document.getElementById('auditPageSize');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');
  const currentPageEl = document.getElementById('currentPage');

  let auditItems = [];
  let page = 1;

  function renderPage() {
    if (!auditContainer) return;
    const pageSize = parseInt(pageSizeSelect.value || '25', 10) || 25;
    const q = (searchInput.value || '').toLowerCase().trim();
    let filtered = auditItems;
    if (q) {
      filtered = auditItems.filter(it => {
        return String(it.action || '').toLowerCase().includes(q)
          || String(it.user_id || '').toLowerCase().includes(q)
          || String(it.entity_type || '').toLowerCase().includes(q)
          || String(it.entity_id || '').toLowerCase().includes(q)
          || String(it.metadata || '').toLowerCase().includes(q)
          || String(it.ip_address || '').toLowerCase().includes(q);
      });
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
    if (page > totalPages) page = totalPages;
    const start = (page - 1) * pageSize;
    const slice = filtered.slice(start, start + pageSize);

    currentPageEl.textContent = String(page) + ' / ' + String(totalPages);

    if (!slice.length) {
      auditContainer.innerHTML = '<div class="empty-state">No audit logs found.</div>';
      return;
    }

    const rows = slice.map((it) => {
      const ts = new Date(it.created_at || Date.now()).toLocaleString();
      return `
        <div class="audit-row">
          <div class="audit-col"><strong>${ts}</strong></div>
          <div class="audit-col">User: ${it.user_id || '—'}</div>
          <div class="audit-col">Action: ${it.action}</div>
          <div class="audit-col">Entity: ${it.entity_type || '—'} ${it.entity_id || ''}</div>
          <div class="audit-col">IP: ${it.ip_address || '—'}</div>
          <div class="audit-col">Meta: <small>${it.metadata || '—'}</small></div>
        </div>
      `;
    }).join('');

    auditContainer.innerHTML = rows;
  }

  async function loadAudit() {
    if (!auditContainer) return;
    auditContainer.innerHTML = '<div class="empty-state">Loading audit logs…</div>';
    try {
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('page_size', String(pageSizeSelect.value || '25'));
      params.set('q', searchInput.value || '');
      params.set('sort_by', document.getElementById('auditSortBy').value || 'created_at');
      params.set('sort_order', document.getElementById('auditSortOrder').value || 'DESC');
      const fromVal = (document.getElementById('fromDate') || {}).value || '';
      const toVal = (document.getElementById('toDate') || {}).value || '';
      if (fromVal) params.set('from', fromVal);
      if (toVal) params.set('to', toVal);

      const res = await fetch(API_BASE + '/admin/audit?' + params.toString(), {
        headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('fp_token') || '') }
      }).then(parseJson);

      if (!res.items) {
        auditItems = [];
        auditContainer.innerHTML = '<div class="empty-state">No audit items or access denied.</div>';
        return;
      }

      auditItems = res.items;
      page = 1;
      renderPage();
    } catch (err) {
      auditContainer.innerHTML = '<div class="empty-state">Unable to load audit logs.</div>';
    }
  }

  // Export CSV using server-side export endpoint
  const exportBtn = document.getElementById('exportCsv');
  if (exportBtn) {
    exportBtn.addEventListener('click', async () => {
      const token = localStorage.getItem('fp_token') || '';
      const params = new URLSearchParams();
      params.set('page', String(page));
      params.set('page_size', String(pageSizeSelect.value || '25'));
      params.set('q', searchInput.value || '');
      params.set('sort_by', document.getElementById('auditSortBy').value || 'created_at');
      params.set('sort_order', document.getElementById('auditSortOrder').value || 'DESC');
      params.set('export', 'csv');

      try {
        const resp = await fetch(API_BASE + '/admin/audit?' + params.toString(), {
          headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!resp.ok) {
          alert('Export failed: ' + resp.statusText);
          return;
        }
        const blob = await resp.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'audit_logs.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      } catch (err) {
        alert('Export failed');
      }
    });
  }

  // Preset buttons
  const presetToday = document.getElementById('presetToday');
  const preset7 = document.getElementById('preset7');
  const preset30 = document.getElementById('preset30');
  function setDateRange(days) {
    const now = new Date();
    const to = now.toISOString().slice(0,10);
    const from = new Date(now.getTime() - (days-1)*24*60*60*1000).toISOString().slice(0,10);
    if (fromDate) fromDate.value = from;
    if (toDate) toDate.value = to;
    loadAudit();
  }
  if (presetToday) presetToday.addEventListener('click', () => setDateRange(1));
  if (preset7) preset7.addEventListener('click', () => setDateRange(7));
  if (preset30) preset30.addEventListener('click', () => setDateRange(30));
  if (refreshBtn) refreshBtn.addEventListener('click', loadAudit);
  if (searchInput) searchInput.addEventListener('input', () => { page = 1; renderPage(); });
  if (pageSizeSelect) pageSizeSelect.addEventListener('change', () => { page = 1; renderPage(); });
  if (prevBtn) prevBtn.addEventListener('click', () => { if (page > 1) { page--; renderPage(); } });
  if (nextBtn) nextBtn.addEventListener('click', () => { page++; renderPage(); });

  if (logoutBtn) logoutBtn.addEventListener('click', async () => {
    const token = localStorage.getItem('fp_token');
    if (token) {
      try { await fetch(API_BASE + '/auth/logout', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token } }); } catch(e){}
    }
    localStorage.removeItem('fp_token');
    window.location.href = 'login.html';
  });

  loadAudit();
}

const sendForm = document.getElementById('sendForm');
if (sendForm) {
  const out = document.getElementById('result');
  const cancelBtn = document.getElementById('cancelReviewBtn');
  const confirmBtn = document.getElementById('confirmTransferBtn');

  sendForm.addEventListener('input', () => {
    clearRecipientReview();
    if (out) {
      out.classList.remove('error', 'success');
      out.textContent = 'Ready to send.';
    }
  });

  const submitReview = async () => {
    const recipient = (sendForm.recipient.value || '').trim();
    const amount = parseFloat(sendForm.amount.value);
    const description = (sendForm.description.value || '').trim();

    if (!recipient) {
      out.textContent = 'Please enter a valid recipient wallet number.';
      out.classList.add('error');
      return;
    }

    if (recipient.toUpperCase() === currentWalletState.wallet_number.toUpperCase()) {
      out.textContent = 'Security check failed: you cannot send money to your own wallet.';
      out.classList.add('error');
      return;
    }

    if (!Number.isFinite(amount) || amount <= 0) {
      out.textContent = 'Amount must be greater than zero.';
      out.classList.add('error');
      return;
    }

    if (amount > currentWalletState.balance) {
      out.textContent = 'Warning: this transfer may exceed your available balance. The server will validate and reject if funds are insufficient.';
      out.classList.remove('error');
      out.classList.add('warning');
      // do not return here; allow server to make authoritative decision
    }

    try {
      const lookup = await fetch(API_BASE + '/wallet/resolve?wallet_number=' + encodeURIComponent(recipient), {
        headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('fp_token') || '') }
      }).then(parseJson);

      if (!lookup || lookup.error) {
        out.textContent = 'Recipient not found. Please verify the wallet number.';
        out.classList.add('error');
        return;
      }

      showRecipientReview({
        full_name: lookup.full_name,
        wallet_number: lookup.wallet_number,
        amount
      });

      out.textContent = 'Please confirm the recipient before sending.';
      out.classList.remove('error');
      out.classList.add('success');
    } catch (err) {
      out.textContent = 'Unable to verify recipient details.';
      out.classList.add('error');
    }
  };

  sendForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (pendingRecipientConfirmation) {
      return;
    }
    await submitReview();
  });

  confirmBtn.addEventListener('click', async () => {
    if (!pendingRecipientConfirmation) return;

    const confirmedRecipient = pendingRecipientConfirmation.wallet_number;
    const confirmedAmount = pendingRecipientConfirmation.amount;
    const data = {
      recipient: confirmedRecipient,
      amount: confirmedAmount,
      description: (sendForm.description.value || '').trim()
    };

    const idKey = uuid();
    const resp = await fetch(API_BASE + '/transactions', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Idempotency-Key': idKey,
        'Authorization': 'Bearer ' + (localStorage.getItem('fp_token') || '')
      },
      body: JSON.stringify(data)
    }).then(parseJson);

    clearRecipientReview();
    if (resp.status === 'SUCCESS') {
      out.textContent = 'Transfer successful. Reference: ' + (resp.transaction?.transaction_reference || 'n/a');
      out.classList.add('success');
      sendForm.reset();
      const updatedBalance = Math.max(0, currentWalletState.balance - confirmedAmount);
      currentWalletState.balance = updatedBalance;
      const balanceEl = document.getElementById('availableBalance');
      if (balanceEl) balanceEl.textContent = formatMoney(updatedBalance);
    } else {
      out.textContent = 'Security check failed: ' + (resp.reason || resp.error || 'Please verify the transfer details.');
      out.classList.add('error');
    }
  });

  cancelBtn.addEventListener('click', () => {
    clearRecipientReview();
    out.textContent = 'Transfer cancelled. Please review the recipient again.';
    out.classList.remove('success');
    out.classList.add('error');
  });
}
