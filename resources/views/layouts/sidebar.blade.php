<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="appSidebarLabel">{{ config('app.name', 'Laravel') }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="sidebar-inner">
        <div class="sidebar-topbar">
            <a class="sidebar-brand"
                href="{{ request()->is('super-admin/*') ? route('super-admin.dashboard') : url('/') }}">
                <img class="sidebar-logo" src="{{ asset('images/Wappzo-Ecommerce-Logo.svg') }}" alt="Wappzo">
            </a>
            <button class="sidebar-toggle d-none d-lg-inline-flex" type="button" data-sidebar-toggle
                aria-expanded="true" aria-controls="appSidebar" aria-label="Collapse sidebar">
                <i class="arrow-left fa-solid fa-chevron-left" aria-hidden="true"></i>
                <i class="arrow-right fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="Main navigation">
            <span class="sidebar-label">Menu</span>
            <a class="sidebar-link {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}"
                href="{{ request()->is('super-admin/*') ? route('super-admin.dashboard') : url('/') }}"
                data-tooltip="Dashboard"><i class="fa-solid fa-house" aria-hidden="true"></i><span>Dashboard</span></a>
            <a class="sidebar-link {{ request()->routeIs('super-admin.admin.*') ? 'active' : '' }}"
                href="{{ route('super-admin.admin.index') }}" data-tooltip="Users"><i class="fa-solid fa-users"
                    aria-hidden="true"></i><span>Users</span></a>
            <a class="sidebar-link {{ request()->routeIs('super-admin.package.*') ? 'active' : '' }}"
                href="{{ route('super-admin.package.index') }}" data-tooltip="Packages"><i class="fa-solid fa-box-open"
                    aria-hidden="true"></i><span>Packages</span></a>
            <a class="sidebar-link {{ request()->routeIs('super-admin.audit-logs.*') ? 'active' : '' }}"
                href="{{ route('super-admin.audit-logs.index') }}" data-tooltip="Audit logs"><i
                    class="fa-solid fa-clipboard-list" aria-hidden="true"></i><span>Audit logs</span></a>
        </nav>
    </div>
</aside>
