<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{margin:0;background:#fff;color:#1e293b;font-family:Arial,sans-serif}main{padding:16px;min-width:0}</style>
</head>
<body>
<main>@yield('content')</main>
<script src="{{ asset('js/error-handling.js') }}"></script>
@stack('scripts')
<script>
document.addEventListener('click', event => {
    if (event.target.closest('[data-close-generator]')) {
        event.preventDefault();
        parent.postMessage({type:'close-serial-generator'}, location.origin);
    }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') parent.postMessage({type:'close-serial-generator'}, location.origin);
});
@if($saved ?? false)
parent.postMessage({type:'serial-generator-saved'}, location.origin);
@endif
</script>
</body>
</html>
