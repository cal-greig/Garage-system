<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --steel: #1a1f2e; --steel-mid: #242938; --steel-light: #2e3447;
        --accent: #f59e0b; --green: #10b981; --red: #ef4444;
        --text: #e2e8f0; --text-muted: #7c8a9e; --border: rgba(255,255,255,0.07);
    }
    * { box-sizing: border-box; }
    body { font-family: 'Barlow', sans-serif; background: var(--steel); color: var(--text); min-height: 100vh; margin: 0; }

    .page-header {
        background: var(--steel-mid); border-bottom: 1px solid var(--border);
        padding: 20px 28px;
    }
    .page-header h1 {
        font-family: 'Barlow Condensed', sans-serif; font-size: 1.8rem;
        font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; color: #fff; margin: 0;
    }

    .form-wrap { max-width: 640px; margin: 36px auto; padding: 0 24px; }

    .form-card {
        background: var(--steel-mid); border: 1px solid var(--border);
        border-radius: 10px; padding: 28px;
    }

    .field-group { margin-bottom: 20px; }

    .field-label {
        display: block; font-size: 0.75rem; font-weight: 600;
        letter-spacing: 0.1em; text-transform: uppercase;
        color: var(--text-muted); margin-bottom: 7px;
    }

    .field-input, .field-select, .field-textarea {
        width: 100%; background: var(--steel-light);
        border: 1px solid var(--border); border-radius: 7px;
        color: var(--text); font-family: 'Barlow', sans-serif;
        font-size: 0.95rem; padding: 11px 14px;
        transition: border-color 0.15s, box-shadow 0.15s;
        appearance: none; -webkit-appearance: none;
    }

    .field-input:focus, .field-select:focus, .field-textarea:focus {
        outline: none; border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(245,158,11,0.12);
    }

    .field-input::placeholder, .field-textarea::placeholder { color: var(--text-muted); }

    .field-textarea { resize: vertical; min-height: 90px; }

    .field-select {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%237c8a9e' d='M6 8L0 0h12z'/%3E%3C/svg%3E");
        background-repeat: no-repeat; background-position: right 14px center;
        padding-right: 36px; cursor: pointer;
    }

    .field-select option { background: var(--steel-mid); }

    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .submit-btn {
        width: 100%; padding: 13px; margin-top: 8px;
        background: var(--accent); color: #000; border: none;
        border-radius: 8px; font-family: 'Barlow', sans-serif;
        font-size: 0.95rem; font-weight: 700; letter-spacing: 0.02em;
        cursor: pointer; transition: background 0.15s;
    }
    .submit-btn:hover { background: #d97706; }

    .back-link {
        display: inline-flex; align-items: center; gap: 6px;
        margin-top: 16px; color: var(--text-muted); font-size: 0.88rem;
        text-decoration: none; transition: color 0.15s;
    }
    .back-link:hover { color: var(--accent); }

    .divider { border: none; border-top: 1px solid var(--border); margin: 24px 0; }

    @media (max-width: 600px) { .form-grid-2 { grid-template-columns: 1fr; } }
</style>
