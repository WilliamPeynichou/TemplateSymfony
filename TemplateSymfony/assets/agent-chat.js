import './styles/agent-chat.css';
import { formatPendingAction } from './agent-chat-format.js';

// ─── ÉTAT ────────────────────────────────────────────────────────────────────
let currentConversationId = null;
let validationMode        = 'singular';
let isLoading             = false;
let pendingAction         = null;

// teamId extrait du DOM (injecté par Twig dans data-team-id sur le body)
const teamId = document.body.dataset.teamId ? parseInt(document.body.dataset.teamId) : null;

// ─── ÉLÉMENTS DOM ────────────────────────────────────────────────────────────
const fab        = document.getElementById('agent-fab');
const overlay    = document.getElementById('agent-overlay');
const sidebar    = document.getElementById('agent-sidebar');
const closeBtn   = document.getElementById('agent-close');
const messages   = document.getElementById('agent-messages');
const input      = document.getElementById('agent-input');
const sendBtn    = document.getElementById('agent-send');
const modeToggle = document.getElementById('agent-mode-toggle');
const modeLabel  = document.getElementById('agent-mode-label');
const convBar    = document.getElementById('agent-conv-bar');
const dot        = document.getElementById('agent-dot');

async function parseApiResponse(response) {
    const raw = await response.text();
    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');

    if (isJson) {
        try {
            return JSON.parse(raw);
        } catch {
            throw new Error('Réponse JSON invalide du serveur.');
        }
    }

    const preview = (raw || '').replace(/\s+/g, ' ').slice(0, 180);
    throw new Error(`Réponse non JSON (${response.status}) : ${preview || 'vide'}`);
}

// ─── OUVERTURE / FERMETURE ───────────────────────────────────────────────────
function openSidebar() {
    sidebar.classList.add('tactical-open');
    overlay.classList.add('tactical-open');
    input.focus();
    loadConversations();
}

function closeSidebar() {
    sidebar.classList.remove('tactical-open');
    overlay.classList.remove('tactical-open');
}

fab.addEventListener('click', openSidebar);
closeBtn.addEventListener('click', closeSidebar);
overlay.addEventListener('click', closeSidebar);

// ─── MODE VALIDATION ─────────────────────────────────────────────────────────
modeToggle.addEventListener('change', () => {
    validationMode = modeToggle.checked ? 'auto' : 'singular';
    modeLabel.textContent = modeToggle.checked ? 'Mode auto' : 'Mode manuel';
    modeLabel.closest('.tactical-agent-mode-toggle').classList.toggle('tactical-auto-on', modeToggle.checked);
});

// ─── CONVERSATIONS ───────────────────────────────────────────────────────────
async function loadConversations() {
    try {
        const params = teamId ? `?team_id=${teamId}` : '';
        const res    = await fetch(`/api/v1/agent/conversations${params}`, { credentials: 'include' });
        const data   = await parseApiResponse(res);
        if (!data.success) return;

        renderConvBar(data.data || []);
    } catch (_) {}
}

function renderConvBar(convs) {
    convBar.innerHTML = '';

    const newBtn = document.createElement('button');
    newBtn.className   = 'tactical-agent-conv-new';
    newBtn.textContent = '+ Nouveau';
    newBtn.addEventListener('click', () => {
        currentConversationId = null;
        messages.innerHTML = '';
        pendingAction = null;
        document.querySelectorAll('.tactical-agent-conv-chip').forEach(c => c.classList.remove('active'));
    });
    convBar.appendChild(newBtn);

    convs.forEach(conv => {
        const chip = document.createElement('button');
        chip.className   = 'tactical-agent-conv-chip' + (conv.id === currentConversationId ? ' tactical-active' : '');
        chip.dataset.id  = String(conv.id);
        chip.textContent = conv.title || `Conv. #${conv.id}`;
        chip.title       = conv.title;
        chip.addEventListener('click', () => loadConversation(conv.id));
        convBar.appendChild(chip);
    });
}

async function loadConversation(convId) {
    currentConversationId = convId;
    messages.innerHTML    = '';
    pendingAction         = null;

    document.querySelectorAll('.tactical-agent-conv-chip').forEach(c => {
        c.classList.toggle('tactical-active', parseInt(c.dataset.id) === convId);
    });

    try {
        const res  = await fetch(`/api/v1/conversations/${convId}/messages?limit=50`, { credentials: 'include' });
        const data = await parseApiResponse(res);
        if (!data.success) return;

        (data.data || []).forEach(msg => {
            if (msg.role === 'user' || msg.role === 'assistant') {
                appendMessage(msg.role, msg.content);
            }
        });

        const detailRes  = await fetch(`/api/v1/conversations/${convId}`, { credentials: 'include' });
        const detailData = await parseApiResponse(detailRes);
        if (detailData.success && detailData.data?.pending_action) {
            pendingAction = detailData.data.pending_action;
            appendActionCard(detailData.data.pending_action);
        }
        scrollToBottom();
    } catch (_) {}
}

// ─── ENVOI D'UN MESSAGE ──────────────────────────────────────────────────────
async function sendMessage(text, confirmAction = null) {
    if (isLoading) return;

    setLoading(true);

    try {
        const body = {
            message:         text,
            conversation_id: currentConversationId,
            team_id:         teamId,
            validation_mode: validationMode,
        };
        if (confirmAction) body.confirm_action = confirmAction;

        const controller = new AbortController();
        const fetchTimeout = setTimeout(() => controller.abort(), 630_000); // 10.5 min
        const res  = await fetch('/api/v1/agent/chat', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body:         JSON.stringify(body),
            signal:       controller.signal,
        });
        clearTimeout(fetchTimeout);
        const data = await parseApiResponse(res);

        if (!data.success) {
            appendMessage('assistant', `Erreur : ${data.error || 'inconnue'}`);
            return;
        }

        currentConversationId = data.conversation_id;
        appendMessage('assistant', data.reply);

        if (data.pending_action) {
            pendingAction = data.pending_action;
            appendActionCard(data.pending_action);
        } else {
            pendingAction = null;
        }

        await loadConversations();

    } catch (e) {
        console.error('[Agent] Erreur:', e);
        appendMessage('assistant', `Erreur: ${e.message || e}`);
    } finally {
        setLoading(false);
        scrollToBottom();
    }
}

// ─── GESTION DU FORMULAIRE ───────────────────────────────────────────────────
sendBtn.addEventListener('click', handleSend);
input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
});

function handleSend() {
    const text = input.value.trim();
    if (!text || isLoading) return;
    appendMessage('user', text);
    input.value = '';
    autoResizeInput();
    sendMessage(text);
}

input.addEventListener('input', autoResizeInput);
function autoResizeInput() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 120) + 'px';
}

// ─── RENDU DES MESSAGES ──────────────────────────────────────────────────────
function appendMessage(role, content) {
    const wrapper = document.createElement('div');
    wrapper.className = `tactical-agent-message tactical-agent-message--${role}`;

    const bubble = document.createElement('div');
    bubble.className = 'tactical-agent-bubble';
    bubble.textContent = content;

    wrapper.appendChild(bubble);
    messages.appendChild(wrapper);
    scrollToBottom();
}

function appendActionCard(action) {
    const formatted = formatPendingAction(action);
    const card = document.createElement('div');
    card.className = 'tactical-agent-action-card';

    const label = document.createElement('div');
    label.className = 'tactical-agent-action-card__label';
    label.textContent = 'Action en attente de validation';
    card.appendChild(label);

    const toolNameRow = document.createElement('div');
    const toolName = document.createElement('strong');
    toolName.textContent = formatted.name;
    toolNameRow.appendChild(toolName);
    card.appendChild(toolNameRow);

    const argsPreview = document.createElement('pre');
    argsPreview.style.fontSize = '.78rem';
    argsPreview.style.margin = '.4rem 0';
    argsPreview.style.overflow = 'auto';
    argsPreview.textContent = formatted.argsText;
    card.appendChild(argsPreview);

    const btns = document.createElement('div');
    btns.className = 'tactical-agent-action-card__btns';
    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'tactical-agent-confirm-btn';
    confirmBtn.textContent = '✓ Confirmer';
    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'tactical-agent-cancel-btn';
    cancelBtn.textContent = '✕ Annuler';
    btns.appendChild(confirmBtn);
    btns.appendChild(cancelBtn);
    card.appendChild(btns);

    const lastMsg = messages.lastElementChild;
    if (lastMsg) lastMsg.appendChild(card);
    else messages.appendChild(card);

    confirmBtn.addEventListener('click', () => {
        card.remove();
        appendMessage('user', '✓ Confirmé');
        sendMessage('confirmer', 'confirm');
    });
    cancelBtn.addEventListener('click', () => {
        card.remove();
        appendMessage('user', '✕ Annulé');
        sendMessage('annuler', 'cancel');
        pendingAction = null;
    });

    scrollToBottom();
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function setLoading(state) {
    isLoading = state;
    sendBtn.disabled = state;
    dot.className = 'tactical-agent-header__dot' + (state ? ' tactical-loading' : '');

    // Indicateur de frappe
    const existing = document.getElementById('agent-typing-indicator');
    if (state && !existing) {
        const typing = document.createElement('div');
        typing.id = 'agent-typing-indicator';
        typing.className = 'tactical-agent-message tactical-agent-message--assistant';
        typing.innerHTML = '<div class="tactical-agent-bubble tactical-agent-typing"><span></span><span></span><span></span></div>';
        messages.appendChild(typing);
        scrollToBottom();
    } else if (!state && existing) {
        existing.remove();
    }
}

function scrollToBottom() {
    messages.scrollTop = messages.scrollHeight;
}
