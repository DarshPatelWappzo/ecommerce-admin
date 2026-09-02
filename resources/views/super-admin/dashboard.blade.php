@extends('layouts.app')

@section('title', 'Super Admin Dashboard')

@section('content')
    <div class="container-fluid">
        <p class="text-primary fw-semibold mb-1">Super Admin</p>
        <h1 class="page-title mb-1">Dashboard</h1>
        <p class="text-secondary mb-4">Welcome back, {{ auth()->user()->name }}.</p>
        <div class="dashboard-card">
            <h2 class="section-title">Administration overview</h2>
            <p class="text-secondary mb-0">Your super admin dashboard is ready for the next modules.</p>
        </div>
    </div>
@endsection
