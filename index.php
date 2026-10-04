<?php
// The interface is served directly by Apache; all live data comes from api.php.
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f3f4ef">
  <title>Benchline | Lab assist</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/styles.css">
  <script src="assets/app.js" defer></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand" href="#overview" aria-label="Benchline home">
        <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i></span>
        <span>benchline<span class="brand-period">.</span></span>
      </a>
      <div class="workspace-label">CAMPUS / ENGINEERING</div>
      <div class="lab-select"><span class="lab-select-dot"></span><span>Computer labs</span><span class="chevron">⌄</span></div>
      <nav class="primary-nav" aria-label="Main navigation">
        <a class="nav-item is-active" href="#overview"><span class="nav-icon">◫</span>Response desk<span class="nav-count" id="navActiveCount">0</span></a>
        <a class="nav-item" href="#stations"><span class="nav-icon">⌘</span>Stations</a>
        <a class="nav-item" href="#activity"><span class="nav-icon">◷</span>Activity log</a>
      </nav>
      <div class="sidebar-spacer"></div>
      <div class="connection-card">
        <div class="connection-heading"><span class="status-dot"></span>System online</div>
        <p>API connected<br><span id="lastSynced">Waiting for first sync</span></p>
      </div>
      <div class="profile-row"><div class="avatar">ML</div><div><strong>Morgan Lee</strong><span>Lab supervisor</span></div><button class="more-button" aria-label="Account options">···</button></div>
    </aside>

    <main class="main-content" id="overview">
      <header class="topbar">
        <div class="breadcrumb">Operations <span>/</span> <strong>Response desk</strong></div>
        <div class="topbar-actions">
          <div class="live-indicator"><span class="pulse-dot"></span>LIVE <span class="live-clock" id="liveClock">--:--:--</span></div>
          <button class="icon-button" id="notificationToggle" type="button" aria-label="Enable browser notifications" title="Enable browser notifications">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
          </button>
          <button class="icon-button" id="soundToggle" type="button" aria-label="Turn alert sound on" title="Turn alert sound on">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4zM15 9a5 5 0 0 1 0 6M18 6a9 9 0 0 1 0 12"/></svg>
          </button>
        </div>
      </header>

      <section class="page-heading">
        <div>
          <div class="eyebrow"><span class="eyebrow-rule"></span>ENGINEERING BUILDING <span class="eyebrow-separator">/</span> FLOOR 02</div>
          <h1>Lab response<span class="heading-period">.</span></h1>
          <p class="page-subtitle">Help requests from across your computer labs.</p>
        </div>
        <button class="primary-button" id="simulateRequest" type="button">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
          Simulate help request
        </button>
      </section>

      <section class="metrics" aria-label="Request summary">
        <article class="metric metric-open"><div class="metric-label">NEEDS ATTENTION <span class="metric-marker"></span></div><div class="metric-bottom"><strong id="openCount">0</strong><span>open requests</span></div></article>
        <article class="metric"><div class="metric-label">ACKNOWLEDGED</div><div class="metric-bottom"><strong id="ackCount">0</strong><span>being handled</span></div></article>
        <article class="metric"><div class="metric-label">AVG. RESPONSE</div><div class="metric-bottom"><strong id="avgResponse">--</strong><span>today</span></div></article>
        <article class="metric"><div class="metric-label">CONNECTED STATIONS</div><div class="metric-bottom"><strong id="stationCount">0<span class="metric-total"> / 12</span></strong><span>online now</span></div></article>
      </section>

      <div class="content-grid">
        <section class="requests-panel" id="activity">
          <div class="section-heading">
            <div><h2>Incoming requests</h2><p>Live queue <span class="queue-live-dot"></span></p></div>
            <div class="filters" role="group" aria-label="Filter requests">
              <button class="filter-button is-selected" data-filter="active" type="button">Active <span id="activeFilterCount">0</span></button>
              <button class="filter-button" data-filter="all" type="button">All</button>
            </div>
          </div>
          <div class="request-list" id="requestList" aria-live="polite" aria-busy="true"></div>
        </section>

        <aside class="right-rail" id="stations">
          <section class="stations-panel">
            <div class="section-heading compact-heading"><div><h2>Lab stations</h2><p>Device status</p></div><button class="text-button" id="refreshButton" type="button" title="Refresh station status">Refresh <span>↻</span></button></div>
            <div class="station-list" id="stationList"></div>
            <button class="outline-button" id="stationOverview" type="button">View all stations <span>→</span></button>
          </section>
          <section class="flow-panel">
            <div class="flow-panel-top"><span class="flow-icon">⌁</span><span class="flow-kicker">IOT SIGNAL PATH</span><span class="flow-live"><i></i> READY</span></div>
            <div class="flow-track" aria-label="Sensor to microcontroller to actuator to interface">
              <div class="flow-step"><span class="flow-symbol sensor-symbol">●</span><span>Sensor</span></div><span class="flow-arrow">→</span>
              <div class="flow-step"><span class="flow-symbol board-symbol">▦</span><span>ESP32</span></div><span class="flow-arrow">→</span>
              <div class="flow-step"><span class="flow-symbol actuator-symbol">◉</span><span>Alert</span></div><span class="flow-arrow">→</span>
              <div class="flow-step"><span class="flow-symbol interface-symbol">▤</span><span>Desk</span></div>
            </div>
            <p class="flow-caption">Button press <span>·</span> signal received <span>·</span> staff notified</p>
          </section>
          <div class="endpoint-note"><span class="endpoint-pip"></span><span>DEVICE ENDPOINT</span><code>/api.php</code><button id="copyEndpoint" type="button" title="Copy API URL" aria-label="Copy API URL">⧉</button></div>
        </aside>
      </div>
      <footer class="page-footer"><span>BENCHLINE LAB ASSIST</span><span>LOCAL NETWORK <i></i> API POLLING EVERY 2 SEC</span></footer>
    </main>
  </div>
  <div class="toast-region" id="toastRegion" aria-live="polite" aria-atomic="true"></div>
</body>
</html>
