<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $title }} — Nexus ERP</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font:15px/1.6 system-ui,sans-serif;background:#f8f7fc;color:#334155}
main{max-width:520px;width:100%;padding:36px;background:white;border:1px solid #e9ddff;border-radius:20px;box-shadow:0 12px 36px #33245b0a}
.code{color:#9333ea;font-weight:700}h1{font-size:25px;color:#172033;margin:10px 0}p{margin:12px 0}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:24px}button,a{font:inherit;text-decoration:none;border:0;border-radius:10px;padding:10px 16px;background:#9333ea;color:white;cursor:pointer}a{background:#f1edfa;color:#6941a5}.reference{font-size:12px;color:#64748b;overflow-wrap:anywhere}
</style></head>
<body><main><div class="code">Nexus ERP · {{ $status }}</div><h1>{{ $title }}</h1><p>{{ $message }}</p>
@if($reference)<p class="reference">Error reference: {{ $reference }}</p>@endif
<div class="actions"><button type="button" onclick="history.length>1 ? history.back() : location.assign('/')">Go back</button><a href="/">Sign in</a></div>
</main></body></html>
