<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>store-forward playground — settings</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f1115; --panel: #171a21; --border: #2a2f3a; --text: #e6e8eb;
            --muted: #8b93a1; --accent: #5b8cff;
            --success: #3ecf8e; --warning: #e5a03e; --dead: #e5484d;
        }
        * { box-sizing: border-box; }
        body {
            background: var(--bg); color: var(--text); margin: 0;
            font: 14px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .wrap { max-width: 760px; margin: 0 auto; padding: 32px 20px 60px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: var(--muted); margin: 0 0 24px; font-size: 13px; }
        a { color: var(--accent); }
        .status-msg {
            background: rgba(91,140,255,.12); border: 1px solid rgba(91,140,255,.35);
            padding: 10px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;
        }
        .warning-msg {
            background: rgba(229,160,62,.12); border: 1px solid var(--warning);
            padding: 10px 14px; border-radius: 8px; margin-bottom: 20px; font-size: 13px;
        }
        .warning-msg code { color: var(--warning); }
        .errors { color: var(--dead); font-size: 12px; margin-top: 6px; }
        .panel {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 10px; padding: 20px 22px; margin-bottom: 20px;
        }
        .panel h2 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 0 0 6px; }
        .current { font-size: 13px; color: var(--muted); margin: 0 0 18px; }
        .current code { color: var(--text); }

        .tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        .tabs input { position: absolute; opacity: 0; pointer-events: none; }
        .tabs label {
            font-size: 12.5px; font-family: ui-monospace, monospace;
            background: #0d0f13; border: 1px solid var(--border); color: var(--muted);
            border-radius: 20px; padding: 7px 14px; cursor: pointer;
        }
        .tabs .pf { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; margin-right: 6px; opacity: .7; }
        .tabs input:checked + label { background: var(--accent); border-color: var(--accent); color: #fff; }
        .tabs label.not-installed::after { content: " ⚠"; }

        .fieldset { display: none; }
        .fieldset.active { display: block; }
        .field { margin-bottom: 14px; }
        label.field-label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; }
        .env-tag { float: right; font-family: ui-monospace, monospace; font-size: 10.5px; color: var(--muted); }
        input[type=text], input[type=password] {
            width: 100%; background: #0d0f13; border: 1px solid var(--border);
            color: var(--text); border-radius: 6px; padding: 8px 10px; font-family: inherit; font-size: 13px;
        }
        .secret-wrap { position: relative; }
        .secret-wrap input { padding-right: 58px; }
        .secret-toggle {
            position: absolute; right: 5px; top: 5px; bottom: 5px; padding: 0 10px;
            font-size: 10.5px; font-weight: 600; text-transform: uppercase;
            background: #171a21; border: 1px solid var(--border); color: var(--muted); border-radius: 4px;
            cursor: pointer;
        }
        .no-fields { color: var(--muted); font-size: 13px; font-style: italic; }
        .install-hint {
            background: rgba(229,160,62,.1); border: 1px solid var(--warning);
            border-radius: 6px; padding: 10px 14px; font-size: 12.5px; margin-bottom: 16px;
        }
        .install-hint code { color: var(--warning); }

        button {
            background: var(--accent); color: #fff; border: none; border-radius: 6px;
            padding: 9px 18px; font-size: 13px; cursor: pointer; font-weight: 600;
        }
        button:disabled { opacity: .45; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Settings</h1>
    <p class="sub">
        Pick which real infrastructure the <code>demo.custom</code> channel
        uses. Selecting an installed driver writes its credentials to
        <code>.env</code> and switches it live — try it from
        <a href="{{ route('dashboard') }}">the dashboard</a> right after.
        Selecting one that isn't installed shows what to run; it won't
        switch anything or throw.
    </p>
    <p class="sub">
        <strong>Note:</strong> a driver instance is shared by every channel
        that uses it — so if you set <code>demo.custom</code> to
        <code>redis-streams</code>, its stream name here is the <em>same</em>
        config <code>demo.redis</code> reads. That's the library's actual
        behavior (one instance per driver name, cached), not a bug in this
        page — see the "Store, Then Forward" artifact's driver-registry
        section for why.
    </p>

    @if (session('status'))
        <div class="status-msg">{{ session('status') }}</div>
    @endif
    @if (session('warning'))
        <div class="warning-msg">{{ session('warning') }}</div>
    @endif
    @error('driver')
        <div class="errors">{{ $message }}</div>
    @enderror

    <div class="panel">
        <h2>demo.custom</h2>
        <p class="current">Currently using: <code>{{ $currentDriver }}</code></p>

        <form method="POST" action="{{ route('settings.update') }}" id="settingsForm">
            @csrf

            <div class="tabs">
                @foreach ($drivers as $key => $driver)
                    <input type="radio" name="driver" id="tab_{{ $key }}" value="{{ $key }}"
                        data-installed="{{ $installed[$key] ? '1' : '0' }}"
                        {{ $key === $currentDriver ? 'checked' : '' }}>
                    <label for="tab_{{ $key }}" class="{{ $installed[$key] ? '' : 'not-installed' }}">
                        <span class="pf">{{ $driver['platform'] }}</span>{{ $driver['label'] }}
                    </label>
                @endforeach
            </div>

            @foreach ($drivers as $key => $driver)
                <div class="fieldset {{ $key === $currentDriver ? 'active' : '' }}" data-fieldset="{{ $key }}">
                    @if (! $installed[$key])
                        <div class="install-hint">
                            <strong>Not installed.</strong> Run
                            <code>composer require {{ $driver['package'] }}</code>
                            in <code>playground/</code>, then reload this page —
                            saving now will leave <code>demo.custom</code> unchanged.
                        </div>
                    @endif

                    @if (count($driver['fields']) === 0)
                        <p class="no-fields">
                            {{ $key === 'log' ? 'Ships in core, needs no configuration.' : 'No credentials needed.' }}
                        </p>
                    @endif

                    @foreach ($driver['fields'] as $field)
                        <div class="field">
                            <label class="field-label" for="f_{{ $key }}_{{ $field['key'] }}">
                                {{ $field['label'] }}
                                <span class="env-tag">{{ $field['env'] }}</span>
                            </label>
                            @if (! empty($field['secret']))
                                <div class="secret-wrap">
                                    <input type="password"
                                        id="f_{{ $key }}_{{ $field['key'] }}"
                                        name="fields[{{ $field['env'] }}]"
                                        placeholder="{{ $field['placeholder'] }}"
                                        value="{{ $currentValues[$key][$field['env']] ?? '' }}"
                                        autocomplete="off">
                                    <button type="button" class="secret-toggle" data-for="f_{{ $key }}_{{ $field['key'] }}">Show</button>
                                </div>
                            @else
                                <input type="text"
                                    id="f_{{ $key }}_{{ $field['key'] }}"
                                    name="fields[{{ $field['env'] }}]"
                                    placeholder="{{ $field['placeholder'] }}"
                                    value="{{ $currentValues[$key][$field['env']] ?? '' }}"
                                    autocomplete="off">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach

            <button type="submit" id="saveBtn">Save &amp; switch demo.custom</button>
        </form>
    </div>
</div>

<script>
    const radios = document.querySelectorAll('.tabs input[type=radio]');
    const saveBtn = document.getElementById('saveBtn');

    function syncActive() {
        const checked = document.querySelector('.tabs input[type=radio]:checked');
        document.querySelectorAll('.fieldset').forEach(fs => {
            fs.classList.toggle('active', fs.dataset.fieldset === checked.value);
        });
        saveBtn.textContent = checked.dataset.installed === '1'
            ? 'Save & switch demo.custom'
            : 'Not installed — see instructions above';
    }

    radios.forEach(r => r.addEventListener('change', syncActive));
    syncActive();

    document.querySelectorAll('.secret-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.for);
            const showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            btn.textContent = showing ? 'Show' : 'Hide';
        });
    });
</script>
</body>
</html>
