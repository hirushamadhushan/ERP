@extends('layouts.app')

@section('title', 'Dashboard')
@section('subtitle', 'Real-time system overview')

@section('content')

    <!-- KPI Cards Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

        <!-- Total Users -->
        <div class="bg-white p-5 rounded-2xl border border-purple-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-500">Total Users</span>
                <h3 class="text-3xl font-bold text-slate-900 mt-1">{{ $totalUsers }}</h3>
                <span class="text-[11px] font-semibold text-purple-600 mt-1 inline-block">In Database</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-purple-50 text-purple-600 shrink-0">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Active Users -->
        <div class="bg-white p-5 rounded-2xl border border-purple-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-500">Active Users</span>
                <h3 class="text-3xl font-bold text-slate-900 mt-1">{{ $activeUsers }}</h3>
                <span class="text-[11px] font-semibold text-emerald-600 mt-1 inline-block">Online Now</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-emerald-50 text-emerald-600 shrink-0">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Admins -->
        <div class="bg-white p-5 rounded-2xl border border-purple-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-500">Administrators</span>
                <h3 class="text-3xl font-bold text-slate-900 mt-1">{{ $adminUsers }}</h3>
                <span class="text-[11px] font-semibold text-indigo-600 mt-1 inline-block">Full Access</span>
            </div>
            <div class="p-3.5 rounded-2xl bg-indigo-50 text-indigo-600 shrink-0">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
        </div>

        <!-- DB Status -->
        <div class="bg-white p-5 rounded-2xl border border-purple-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-medium text-slate-500">Database</span>
                <h3 class="text-lg font-bold text-slate-900 mt-1">MySQL</h3>
                <span class="text-[11px] font-semibold text-emerald-600 mt-1 inline-block flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                    Connected
                </span>
            </div>
            <div class="p-3.5 rounded-2xl bg-amber-50 text-amber-600 shrink-0">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
            </div>
        </div>

    </div>

    <!-- Recently Added Users -->
    <div class="bg-white rounded-3xl border border-purple-100 shadow-sm overflow-hidden">

        <div class="px-6 py-4 border-b border-purple-100 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Recently Added Users</h2>
                <p class="text-xs text-slate-400 mt-0.5">Last 5 users registered in the system</p>
            </div>
            <a href="{{ route('users.index') }}" class="text-xs font-semibold text-purple-600 hover:text-purple-700 transition-colors">
                View All →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-purple-50/50 border-b border-purple-100 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                        <th class="py-3 px-6">Name</th>
                        <th class="py-3 px-6">Email</th>
                        <th class="py-3 px-6">Role</th>
                        <th class="py-3 px-6">Status</th>
                        <th class="py-3 px-6">Registered</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-purple-50 text-xs font-medium text-slate-700">
                    @forelse($recentUsers as $user)
                        <tr class="hover:bg-purple-50/30 transition-colors">
                            <td class="py-3.5 px-6 flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full text-white flex items-center justify-center text-xs font-bold shrink-0"
                                     style="background: linear-gradient(135deg, #7c3aed, #4f46e5);">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <span class="font-semibold text-slate-900">{{ $user->name }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-500">{{ $user->email }}</td>
                            <td class="py-3.5 px-6">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold bg-purple-100 text-purple-800 border border-purple-200">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                @if($user->status === 'active')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @elseif($user->status === 'offline')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Offline
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Suspended
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-slate-400">{{ $user->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 px-6 text-center text-slate-400 text-xs">No users found in the database.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

@endsection
