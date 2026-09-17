<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="{{ asset('css/select2-overrides.css') }}" rel="stylesheet">
    @stack('styles')
</head>

<body>
    <div class="app-shell">
        @include($sidebarView ?? 'layouts.sidebar')
        <div class="app-main">
            @include($headerView ?? 'layouts.header')
            <main class="app-content">@yield('content')</main>
            @include($footerView ?? 'layouts.footer')
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const storageKey = 'sidebar-collapsed';
            const toggle = document.querySelector('[data-sidebar-toggle]');

            if (localStorage.getItem(storageKey) === 'true') {
                document.body.classList.add('sidebar-collapsed');
            }

            const initialCollapsed = document.body.classList.contains('sidebar-collapsed');
            toggle?.setAttribute('aria-expanded', String(!initialCollapsed));
            toggle?.setAttribute('aria-label', initialCollapsed ? 'Expand sidebar' : 'Collapse sidebar');

            toggle?.addEventListener('click', () => {
                const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem(storageKey, isCollapsed);
                toggle.setAttribute('aria-expanded', String(!isCollapsed));
                toggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/select2.js') }}"></script>
    @stack('scripts')
</body>

</html>
