const API_URL = new URL('../api.php', document.currentScript.src).toString();
const POLL_INTERVAL = 2000;
const stations = [
  { id: 'LAB-201', room: 'Lab 2A · Row A', online: true },
  { id: 'LAB-204', room: 'Lab 2A · Row B', online: true },
  { id: 'LAB-208', room: 'Lab 2B · Row A', online: true },
  { id: 'LAB-211', room: 'Lab 2B · Row C', online: true },
  { id: 'LAB-216', room: 'Lab 2C · Row A', online: false },
];
const messages = [
  'I need help with my workstation.',
  'The monitor is not displaying anything.',
  'I cannot log in to this computer.',
  'I need assistance with the lab software.',
];
let requests = [];
let currentFilter = 'active';
let knownRequestIds = new Set();
let soundEnabled = false;
let notificationEnabled = false;
let lastRequestAt = null;

const $ = (selector) => document.querySelector(selector);

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
}

function timeAgo(value) {
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(value).getTime()) / 1000));
  if (seconds < 60) return `${seconds}s ago`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  return `${hours}h ago`;
}

function statusLabel(status) {
  return status === 'acknowledged' ? 'Acknowledged' : status === 'resolved' ? 'Resolved' : 'Awaiting help';
}

function renderRequests() {
  const active = requests.filter((request) => request.status !== 'resolved');
  const visible = currentFilter === 'active' ? active : [...requests].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  $('#openCount').textContent = active.filter((request) => request.status === 'open').length;
  $('#ackCount').textContent = active.filter((request) => request.status === 'acknowledged').length;
  $('#navActiveCount').textContent = active.length;
  $('#activeFilterCount').textContent = active.length;
  $('#stationCount').innerHTML = `${stations.filter((station) => station.online).length}<span class="metric-total"> / 12</span>`;
  const acknowledged = requests.filter((request) => request.acknowledgedAt && request.status !== 'open');
  const responseTimes = acknowledged.map((request) => (new Date(request.acknowledgedAt) - new Date(request.createdAt)) / 60000).filter((minutes) => minutes >= 0);
  $('#avgResponse').textContent = responseTimes.length ? `${(responseTimes.reduce((sum, minutes) => sum + minutes, 0) / responseTimes.length).toFixed(1)}m` : '--';

  const list = $('#requestList');
  list.setAttribute('aria-busy', 'false');
  if (!visible.length) {
    list.innerHTML = `<div class="empty-state"><span class="empty-icon">✓</span><strong>${currentFilter === 'active' ? 'All clear for now' : 'No requests yet'}</strong><span>New station alerts will appear here.</span></div>`;
    return;
  }
  list.innerHTML = visible.map((request) => {
    const isActive = request.status !== 'resolved';
    const statusClass = request.status === 'acknowledged' ? 'status-acknowledged' : '';
    const urgency = request.priority === 'high' ? 'high' : 'normal';
    const action = request.status === 'open'
      ? `<button class="small-button" data-action="acknowledge" data-id="${escapeHtml(request.id)}" type="button">Acknowledge</button>`
      : isActive ? `<button class="small-button resolve" data-action="resolve" data-id="${escapeHtml(request.id)}" type="button">Resolve</button>` : '';
    return `<article class="request-card priority-${urgency} ${statusClass}">
      <div class="request-topline"><div><div class="station-name"><span class="priority-dot ${urgency === 'normal' ? 'normal' : ''}"></span>${escapeHtml(request.stationId)}</div><span class="request-machine">${escapeHtml(request.room || 'Computer lab')}</span></div><time class="request-time" datetime="${escapeHtml(request.createdAt)}" title="${new Date(request.createdAt).toLocaleString()}">${timeAgo(request.createdAt)}</time></div>
      <p class="request-message">${escapeHtml(request.message || 'A student is requesting assistance.')}</p>
      <div class="request-bottom"><span class="status-badge ${request.status}">${statusLabel(request.status)}</span><div class="request-actions">${action}</div></div>
    </article>`;
  }).join('');
}

function renderStations() {
  $('#stationList').innerHTML = stations.slice(0, 4).map((station) => `<div class="station-row"><span class="station-device-icon">⌘</span><span class="station-info"><strong>${station.id}</strong><span>${station.room}</span></span><span class="station-state ${station.online ? '' : 'offline'}"><i></i>${station.online ? 'ONLINE' : 'OFFLINE'}</span></div>`).join('');
}

function toast(title, detail) {
  const element = document.createElement('div');
  element.className = 'toast';
  element.innerHTML = `<strong>${escapeHtml(title)}</strong><span>${escapeHtml(detail)}</span>`;
  $('#toastRegion').append(element);
  window.setTimeout(() => element.remove(), 4200);
}

function alertFor(request) {
  toast('New help request', `${request.stationId} · ${request.message}`);
  if (notificationEnabled && 'Notification' in window && Notification.permission === 'granted') {
    new Notification(`Help request · ${request.stationId}`, { body: request.message, tag: request.id });
  }
  if (soundEnabled) playAlert();
}

function playAlert() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) return;
  const context = new AudioContextClass();
  const oscillator = context.createOscillator();
  const gain = context.createGain();
  oscillator.type = 'sine';
  oscillator.frequency.value = 740;
  gain.gain.setValueAtTime(0.0001, context.currentTime);
  gain.gain.exponentialRampToValueAtTime(0.12, context.currentTime + 0.02);
  gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.22);
  oscillator.connect(gain);
  gain.connect(context.destination);
  oscillator.start();
  oscillator.stop(context.currentTime + 0.24);
  oscillator.onended = () => context.close();
}

async function syncRequests() {
  try {
    const response = await fetch(API_URL, { cache: 'no-store' });
    if (!response.ok) throw new Error(`API returned ${response.status}`);
    const payload = await response.json();
    if (!payload.ok || !Array.isArray(payload.requests)) throw new Error('Invalid API response');
    requests = payload.requests;
    const incoming = requests.filter((request) => !knownRequestIds.has(request.id));
    if (knownRequestIds.size) incoming.forEach(alertFor);
    knownRequestIds = new Set(requests.map((request) => request.id));
    $('#lastSynced').textContent = `Synced ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
    $('.connection-heading').lastChild.textContent = 'System online';
    $('.connection-card p').innerHTML = 'API connected<br><span id="lastSynced">' + $('#lastSynced').textContent + '</span>';
    renderRequests();
  } catch (error) {
    $('#lastSynced').textContent = 'Retrying connection';
    $('.connection-heading').lastChild.textContent = 'Reconnecting';
  }
}

async function postAction(action, requestId, data = {}) {
  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, id: requestId, ...data }),
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.error || 'Request failed');
    await syncRequests();
    if (action === 'create') toast('Request sent', `${data.stationId} has been added to the live queue.`);
    if (action === 'acknowledge') toast('Request acknowledged', 'The station has been marked as in progress.');
    if (action === 'resolve') toast('Request resolved', 'The lab queue has been updated.');
  } catch (error) {
    toast('Could not update request', error.message);
  }
}

function createDemoRequest() {
  const candidates = stations.filter((station) => station.online);
  const station = candidates[Math.floor(Math.random() * candidates.length)];
  const message = messages[Math.floor(Math.random() * messages.length)];
  postAction('create', null, { stationId: station.id, room: station.room, message, priority: Math.random() > 0.75 ? 'high' : 'normal', source: 'dashboard-demo' });
}

function updateClock() {
  $('#liveClock').textContent = new Date().toLocaleTimeString([], { hour12: false });
}

async function initNotifications() {
  if (!('Notification' in window)) {
    toast('Notifications unavailable', 'This browser does not support desktop notifications.');
    return;
  }
  const permission = await Notification.requestPermission();
  notificationEnabled = permission === 'granted';
  $('#notificationToggle').classList.toggle('is-on', notificationEnabled);
  toast(notificationEnabled ? 'Notifications enabled' : 'Notifications not enabled', notificationEnabled ? 'New station requests will appear on your desktop.' : 'Allow notifications in your browser settings to enable alerts.');
}

$('#simulateRequest').addEventListener('click', createDemoRequest);
$('#refreshButton').addEventListener('click', syncRequests);
$('#notificationToggle').addEventListener('click', initNotifications);
$('#soundToggle').addEventListener('click', () => {
  soundEnabled = !soundEnabled;
  $('#soundToggle').classList.toggle('is-on', soundEnabled);
  $('#soundToggle').title = soundEnabled ? 'Turn alert sound off' : 'Turn alert sound on';
  $('#soundToggle').setAttribute('aria-label', $('#soundToggle').title);
  if (soundEnabled) playAlert();
});
$('#copyEndpoint').addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(API_URL);
    toast('Endpoint copied', API_URL);
  } catch {
    toast('Device endpoint', API_URL);
  }
});
$('#stationOverview').addEventListener('click', () => toast('Station overview', '12 stations are registered for this lab.'));
document.querySelectorAll('.filter-button').forEach((button) => button.addEventListener('click', () => {
  currentFilter = button.dataset.filter;
  document.querySelectorAll('.filter-button').forEach((filter) => filter.classList.toggle('is-selected', filter === button));
  renderRequests();
}));
$('#requestList').addEventListener('click', (event) => {
  const button = event.target.closest('[data-action]');
  if (button) postAction(button.dataset.action, button.dataset.id);
});

renderStations();
updateClock();
window.setInterval(updateClock, 1000);
syncRequests();
window.setInterval(syncRequests, POLL_INTERVAL);
