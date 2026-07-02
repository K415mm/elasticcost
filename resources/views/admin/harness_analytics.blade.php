<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>phpkaiharness Diagnostics & Analytics</title>
    <!-- Modern Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(22, 28, 45, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            
            --primary: #4f46e5;
            --primary-glow: rgba(79, 70, 229, 0.4);
            --secondary: #06b6d4;
            --secondary-glow: rgba(6, 182, 212, 0.4);
            --success: #10b981;
            --accent: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 2.5rem;
            background-image: 
                radial-gradient(at 10% 20%, rgba(79, 70, 229, 0.15) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(6, 182, 212, 0.15) 0px, transparent 50%);
            background-attachment: fixed;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header Layout */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
        }

        .logo-section h1 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a5b4fc, #818cf8, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logo-section p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-top: 0.3rem;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        /* Grid Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(12px);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .stat-card.blue::before { background: linear-gradient(90deg, var(--primary), #818cf8); }
        .stat-card.cyan::before { background: linear-gradient(90deg, var(--secondary), #22d3ee); }
        .stat-card.emerald::before { background: linear-gradient(90deg, var(--success), #34d399); }
        .stat-card.amber::before { background: linear-gradient(90deg, var(--accent), #fbbf24); }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-val {
            font-size: 2.2rem;
            font-weight: 700;
            margin-top: 0.5rem;
            color: #ffffff;
        }

        .stat-desc {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-top: 0.4rem;
        }

        /* Dual Column Content */
        .dashboard-body {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .dashboard-body {
                grid-template-columns: 1fr;
            }
        }

        /* Section Layouts */
        .section-box {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2rem;
            backdrop-filter: blur(12px);
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.15);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-left: 4px solid var(--secondary);
            padding-left: 0.75rem;
        }

        /* Sessions Table styling */
        .sessions-table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.92rem;
            vertical-align: middle;
        }

        tr.session-row {
            cursor: pointer;
            transition: background 0.2s ease;
        }

        tr.session-row:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        /* Badge Pills */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .badge.fast-path { background: rgba(245, 158, 11, 0.15); color: #facc15; }
        .badge.action { background: rgba(79, 70, 229, 0.15); color: #a5b4fc; }
        .badge.chat { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }

        .time-text {
            font-weight: 600;
            color: #a5f3fc;
        }

        /* Analytics Benefits List */
        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1.2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .benefit-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .benefit-icon {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            padding: 0.4rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .benefit-content h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffffff;
        }

        .benefit-content p {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.2rem;
            line-height: 1.35;
        }

        /* Side-Drawer Details Panel */
        .drawer {
            position: fixed;
            top: 0;
            right: -600px;
            width: 600px;
            height: 100vh;
            background: #0d1326;
            border-left: 1px solid var(--border-color);
            box-shadow: -10px 0 40px rgba(0,0,0,0.5);
            z-index: 1000;
            transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
        }

        .drawer.open {
            right: 0;
        }

        .drawer-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(22, 28, 45, 0.4);
        }

        .drawer-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #ffffff;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close-btn:hover {
            color: #ffffff;
        }

        .drawer-content {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
        }

        .drawer-section {
            margin-bottom: 2rem;
        }

        .drawer-section-title {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .dialog-bubble {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem;
            font-size: 0.95rem;
            line-height: 1.45;
            white-space: pre-wrap;
        }

        .dialog-bubble.prompt {
            border-left: 4px solid var(--primary);
        }

        .dialog-bubble.response {
            border-left: 4px solid var(--success);
        }

        /* Detail Call Card styling */
        .step-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }

        .step-card-header {
            padding: 0.75rem 1.25rem;
            background: rgba(255, 255, 255, 0.03);
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }

        .step-card-header:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .step-title {
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .step-title.llm_call { color: #a5b4fc; }
        .step-title.tool_call { color: #34d399; }

        .step-card-content {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: none;
            background: #090e1c;
        }

        pre {
            background: rgba(0, 0, 0, 0.3);
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-x: auto;
            color: #818cf8;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
        }
        
        /* Pagination */
        .pagination-container {
            margin-top: 1.5rem;
            display: flex;
            justify-content: center;
        }
        
        .pagination-container nav {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <header>
        <div class="logo-section">
            <h1>phpkaiharness</h1>
            <p>Framework Diagnostics & Analytics Substrate</p>
        </div>
        <a href="{{ route('ai-chat.index') }}" class="back-btn">← Back to Chat</a>
    </header>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-label">Total Agent runs</div>
            <div class="stat-val">{{ $totalSessions }}</div>
            <div class="stat-desc">Unique user sessions processed</div>
        </div>
        <div class="stat-card cyan">
            <div class="stat-label">Avg Session Latency</div>
            <div class="stat-val">{{ number_format($avgDuration / 1000, 2) }}s</div>
            <div class="stat-desc">Round-trip execution latency</div>
        </div>
        <div class="stat-card emerald">
            <div class="stat-label">Total Tokens Tracked</div>
            <div class="stat-val">{{ number_format($totalTokens) }}</div>
            <div class="stat-desc">Prompt & eval token metrics</div>
        </div>
        <div class="stat-card amber">
            <div class="stat-label">Saved LLM Runs</div>
            <div class="stat-val">{{ $totalSavedModelRuns }}</div>
            <div class="stat-desc">Calculated via fast-path bypasses</div>
        </div>
    </div>

    <!-- Body columns -->
    <div class="dashboard-body">
        
        <!-- Left: Sessions Table -->
        <div class="section-box">
            <h2 class="section-title">Execution Session Log</h2>
            
            <div class="sessions-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Session UUID</th>
                            <th>Prompt</th>
                            <th>Routing Method</th>
                            <th>Iterations</th>
                            <th>Duration</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            <tr class="session-row" onclick="openDetails('{{ $session->id }}')">
                                <td style="font-family: monospace; font-size: 0.8rem; color: var(--text-secondary);">
                                    {{ substr($session->id, 0, 8) }}...{{ substr($session->id, -8) }}
                                </td>
                                <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500;">
                                    {{ $session->prompt }}
                                </td>
                                <td>
                                    @if(str_contains($session->method, 'fast-path'))
                                        <span class="badge fast-path">Fast-path</span>
                                    @elseif(str_contains($session->method, 'action'))
                                        <span class="badge action">Executor loop</span>
                                    @else
                                        <span class="badge chat">Simple chat</span>
                                    @endif
                                </td>
                                <td style="text-align: center; font-weight: 600;">
                                    {{ $session->iterations }}
                                </td>
                                <td class="time-text">
                                    {{ number_format($session->total_duration_ms / 1000, 2) }}s
                                </td>
                                <td style="color: var(--text-secondary); font-size: 0.85rem;">
                                    {{ $session->created_at->format('Y-m-d H:i:s') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 3rem;">
                                    No harness logs registered yet. Execute the agent through the chat interface.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                {{ $sessions->links() }}
            </div>
        </div>

        <!-- Right: Benefits/Analytics Summary -->
        <div class="section-box">
            <h2 class="section-title">Harness Analytics</h2>
            
            <div style="margin-top: 1rem;">
                <div class="benefit-item">
                    <div class="benefit-icon">🚀</div>
                    <div class="benefit-content">
                        <h4>Zero-Latency Routing</h4>
                        <p>Saved <strong>{{ $totalSavedModelRuns }}</strong> intent classifier model runs using regex keyword fast-paths.</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon">🛠️</div>
                    <div class="benefit-content">
                        <h4>Parallel Tool Calling</h4>
                        <p>Tracked <strong>{{ $totalToolRuns }}</strong> tool execution calls securely inside the WSL virtual environment.</p>
                    </div>
                </div>

                <div class="benefit-item">
                    <div class="benefit-icon">🔑</div>
                    <div class="benefit-content">
                        <h4>Decoupled Infrastructure</h4>
                        <p>All tool calls runs separately without bloating Laravel dependencies, making code maintenance and updates clean.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Details Side-Drawer Panel -->
<div class="overlay" id="overlay" onclick="closeDetails()"></div>
<div class="drawer" id="drawer">
    <div class="drawer-header">
        <h3>Session Execution Timeline</h3>
        <button class="close-btn" onclick="closeDetails()">&times;</button>
    </div>
    
    <div class="drawer-content" id="drawer-content">
        <!-- Appended dynamically via JS -->
    </div>
</div>

<!-- Diagnostics inspect script -->
<script>
    function openDetails(sessionId) {
        document.getElementById('overlay').style.display = 'block';
        document.getElementById('drawer').classList.add('open');
        
        const contentContainer = document.getElementById('drawer-content');
        contentContainer.innerHTML = '<div style="text-align:center; padding: 3rem; color: var(--text-secondary);">Loading logs execution detail...</div>';

        fetch(`/admin/harness-analytics/${sessionId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    contentContainer.innerHTML = '<div style="color:#ef4444; padding: 2rem;">Failed to fetch detail log data.</div>';
                    return;
                }

                const s = data.session;
                let html = `
                    <div class="drawer-section">
                        <div class="drawer-section-title">User Prompt</div>
                        <div class="dialog-bubble prompt">${escapeHtml(s.prompt)}</div>
                    </div>
                `;

                if (s.response) {
                    html += `
                        <div class="drawer-section">
                            <div class="drawer-section-title">Agent Response</div>
                            <div class="dialog-bubble response">${escapeHtml(s.response)}</div>
                        </div>
                    `;
                }

                html += `<div class="drawer-section"><div class="drawer-section-title">Step-by-Step Executions Timeline</div>`;

                if (!s.details || s.details.length === 0) {
                    html += '<div style="color: var(--text-secondary); font-size: 0.9rem;">No granular LLM or tool calls occurred.</div>';
                } else {
                    s.details.forEach((step, index) => {
                        const stepClass = step.type === 'llm_call' ? 'llm_call' : 'tool_call';
                        const label = step.type === 'llm_call' ? `LLM Call: ${step.name}` : `Tool Execution: ${step.name}`;
                        const timing = `${(step.duration_ms / 1000).toFixed(2)}s`;
                        
                        let payloadStr = '';
                        let responseStr = '';
                        try {
                            payloadStr = typeof step.payload === 'string' ? step.payload : JSON.stringify(step.payload, null, 2);
                            responseStr = typeof step.response === 'string' ? step.response : JSON.stringify(step.response, null, 2);
                        } catch(e) {}

                        html += `
                            <div class="step-card">
                                <div class="step-card-header" onclick="toggleCardContent(this)">
                                    <div class="step-title ${stepClass}">
                                        <span>${step.type === 'llm_call' ? '🔮' : '⚡'}</span>
                                        <span>${escapeHtml(label)}</span>
                                    </div>
                                    <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                        ${step.type === 'llm_call' ? `<span style="margin-right:0.75rem; color:#818cf8;">${step.tokens_prompt + step.tokens_completion} tokens</span>` : ''}
                                        <span class="time-text">${timing}</span>
                                    </div>
                                </div>
                                <div class="step-card-content">
                                    <div style="margin-bottom:1rem;">
                                        <div class="drawer-section-title" style="font-size:0.75rem; color:#818cf8;">Input / Parameters</div>
                                        <pre><code>${escapeHtml(payloadStr)}</code></pre>
                                    </div>
                                    <div>
                                        <div class="drawer-section-title" style="font-size:0.75rem; color:#34d399;">Output / Response</div>
                                        <pre style="color:#34d399;"><code style="color:#34d399;">${escapeHtml(responseStr)}</code></pre>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }

                html += `</div>`;
                contentContainer.innerHTML = html;
            });
    }

    function closeDetails() {
        document.getElementById('overlay').style.display = 'none';
        document.getElementById('drawer').classList.remove('open');
    }

    function toggleCardContent(headerElement) {
        const content = headerElement.nextElementSibling;
        if (content.style.display === 'block') {
            content.style.display = 'none';
        } else {
            content.style.display = 'block';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .toString()
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
</script>
</body>
</html>
