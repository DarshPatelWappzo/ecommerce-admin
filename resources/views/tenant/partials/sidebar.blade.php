<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="appSidebarLabel">Tenant Administration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="sidebar-inner">
        <div class="sidebar-topbar">
            <a class="sidebar-brand" href="{{ route('tenant.dashboard') }}">
                <img class="sidebar-logo" src="{{ asset('images/Wappzo-Ecommerce-Logo.svg') }}" alt="Wappzo">
            </a>
            <button class="sidebar-toggle d-none d-lg-inline-flex" type="button" data-sidebar-toggle
                aria-expanded="true" aria-controls="appSidebar" aria-label="Collapse sidebar">
                <i class="arrow-left fa-solid fa-chevron-left" aria-hidden="true"></i>
                <i class="arrow-right fa-solid fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>
        <nav class="sidebar-nav" aria-label="Tenant navigation">
            <span class="sidebar-label">Menu</span>
            <a class="sidebar-link {{ request()->routeIs('tenant.dashboard') ? 'active' : '' }}"
                href="{{ route('tenant.dashboard') }}" data-tooltip="Dashboard">
                <i class="fa-solid fa-house" aria-hidden="true"></i>
                <span>Dashboard</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('tenant.roles.*') ? 'active' : '' }}"
                href="{{ route('tenant.roles.index') }}" data-tooltip="Roles">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                <span>Roles &amp; permissions</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('tenant.users.*') ? 'active' : '' }}"
                href="{{ route('tenant.users.index') }}" data-tooltip="Users">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Users</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('tenant.categories.*') ? 'active' : '' }}"
                href="{{ route('tenant.categories.index') }}" data-tooltip="Categories">
                <i class="fa-solid fa-list" aria-hidden="true"></i>
                <span>Categories</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('tenant.products.*') ? 'active' : '' }}"
                {{-- href="{{ route('tenant.products.index') }}"  --}}
                href="#" data-tooltip="Products">
                <i class="fa-solid fa-box" aria-hidden="true"></i>
                <span>Products</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('tenant.customers.*') ? 'active' : '' }}"
                {{-- href="{{ route('tenant.products.index') }}"  --}}
                href="#" data-tooltip="Customers">
                <i class="fa-solid fa-users" aria-hidden="true"></i>
                <span>Customers</span>
            </a>
        </nav>
    </div>
</aside>
