<!DOCTYPE html>
{{-- D-060: shown only when a restore and its automatic rollback both failed. Standalone on purpose: the database may be unusable. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restore needs attention</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; }
        main { max-width: 40rem; margin: 3rem auto; padding: 1.5rem; background: #fff; border: 1px solid #fecaca; border-radius: 1rem; }
        h1 { color: #b91c1c; font-size: 1.4rem; margin-top: 0; }
        code { background: #f1f5f9; padding: .1rem .3rem; border-radius: .25rem; }
    </style>
</head>
<body>
    <main role="alert">
        <h1>The restore did not finish</h1>
        <p>{{ $message }}</p>
        <p>The site is in maintenance mode so that nobody works on incomplete records. Ask the system administrator to finish it from SSH in the site folder.</p>
    </main>
</body>
</html>
