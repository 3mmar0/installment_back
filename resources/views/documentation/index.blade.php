<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installment Manager API Documentation</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f5f7fb;
            --card: #ffffff;
            --primary: #4c6ef5;
            --primary-dark: #364fc7;
            --text: #1f2933;
            --muted: #52606d;
            --border: #e4e7eb;
            --pill-bg: #edf2ff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 48px 20px 80px;
        }

        header {
            text-align: center;
            margin-bottom: 48px;
        }

        header h1 {
            font-size: 2.75rem;
            font-weight: 700;
            color: var(--primary-dark);
        }

        header p {
            margin-top: 12px;
            font-size: 1.05rem;
            color: var(--muted);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--pill-bg);
            color: var(--primary-dark);
            border-radius: 999px;
            font-weight: 600;
            margin-top: 16px;
        }

        section {
            margin-bottom: 40px;
        }

        .card {
            background: var(--card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 12px 24px rgba(76, 110, 245, 0.08);
            padding: 28px;
            position: relative;
        }

        h2 {
            font-size: 1.75rem;
            margin-bottom: 16px;
            color: var(--primary-dark);
        }

        h3 {
            font-size: 1.2rem;
            margin-top: 24px;
            margin-bottom: 12px;
            color: var(--primary);
        }

        p {
            color: var(--muted);
            line-height: 1.65;
        }

        ul {
            margin: 16px 0 0 20px;
            color: var(--muted);
            line-height: 1.6;
        }

        .endpoint-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-top: 16px;
        }

        .endpoint {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px;
            background: #fff;
        }

        .endpoint span {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .endpoint span code {
            background: #f1f3fb;
            border-radius: 6px;
            padding: 2px 8px;
            font-size: 0.85rem;
            color: var(--primary-dark);
        }

        .endpoint p {
            font-size: 0.95rem;
            color: var(--muted);
        }

        code {
            font-family: 'Fira Code', 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            background: #f1f3fb;
            border-radius: 6px;
            padding: 2px 6px;
            font-size: 0.9rem;
        }

        .note {
            margin-top: 18px;
            padding: 16px;
            border-left: 3px solid var(--primary);
            background: #eef2ff;
            border-radius: 10px;
            color: var(--muted);
        }

        footer {
            text-align: center;
            color: var(--muted);
            margin-top: 48px;
            font-size: 0.9rem;
        }

        footer strong {
            color: var(--primary-dark);
        }
    </style>
</head>

<body>
    <div class="container">
        <header>
            <h1>Installment Manager API</h1>
            <p>REST interface for managing customers, installments, notifications, and subscription limits.</p>
            <div class="pill">
                <span>v2 Subscription Limits</span>
            </div>
        </header>

        <section>
            <div class="card">
                <h2>Authentication</h2>
                <p>Use Laravel Sanctum Bearer tokens. Include <code>Authorization: Bearer &lt;token&gt;</code> in
                    protected requests.</p>
                <div class="endpoint-grid">
                    <div class="endpoint">
                        <span><code>POST</code> /api/auth/register</span>
                        <p>Register a new user. Optional <code>subscription_id</code> immediately assigns a plan.</p>
                    </div>
                    <div class="endpoint">
                        <span><code>POST</code> /api/auth/login</span>
                        <p>Authenticate and get token + user profile (with userLimit snapshot).</p>
                    </div>
                    <div class="endpoint">
                        <span><code>GET</code> /api/auth/me</span>
                        <p>Retrieve authenticated profile and current limit usage.</p>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="card">
                <h2>Subscription Plans</h2>
                <p>Owners configure reusable plans that define resource limits. Public consumers can inspect active
                    plans before subscribing.</p>
                <div class="endpoint-grid">
                    <div class="endpoint">
                        <span><code>GET</code> /api/subscriptions</span>
                        <p>Public catalogue of active plans.</p>
                    </div>
                    <div class="endpoint">
                        <span><code>GET</code> /api/subscriptions/admin</span>
                        <p>Owner paginated list (active + inactive).</p>
                    </div>
                    <div class="endpoint">
                        <span><code>POST</code> /api/subscriptions</span>
                        <p>Create plan (owner). Include customers/installments/notifications JSON thresholds.</p>
                    </div>
                    <div class="endpoint">
                        <span><code>POST</code> /api/subscriptions/{id}/assign</span>
                        <p>Assign plan to user and sync <code>user_limits</code>. Optional start/end/status overrides.
                        </p>
                    </div>
                </div>
                <div class="note">
                    Plans persist configuration (limits, features, price) and feed the <code>user_limits</code> table
                    through the helper.
                </div>
            </div>
        </section>

        <section>
            <div class="card">
                <h2>User Limits</h2>
                <p>The <code>user_limits</code> table now tracks customers, installments, and notifications usage.
                    Owners may audit and override limits, while regular users can inspect their own allowances.</p>
                <h3>Owner endpoints</h3>
                <div class="endpoint-grid">
                    <div class="endpoint">
                        <span><code>GET</code> /api/limits</span>
                        <p>Paginated list of user limit profiles.</p>
                    </div>
                    <div class="endpoint">
                        <span><code>POST</code> /api/limits</span>
                        <p>Create or update a specific user limit profile manually.</p>
                    </div>
                </div>
                <h3>User endpoints</h3>
                <div class="endpoint-grid">
                    <div class="endpoint">
                        <span><code>GET</code> /api/limits/current</span>
                        <p>Detailed snapshot (limits, usage, remaining).</p>
                    </div>
                    <div class="endpoint">
                        <span><code>GET</code> /api/limits/can-create/{resource}</span>
                        <p>Check capacity for customers/installments/notifications.</p>
                    </div>
                    <div class="endpoint">
                        <span><code>POST</code> /api/limits/refresh</span>
                        <p>Recalculate usage counters from persisted data.</p>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="card">
                <h2>Core Resources</h2>
                <p>Customers, installments, notification workflows remain the same but now sit behind
                    <code>EnsureActiveSubscription</code>, which reads from user limits instead of legacy
                    plan/subscription models.</p>
                <ul>
                    <li><code>/api/customer-*</code> & <code>/api/installment-*</code> endpoints continue to be
                        available.</li>
                    <li>Usage increments/decrements are handled via <code>LimitsHelper</code> to keep counters
                        consistent.</li>
                    <li>Owners are automatically granted unlimited allowances.</li>
                </ul>
            </div>
        </section>

        <footer>
            Maintained in tandem with the <strong>UserLimit</strong> model and new subscription architecture.
        </footer>
    </div>
</body>

</html>
