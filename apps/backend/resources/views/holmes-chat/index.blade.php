<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Holmes Chat - Skylogs</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --panel: #ffffff;
            --border: #d8dee9;
            --text: #1f2937;
            --muted: #6b7280;
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --assistant: #eef2ff;
            --danger: #dc2626;
            --shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(180deg, #eef2ff 0%, var(--bg) 220px);
            color: var(--text);
            min-height: 100vh;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px 16px 32px;
        }

        .header {
            text-align: center;
            margin-bottom: 24px;
        }

        .header h1 {
            margin: 0 0 8px;
            font-size: 2rem;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            margin: 0;
            color: var(--muted);
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .login-card {
            max-width: 420px;
            margin: 48px auto;
            padding: 32px;
        }

        .login-card h2 {
            margin: 0 0 8px;
            font-size: 1.25rem;
        }

        .login-card p {
            margin: 0 0 24px;
            color: var(--muted);
            font-size: 0.95rem;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        input, textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            font: inherit;
            background: #fff;
        }

        input:focus, textarea:focus {
            outline: 2px solid rgba(37, 99, 235, 0.2);
            border-color: var(--primary);
        }

        .field { margin-bottom: 16px; }

        .btn {
            border: none;
            border-radius: 10px;
            padding: 12px 18px;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
        }

        .btn-primary:hover { background: var(--primary-dark); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--text);
        }

        .error-box {
            background: #fef2f2;
            color: var(--danger);
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            background: #fafbff;
        }

        .toolbar .user {
            font-size: 0.9rem;
            color: var(--muted);
        }

        .messages {
            min-height: 420px;
            max-height: 60vh;
            overflow-y: auto;
            padding: 20px;
            background: var(--bg);
        }

        .empty-state {
            height: 380px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            text-align: center;
            padding: 0 24px;
        }

        .message-row {
            display: flex;
            margin-bottom: 14px;
        }

        .message-row.user { justify-content: flex-end; }
        .message-row.assistant { justify-content: flex-start; }

        .bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 16px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .message-row.user .bubble {
            background: var(--primary);
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .message-row.assistant .bubble {
            background: var(--assistant);
            color: var(--text);
            border-bottom-left-radius: 4px;
        }

        .loading-bubble {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--assistant);
            color: var(--muted);
            padding: 12px 16px;
            border-radius: 16px;
        }

        .composer {
            display: flex;
            gap: 12px;
            padding: 16px 20px;
            border-top: 1px solid var(--border);
            background: var(--panel);
            align-items: flex-end;
        }

        .composer textarea {
            resize: vertical;
            min-height: 72px;
        }

        .hidden { display: none !important; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Holmes Chat</h1>
        <p>Test UI for HolmesGPT incident investigation (owner access only)</p>
    </div>

    @if (! $user)
        <div class="card login-card">
            <h2>Sign in</h2>
            <p>Use an owner account to access the HolmesGPT chat.</p>

            @if ($errors->any())
                <div class="error-box">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('holmes-chat.login') }}">
                @csrf
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </div>
                <button class="btn btn-primary" type="submit" style="width: 100%;">Sign in</button>
            </form>
        </div>
    @else
        <div class="card" id="chat-app">
            <div class="toolbar">
                <div class="user">Signed in as <strong>{{ $user->name }}</strong> ({{ $user->username }})</div>
                <form method="POST" action="{{ route('holmes-chat.logout') }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit">Logout</button>
                </form>
            </div>

            <div id="error-box" class="error-box hidden"></div>

            <div class="messages" id="messages">
                <div class="empty-state" id="empty-state">
                    Start a conversation by asking about fired alerts or cluster health.
                </div>
            </div>

            <div class="composer">
                <textarea
                    id="message-input"
                    placeholder="Ask HolmesGPT about alerts, pods, or cluster health..."
                    rows="3"
                ></textarea>
                <button class="btn btn-primary" id="send-btn" type="button" style="min-width: 110px;">Send</button>
            </div>
        </div>
    @endif
</div>

@if ($user)
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    const messagesEl = document.getElementById('messages');
    const emptyStateEl = document.getElementById('empty-state');
    const inputEl = document.getElementById('message-input');
    const sendBtn = document.getElementById('send-btn');
    const errorBox = document.getElementById('error-box');

    let conversationHistory = [];
    let loading = false;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    function clearError() {
        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    }

    function appendMessage(role, content) {
        if (emptyStateEl) {
            emptyStateEl.remove();
        }

        const row = document.createElement('div');
        row.className = `message-row ${role}`;

        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.textContent = content;

        row.appendChild(bubble);
        messagesEl.appendChild(row);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setLoading(isLoading) {
        loading = isLoading;
        sendBtn.disabled = isLoading;
        inputEl.disabled = isLoading;

        const existing = document.getElementById('loading-row');
        if (isLoading) {
            if (!existing) {
                const row = document.createElement('div');
                row.className = 'message-row assistant';
                row.id = 'loading-row';
                row.innerHTML = '<div class="loading-bubble">HolmesGPT is investigating...</div>';
                messagesEl.appendChild(row);
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        } else if (existing) {
            existing.remove();
        }
    }

    function renderHistory(history) {
        messagesEl.innerHTML = '';
        const visible = history.filter((message) => message.role === 'user' || message.role === 'assistant');

        if (visible.length === 0) {
            messagesEl.innerHTML = '<div class="empty-state" id="empty-state">Start a conversation by asking about fired alerts or cluster health.</div>';
            return;
        }

        visible.forEach((message) => appendMessage(message.role, message.content));
    }

    async function sendMessage() {
        const ask = inputEl.value.trim();
        if (!ask || loading) {
            return;
        }

        clearError();
        appendMessage('user', ask);
        inputEl.value = '';
        setLoading(true);

        try {
            const response = await fetch(@json(route('holmes-chat.send')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    ask,
                    conversationHistory,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || 'Failed to get a response from HolmesGPT.');
            }

            conversationHistory = data.conversationHistory || [];
            renderHistory(conversationHistory);
        } catch (error) {
            showError(error.message || 'Failed to get a response from HolmesGPT. Check that it is configured and reachable.');
        } finally {
            setLoading(false);
        }
    }

    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
</script>
@endif
</body>
</html>
