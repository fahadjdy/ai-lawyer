<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 2cm; }
        * { box-sizing: border-box; }
        body { font-family: 'Times New Roman', Georgia, serif; color: #111; line-height: 1.65; max-width: 820px; margin: 0 auto; padding: 0 1.25rem 3rem; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; background: #fff; padding: .9rem 0 1rem; border-bottom: 1px solid #eee; margin-bottom: 2rem; }
        .toolbar button { background: #4f46e5; color: #fff; border: 0; border-radius: 6px; padding: .55rem 1.1rem; font: 600 14px system-ui, sans-serif; cursor: pointer; }
        .toolbar button:hover { background: #4338ca; }
        .toolbar .muted { color: #666; font-size: .82rem; font-family: system-ui, sans-serif; }
        .doc :is(h1, h2, h3) { text-align: center; }
        .doc table { width: 100%; border-collapse: collapse; }
        .doc td, .doc th { border: 1px solid #999; padding: 6px 8px; }
        .blank { display: inline-block; min-width: 140px; border-bottom: 1px solid #888; }
        @media print {
            .toolbar { display: none; }
            body { max-width: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print / Save as PDF</button>
        <span class="muted">In the print dialog choose “Save as PDF”. Underlined gaps (___) are fields to complete by hand.</span>
    </div>
    <div class="doc">{!! $body !!}</div>
</body>
</html>
