<header class="app-header">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3">
        <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#appSidebar" aria-controls="appSidebar">
            <span class="visually-hidden">Toggle navigation</span>
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <span class="text-secondary small">{{ now()->format('D, d M Y') }}</span>
            <div class="dropdown">
                <button class="user-menu-button" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar"
                        aria-hidden="true">{{ strtoupper(substr($tenantUser->first_name, 0, 1)) }}</span>
                    <i class="fa-solid fa-chevron-down user-menu-chevron" aria-hidden="true"></i>
                    <span class="visually-hidden">Open account menu</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end user-dropdown p-3">
                    <p class="mb-1 fw-semibold text-break">
                        {{ trim($tenantUser->first_name . ' ' . $tenantUser->last_name) }}
                    </p>
                    <p class="mb-2 text-muted small text-break">{{ $tenantUser->email }}</p>
                    <p class="mb-3 text-muted small text-break">
                        <i class="fa-solid fa-globe me-1" aria-hidden="true"></i>{{ $tenantDomain }}
                    </p>
                    <form method="POST" action="{{ route('tenant.logout') }}">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm w-100" type="submit">
                            <i class="fa-solid fa-right-from-bracket me-1" aria-hidden="true"></i>
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
