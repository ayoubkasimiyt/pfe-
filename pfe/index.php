<?php
declare(strict_types=1);
session_start();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MailFlow — Email Marketing Platform</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<!-- AUTH SCREEN -->
<section class="auth-screen" id="authScreen">
  <div class="auth-brand">
    <div class="auth-logo">✉</div>
    <span>MailFlow</span>
  </div>
  <div class="auth-panel">
    <div class="auth-copy">
      <p class="auth-kicker">Email marketing platform</p>
      <h1>Welcome back to your campaign hub.</h1>
      <p>Sign in or create an account to manage campaigns, contacts, analytics, and billing.</p>
    </div>

    <div class="auth-card">
      <div class="auth-tabs">
        <button class="auth-tab active" id="loginTab" onclick="showAuthView('login')">Login</button>
        <button class="auth-tab" id="signupTab" onclick="showAuthView('signup')">Sign up</button>
      </div>

      <form class="auth-form active" id="loginForm" onsubmit="handleLogin(event)">
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="loginEmail" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" id="loginPassword" placeholder="Enter your password" required>
        </div>
        <button class="btn btn-primary btn-lg auth-submit" type="submit">Login</button>
        <p class="auth-switch">No account yet? <button type="button" onclick="showAuthView('signup')">Create one</button></p>
      </form>

      <form class="auth-form" id="signupForm" onsubmit="handleSignup(event)">
        <div class="form-row">
          <div class="form-group">
            <label>First Name</label>
            <input type="text" id="signupFirstName" placeholder="John" required>
          </div>
          <div class="form-group">
            <label>Last Name</label>
            <input type="text" id="signupLastName" placeholder="Doe" required>
          </div>
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" id="signupEmail" placeholder="you@example.com" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" id="signupPassword" placeholder="At least 6 characters" minlength="6" required>
        </div>
        <button class="btn btn-primary btn-lg auth-submit" type="submit">Create Account</button>
        <p class="auth-switch">Already have an account? <button type="button" onclick="showAuthView('login')">Login</button></p>
      </form>

      <div class="auth-message" id="authMessage"></div>
    </div>
  </div>
</section>

<!-- CAMPAIGN MODAL -->
<div class="modal-overlay" id="campaignModal">
  <div class="modal" style="max-width:760px">
    <div class="modal-header">
      <div class="modal-title" id="campaignModalTitle">Create Campaign</div>
      <button class="modal-close" onclick="closeCampaignModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editCampaignId">
      <div class="campaign-form-section">
        <div class="campaign-form-title">Campaign Setup</div>
        <div class="form-row">
          <div class="form-group">
            <label>Campaign Name *</label>
            <input type="text" id="campName" placeholder="e.g. Summer Sale 2025">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select id="campStatus">
              <option value="draft">Draft</option>
              <option value="scheduled">Scheduled</option>
              <option value="sent">Sent</option>
            </select>
          </div>
        </div>
      </div>

      <div class="campaign-form-section">
        <div class="campaign-form-title">Sender</div>
        <div class="form-row">
          <div class="form-group">
            <label>Sender Name</label>
            <input type="text" id="campSenderName" placeholder="Your Name">
          </div>
          <div class="form-group">
            <label>Sender Email</label>
            <input type="email" id="campSenderEmail" placeholder="you@example.com">
          </div>
        </div>
      </div>

      <div class="campaign-form-section audience-section">
        <div class="campaign-form-title">Audience / Send Data</div>

        <!-- Copy-paste emails -->
        <div class="form-group" id="audiencePasteGroup">
          <label>Emails To Send To</label>
          <textarea id="campRecipients" placeholder="client1@example.com&#10;client2@example.com&#10;client3@example.com" oninput="updateAudienceCount()"></textarea>
          <div class="field-help"><span id="audienceCount">0</span> recipients detected. One email per line, or separate with comma.</div>
        </div>

        <!-- Audience Tags / Notes -->
        <div class="form-group">
          <label>Audience Tags / Notes</label>
          <input type="text" id="campAudienceTags" placeholder="vip, buyers, newsletter">
          <div class="field-help">Comma-separated tags to organize your audience segments.</div>
        </div>
        <div class="form-group">
          <label>Notes</label>
          <textarea id="campAudienceNotes" placeholder="Add any notes about this audience..." rows="2" style="min-height:60px"></textarea>
        </div>
      </div>

      <div class="campaign-form-section">
        <div class="campaign-form-title">Email Design</div>
        <div class="form-group">
          <label>Email Content</label>
          <div class="editor-toolbar">
            <button onclick="execFormat('bold')" title="Bold"><b>B</b></button>
            <button onclick="execFormat('italic')" title="Italic"><i>I</i></button>
            <button onclick="execFormat('underline')" title="Underline"><u>U</u></button>
            <button onclick="execFormat('insertUnorderedList')" title="List">≡</button>
            <button onclick="execLink()" title="Link">🔗</button>
            <button onclick="document.getElementById('campContent').innerHTML=''" title="Clear" style="margin-left:auto">✕</button>
          </div>
          <div class="email-editor" id="campContent" contenteditable="true" placeholder="Write your email content here..."></div>
        </div>
        <div class="product-builder">
          <div class="product-builder-head">
            <div>
              <div class="product-builder-title">Product Block</div>
              <div class="product-builder-sub">Add product data, image URL, and product link to the email.</div>
            </div>
            <button class="btn btn-secondary btn-sm" type="button" onclick="insertProductBlock()">Add to Email</button>
          </div>
          <div class="product-builder-grid">
            <div class="product-fields">
              <div class="form-group">
                <label>Subject Line *</label>
                <input type="text" id="campSubject" placeholder="🎉 Don't miss our biggest sale ever!">
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Product Name</label>
                  <input type="text" id="productName" placeholder="Premium Hoodie" oninput="updateProductPreview()">
                </div>
                <div class="form-group">
                  <label>Price / Offer</label>
                  <input type="text" id="productPrice" placeholder="$49.00 or 30% OFF" oninput="updateProductPreview()">
                </div>
              </div>
              <div class="form-group">
                <label>Product Image URL</label>
                <input type="url" id="productImage" placeholder="https://example.com/product.jpg" oninput="updateProductPreview()">
              </div>
              <div class="form-group">
                <label>Product Link</label>
                <input type="url" id="productLink" placeholder="https://example.com/product" oninput="updateProductPreview()">
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label>Button Text</label>
                  <input type="text" id="productCta" placeholder="Shop Now" oninput="updateProductPreview()">
                </div>
                <div class="form-group">
                  <label>Short Description</label>
                  <input type="text" id="productDesc" placeholder="Limited stock available today." oninput="updateProductPreview()">
                </div>
              </div>
            </div>
            <div class="product-preview" id="productPreview">
              <div class="product-preview-image">Image preview</div>
              <div class="product-preview-body">
                <div class="product-preview-name">Product name</div>
                <div class="product-preview-desc">Add a short product description.</div>
                <div class="product-preview-price">$0.00</div>
                <div class="product-preview-button">Shop Now</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="form-group" id="schedDateGroup" style="display:none">
        <label>Schedule Date & Time</label>
        <input type="datetime-local" id="campScheduleDate">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeCampaignModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveCampaign()" id="campSaveBtn">Save Campaign</button>
    </div>
  </div>
</div>

<!-- CONTACT MODAL -->
<div class="modal-overlay" id="contactModal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="contactModalTitle">Add Contact</div>
      <button class="modal-close" onclick="closeContactModal()">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="editContactId">
      
      <!-- Copy-Paste Fields -->
      <div id="contactPasteFields">
        <div class="form-group">
          <label>Paste Contact Emails *</label>
          <textarea id="contPasteArea" placeholder="jane@example.com&#10;john@example.com, bob@example.com" rows="6" style="min-height:120px"></textarea>
          <div class="field-help">One email per line, or separated by commas. Extracted valid emails will be subscribed.</div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Company</label>
            <input type="text" id="contCompany" placeholder="Company name">
          </div>
          <div class="form-group">
            <label>Fax</label>
            <input type="tel" id="contFax" placeholder="+1 (555) 123-4567">
          </div>
        </div>
        
        <div class="form-group">
          <label>Tags</label>
          <input type="text" id="contTags" placeholder="vip, newsletter, buyer">
          <div class="field-help">Comma-separated tags for the contact.</div>
        </div>
        <div class="form-group">
          <label>Status</label>
          <select id="contStatus">
            <option value="active">Active</option>
            <option value="unsubscribed">Unsubscribed</option>
            <option value="bounced">Bounced</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeContactModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveContact()">Save Contact</button>
    </div>
  </div>
</div>

<!-- APP SHELL -->
<div class="app-shell locked">

<!-- SIDEBAR OVERLAY -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">✉</div>
    <span class="logo-text">MailFlow</span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <div class="nav-item active" data-page="home" onclick="navigate('home')">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      Dashboard
    </div>
    <div class="nav-item" data-page="campaigns" onclick="navigate('campaigns')">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      Campaigns
      <span class="nav-badge" id="campaignBadge">0</span>
    </div>
    <div class="nav-item" data-page="contacts" onclick="navigate('contacts')">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Contacts
      <span class="nav-badge" id="contactBadge">0</span>
    </div>

    <div class="nav-section-label">Account</div>
    <div class="nav-item" data-page="pricing" onclick="navigate('pricing')">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
      Pricing
    </div>
    <div class="nav-item" data-page="profile" onclick="navigate('profile')">
      <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Profile
    </div>
  </nav>
</aside>

<!-- MAIN -->
<main class="main-content">
  <header class="topbar">
    <!-- Topbar search -->
    <div class="topbar-search">
      <svg class="topbar-search-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="Search anything..." id="topbarSearchInput" oninput="globalSearch(this.value)" onfocus="globalSearch(this.value)">
    </div>

    <div class="topbar-actions">
      <!-- Theme toggle -->
      <div class="theme-toggle" id="themeBtn" onclick="toggleTheme()" title="Toggle theme">🌙</div>

      <!-- Profile dropdown -->
      <div class="profile-wrap">
        <div class="profile-trigger" id="profileToggle" onclick="toggleProfileMenu(event)">
          <div class="pt-av" id="topbarAvatar">?</div>
          <span class="pt-name" id="topbarName">User</span>
          <span class="pt-caret">▾</span>
        </div>
        <div class="profile-menu" id="profileMenuDrop">
          <div class="pm-header">
            <div class="pm-name" id="pmName">User</div>
            <div class="pm-email" id="pmEmail">you@example.com</div>
          </div>
          <div class="pm-divider"></div>
          <div class="pm-item" onclick="navigate('profile');closeAllDropdowns()">👤 My Profile</div>
          <div class="pm-item" onclick="navigate('pricing');closeAllDropdowns()">💳 Billing</div>
          <div class="pm-divider"></div>
          <div class="pm-item danger" onclick="handleLogout()">🚪 Sign Out</div>
        </div>
      </div>
    </div>
  </header>

  <!-- ══════════════ HOME ══════════════ -->
  <section class="page active" id="page-home">
    <div class="hero-section">
      <div class="hero-greeting">👋 Welcome back</div>
      <h1 class="hero-title" id="heroName">Good morning!</h1>
      <p class="hero-sub">Your campaigns are performing great. Let's reach your audience with something amazing today.</p>
      <div class="hero-actions">
        <button class="btn btn-primary" onclick="navigate('about')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Learn About Email Marketing
        </button>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card" style="--grad:var(--gradient-1)">
        <div class="stat-icon" style="background:rgba(79,124,255,.14);color:#818cf8">📧</div>
        <div class="stat-value" id="homeTotalCampaigns">0</div>
        <div class="stat-label">Total Campaigns</div>
        <div class="stat-change up" id="homeCampChange">↑ +0 this week</div>
      </div>
      <div class="stat-card" style="--grad:var(--gradient-2)">
        <div class="stat-icon" style="background:rgba(52,211,153,.14);color:#34d399">👥</div>
        <div class="stat-value" id="homeTotalContacts">0</div>
        <div class="stat-label">Total Contacts</div>
        <div class="stat-change up">↑ +0 this week</div>
      </div>
      <div class="stat-card" style="--grad:var(--gradient-3)">
        <div class="stat-icon" style="background:rgba(244,114,182,.14);color:#f472b6">🖱️</div>
        <div class="stat-value" id="homeClickRate">0%</div>
        <div class="stat-label">Avg Click Rate</div>
        <div class="stat-change down">↓ -0.8%</div>
      </div>
    </div>

    <div class="two-col">
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Quick Actions</div><div class="card-subtitle">Jump into the most common tasks</div></div>
        </div>
        <div class="quick-actions-grid">
          <div class="quick-action-card" onclick="openCampaignModal()">
            <div class="qa-icon" style="background:rgba(79,124,255,.15)">✉</div>
            <div class="qa-title">New Campaign</div>
            <div class="qa-desc">Draft and send a new email campaign</div>
          </div>
          <div class="quick-action-card" onclick="navigate('contacts');setTimeout(openContactModal,150)">
            <div class="qa-icon" style="background:rgba(52,211,153,.15)">👤</div>
            <div class="qa-title">Add Contact</div>
            <div class="qa-desc">Add a subscriber to your list</div>
          </div>
          <div class="quick-action-card" onclick="navigate('pricing')">
            <div class="qa-icon" style="background:rgba(244,114,182,.15)">📥</div>
            <div class="qa-title">Pricing</div>
            <div class="qa-desc">View plans and upgrade your account</div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Recent Activity</div></div>
        <div id="recentActivity">
          <div class="activity-item">
            <div class="activity-dot" style="background:#818cf8"></div>
            <div class="activity-text"><strong>Welcome!</strong> Start creating your first campaign</div>
            <div class="activity-time">Now</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ══════════════ CAMPAIGNS ══════════════ -->
  <section class="page" id="page-about">
    <div class="hero-section">
      <div class="hero-greeting">MailFlow Guide</div>
      <h1 class="hero-title">What is Email Marketing?</h1>
      <p class="hero-sub">Email marketing helps a company send promotions, updates, newsletters, and customer follow-up messages directly to people by email.</p>
      <div class="hero-actions">
        <button class="btn btn-primary" onclick="navigate('home')">Back to Dashboard</button>
      </div>
    </div>

    <div class="two-col">
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Why Companies Use Email Marketing</div>
            <div class="card-subtitle">Simple business value in one place</div>
          </div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:#818cf8"></div>
          <div class="activity-text"><strong>Promotions:</strong> Companies share offers, launches, and announcements quickly.</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:#34d399"></div>
          <div class="activity-text"><strong>Customer relationships:</strong> It helps brands stay connected with subscribers and buyers.</div>
        </div>
        <div class="activity-item">
          <div class="activity-dot" style="background:#fb923c"></div>
          <div class="activity-text"><strong>Better sales decisions:</strong> Performance data helps improve the next campaign.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">About MailFlow</div>
            <div class="card-subtitle">What the company platform does</div>
          </div>
        </div>
        <div style="color:var(--text-secondary);font-size:14px;line-height:1.8">
          MailFlow is an email marketing platform for companies that want to manage contacts, prepare campaigns, send emails, and review results from one dashboard.
          <br><br>
          The goal of MailFlow is to make email marketing easier by giving businesses one place to organize their audience and communicate with them professionally.
        </div>
      </div>
    </div>
  </section>

  <section class="page" id="page-campaigns">
    <div class="filter-bar">
      <div class="search-bar" style="max-width:260px">
        <svg class="search-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search campaigns..." id="campaignSearch" oninput="filterCampaigns()">
      </div>
      <button class="filter-chip active" data-filter="all" onclick="setFilter(this,'all')">All</button>
      <button class="filter-chip" data-filter="draft" onclick="setFilter(this,'draft')">Draft</button>
      <button class="filter-chip" data-filter="sent" onclick="setFilter(this,'sent')">Sent</button>
      <button class="filter-chip" data-filter="scheduled" onclick="setFilter(this,'scheduled')">Scheduled</button>
      <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="openCampaignModal()">+ Create</button>
    </div>
    <div class="campaigns-grid" id="campaignsGrid"></div>
  </section>

  <!-- ══════════════ ANALYTICS ══════════════ -->
  <section class="page" id="page-contacts">
    <div class="contacts-toolbar">
      <div class="search-bar">
        <svg class="search-icon" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="Search contacts..." id="contactSearch" oninput="filterContacts()">
      </div>
      <label class="btn btn-secondary btn-sm" style="cursor:pointer">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Import CSV
        <input type="file" accept=".csv" id="csvInput" style="display:none" onchange="importCSV(event)">
      </label>
      <button class="btn btn-primary btn-sm" onclick="openContactModal()">+ Add Contact</button>
      <div style="margin-left:auto;font-size:12px;color:var(--text-muted)">
        <span id="contactCountLabel">0</span> contacts
        <button class="btn btn-danger btn-sm" id="deleteSelectedBtn" onclick="deleteSelectedContacts()" style="display:none;margin-left:10px">Delete Selected</button>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
            <th>Name</th><th>Email</th><th>Company</th><th>Fax</th><th>Tags</th><th>Status</th><th>Added</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="contactsTable">
          <tr>
            <td><input type="checkbox"></td>
            <td>Jane Doe</td>
            <td>jane@example.com</td>
            <td>Acme Corp.</td>
            <td>+1 (555) 123-4567</td>
            <td><span class="tag">VIP</span><span class="tag">newsletter</span></td>
            <td><span class="status-badge active">Active</span></td>
            <td>Today</td>
            <td>
              <button class="btn btn-secondary btn-sm" type="button" onclick="editContact(this)">Edit</button>
              <button class="btn btn-danger btn-sm" type="button" onclick="deleteContact(this)">Delete</button>
            </td>
          </tr>
      </table>
      <div id="noContactsMsg" class="no-results">No contacts yet. Add your first contact above!</div>
    </div>
  </section>

  <!-- ══════════════ PRICING ══════════════ -->
  <section class="page" id="page-pricing">
    <div style="text-align:center;margin-bottom:32px">
      <h2 style="font-family:'Syne',sans-serif;font-size:30px;font-weight:800;margin-bottom:8px">Simple, transparent pricing</h2>
      <p style="color:var(--text-secondary);font-size:14px">Start free, scale as you grow. No hidden fees.</p>
    </div>

    <!-- Billing toggle -->
    <div class="billing-toggle">
      <span class="bt-label active" id="monthlyLabel">Monthly</span>
      <div class="toggle" id="billingToggle" onclick="toggleBilling()">
        <div class="toggle-track"><div class="toggle-thumb"></div></div>
      </div>
      <span class="bt-label" id="yearlyLabel">Yearly <span class="bt-save">Save 20%</span></span>
    </div>

    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="plan-name">Free</div>
        <div class="plan-price" id="freePrice">$0<span>/mo</span></div>
        <div class="plan-period">Forever free, no credit card needed</div>
        <ul class="plan-features">
          <li><span class="feature-check yes">✓</span>500 contacts</li>
          <li><span class="feature-check yes">✓</span>1,000 emails/month</li>
          
        </ul>
        <button class="btn btn-secondary" style="width:100%" onclick="selectPlan('Free')">Current Plan</button>
      </div>

      <div class="pricing-card featured">
        <div class="plan-badge">⭐ Most Popular</div>
        <div class="plan-name">Pro</div>
        <div class="plan-price" id="proPrice">$29<span>/mo</span></div>
        <div class="plan-period" id="proPeriod">Billed monthly</div>
        <ul class="plan-features">
          <li><span class="feature-check yes">✓</span>10,000 contacts</li>
          <li><span class="feature-check yes">✓</span>100,000 emails/month</li>
          <li><span class="feature-check yes">✓</span>Unlimited campaigns</li>
          
        </ul>
        <button class="btn btn-primary" style="width:100%" onclick="openCheckout('Pro')">Upgrade to Pro</button>
      </div>

      <div class="pricing-card">
        <div class="plan-name">Business</div>
        <div class="plan-price" id="bizPrice">$79<span>/mo</span></div>
        <div class="plan-period" id="bizPeriod">Billed monthly</div>
        <ul class="plan-features">
          <li><span class="feature-check yes">✓</span>100,000 contacts</li>
          <li><span class="feature-check yes">✓</span>1M emails/month</li>
        
        </ul>
        <button class="btn btn-secondary" style="width:100%" onclick="openCheckout('Business')">Get Business</button>
      </div>

      <div class="pricing-card">
        <div class="plan-name">Enterprise</div>
        <div class="plan-price">Custom</div>
        <div class="plan-period">Custom billing available</div>
        <ul class="plan-features">
          <li><span class="feature-check yes">✓</span>Unlimited contacts</li>
          <li><span class="feature-check yes">✓</span>Unlimited emails</li>
        
        </ul>
        <button class="btn btn-secondary" style="width:100%" onclick="openCheckout('Enterprise')">Contact Sales</button>
      </div>
    </div>

  </section>

  <!-- ══════════════ PAYMENT CHECKOUT ══════════════ -->
  <section class="page" id="page-payment">
    <div class="card" style="max-width:760px;margin:0 auto">
      <div class="card-title" style="margin-bottom:12px">Payment Information</div>
      <div style="color:var(--text-secondary);font-size:14px;margin-bottom:20px">Enter your card details to complete the <strong id="checkoutPlanLabel">Pro</strong> upgrade.</div>
      <form id="paymentForm" onsubmit="handlePaymentSubmit(event)">
        <div class="form-group">
          <label>Selected plan</label>
          <input type="text" id="checkoutPlan" readonly style="background:var(--bg-muted);cursor:not-allowed">
        </div>
        <div class="form-group">
          <label>Cardholder name</label>
          <input type="text" id="cardholderName" placeholder="Name on card" required>
        </div>
        <div class="form-group">
          <label>Card number</label>
          <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" required>
        </div>
        <div class="form-row">
          <div class="form-group" style="flex:1">
            <label>Expiry date</label>
            <input type="text" id="cardExpiry" placeholder="MM/YY" required>
          </div>
          <div class="form-group" style="flex:1">
            <label>CVC</label>
            <input type="text" id="cardCvc" placeholder="123" required>
          </div>
        </div>
        <div class="form-group">
          <label>Billing email</label>
          <input type="email" id="billingEmail" placeholder="you@example.com" required>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:14px">
          <button class="btn btn-primary" type="submit">Submit payment</button>
          <button class="btn btn-secondary" type="button" onclick="navigate('pricing')">Back to pricing</button>
        </div>
      </form>
    </div>
  </section>

  <!-- ══════════════ PROFILE ══════════════ -->
  <section class="page" id="page-profile">
    <div class="profile-header">
      <div class="profile-avatar" id="profileAvatar" onclick="changeAvatar()">U<div class="avatar-edit">✏</div></div>
      <input type="file" id="avatarInput" accept="image/png,image/jpeg,image/webp" style="display:none" onchange="handleAvatarSelected(event)">
      <div class="profile-info">
        <div class="name" id="profileName">User</div>
        <div class="email" id="profileEmail">you@example.com</div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
          <span class="badge badge-blue" id="profilePlanBadge">Free Plan</span>
          <span class="badge badge-green"><span class="badge-dot"></span>Active</span>
        </div>
      </div>
      <button class="btn btn-danger btn-sm" style="margin-left:auto" onclick="handleLogout()">Sign Out</button>
    </div>

    <div class="profile-tabs">
      <div class="profile-tab active" onclick="switchProfileTab('tab-info',this)">Account Info</div>
      <div class="profile-tab" onclick="switchProfileTab('tab-billing',this)">Billing</div>
      <div class="profile-tab" onclick="switchProfileTab('tab-security',this)">Security</div>
    </div>

    <!-- Account Info -->
    <div class="tab-panel active" id="tab-info">
      <div class="card">
        <div class="card-title" style="margin-bottom:18px">Personal Information</div>
        <div class="form-row">
          <div class="form-group"><label>First Name</label><input type="text" id="pFirstName" value=""></div>
          <div class="form-group"><label>Last Name</label><input type="text" id="pLastName" value=""></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Email</label><input type="email" id="pEmail" value=""></div>
          <div class="form-group"><label>Phone</label><input type="tel" id="pPhone" placeholder="+1 (555) 000-0000"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Company</label><input type="text" id="pCompany" placeholder="Your company"></div>
          <div class="form-group"><label>Website</label><input type="url" id="pWebsite" placeholder="https://example.com"></div>
        </div>
        <div class="form-group"><label>Bio</label><textarea id="pBio" placeholder="Tell us about yourself..." rows="3"></textarea></div>
        <button class="btn btn-primary" onclick="saveProfile()">Save Changes</button>
      </div>
    </div>

    <!-- Billing -->
    <div class="tab-panel" id="tab-billing">
      <div class="current-plan-card">
        <div class="cp-label">Current Plan</div>
        <div class="cp-name" id="cpName">Free Plan</div>
        <div class="cp-desc" id="cpDesc">You are on the free plan. Upgrade to unlock more features.</div>
      </div>
      <div class="card">
        <div class="card-title" id="billingHistoryTitle" style="margin-bottom:16px">Billing History</div>
        <div class="billing-row"><div class="billing-icon">💳</div><div class="billing-desc"><div class="bname">Pro Plan — Monthly</div><div class="bdate">Jan 1, 2025</div></div><div class="billing-amount">$29.00</div></div>
        <div class="billing-row"><div class="billing-icon">💳</div><div class="billing-desc"><div class="bname">Pro Plan — Monthly</div><div class="bdate">Dec 1, 2024</div></div><div class="billing-amount">$29.00</div></div>
        <div class="billing-row"><div class="billing-icon">🎁</div><div class="billing-desc"><div class="bname">Free Plan (started)</div><div class="bdate">Nov 15, 2024</div></div><div class="billing-amount">$0.00</div></div>
      </div>
    </div>

    <!-- Security -->
    <div class="tab-panel" id="tab-security">
      <div class="card" style="margin-bottom:16px">
        <div class="card-title" style="margin-bottom:18px">Change Password</div>
        <div class="form-group"><label>Current Password</label><input type="password" id="currentPass" placeholder="Enter current password"></div>
        <div class="form-group">
          <label>New Password</label>
          <input type="password" id="newPass" placeholder="Enter new password" oninput="checkPasswordStrength(this.value)">
          <div class="password-strength"><div class="ps-fill" id="passStrength" style="width:0%;background:#f87171"></div></div>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px" id="passStrengthLabel">Enter a password</div>
        </div>
        <div class="form-group"><label>Confirm New Password</label><input type="password" id="confirmPass" placeholder="Confirm new password"></div>
        <button class="btn btn-primary" onclick="changePassword()">Update Password</button>
      </div>
      <div class="card">
        <div class="card-title" style="margin-bottom:18px">Account Settings</div>
        <div style="display:flex;align-items:center;justify-content:space-between">
          <div><div style="font-size:13px;font-weight:600;color:var(--text-primary)">Delete Account</div><div style="font-size:12px;color:var(--text-muted);margin-top:2px">Permanently delete your account and all data</div></div>
          <button class="btn btn-danger btn-sm" onclick="deleteAccount()">Delete Account</button>
        </div>
      </div>
    </div>


</main>
</div>

<script>
window.MAILFLOW_BOOT = {
  loggedIn: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>
};
</script>
<script src="script.js"></script>
</body>
</html>
