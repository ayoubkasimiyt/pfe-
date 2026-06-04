let campaigns = [];
let contacts = [];
let profile = {
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  company: '',
  website: '',
  bio: '',
  plan: 'Free',
  avatar_path: ''
};

let currentFilter = 'all';
let checkoutPlan = '';
let isYearly = false;
let charts = {};
let billingHistory = [];

const statusConfig = {
  sent: { color: '#34d399', bg: 'rgba(52,211,153,.12)', label: 'Sent' },
  draft: { color: '#94a3b8', bg: 'rgba(148,163,184,.12)', label: 'Draft' },
  scheduled: { color: '#fb923c', bg: 'rgba(251,146,60,.12)', label: 'Scheduled' }
};

const typeIcons = ['📧', '📰', '🎉', '🛒', '💼', '📊', '🎯', '💌'];

window.addEventListener('DOMContentLoaded', async () => {
  applyTheme(localStorage.getItem('mf_theme') || 'dark');
  bindStaticEvents();
  await bootstrap();
});

function bindStaticEvents() {
  const campStatus = document.getElementById('campStatus');
  if (campStatus) {
    campStatus.addEventListener('change', toggleScheduleField);
  }

  document.addEventListener('click', (event) => {
    const menu = document.getElementById('profileMenuDrop');
    const toggle = document.getElementById('profileToggle');
    if (menu && toggle && !menu.contains(event.target) && !toggle.contains(event.target)) {
      menu.classList.remove('open');
    }
  });
}

async function bootstrap() {
  try {
    const response = await api('session');
    if (!response.authenticated) {
      showAuthPage();
      return;
    }

    hydrateState(response);
    initializeApp();
  } catch (error) {
    showAuthPage();
    showToast(error.message || 'Unable to load the application right now.', 'error');
  }
}

function hydrateState(payload) {
  profile = payload.user || profile;
  campaigns = Array.isArray(payload.campaigns) ? payload.campaigns : [];
  contacts = Array.isArray(payload.contacts) ? payload.contacts : [];
  billingHistory = Array.isArray(payload.billing_history) ? payload.billing_history : [];
}

function initializeApp() {
  showAppPage();
  applyProfile();
  updateAudienceCount();
  renderCampaigns();
  renderContacts();
  renderAnalytics();
  updateHomeStats();
  updateUsage();
  updateBadges();
  renderBillingHistory();
}

async function reloadAppData() {
  const response = await api('session');
  if (!response.authenticated) {
    handleForcedLogout();
    return;
  }

  hydrateState(response);
  initializeApp();
}

async function api(action, method = 'GET', data = null) {
  const options = {
    method,
    credentials: 'same-origin',
    headers: {}
  };

  if (data !== null) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(data);
  }

  const response = await fetch(`api.php?action=${encodeURIComponent(action)}`, options);
  const payload = await response.json().catch(() => ({}));

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || 'Request failed.');
  }

  return payload;
}

function showAuthPage() {
  document.getElementById('authScreen')?.classList.remove('hidden');
  document.querySelector('.app-shell')?.classList.add('locked');
}

function showAppPage() {
  document.getElementById('authScreen')?.classList.add('hidden');
  document.querySelector('.app-shell')?.classList.remove('locked');
}

function showAuthView(view) {
  const isSignup = view === 'signup';
  document.getElementById('loginTab')?.classList.toggle('active', !isSignup);
  document.getElementById('signupTab')?.classList.toggle('active', isSignup);
  document.getElementById('loginForm')?.classList.toggle('active', !isSignup);
  document.getElementById('signupForm')?.classList.toggle('active', isSignup);
  setAuthMessage('');
}

function setAuthMessage(text, type = 'error') {
  const el = document.getElementById('authMessage');
  if (!el) return;
  el.textContent = text;
  el.classList.toggle('success', type === 'success');
}

async function handleLogin(event) {
  event.preventDefault();

  const email = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;

  try {
    const response = await api('login', 'POST', { email, password });
    hydrateState(response);
    setAuthMessage('Login successful.', 'success');
    initializeApp();
  } catch (error) {
    setAuthMessage(error.message);
  }
}

async function handleSignup(event) {
  event.preventDefault();

  const first_name = document.getElementById('signupFirstName').value.trim();
  const last_name = document.getElementById('signupLastName').value.trim();
  const email = document.getElementById('signupEmail').value.trim();
  const password = document.getElementById('signupPassword').value;

  try {
    const response = await api('register', 'POST', {
      first_name,
      last_name,
      email,
      password
    });

    hydrateState(response);
    setAuthMessage('Account created successfully.', 'success');
    initializeApp();
  } catch (error) {
    setAuthMessage(error.message);
  }
}

async function handleLogout() {
  try {
    await api('logout', 'POST', {});
  } catch (error) {
    // Ignore logout errors and clear the UI anyway.
  }

  handleForcedLogout();
}

function handleForcedLogout() {
  campaigns = [];
  contacts = [];
  profile = {
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    website: '',
    bio: '',
    plan: 'Free',
    avatar_path: ''
  };

  document.getElementById('loginForm')?.reset();
  document.getElementById('signupForm')?.reset();
  showAuthView('login');
  showAuthPage();
}

function navigate(page) {
  document.querySelectorAll('.page').forEach((section) => section.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach((item) => item.classList.remove('active'));
  document.getElementById(`page-${page}`)?.classList.add('active');
  document.querySelector(`.nav-item[data-page="${page}"]`)?.classList.add('active');

}

function toggleTheme() {
  const next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
  applyTheme(next);
  localStorage.setItem('mf_theme', next);
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  const themeBtn = document.getElementById('themeBtn');
  if (themeBtn) themeBtn.textContent = theme === 'light' ? '☀️' : '🌙';
}

function applyProfile() {
  const fullName = `${profile.first_name || ''} ${profile.last_name || ''}`.trim() || 'User';
  const firstChar = fullName.charAt(0).toUpperCase();

  setText('topbarName', fullName);
  setText('pmName', fullName);
  setText('pmEmail', profile.email || 'you@example.com');
  setText('heroName', `Welcome, ${profile.first_name || 'there'}!`);
  setText('profileName', fullName);
  setText('profileEmail', profile.email || 'you@example.com');
  setText('profilePlanBadge', `${profile.plan || 'Free'} Plan`);
  setText('cpName', `${profile.plan || 'Free'} Plan`);
  setText('cpDesc', getPlanDescription(profile.plan || 'Free'));

  setValue('pFirstName', profile.first_name || '');
  setValue('pLastName', profile.last_name || '');
  setValue('pEmail', profile.email || '');
  setValue('pPhone', profile.phone || '');
  setValue('pCompany', profile.company || '');
  setValue('pWebsite', profile.website || '');
  setValue('pBio', profile.bio || '');
  setValue('billingEmail', profile.email || '');

  applyAvatar('topbarAvatar', profile.avatar_path, firstChar || 'U');
  applyAvatar('profileAvatar', profile.avatar_path, firstChar || 'U');
}

function applyAvatar(id, avatarPath, fallbackLetter) {
  const el = document.getElementById(id);
  if (!el) return;

  const hasImage = Boolean(avatarPath);
  el.style.backgroundImage = hasImage ? `url("${encodeURI(avatarPath)}")` : '';
  el.style.backgroundSize = hasImage ? 'cover' : '';
  el.style.backgroundPosition = hasImage ? 'center' : '';
  el.style.backgroundRepeat = hasImage ? 'no-repeat' : '';

  if (el.firstChild && el.firstChild.nodeType === Node.TEXT_NODE) {
    el.firstChild.nodeValue = hasImage ? '' : fallbackLetter;
  }
}

function updateHomeStats() {
  const sentCampaigns = campaigns.filter((campaign) => campaign.status === 'sent');
  const totalSent = sentCampaigns.reduce((sum, campaign) => sum + Number(campaign.recipients_count || 0), 0);
  const totalOpens = sentCampaigns.reduce((sum, campaign) => sum + Number(campaign.opens_count || 0), 0);
  const totalClicks = sentCampaigns.reduce((sum, campaign) => sum + Number(campaign.clicks_count || 0), 0);
  const openRate = totalSent ? Math.round((totalOpens / totalSent) * 100) : 0;
  const clickRate = totalSent ? Math.round((totalClicks / totalSent) * 100) : 0;

  setText('homeTotalCampaigns', String(campaigns.length));
  setText('homeTotalContacts', String(contacts.length));
  setText('homeOpenRate', `${openRate}%`);
  setText('homeClickRate', `${clickRate}%`);
  setText('homeCampChange', `↑ +${sentCampaigns.length} sent`);
}

function updateUsage() {
  const totalEmails = campaigns.reduce((sum, campaign) => sum + Number(campaign.recipients_count || 0), 0);
  setText('usageCampaigns', String(campaigns.length));
  setText('usageContacts', String(contacts.length));
  setText('usageEmails', String(totalEmails));

  const campBar = document.getElementById('usageCampaignsBar');
  const contactBar = document.getElementById('usageContactsBar');
  const emailBar = document.getElementById('usageEmailsBar');

  if (campBar) campBar.style.width = `${Math.min((campaigns.length / 3) * 100, 100)}%`;
  if (contactBar) contactBar.style.width = `${Math.min((contacts.length / 500) * 100, 100)}%`;
  if (emailBar) emailBar.style.width = `${Math.min((totalEmails / 1000) * 100, 100)}%`;
}

function getPlanDescription(plan) {
  switch ((plan || 'Free').toLowerCase()) {
    case 'pro':
      return 'You are on the Pro plan. Enjoy more contacts and better sending capacity.';
    case 'business':
      return 'You are on the Business plan. Your account is ready for higher email volume.';
    case 'enterprise':
      return 'You are on the Enterprise plan. Your billing is customized for your company.';
    default:
      return 'You are on the free plan. Upgrade to unlock more features.';
  }
}

function updateBadges() {
  setText('campaignBadge', String(campaigns.length));
  setText('contactBadge', String(contacts.length));
}

function renderBillingHistory() {
  const title = document.getElementById('billingHistoryTitle');
  if (!title) return;

  const card = title.closest('.card');
  if (!card) return;

  const rows = billingHistory.length
    ? billingHistory.map((item) => `
      <div class="billing-row">
        <div class="billing-icon">${escHtml(item.icon || '💳')}</div>
        <div class="billing-desc">
          <div class="bname">${escHtml(item.title)}</div>
          <div class="bdate">${escHtml(formatDate(item.created_at))}</div>
        </div>
        <div class="billing-amount">${escHtml(item.amount_label)}</div>
      </div>
    `).join('')
    : `<div class="billing-row"><div class="billing-icon">💳</div><div class="billing-desc"><div class="bname">No billing activity yet</div><div class="bdate">Your future plan changes will appear here.</div></div><div class="billing-amount">$0.00</div></div>`;

  card.innerHTML = `<div class="card-title" id="billingHistoryTitle" style="margin-bottom:16px">Billing History</div>${rows}`;
}

function renderCampaigns() {
  const grid = document.getElementById('campaignsGrid');
  if (!grid) return;

  const query = (document.getElementById('campaignSearch')?.value || '').trim().toLowerCase();
  const filtered = campaigns
    .filter((campaign) => currentFilter === 'all' || campaign.status === currentFilter)
    .filter((campaign) => {
      if (!query) return true;
      return campaign.name.toLowerCase().includes(query) || campaign.subject.toLowerCase().includes(query);
    });

  if (!filtered.length) {
    grid.innerHTML = `<div style="grid-column:1/-1">
      <div class="empty-state">
        <div class="empty-icon">✉️</div>
        <div class="empty-title">${campaigns.length ? 'No matching campaigns' : 'No campaigns yet'}</div>
        <div class="empty-desc">${campaigns.length ? 'Try another search or filter.' : 'Create your first campaign to get started.'}</div>
        <button class="btn btn-primary" onclick="openCampaignModal()">+ Create Campaign</button>
      </div>
    </div>`;
    return;
  }

  grid.innerHTML = filtered.map((campaign, index) => {
    const config = statusConfig[campaign.status] || statusConfig.draft;
    const openRate = campaign.recipients_count ? Math.round((campaign.opens_count / campaign.recipients_count) * 100) : 0;
    const clickRate = campaign.recipients_count ? Math.round((campaign.clicks_count / campaign.recipients_count) * 100) : 0;

    return `
      <div class="campaign-card" style="animation-delay:${index * 0.06}s">
        <div class="campaign-card-top">
          <div class="campaign-type-icon" style="background:rgba(79,124,255,.12)">${typeIcons[index % typeIcons.length]}</div>
          <div class="campaign-menu">
            <button class="btn btn-secondary btn-sm btn-icon" onclick="viewCampaign(${campaign.id})" title="View">👁️</button>
            <button class="btn btn-secondary btn-sm btn-icon" onclick="openCampaignModal(${campaign.id})" title="Edit">✏️</button>
            <button class="btn btn-danger btn-sm btn-icon" onclick="deleteCampaign(${campaign.id})" title="Delete">🗑️</button>
          </div>
        </div>
        <div class="campaign-name">${escHtml(campaign.name)}</div>
        <div class="campaign-subject">${escHtml(campaign.subject)}</div>
        <div style="display:flex;align-items:center;gap:8px;margin-top:10px;flex-wrap:wrap">
          <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:${config.bg};color:${config.color}">
            <span style="width:5px;height:5px;border-radius:50%;background:${config.color};display:inline-block"></span>${config.label}
          </span>
          <span style="font-size:11px;color:var(--text-muted)">${formatDate(campaign.created_at)}</span>
        </div>
        <div class="campaign-stats">
          <div class="cs-item"><div class="cs-val">${campaign.recipients_count || '—'}</div><div class="cs-key">Recipients</div></div>
          <div class="cs-item"><div class="cs-val" style="color:#fb923c">${openRate}%</div><div class="cs-key">Open Rate</div></div>
          <div class="cs-item"><div class="cs-val" style="color:#f472b6">${clickRate}%</div><div class="cs-key">Click Rate</div></div>
        </div>
      </div>
    `;
  }).join('');
}

function filterCampaigns() {
  renderCampaigns();
}

function setFilter(button, filter) {
  currentFilter = filter;
  document.querySelectorAll('.filter-chip').forEach((chip) => chip.classList.remove('active'));
  button?.classList.add('active');
  renderCampaigns();
}

function viewCampaign(id) {
  const selected = campaigns.find((campaign) => Number(campaign.id) === Number(id));
  if (!selected) {
    showToast('Campaign not found.', 'error');
    return;
  }

  openCampaignModal(selected.id);
  showToast('Campaign loaded.', 'info');
}

function openCampaignModal(id = null) {
  resetCampaignModal();

  if (id) {
    const campaign = campaigns.find((item) => Number(item.id) === Number(id));
    if (!campaign) return;

    document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
    document.getElementById('editCampaignId').value = campaign.id;
    setValue('campName', campaign.name);
    setValue('campSubject', campaign.subject);
    setValue('campSenderName', campaign.sender_name || '');
    setValue('campSenderEmail', campaign.sender_email || '');
    setValue('campAudienceTags', campaign.audience_tags || '');
    setValue('campAudienceNotes', campaign.audience_notes || '');
    setValue('campRecipients', campaign.recipients_raw || '');
    setValue('campStatus', campaign.status || 'draft');
    setValue('campScheduleDate', normalizeDateTimeLocal(campaign.schedule_at));
    const editor = document.getElementById('campContent');
    if (editor) editor.innerHTML = campaign.content || '';
  }

  toggleScheduleField();
  updateAudienceCount();
  document.getElementById('campaignModal')?.classList.add('open');
}

function closeCampaignModal() {
  document.getElementById('campaignModal')?.classList.remove('open');
}

function resetCampaignModal() {
  document.getElementById('campaignModalTitle').textContent = 'Create Campaign';
  setValue('editCampaignId', '');
  setValue('campName', '');
  setValue('campSubject', '');
  setValue('campSenderName', `${profile.first_name || ''} ${profile.last_name || ''}`.trim());
  setValue('campSenderEmail', profile.email || '');
  setValue('campRecipients', '');
  setValue('campAudienceTags', '');
  setValue('campAudienceNotes', '');
  setValue('campStatus', 'draft');
  setValue('campScheduleDate', '');
  setValue('productName', '');
  setValue('productPrice', '');
  setValue('productImage', '');
  setValue('productLink', '');
  setValue('productCta', '');
  setValue('productDesc', '');
  const editor = document.getElementById('campContent');
  if (editor) editor.innerHTML = '';
  updateProductPreview();
}

function toggleScheduleField() {
  const status = document.getElementById('campStatus')?.value;
  const group = document.getElementById('schedDateGroup');
  if (group) group.style.display = status === 'scheduled' ? 'block' : 'none';
}

async function saveCampaign() {
  const id = document.getElementById('editCampaignId').value;
  const name = document.getElementById('campName').value.trim();
  const subject = document.getElementById('campSubject').value.trim();
  const sender_name = document.getElementById('campSenderName').value.trim();
  const sender_email = document.getElementById('campSenderEmail').value.trim();
  const content = document.getElementById('campContent').innerHTML.trim();
  const recipients_raw = document.getElementById('campRecipients').value.trim();
  const audience_tags = document.getElementById('campAudienceTags').value.trim();
  const audience_notes = document.getElementById('campAudienceNotes').value.trim();
  const status = document.getElementById('campStatus').value;
  const schedule_at = document.getElementById('campScheduleDate').value || null;

  if (!name) {
    showToast('Campaign name is required.', 'error');
    return;
  }

  if (!subject) {
    showToast('Subject line is required.', 'error');
    return;
  }

  if (status === 'sent' && parseRecipients(recipients_raw).length === 0) {
    showToast('Add at least one valid recipient before sending.', 'error');
    return;
  }

  try {
    const response = await api('campaigns', 'POST', {
      id,
      name,
      subject,
      sender_name,
      sender_email,
      content,
      recipients_raw,
      audience_tags,
      audience_notes,
      status,
      schedule_at
    });

    campaigns = response.campaigns || campaigns;
    closeCampaignModal();
    renderCampaigns();
    renderAnalytics();
    updateHomeStats();
    updateUsage();
    updateBadges();
    showToast(response.message || 'Campaign saved successfully.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function deleteCampaign(id) {
  if (!confirm('Delete this campaign?')) return;

  try {
    const response = await api('campaign_delete', 'POST', { id });
    campaigns = response.campaigns || campaigns.filter((campaign) => Number(campaign.id) !== Number(id));
    renderCampaigns();
    renderAnalytics();
    updateHomeStats();
    updateUsage();
    updateBadges();
    showToast(response.message || 'Campaign deleted.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function renderAnalytics() {
  const sent = campaigns.filter((campaign) => campaign.status === 'sent');
  const totalSent = sent.reduce((sum, campaign) => sum + Number(campaign.recipients_count || 0), 0);
  const totalOpens = sent.reduce((sum, campaign) => sum + Number(campaign.opens_count || 0), 0);
  const totalClicks = sent.reduce((sum, campaign) => sum + Number(campaign.clicks_count || 0), 0);
  const delivered = Math.round(totalSent * 0.97);
  const clickRate = totalSent ? Math.round((totalClicks / totalSent) * 100) : 0;

  setText('aTotalCampaigns', String(campaigns.length));
  setText('aTotalSent', String(totalSent));
  setText('aClickRate', `${clickRate}%`);
  setText('mSent', String(totalSent));
  setText('mDelivered', String(delivered));
  setText('mClicked', String(totalClicks));

  const clickFill = document.getElementById('clickFill');
  if (clickFill) clickFill.style.width = `${clickRate}%`;

  renderAnalyticsTable();
  renderActivityTimeline(sent);
  renderCharts(sent);
}

function renderAnalyticsTable() {
  const tbody = document.getElementById('analyticsTable');
  const empty = document.getElementById('analyticsTableEmpty');
  if (!tbody || !empty) return;

  if (!campaigns.length) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }

  empty.style.display = 'none';
  tbody.innerHTML = campaigns.map((campaign) => {
    const openRate = campaign.recipients_count ? Math.round((campaign.opens_count / campaign.recipients_count) * 100) : 0;
    const clickRate = campaign.recipients_count ? Math.round((campaign.clicks_count / campaign.recipients_count) * 100) : 0;
    return `
      <tr>
        <td>${escHtml(campaign.name)}</td>
        <td>${escHtml(campaign.status)}</td>
        <td>${campaign.recipients_count || 0}</td>
        <td>${openRate}%</td>
        <td>${clickRate}%</td>
        <td>${formatDate(campaign.created_at)}</td>
      </tr>
    `;
  }).join('');
}

function renderActivityTimeline(sentCampaigns) {
  const wrapper = document.getElementById('activityTimeline');
  if (!wrapper) return;

  if (!campaigns.length) {
    wrapper.innerHTML = `<div class="no-results">No campaign activity yet.</div>`;
    return;
  }

  wrapper.innerHTML = campaigns.slice(0, 5).map((campaign) => `
    <div class="activity-item">
      <div class="activity-dot" style="background:${(statusConfig[campaign.status] || statusConfig.draft).color}"></div>
      <div class="activity-text"><strong>${escHtml(campaign.name)}</strong> is ${escHtml(campaign.status)}</div>
      <div class="activity-time">${formatDate(campaign.created_at)}</div>
    </div>
  `).join('');
}

function renderCharts(sentCampaigns) {
  if (typeof Chart === 'undefined') return;

  const performanceCanvas = document.getElementById('performanceChart');
  const statusCanvas = document.getElementById('statusChart');
  if (!performanceCanvas || !statusCanvas) return;

  destroyChart('performanceChart');
  destroyChart('statusChart');

  charts.performanceChart = new Chart(performanceCanvas, {
    type: 'bar',
    data: {
      labels: sentCampaigns.map((campaign) => campaign.name).slice(0, 6),
      datasets: [
        {
          label: 'Recipients',
          data: sentCampaigns.map((campaign) => Number(campaign.recipients_count || 0)).slice(0, 6),
          backgroundColor: '#4f7cff'
        },
        {
          label: 'Opens',
          data: sentCampaigns.map((campaign) => Number(campaign.opens_count || 0)).slice(0, 6),
          backgroundColor: '#34d399'
        }
      ]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });

  charts.statusChart = new Chart(statusCanvas, {
    type: 'doughnut',
    data: {
      labels: ['Draft', 'Scheduled', 'Sent'],
      datasets: [{
        data: [
          campaigns.filter((campaign) => campaign.status === 'draft').length,
          campaigns.filter((campaign) => campaign.status === 'scheduled').length,
          campaigns.filter((campaign) => campaign.status === 'sent').length
        ],
        backgroundColor: ['#94a3b8', '#fb923c', '#34d399']
      }]
    },
    options: { responsive: true, maintainAspectRatio: false }
  });
}

function destroyChart(key) {
  if (charts[key]) {
    charts[key].destroy();
    delete charts[key];
  }
}

function openContactModal() {
  document.getElementById('contactModalTitle').textContent = document.getElementById('editContactId').value ? 'Edit Contact' : 'Add Contact';
  document.getElementById('contactModal')?.classList.add('open');
}

function closeContactModal() {
  document.getElementById('contactModal')?.classList.remove('open');
  setValue('editContactId', '');
  setValue('contPasteArea', '');
  setValue('contCompany', '');
  setValue('contFax', '');
  setValue('contTags', '');
  setValue('contStatus', 'active');
}

async function saveContact() {
  const id = document.getElementById('editContactId').value;
  const emails = parseRecipients(document.getElementById('contPasteArea').value);
  const company = document.getElementById('contCompany').value.trim();
  const fax = document.getElementById('contFax').value.trim();
  const tags = document.getElementById('contTags').value.trim();
  const status = document.getElementById('contStatus').value;

  if (!emails.length) {
    showToast('Please add at least one valid email address.', 'error');
    return;
  }

  try {
    const response = await api('contacts', 'POST', {
      id,
      emails,
      company,
      fax,
      tags,
      status
    });

    contacts = response.contacts || contacts;
    closeContactModal();
    renderContacts();
    updateHomeStats();
    updateUsage();
    updateBadges();
    showToast(response.message || 'Contact saved successfully.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function renderContacts() {
  const tbody = document.getElementById('contactsTable');
  const empty = document.getElementById('noContactsMsg');
  if (!tbody || !empty) return;

  const query = (document.getElementById('contactSearch')?.value || '').trim().toLowerCase();
  const filtered = contacts.filter((contact) => {
    if (!query) return true;
    return (
      contact.email.toLowerCase().includes(query) ||
      (contact.company || '').toLowerCase().includes(query) ||
      (contact.tags_text || '').toLowerCase().includes(query)
    );
  });

  if (!filtered.length) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    setText('contactCountLabel', '0');
    return;
  }

  empty.style.display = 'none';
  setText('contactCountLabel', String(filtered.length));
  tbody.innerHTML = filtered.map((contact) => {
    const tagsHtml = (contact.tags || []).map((tag) => `<span class="tag">${escHtml(tag)}</span>`).join('');
    return `
      <tr data-contact-id="${contact.id}">
        <td><input type="checkbox" class="contact-checkbox" value="${contact.id}" onchange="updateDeleteBtn()"></td>
        <td>${escHtml(contact.name || contact.email.split('@')[0])}</td>
        <td>${escHtml(contact.email)}</td>
        <td>${escHtml(contact.company || '—')}</td>
        <td>${escHtml(contact.fax || '—')}</td>
        <td>${tagsHtml || '<span style="opacity:0.5">No tags</span>'}</td>
        <td><span class="status-badge ${escAttr((contact.status || 'active').toLowerCase())}">${escHtml(contact.status || 'active')}</span></td>
        <td>${formatDate(contact.created_at)}</td>
        <td>
          <button class="btn btn-secondary btn-sm" type="button" onclick="editContact(${contact.id})">Edit</button>
          <button class="btn btn-danger btn-sm" type="button" onclick="deleteContact(${contact.id})">Delete</button>
        </td>
      </tr>
    `;
  }).join('');
}

function filterContacts() {
  renderContacts();
}

function editContact(id) {
  const contact = contacts.find((item) => Number(item.id) === Number(id));
  if (!contact) return;

  setValue('editContactId', contact.id);
  setValue('contPasteArea', contact.email);
  setValue('contCompany', contact.company || '');
  setValue('contFax', contact.fax || '');
  setValue('contTags', contact.tags_text || '');
  setValue('contStatus', contact.status || 'active');
  document.getElementById('contactModalTitle').textContent = 'Edit Contact';
  openContactModal();
}

async function deleteContact(id) {
  if (!confirm('Delete this contact?')) return;

  try {
    const response = await api('contact_delete', 'POST', { ids: [id] });
    contacts = response.contacts || contacts.filter((contact) => Number(contact.id) !== Number(id));
    renderContacts();
    updateHomeStats();
    updateUsage();
    updateBadges();
    updateDeleteBtn();
    showToast(response.message || 'Contact deleted.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function toggleSelectAll(checkbox) {
  document.querySelectorAll('.contact-checkbox').forEach((item) => {
    item.checked = checkbox.checked;
  });
  updateDeleteBtn();
}

function updateDeleteBtn() {
  const checked = Array.from(document.querySelectorAll('.contact-checkbox:checked')).map((input) => Number(input.value));
  const deleteBtn = document.getElementById('deleteSelectedBtn');
  if (deleteBtn) deleteBtn.style.display = checked.length ? 'inline-flex' : 'none';
}

async function deleteSelectedContacts() {
  const ids = Array.from(document.querySelectorAll('.contact-checkbox:checked')).map((input) => Number(input.value));
  if (!ids.length) return;
  if (!confirm('Delete selected contacts?')) return;

  try {
    const response = await api('contact_delete', 'POST', { ids });
    contacts = response.contacts || contacts.filter((contact) => !ids.includes(Number(contact.id)));
    const selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
    renderContacts();
    updateHomeStats();
    updateUsage();
    updateBadges();
    updateDeleteBtn();
    showToast(response.message || 'Selected contacts deleted.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function importCSV(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = async () => {
    try {
      const text = String(reader.result || '');
      const importedContacts = parseContactsCsv(text);
      if (!importedContacts.length) {
        showToast('No valid contacts were found in the CSV file.', 'error');
        return;
      }

      const response = await api('contacts', 'POST', { contacts: importedContacts });
      contacts = response.contacts || contacts;
      renderContacts();
      updateHomeStats();
      updateUsage();
      updateBadges();
      updateDeleteBtn();
      showToast(response.message || `${importedContacts.length} contacts imported successfully.`, 'success');
    } catch (error) {
      showToast(error.message || 'CSV import failed.', 'error');
    } finally {
      event.target.value = '';
    }
  };

  reader.onerror = () => {
    showToast('Unable to read the selected CSV file.', 'error');
    event.target.value = '';
  };

  reader.readAsText(file);
}

async function saveProfile() {
  const payload = {
    first_name: document.getElementById('pFirstName').value.trim(),
    last_name: document.getElementById('pLastName').value.trim(),
    email: document.getElementById('pEmail').value.trim(),
    phone: document.getElementById('pPhone').value.trim(),
    company: document.getElementById('pCompany').value.trim(),
    website: document.getElementById('pWebsite').value.trim(),
    bio: document.getElementById('pBio').value.trim()
  };

  try {
    const response = await api('profile_update', 'POST', payload);
    profile = { ...profile, ...payload, ...(response.user || {}) };
    applyProfile();
    showToast(response.message || 'Profile updated successfully.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function changePassword() {
  const current_password = document.getElementById('currentPass').value;
  const new_password = document.getElementById('newPass').value;
  const confirm_password = document.getElementById('confirmPass').value;

  if (new_password !== confirm_password) {
    showToast('Password confirmation does not match.', 'error');
    return;
  }

  try {
    const response = await api('password_change', 'POST', {
      current_password,
      new_password
    });
    document.getElementById('currentPass').value = '';
    document.getElementById('newPass').value = '';
    document.getElementById('confirmPass').value = '';
    checkPasswordStrength('');
    showToast(response.message || 'Password updated successfully.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

async function deleteAccount() {
  if (!confirm('Delete your account and all your data?')) return;

  try {
    await api('delete_account', 'POST', {});
    showToast('Account deleted successfully.', 'success');
    handleForcedLogout();
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function toggleProfileMenu(event) {
  event?.stopPropagation();
  document.getElementById('profileMenuDrop')?.classList.toggle('open');
}

function closeAllDropdowns() {
  document.getElementById('profileMenuDrop')?.classList.remove('open');
}

function switchProfileTab(id, tab = null) {
  document.querySelectorAll('.tab-panel').forEach((panel) => panel.classList.remove('active'));
  document.querySelectorAll('.profile-tab').forEach((item) => item.classList.remove('active'));
  document.getElementById(id)?.classList.add('active');
  if (tab) tab.classList.add('active');
  else {
    document.querySelector(`.profile-tab[onclick*="${id}"]`)?.classList.add('active');
  }
}

function toggleSwitch(element) {
  element?.classList.toggle('on');
}

function saveNotifications() {
  showToast('Preferences saved on the interface.', 'success');
}

function globalSearch(query) {
  const value = query.trim().toLowerCase();
  if (!value) return;

  if (campaigns.some((campaign) => campaign.name.toLowerCase().includes(value) || campaign.subject.toLowerCase().includes(value))) {
    navigate('campaigns');
    setValue('campaignSearch', query);
    renderCampaigns();
    return;
  }

  if (contacts.some((contact) => contact.email.toLowerCase().includes(value) || (contact.company || '').toLowerCase().includes(value))) {
    navigate('contacts');
    setValue('contactSearch', query);
    renderContacts();
  }
}

function toggleBilling() {
  isYearly = !isYearly;
  document.getElementById('billingToggle')?.classList.toggle('on', isYearly);
  document.getElementById('monthlyLabel')?.classList.toggle('active', !isYearly);
  document.getElementById('yearlyLabel')?.classList.toggle('active', isYearly);
  const proPrice = document.getElementById('proPrice');
  const bizPrice = document.getElementById('bizPrice');
  const proPeriod = document.getElementById('proPeriod');
  const bizPeriod = document.getElementById('bizPeriod');

  if (proPrice) proPrice.innerHTML = isYearly ? '$24<span>/mo</span>' : '$29<span>/mo</span>';
  if (bizPrice) bizPrice.innerHTML = isYearly ? '$66<span>/mo</span>' : '$79<span>/mo</span>';
  if (proPeriod) proPeriod.textContent = isYearly ? 'Billed annually ($288/yr)' : 'Billed monthly';
  if (bizPeriod) bizPeriod.textContent = isYearly ? 'Billed annually ($792/yr)' : 'Billed monthly';
}

function selectPlan(plan) {
  if ((profile.plan || 'Free') === plan) {
    showToast(`${plan} is already your current plan.`, 'info');
    return;
  }

  if (plan === 'Free') {
    updatePlan(plan);
    return;
  }

  openCheckout(plan);
}

function openCheckout(plan) {
  checkoutPlan = plan;
  setText('checkoutPlanLabel', plan);
  setValue('checkoutPlan', plan);
  navigate('payment');
}

function handlePaymentSubmit(event) {
  event.preventDefault();
  updatePlan(checkoutPlan || document.getElementById('checkoutPlan').value, {
    billing_email: document.getElementById('billingEmail').value.trim(),
    cardholder_name: document.getElementById('cardholderName').value.trim(),
    card_number: document.getElementById('cardNumber').value.trim(),
    card_expiry: document.getElementById('cardExpiry').value.trim(),
    card_cvc: document.getElementById('cardCvc').value.trim()
  });
}

async function updatePlan(plan, paymentData = null) {
  if (!plan) {
    showToast('Please select a plan first.', 'error');
    return;
  }

  try {
    const response = await api(paymentData ? 'checkout_plan' : 'change_plan', 'POST', {
      plan,
      payment: paymentData
    });

    profile = response.user || profile;
    billingHistory = response.billing_history || billingHistory;
    applyProfile();
    renderBillingHistory();

    if (paymentData) {
      document.getElementById('paymentForm')?.reset();
      navigate('profile');
      switchProfileTab('tab-billing');
    }

    showToast(response.message || 'Plan updated successfully.', 'success');
  } catch (error) {
    showToast(error.message, 'error');
  }
}

function checkPasswordStrength(value) {
  const fill = document.getElementById('passStrength');
  const label = document.getElementById('passStrengthLabel');
  if (!fill || !label) return;

  let score = 0;
  if (value.length >= 8) score += 25;
  if (/[A-Z]/.test(value)) score += 25;
  if (/[0-9]/.test(value)) score += 25;
  if (/[^A-Za-z0-9]/.test(value)) score += 25;

  fill.style.width = `${score}%`;
  fill.style.background = score >= 75 ? '#34d399' : score >= 50 ? '#fb923c' : '#f87171';
  label.textContent = value ? (score >= 75 ? 'Strong password' : score >= 50 ? 'Medium password' : 'Weak password') : 'Enter a password';
}

function changeAvatar() {
  document.getElementById('avatarInput')?.click();
}

async function handleAvatarSelected(event) {
  const file = event.target.files?.[0];
  if (!file) return;

  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    showToast('Please choose PNG, JPG, or WEBP image.', 'error');
    event.target.value = '';
    return;
  }

  if (file.size > 2 * 1024 * 1024) {
    showToast('Profile photo must be smaller than 2MB.', 'error');
    event.target.value = '';
    return;
  }

  const formData = new FormData();
  formData.append('avatar', file);

  try {
    const response = await fetch('api.php?action=upload_avatar', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });
    const payload = await response.json().catch(() => ({}));

    if (!response.ok || payload.success === false) {
      throw new Error(payload.message || 'Avatar upload failed.');
    }

    profile = payload.user || profile;
    applyProfile();
    showToast(payload.message || 'Profile photo updated successfully.', 'success');
  } catch (error) {
    showToast(error.message || 'Avatar upload failed.', 'error');
  } finally {
    event.target.value = '';
  }
}

function updateAudienceCount() {
  setText('audienceCount', String(parseRecipients(document.getElementById('campRecipients')?.value || '').length));
}

function parseRecipients(value) {
  const unique = new Set();
  String(value || '')
    .split(/[\n,;]+/)
    .map((item) => item.trim().toLowerCase())
    .filter((item) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(item))
    .forEach((item) => unique.add(item));

  return Array.from(unique);
}

function parseContactsCsv(text) {
  const rows = parseCsvRows(text);
  if (!rows.length) return [];

  const firstRow = rows[0].map((cell) => normalizeCsvHeader(cell));
  const hasHeader = firstRow.some((header) => ['email', 'company', 'fax', 'tags', 'status'].includes(header));
  const dataRows = hasHeader ? rows.slice(1) : rows;
  const contactsMap = new Map();

  dataRows.forEach((row) => {
    const contact = hasHeader ? contactFromHeaderRow(firstRow, row) : contactFromPlainRow(row);
    if (!contact?.email) return;

    contactsMap.set(contact.email, contact);
  });

  return Array.from(contactsMap.values());
}

function parseCsvRows(text) {
  const rows = [];
  let row = [];
  let cell = '';
  let inQuotes = false;

  for (let i = 0; i < text.length; i += 1) {
    const char = text[i];
    const next = text[i + 1];

    if (char === '"') {
      if (inQuotes && next === '"') {
        cell += '"';
        i += 1;
      } else {
        inQuotes = !inQuotes;
      }
      continue;
    }

    if (char === ',' && !inQuotes) {
      row.push(cell.trim());
      cell = '';
      continue;
    }

    if ((char === '\n' || char === '\r') && !inQuotes) {
      if (char === '\r' && next === '\n') {
        i += 1;
      }
      row.push(cell.trim());
      if (row.some((item) => item !== '')) {
        rows.push(row);
      }
      row = [];
      cell = '';
      continue;
    }

    cell += char;
  }

  if (cell !== '' || row.length) {
    row.push(cell.trim());
    if (row.some((item) => item !== '')) {
      rows.push(row);
    }
  }

  return rows;
}

function normalizeCsvHeader(value) {
  const clean = String(value || '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '');
  const aliases = {
    email: 'email',
    emailaddress: 'email',
    emailid: 'email',
    mail: 'email',
    company: 'company',
    companyname: 'company',
    organization: 'company',
    organisation: 'company',
    business: 'company',
    fax: 'fax',
    faxnumber: 'fax',
    tags: 'tags',
    tag: 'tags',
    labels: 'tags',
    segments: 'tags',
    segment: 'tags',
    status: 'status',
    state: 'status'
  };

  return aliases[clean] || clean;
}

function contactFromHeaderRow(headers, row) {
  const contact = {
    email: '',
    company: '',
    fax: '',
    tags: [],
    status: 'active'
  };

  headers.forEach((header, index) => {
    const value = String(row[index] || '').trim();
    if (!value) return;

    if (header === 'email') {
      const email = firstValidEmail(value);
      if (email) contact.email = email;
    } else if (header === 'company') {
      contact.company = value;
    } else if (header === 'fax') {
      contact.fax = value;
    } else if (header === 'tags') {
      contact.tags = splitTags(value);
    } else if (header === 'status') {
      contact.status = normalizeImportedStatus(value);
    }
  });

  return contact.email ? contact : null;
}

function contactFromPlainRow(row) {
  const values = row.map((cell) => String(cell || '').trim()).filter(Boolean);
  if (!values.length) return null;

  const email = firstValidEmail(values.join(' '));
  if (!email) return null;

  return {
    email,
    company: '',
    fax: '',
    tags: [],
    status: 'active'
  };
}

function firstValidEmail(value) {
  return parseRecipients(value)[0] || '';
}

function splitTags(value) {
  return String(value || '')
    .split(/[;,]+/)
    .map((item) => item.trim())
    .filter(Boolean);
}

function normalizeImportedStatus(value) {
  const clean = String(value || '').trim().toLowerCase();
  if (['inactive', 'disabled'].includes(clean)) return 'inactive';
  if (['unsubscribed', 'unsubscribe', 'optout', 'optedout'].includes(clean)) return 'unsubscribed';
  if (['bounced', 'bounce'].includes(clean)) return 'bounced';
  return 'active';
}

function execFormat(command) {
  document.execCommand(command, false, null);
}

function execLink() {
  const url = prompt('Enter the URL');
  if (url) document.execCommand('createLink', false, url);
}

function updateProductPreview() {
  const preview = document.getElementById('productPreview');
  if (!preview) return;

  const name = document.getElementById('productName').value.trim() || 'Product name';
  const price = document.getElementById('productPrice').value.trim() || '$0.00';
  const image = document.getElementById('productImage').value.trim();
  const cta = document.getElementById('productCta').value.trim() || 'Shop Now';
  const desc = document.getElementById('productDesc').value.trim() || 'Add a short product description.';

  preview.innerHTML = `
    <div class="product-preview-image"${image ? ` style="background-image:url('${escAttr(image)}');background-size:cover;background-position:center"` : ''}>${image ? '' : 'Image preview'}</div>
    <div class="product-preview-body">
      <div class="product-preview-name">${escHtml(name)}</div>
      <div class="product-preview-desc">${escHtml(desc)}</div>
      <div class="product-preview-price">${escHtml(price)}</div>
      <div class="product-preview-button">${escHtml(cta)}</div>
    </div>
  `;
}

function insertProductBlock() {
  const name = document.getElementById('productName').value.trim();
  const price = document.getElementById('productPrice').value.trim();
  const image = document.getElementById('productImage').value.trim();
  const link = document.getElementById('productLink').value.trim();
  const cta = document.getElementById('productCta').value.trim() || 'Shop Now';
  const desc = document.getElementById('productDesc').value.trim();

  const editor = document.getElementById('campContent');
  if (!editor) return;

  editor.innerHTML += `
    <div style="border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin:12px 0">
      ${image ? `<img src="${escAttr(image)}" alt="${escAttr(name || 'Product')}" style="max-width:100%;border-radius:10px;margin-bottom:12px">` : ''}
      <h3 style="margin:0 0 8px">${escHtml(name || 'Product')}</h3>
      ${desc ? `<p style="margin:0 0 8px">${escHtml(desc)}</p>` : ''}
      ${price ? `<p style="margin:0 0 12px;font-weight:700">${escHtml(price)}</p>` : ''}
      ${link ? `<a href="${escAttr(link)}" target="_blank" style="display:inline-block;padding:10px 16px;border-radius:10px;background:#4f7cff;color:#fff;text-decoration:none">${escHtml(cta)}</a>` : ''}
    </div>
  `;

  showToast('Product block added to the email body.', 'success');
}

function exportReport() {
  const lines = [
    'MailFlow Analytics Report',
    `Campaigns: ${campaigns.length}`,
    `Contacts: ${contacts.length}`
  ];

  const blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'mailflow-report.txt';
  link.click();
  URL.revokeObjectURL(url);
}

function closeMobileSidebar() {}

function showToast(message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = message;
  container.appendChild(toast);

  setTimeout(() => toast.classList.add('show'), 10);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 250);
  }, 2800);
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function setValue(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = value;
}

function formatDate(value) {
  if (!value) return 'Today';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Today';
  return date.toLocaleDateString();
}

function normalizeDateTimeLocal(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (num) => String(num).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function escHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function escAttr(value) {
  return escHtml(value);
}
