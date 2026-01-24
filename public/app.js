const state = {
  user: null,
  sessionId: null,
  sessionCode: null,
  role: null,
  commandLastId: 0,
  chatLastId: 0,
  signalLastId: 0,
  commandPoller: null,
  chatPoller: null,
  signalPoller: null,
  commandQueue: [],
  runningTimer: false,
  localQueue: [],
  peer: null,
  peerRole: null,
  localStream: null,
  remoteStream: null,
};

const els = {
  entryOverlay: document.getElementById("entry-overlay"),
  authPanel: document.getElementById("auth-panel"),
  modePanel: document.getElementById("mode-panel"),
  subPanel: document.getElementById("sub-panel"),
  masterPanel: document.getElementById("master-panel"),
  authMessage: document.getElementById("auth-message"),
  modeMessage: document.getElementById("mode-message"),
  masterMessage: document.getElementById("master-message"),
  logoutBtn: document.getElementById("logout-btn"),
  authTabs: Array.from(document.querySelectorAll(".auth-tab")),
  authForms: {
    login: document.getElementById("login-form"),
    signup: document.getElementById("signup-form"),
  },
  subJoin: document.getElementById("sub-join"),
  masterBtn: document.getElementById("master-btn"),
  subBtn: document.getElementById("sub-btn"),
  subCode: document.getElementById("sub-code"),
  subConnect: document.getElementById("sub-connect"),
  sessionCode: document.getElementById("session-code"),
  copyCode: document.getElementById("copy-code"),
  queueList: document.getElementById("queue-list"),
  queueTemplate: document.getElementById("queue-item-template"),
  queueButtons: Array.from(document.querySelectorAll("[data-phase]")),
  inhaleDuration: document.getElementById("inhale-duration"),
  holdDuration: document.getElementById("hold-duration"),
  breakDuration: document.getElementById("break-duration"),
  sendQueue: document.getElementById("send-queue"),
  sequenceName: document.getElementById("sequence-name"),
  saveSequence: document.getElementById("save-sequence"),
  sequenceSelect: document.getElementById("sequence-select"),
  applySequence: document.getElementById("apply-sequence"),
  surpriseText: document.getElementById("surprise-text"),
  surpriseDuration: document.getElementById("surprise-duration"),
  sendSurprise: document.getElementById("send-surprise"),
  masterChatStream: document.getElementById("master-chat-stream"),
  masterChatForm: document.getElementById("master-chat-form"),
  masterChatInput: document.getElementById("master-chat-input"),
  subChat: document.getElementById("sub-chat"),
  subPhase: document.getElementById("sub-phase"),
  subTimer: document.getElementById("sub-timer"),
  surpriseOverlay: document.getElementById("surprise-overlay"),
  surpriseMessage: document.getElementById("surprise-message"),
  surpriseCountdown: document.getElementById("surprise-countdown"),
  previewCam: document.getElementById("preview-cam"),
  broadcastCam: document.getElementById("broadcast-cam"),
  masterLocal: document.getElementById("master-local"),
  masterRemote: document.getElementById("master-remote"),
  subLocal: document.getElementById("sub-local"),
  subRemote: document.getElementById("sub-remote"),
};

const COMMAND_POLL_MS = 2000;
const CHAT_POLL_MS = 2000;
const SIGNAL_POLL_MS = 1500;
const BROADCAST_DELAY_MS = 3000;

function showMessage(target, message, tone = "info") {
  if (!target) return;
  target.textContent = message;
  target.style.color = tone === "error" ? "var(--danger)" : "var(--accent-strong)";
}

async function apiFetch(path, options = {}) {
  const response = await fetch(path, {
    credentials: "include",
    headers: {
      "Content-Type": "application/json",
      ...(options.headers || {}),
    },
    ...options,
  });

  const data = await response.json().catch(() => ({ ok: false, error: "Invalid server response." }));
  if (!response.ok || data.ok === false) {
    const error = data.error || `Request failed (${response.status})`;
    throw new Error(error);
  }
  return data;
}

function hidePanels() {
  [els.authPanel, els.modePanel, els.subPanel, els.masterPanel].forEach((panel) => panel.classList.add("hidden"));
}

function resetRealtime() {
  ["commandPoller", "chatPoller", "signalPoller"].forEach((key) => {
    if (state[key]) {
      clearInterval(state[key]);
      state[key] = null;
    }
  });
  state.commandLastId = 0;
  state.chatLastId = 0;
  state.signalLastId = 0;
  state.commandQueue = [];
  state.runningTimer = false;
  teardownPeer();
}

function teardownPeer() {
  if (state.peer) {
    state.peer.ontrack = null;
    state.peer.onicecandidate = null;
    state.peer.close();
  }
  state.peer = null;
  state.peerRole = null;
  state.remoteStream = null;
  if (els.masterRemote) els.masterRemote.srcObject = null;
  if (els.subRemote) els.subRemote.srcObject = null;
}

async function ensureLocalStream() {
  if (state.localStream) {
    return state.localStream;
  }
  const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
  state.localStream = stream;
  return stream;
}

function attachLocalStream(role) {
  if (!state.localStream) return;
  if (role === "master") {
    els.masterLocal.srcObject = state.localStream;
  } else {
    els.subLocal.srcObject = state.localStream;
  }
}

function createPeer(role) {
  if (state.peer && state.peerRole === role) {
    return state.peer;
  }

  teardownPeer();

  const peer = new RTCPeerConnection({
    iceServers: [{ urls: "stun:stun.l.google.com:19302" }],
  });

  if (state.localStream) {
    state.localStream.getTracks().forEach((track) => peer.addTrack(track, state.localStream));
  }

  peer.ontrack = (event) => {
    const [stream] = event.streams;
    state.remoteStream = stream;
    if (role === "master") {
      els.masterRemote.srcObject = stream;
    } else {
      els.subRemote.srcObject = stream;
    }
  };

  peer.onicecandidate = (event) => {
    if (event.candidate && state.sessionId && state.role) {
      const targetRole = state.role === "master" ? "sub" : "master";
      postSignal(targetRole, "ice", { candidate: event.candidate });
    }
  };

  state.peer = peer;
  state.peerRole = role;
  return peer;
}

async function postSignal(targetRole, signalType, signalData) {
  if (!state.sessionId) return;
  try {
    await apiFetch("/api/post-signal.php", {
      method: "POST",
      body: JSON.stringify({
        session_id: state.sessionId,
        target_role: targetRole,
        signal_type: signalType,
        signal_data: signalData,
      }),
    });
  } catch (error) {
    console.warn("Signal post failed", error);
  }
}

async function pollSignals() {
  if (!state.sessionId || !state.role) return;
  try {
    const data = await apiFetch(
      `/api/fetch-signals.php?session_id=${state.sessionId}&last_id=${state.signalLastId}`,
      { method: "GET" }
    );
    state.signalLastId = data.last_id;
    for (const signal of data.signals) {
      handleSignal(signal);
    }
  } catch (error) {
    console.warn("Signal polling error", error);
  }
}

async function handleSignal(signal) {
  if (!state.role) return;
  const peer = createPeer(state.role);

  if (signal.signal_type === "offer" && state.role === "sub") {
    await peer.setRemoteDescription(new RTCSessionDescription(signal.signal_data));
    const answer = await peer.createAnswer();
    await peer.setLocalDescription(answer);
    await postSignal("master", "answer", answer);
    return;
  }

  if (signal.signal_type === "answer" && state.role === "master") {
    await peer.setRemoteDescription(new RTCSessionDescription(signal.signal_data));
    return;
  }

  if (signal.signal_type === "ice") {
    try {
      await peer.addIceCandidate(new RTCIceCandidate(signal.signal_data.candidate));
    } catch (error) {
      console.warn("ICE candidate rejected", error);
    }
  }
}

function startSignalPolling() {
  if (state.signalPoller) clearInterval(state.signalPoller);
  state.signalPoller = setInterval(pollSignals, SIGNAL_POLL_MS);
  pollSignals();
}

function renderQueue() {
  els.queueList.innerHTML = "";
  state.localQueue.forEach((item, index) => {
    const fragment = els.queueTemplate.content.cloneNode(true);
    const root = fragment.querySelector(".queue__item");
    const phase = fragment.querySelector(".queue__phase");
    const meta = fragment.querySelector(".queue__meta");
    const remove = fragment.querySelector(".queue__remove");

    phase.textContent = item.phase;
    meta.textContent = `${item.duration}s • starts +${item.offset}s`;
    remove.addEventListener("click", () => {
      state.localQueue.splice(index, 1);
      recomputeOffsets();
      renderQueue();
    });

    root.dataset.index = String(index);
    els.queueList.appendChild(fragment);
  });
}

function recomputeOffsets() {
  let offset = 0;
  state.localQueue = state.localQueue.map((item, idx) => {
    const next = { ...item, offset };
    offset += idx === state.localQueue.length - 1 ? item.duration : item.duration;
    return next;
  });
}

function queueTimer(phase) {
  const durationInput =
    phase === "Inhale" ? els.inhaleDuration : phase === "Hold" ? els.holdDuration : els.breakDuration;
  const duration = Math.max(1, parseInt(durationInput.value, 10) || 1);

  const offset = state.localQueue.reduce((total, item) => total + item.duration, 0);
  state.localQueue.push({ phase, duration, offset });
  renderQueue();
  showMessage(els.masterMessage, `${phase} queued (${duration}s).`);
}

function buildSequencePayload() {
  const sequence = [];
  state.localQueue.forEach((item, index) => {
    sequence.push({
      type: "timer",
      delay: index === 0 ? 0 : state.localQueue[index - 1].duration,
      payload: { phase: item.phase, duration: item.duration },
    });
  });
  return sequence;
}

async function broadcastQueue() {
  if (!state.sessionId) return;
  if (state.localQueue.length === 0) {
    showMessage(els.masterMessage, "Queue is empty.", "error");
    return;
  }

  const base = new Date(Date.now() + BROADCAST_DELAY_MS);
  let offsetSeconds = 0;

  try {
    for (const item of state.localQueue) {
      const executeAt = new Date(base.getTime() + offsetSeconds * 1000);
      await apiFetch("/api/send-command.php", {
        method: "POST",
        body: JSON.stringify({
          session_id: state.sessionId,
          type: "timer",
          payload: { phase: item.phase, duration: item.duration },
          execute_at: executeAt.toISOString(),
        }),
      });
      offsetSeconds += item.duration;
    }

    await postChat(`Sequence broadcast (${state.localQueue.length} steps).`);
    state.localQueue = [];
    renderQueue();
    showMessage(els.masterMessage, "Queue broadcast to sub.");
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

async function postChat(message) {
  if (!state.sessionId) return;
  await apiFetch("/api/post-chat.php", {
    method: "POST",
    body: JSON.stringify({ session_id: state.sessionId, message }),
  });
}

function appendChat(streamEl, chat) {
  const line = document.createElement("p");
  line.className = "chat-line";
  const label = chat.sender_role === "system" ? "SYSTEM" : chat.sender_role.toUpperCase();
  line.textContent = `${label}: ${chat.message}`;
  streamEl.appendChild(line);
  streamEl.scrollTop = streamEl.scrollHeight;
}

async function pollChats() {
  if (!state.sessionId) return;
  try {
    const data = await apiFetch(`/api/fetch-chats.php?session_id=${state.sessionId}&last_id=${state.chatLastId}`, {
      method: "GET",
    });
    state.chatLastId = data.last_id;
    data.chats.forEach((chat) => {
      if (state.role === "master") {
        appendChat(els.masterChatStream, chat);
      } else {
        appendChat(els.subChat, chat);
      }
    });
  } catch (error) {
    console.warn("Chat polling error", error);
  }
}

function startChatPolling() {
  if (state.chatPoller) clearInterval(state.chatPoller);
  state.chatPoller = setInterval(pollChats, CHAT_POLL_MS);
  pollChats();
}

function scheduleCommand(command) {
  const executeAt = command.execute_at ? new Date(command.execute_at).getTime() : Date.now();
  const delay = Math.max(0, executeAt - Date.now());
  window.setTimeout(() => {
    state.commandQueue.push(command);
    runNextCommand();
  }, delay);
}

function runNextCommand() {
  if (state.runningTimer || state.commandQueue.length === 0) {
    return;
  }

  const next = state.commandQueue.shift();
  if (!next) return;

  if (next.type === "timer") {
    const phase = next.payload?.phase || "Command";
    const duration = Math.max(1, parseInt(next.payload?.duration, 10) || 1);
    runTimer(phase, duration);
    return;
  }

  if (next.type === "surprise") {
    const prompt = next.payload?.prompt || "Follow the command";
    const duration = Math.max(3, parseInt(next.payload?.duration, 10) || 10);
    triggerSurprise(prompt, duration);
    runNextCommand();
    return;
  }

  runNextCommand();
}

function runTimer(phase, duration) {
  state.runningTimer = true;
  let remaining = duration;
  els.subPhase.textContent = phase;
  els.subTimer.textContent = String(remaining);

  const interval = setInterval(() => {
    remaining -= 1;
    els.subTimer.textContent = remaining > 0 ? String(remaining) : "0";
    if (remaining <= 0) {
      clearInterval(interval);
      state.runningTimer = false;
      runNextCommand();
    }
  }, 1000);
}

function triggerSurprise(prompt, duration) {
  els.surpriseMessage.textContent = prompt;
  els.surpriseOverlay.classList.remove("hidden");

  let remaining = duration;
  els.surpriseCountdown.textContent = String(remaining);

  const interval = setInterval(() => {
    remaining -= 1;
    els.surpriseCountdown.textContent = remaining > 0 ? String(remaining) : "0";
    if (remaining <= 0) {
      clearInterval(interval);
      els.surpriseOverlay.classList.add("hidden");
    }
  }, 1000);
}

async function pollCommands() {
  if (!state.sessionId || state.role !== "sub") return;
  try {
    const data = await apiFetch(
      `/api/fetch-commands.php?session_id=${state.sessionId}&last_id=${state.commandLastId}`,
      { method: "GET" }
    );
    state.commandLastId = data.last_id;
    data.commands.forEach(scheduleCommand);
  } catch (error) {
    console.warn("Command polling error", error);
  }
}

function startCommandPolling() {
  if (state.commandPoller) clearInterval(state.commandPoller);
  state.commandPoller = setInterval(pollCommands, COMMAND_POLL_MS);
  pollCommands();
}

async function loadSequences() {
  if (!state.sessionId || state.role !== "master") return;
  try {
    const data = await apiFetch("/api/list-sequences.php", { method: "GET" });
    els.sequenceSelect.innerHTML = '<option value="">Load saved sequence</option>';
    data.sequences.forEach((sequence) => {
      const opt = document.createElement("option");
      opt.value = String(sequence.id);
      opt.textContent = `${sequence.name} (${sequence.sequence.length} steps)`;
      opt.dataset.sequence = JSON.stringify(sequence.sequence);
      els.sequenceSelect.appendChild(opt);
    });
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

async function saveSequence() {
  const name = els.sequenceName.value.trim();
  if (!name) {
    showMessage(els.masterMessage, "Name your sequence first.", "error");
    return;
  }
  if (state.localQueue.length === 0) {
    showMessage(els.masterMessage, "Queue is empty.", "error");
    return;
  }

  try {
    const sequence = buildSequencePayload();
    await apiFetch("/api/save-sequence.php", {
      method: "POST",
      body: JSON.stringify({ name, sequence }),
    });
    els.sequenceName.value = "";
    showMessage(els.masterMessage, "Sequence saved.");
    loadSequences();
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

async function applySequence() {
  const sequenceId = parseInt(els.sequenceSelect.value, 10);
  if (!sequenceId) {
    showMessage(els.masterMessage, "Choose a saved sequence.", "error");
    return;
  }

  try {
    const startAt = new Date(Date.now() + BROADCAST_DELAY_MS).toISOString();
    await apiFetch("/api/apply-sequence.php", {
      method: "POST",
      body: JSON.stringify({ session_id: state.sessionId, sequence_id: sequenceId, start_at: startAt }),
    });
    showMessage(els.masterMessage, "Saved sequence applied.");
    await postChat("Saved sequence broadcast.");
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

async function sendSurprise() {
  const prompt = els.surpriseText.value.trim();
  const duration = Math.max(3, parseInt(els.surpriseDuration.value, 10) || 10);
  if (!prompt) {
    showMessage(els.masterMessage, "Add a surprise prompt.", "error");
    return;
  }

  try {
    await apiFetch("/api/send-command.php", {
      method: "POST",
      body: JSON.stringify({
        session_id: state.sessionId,
        type: "surprise",
        payload: { prompt, duration },
        execute_at: new Date().toISOString(),
      }),
    });
    els.surpriseText.value = "";
    showMessage(els.masterMessage, "Surprise sent.");
    await postChat(`Surprise issued: ${prompt}`);
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

async function startMaster() {
  resetRealtime();
  hidePanels();
  els.masterPanel.classList.remove("hidden");
  state.role = "master";

  try {
    const data = await apiFetch("/api/create-session.php", { method: "POST", body: JSON.stringify({}) });
    state.sessionId = data.session.id;
    state.sessionCode = data.session.code;
    els.sessionCode.textContent = state.sessionCode;
    showMessage(els.masterMessage, "Session live. Share the code.");
    startChatPolling();
    startSignalPolling();
    loadSequences();
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

async function startSub() {
  const code = els.subCode.value.trim();
  if (code.length !== 6) {
    showMessage(els.modeMessage, "Enter a 6-digit code.", "error");
    return;
  }

  showMessage(els.modeMessage, "Requesting webcam + mic permission...");
  try {
    await ensureLocalStream();
  } catch (error) {
    showMessage(els.modeMessage, "Camera + mic permission required for sub mode.", "error");
    return;
  }

  try {
    const data = await apiFetch("/api/join-session.php", {
      method: "POST",
      body: JSON.stringify({ code }),
    });

    resetRealtime();
    state.role = "sub";
    state.sessionId = data.session.id;
    state.sessionCode = data.session.code;

    hidePanels();
    els.subPanel.classList.remove("hidden");
    attachLocalStream("sub");
    showMessage(els.modeMessage, "Connected.");

    startCommandPolling();
    startChatPolling();
    startSignalPolling();
    showMessage(els.modeMessage, "");
  } catch (error) {
    showMessage(els.modeMessage, error.message, "error");
  }
}

async function previewCamera() {
  try {
    await ensureLocalStream();
    attachLocalStream("master");
    showMessage(els.masterMessage, "Webcam ready.");
  } catch (error) {
    showMessage(els.masterMessage, "Unable to access webcam.", "error");
  }
}

async function broadcastCamera() {
  if (!state.sessionId || state.role !== "master") return;
  try {
    await ensureLocalStream();
    attachLocalStream("master");

    const peer = createPeer("master");
    const offer = await peer.createOffer();
    await peer.setLocalDescription(offer);
    await postSignal("sub", "offer", offer);
    showMessage(els.masterMessage, "Broadcasting. Waiting for sub.");
  } catch (error) {
    showMessage(els.masterMessage, error.message, "error");
  }
}

function setAuthed(user) {
  state.user = user;
  els.logoutBtn.classList.toggle("hidden", !user);
}

async function refreshSession() {
  try {
    const data = await apiFetch("/api/me.php", { method: "GET" });
    if (data.user) {
      setAuthed(data.user);
      hidePanels();
      els.modePanel.classList.remove("hidden");
    } else {
      setAuthed(null);
      hidePanels();
      els.authPanel.classList.remove("hidden");
    }
  } catch (error) {
    setAuthed(null);
    hidePanels();
    els.authPanel.classList.remove("hidden");
  }
}

async function handleAuthSubmit(form, endpoint) {
  const formData = new FormData(form);
  const payload = Object.fromEntries(formData.entries());
  showMessage(els.authMessage, "Working...");
  try {
    const data = await apiFetch(endpoint, {
      method: "POST",
      body: JSON.stringify(payload),
    });
    setAuthed(data.user);
    showMessage(els.authMessage, "Welcome in.");
    hidePanels();
    els.modePanel.classList.remove("hidden");
  } catch (error) {
    showMessage(els.authMessage, error.message, "error");
  }
}

async function handleLogout() {
  try {
    await apiFetch("/api/logout.php", { method: "POST", body: JSON.stringify({}) });
  } catch (error) {
    console.warn("Logout failed", error);
  }
  resetRealtime();
  state.sessionId = null;
  state.role = null;
  state.localQueue = [];
  setAuthed(null);
  hidePanels();
  els.authPanel.classList.remove("hidden");
  showMessage(els.authMessage, "Logged out.");
}

function wireAuthTabs() {
  els.authTabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      els.authTabs.forEach((t) => t.classList.remove("is-active"));
      tab.classList.add("is-active");
      const target = tab.dataset.tab;
      Object.entries(els.authForms).forEach(([key, form]) => {
        form.classList.toggle("is-active", key === target);
      });
      showMessage(els.authMessage, "");
    });
  });
}

function wireEntryOverlay() {
  if (!els.entryOverlay) return;
  window.setTimeout(() => {
    els.entryOverlay.setAttribute("aria-hidden", "true");
  }, 6600);
}

function wireEvents() {
  wireAuthTabs();
  wireEntryOverlay();

  els.authForms.login.addEventListener("submit", (event) => {
    event.preventDefault();
    handleAuthSubmit(els.authForms.login, "/api/login.php");
  });

  els.authForms.signup.addEventListener("submit", (event) => {
    event.preventDefault();
    handleAuthSubmit(els.authForms.signup, "/api/signup.php");
  });

  els.masterBtn.addEventListener("click", startMaster);
  els.subBtn.addEventListener("click", () => {
    els.subJoin.classList.toggle("hidden");
    showMessage(els.modeMessage, "Enter the 6-digit master code.");
  });
  els.subConnect.addEventListener("click", startSub);
  els.logoutBtn.addEventListener("click", handleLogout);

  els.queueButtons.forEach((button) => {
    button.addEventListener("click", () => queueTimer(button.dataset.phase));
  });

  els.sendQueue.addEventListener("click", broadcastQueue);
  els.saveSequence.addEventListener("click", saveSequence);
  els.applySequence.addEventListener("click", applySequence);
  els.sendSurprise.addEventListener("click", sendSurprise);

  els.copyCode.addEventListener("click", async () => {
    if (!state.sessionCode) return;
    try {
      await navigator.clipboard.writeText(state.sessionCode);
      showMessage(els.masterMessage, "Code copied.");
    } catch (error) {
      showMessage(els.masterMessage, "Unable to copy code.", "error");
    }
  });

  els.masterChatForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const message = els.masterChatInput.value.trim();
    if (!message) return;
    try {
      await postChat(message);
      els.masterChatInput.value = "";
      showMessage(els.masterMessage, "Chat sent.");
    } catch (error) {
      showMessage(els.masterMessage, error.message, "error");
    }
  });

  els.previewCam.addEventListener("click", previewCamera);
  els.broadcastCam.addEventListener("click", broadcastCamera);
}

wireEvents();
refreshSession();
