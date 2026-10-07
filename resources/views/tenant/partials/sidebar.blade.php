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
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'taxes.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.taxes.*') ? 'active' : '' }}"
                    href="{{ route('tenant.taxes.index') }}" data-tooltip="Taxes">
                    <i class="fa-solid fa-percent" aria-hidden="true"></i>
                    <span>Taxes</span>
                </a>
            @endif
            <a class="sidebar-link {{ request()->routeIs('tenant.categories.*') ? 'active' : '' }}"
                href="{{ route('tenant.categories.index') }}" data-tooltip="Categories">
                <i class="fa-solid fa-list" aria-hidden="true"></i>
                <span>Categories</span>
            </a>
            <a class="sidebar-link {{ request()->routeIs('tenant.products.*') ? 'active' : '' }}"
                href="{{ route('tenant.products.index') }}" data-tooltip="Products">
                <i class="fa-solid fa-box" aria-hidden="true"></i>
                <span>Products</span>
            </a>

            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'customers.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.customers.*') ? 'active' : '' }}"
                    href="{{ route('tenant.customers.index') }}" data-tooltip="Customers">
                    <i class="fa-solid fa-users" aria-hidden="true"></i>
                    <span>Customers</span>
                </a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'coupons.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.coupons.*') ? 'active' : '' }}"
                    href="{{ route('tenant.coupons.index') }}" data-tooltip="Coupons"><i class="fa-solid fa-ticket"
                        aria-hidden="true"></i><span>Coupons</span></a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'orders.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.orders.*') ? 'active' : '' }}"
                    href="{{ route('tenant.orders.index') }}" data-tooltip="Orders"><i class="fa-solid fa-receipt"
                        aria-hidden="true"></i><span>Orders</span></a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'returns.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.returns.*') ? 'active' : '' }}"
                    href="{{ route('tenant.returns.index') }}" data-tooltip="Returns">
                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Returns</span>
                </a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(),
                        'replacements.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.replacements.*') ? 'active' : '' }}"
                    href="{{ route('tenant.replacements.index') }}" data-tooltip="Replacements">
                    <i class="fa-solid fa-repeat" aria-hidden="true"></i><span>Replacements</span>
                </a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'payments.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.payments.*') ? 'active' : '' }}"
                    href="{{ route('tenant.payments.index') }}" data-tooltip="Payments">
                    <i class="fa-solid fa-credit-card" aria-hidden="true"></i><span>Payments</span>
                </a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(), 'invoices.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.invoices.*') ? 'active' : '' }}"
                    href="{{ route('tenant.invoices.index') }}" data-tooltip="Invoices">
                    <i class="fa-solid fa-file-invoice" aria-hidden="true"></i><span>Invoices</span>
                </a>
            @endif
            @if (auth('tenant')->user()?->status === 'active' &&
                    app(\App\Repositories\TenantRoleRepository::class)->userHasPermission(auth('tenant')->user(),
                        'audit_logs.view'))
                <a class="sidebar-link {{ request()->routeIs('tenant.audit-logs.*') ? 'active' : '' }}"
                    href="{{ route('tenant.audit-logs.index') }}" data-tooltip="Audit Logs">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Audit Logs</span>
                </a>
            @endif
        </nav>
    </div>
</aside>
