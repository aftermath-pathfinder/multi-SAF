<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>store-forward playground</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f1115; --panel: #171a21; --border: #2a2f3a; --text: #e6e8eb;
            --muted: #8b93a1; --accent: #5b8cff;
            --pending: #8b93a1; --processing: #5b8cff; --sent: #3ecf8e;
            --failed: #e5a03e; --dead: #e5484d;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text); margin: 0;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 60px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: var(--muted); margin: 0 0 28px; font-size: 13px; }
        .panel {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 10px; padding: 18px 20px; margin-bottom: 20px;
        }
        .panel h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 0 0 14px; }
        form.publish { display: grid; grid-template-columns: 1fr 2fr auto; gap: 10px; align-items: start; }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        select, textarea, input[type=text] {
            width: 100%; background: #0d0f13; border: 1px solid var(--border);
            color: var(--text); border-radius: 6px; padding: 8px 10px; font-family: inherit; font-size: 13px;
        }
        textarea { min-height: 40px; resize: vertical; font-family: ui-monospace, monospace; }
        button {
            background: var(--accent); color: #fff; border: none; border-radius: 6px;
            padding: 9px 16px; font-size: 13px; cursor: pointer; font-weight: 600;
        }
        button.secondary { background: transparent; border: 1px solid var(--border); color: var(--text); }
        .actions { display: flex; gap: 10px; }
        .status-msg {
            background: rgba(91,140,255,.12); border: 1px solid rgba(91,140,255,.35);
            color: var(--text); padding: 10px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;
        }
        .errors { color: var(--dead); font-size: 12px; margin-top: 6px; }
        .counts { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .count-tile {
            background: var(--panel); border: 1px solid var(--border); border-radius: 8px;
            padding: 10px 16px; min-width: 90px;
        }
        .count-tile .n { font-size: 22px; font-weight: 700; }
        .count-tile .l { font-size: 11px; color: var(--muted); text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
        th { color: var(--muted); font-weight: 600; font-size: 11px; text-transform: uppercase; }
        td.mono { font-family: ui-monospace, monospace; font-size: 12px; }
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px;
            font-weight: 700; text-transform: uppercase; color: #06070a;
        }
        .badge.pending { background: var(--pending); }
        .badge.processing { background: var(--processing); color: #fff; }
        .badge.sent { background: var(--sent); }
        .badge.dead { background: var(--dead); color: #fff; }
        .err { color: var(--dead); max-width: 260px; overflow-wrap: anywhere; }
        .empty { color: var(--muted); padding: 20px 0; text-align: center; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>store-forward playground</h1>
    <p class="sub">
        Publish a message, then watch it move through the outbox:
        <strong>pending → processing → sent</strong> (or, if the transport
        fails, <strong>pending</strong> again with a growing attempt count,
        eventually <strong>dead</strong>). See the repo root's
        <code>README.md</code> for the store-and-forward design, and this
        directory's <code>CLAUDE.md</code> for a tour of this app.
    </p>

    @if (session('status'))
        <div class="status-msg">{{ session('status') }}</div>
    @endif

    <div class="panel">
        <h2>Publish a message</h2>
        <form class="publish" method="POST" action="{{ route('publish') }}">
            @csrf
            <div>
                <label for="channel">Channel</label>
                <select name="channel" id="channel">
                    @foreach ($channels as $channel)
                        <option value="{{ $channel }}">{{ $channel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="payload">Payload (JSON)</label>
                <textarea name="payload" id="payload">{"order_id": 1, "total": 42.50}</textarea>
            </div>
            <div style="padding-top: 22px;">
                <button type="submit">Publish</button>
            </div>
        </form>
        @error('payload')
            <div class="errors">{{ $message }}</div>
        @enderror
    </div>

    <div class="actions" style="margin-bottom: 20px;">
        <form method="POST" action="{{ route('process') }}">
            @csrf
            <button type="submit">Process pending now</button>
        </form>
        <form method="POST" action="{{ route('retry-dead') }}">
            @csrf
            <button type="submit" class="secondary">Retry dead messages</button>
        </form>
    </div>

    <div class="counts" id="counts"></div>

    <div class="panel">
        <h2>Outbox (latest 50)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Channel</th><th>Status</th><th>Attempts</th>
                    <th>Last error</th><th>Created</th><th>Sent</th>
                </tr>
            </thead>
            <tbody id="messages-body">
                <tr><td colspan="7" class="empty">Loading…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    const statusClass = (s) => ['pending', 'processing', 'sent', 'dead'].includes(s) ? s : 'pending';

    async function refresh() {
        let data;
        try {
            const res = await fetch('{{ route('messages') }}', { headers: { Accept: 'application/json' } });
            data = await res.json();
        } catch (e) {
            return; // transient network hiccup — next poll will retry
        }

        const counts = data.counts || {};
        const order = ['pending', 'processing', 'sent', 'dead'];
        document.getElementById('counts').innerHTML = order.map(status => `
            <div class="count-tile">
                <div class="n">${counts[status] ?? 0}</div>
                <div class="l">${status}</div>
            </div>
        `).join('');

        const rows = data.messages || [];
        const body = document.getElementById('messages-body');
        body.innerHTML = rows.length === 0
            ? '<tr><td colspan="7" class="empty">No messages yet — publish one above.</td></tr>'
            : rows.map(m => `
                <tr>
                    <td class="mono">${m.id}</td>
                    <td class="mono">${escapeHtml(m.channel)}</td>
                    <td><span class="badge ${statusClass(m.status)}">${escapeHtml(m.status)}</span></td>
                    <td>${m.attempts}</td>
                    <td class="err">${m.last_error ? escapeHtml(m.last_error) : ''}</td>
                    <td class="mono">${escapeHtml(m.created_at)}</td>
                    <td class="mono">${m.sent_at ? escapeHtml(m.sent_at) : ''}</td>
                </tr>
            `).join('');
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }

    refresh();
    setInterval(refresh, 1500);
</script>
</body>
</html>
