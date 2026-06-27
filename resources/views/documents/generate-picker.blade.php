<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate · {{ $template->title }}</title>
    <style>
        body { font-family: system-ui, 'Segoe UI', Roboto, sans-serif; max-width: 560px; margin: 3rem auto; padding: 0 1.25rem; color: #111; }
        h1 { font-size: 1.25rem; margin-bottom: .4rem; }
        .muted { color: #666; font-size: .9rem; }
        .field { margin: 1.5rem 0; }
        label { display: block; font-size: .85rem; color: #555; margin-bottom: .4rem; }
        select { width: 100%; padding: .65rem; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; background: #fff; }
        button { background: #4f46e5; color: #fff; border: 0; border-radius: 8px; padding: .65rem 1.3rem; font: 600 15px inherit; cursor: pointer; }
        button:hover { background: #4338ca; }
    </style>
</head>
<body>
    <h1>Generate “{{ $template->title }}”</h1>
    <p class="muted">Pick a case to fill the document's merge fields from. Unmatched fields print as blanks to complete by hand.</p>

    <form method="get" action="{{ url('/templates/'.$template->uuid.'/generate') }}" target="_blank">
        <div class="field">
            <label for="case">Case</label>
            <select name="case" id="case">
                <option value="">— No case (all fields blank) —</option>
                @foreach ($cases as $c)
                    <option value="{{ $c->uuid }}">{{ $c->case_number ? $c->case_number.' — ' : '' }}{{ $c->title }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit">Generate document</button>
    </form>
</body>
</html>
