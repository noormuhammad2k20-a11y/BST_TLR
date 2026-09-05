@extends('cloth-store.layouts.app')
@section('title', 'Users & Roles')
@section('spaPage', 'cloth-store-users')

@section('content')
<div class="page flex justify-between items-center mb-6">
  <div>
    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Users, Roles & Permissions</h1>
    <p class="text-sm text-slate-500 mt-0.5">Manage system access, roles, and view activity logs.</p>
  </div>
</div>

<div class="page border-b border-slate-200 mb-6">
  <nav class="-mb-px flex space-x-8">
    <button onclick="switchTab('users')" class="tab-btn py-4 px-1 border-b-2 font-bold text-sm border-indigo-500 text-indigo-600" id="tab-users">System Users</button>
    <button onclick="switchTab('roles')" class="tab-btn py-4 px-1 border-b-2 font-bold text-sm border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300" id="tab-roles">Roles & Permissions</button>
    <button onclick="switchTab('logs')" class="tab-btn py-4 px-1 border-b-2 font-bold text-sm border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300" id="tab-logs">Activity Logs</button>
  </nav>
</div>

<!-- Tab: Users -->
<div id="panel-users" class="tab-panel page">
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
      <h3 class="font-bold text-slate-800">All Authorized Users</h3>
    </div>
    <table class="w-full text-sm text-left">
      <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
        <tr>
          <th class="px-5 py-3 font-bold">Name</th>
          <th class="px-5 py-3 font-bold">Email</th>
          <th class="px-5 py-3 font-bold">Assigned Roles</th>
          <th class="px-5 py-3 font-bold">Status</th>
          <th class="px-5 py-3 font-bold text-right">Last Login</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        @foreach($users as $user)
        <tr class="hover:bg-slate-50">
          <td class="px-5 py-3 font-bold text-slate-800">{{ $user->name }}</td>
          <td class="px-5 py-3 text-slate-600">{{ $user->email }}</td>
          <td class="px-5 py-3">
            @foreach($user->csRoles as $role)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800 mb-1 mr-1">{{ $role->name }}</span>
            @endforeach
            @if($user->csRoles->isEmpty())
            <span class="text-xs text-slate-400 italic">No specific roles</span>
            @endif
          </td>
          <td class="px-5 py-3">
            @if($user->is_active) <span class="badge badge-progress text-[10px]">Active</span>
            @else <span class="badge badge-cancelled text-[10px]">Inactive</span> @endif
          </td>
          <td class="px-5 py-3 text-right text-slate-500 text-xs">
            {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<!-- Tab: Roles & Permissions -->
<div id="panel-roles" class="tab-panel page hidden">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-1">
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50"><h3 class="font-bold text-slate-800">Available Roles</h3></div>
        <ul class="divide-y divide-slate-200">
          @foreach($roles as $role)
          <li class="p-4 hover:bg-slate-50">
            <div class="font-bold text-slate-800">{{ $role->name }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $role->permissions->count() }} permissions assigned</div>
          </li>
          @endforeach
        </ul>
      </div>
    </div>
    
    <div class="lg:col-span-2">
      <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
          <h3 class="font-bold text-slate-800">Permissions Matrix</h3>
        </div>
        <div class="p-5">
          <p class="text-sm text-slate-500 mb-6">The system enforces the following permission nodes across modules. Roles can be configured to grant specific access nodes.</p>
          <div class="grid grid-cols-2 gap-6">
            @foreach($permissions as $module => $perms)
            <div class="border border-slate-200 rounded-lg overflow-hidden">
              <div class="bg-slate-100 px-3 py-2 font-bold text-xs text-slate-700 uppercase tracking-widest border-b border-slate-200">{{ $module }}</div>
              <ul class="p-3 space-y-2">
                @foreach($perms as $p)
                <li class="flex items-center gap-2 text-sm text-slate-700">
                  <i class="fa-solid fa-check text-emerald-500 text-xs"></i> {{ str_replace($module . ' - ', '', $p->name) }}
                </li>
                @endforeach
              </ul>
            </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Tab: Activity Logs -->
<div id="panel-logs" class="tab-panel page hidden">
  <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50"><h3 class="font-bold text-slate-800">Recent System Activity</h3></div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm text-left">
        <thead class="bg-slate-50 text-slate-500 text-[10px] uppercase tracking-widest border-b border-slate-200">
          <tr>
            <th class="px-5 py-3 font-bold">Timestamp</th>
            <th class="px-5 py-3 font-bold">User</th>
            <th class="px-5 py-3 font-bold">Module</th>
            <th class="px-5 py-3 font-bold">Action</th>
            <th class="px-5 py-3 font-bold">Details</th>
            <th class="px-5 py-3 font-bold">IP Address</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
          @forelse($logs as $log)
          <tr class="hover:bg-slate-50">
            <td class="px-5 py-3 text-slate-500 whitespace-nowrap text-xs">{{ $log->created_at->format('d M, Y H:i:s') }}</td>
            <td class="px-5 py-3 font-bold text-indigo-600">{{ $log->user->name ?? 'System' }}</td>
            <td class="px-5 py-3 font-medium text-slate-700">{{ $log->module }}</td>
            <td class="px-5 py-3 font-bold text-slate-900">{{ $log->action }}</td>
            <td class="px-5 py-3 text-slate-600">{{ $log->details }}</td>
            <td class="px-5 py-3 text-slate-400 font-mono text-xs">{{ $log->ip_address }}</td>
          </tr>
          @empty
          <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No activity logs recorded yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  function switchTab(tabId) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
       btn.classList.remove('border-indigo-500', 'text-indigo-600');
       btn.classList.add('border-transparent', 'text-slate-500');
    });
    document.getElementById('panel-' + tabId).classList.remove('hidden');
    const activeBtn = document.getElementById('tab-' + tabId);
    activeBtn.classList.remove('border-transparent', 'text-slate-500');
    activeBtn.classList.add('border-indigo-500', 'text-indigo-600');
  }
</script>
@endpush
