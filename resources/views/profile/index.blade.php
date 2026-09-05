@extends('layouts.app')
@section('title', 'My Profile')
@section('spaPage', 'profile')

@push('styles')
<style>
  /* Avatar */
  .avatar {
    width: 36px; height: 36px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: #fff; background: #4F46E5;
  }
  .avatar.sm { width: 32px; height: 32px; font-size: 12px; }
  .avatar.lg { width: 48px; height: 48px; font-size: 16px; }

  /* Badges */
  .badge-vip { background: #FFFBEB; color: #D97706; border: 1px solid #FDE68A; }
  .badge-vip::before { display: none; }
</style>
@endpush

@section('content')
<div class="page flex justify-between items-center mb-6">
  <h1 class="text-2xl font-bold text-slate-900">My Profile</h1>
</div>

<div class="page grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm text-center">
    <div class="avatar mx-auto" style="width:96px;height:96px;font-size:32px">{{ $user->initials }}</div>
    <div class="text-xl font-bold mt-3 text-slate-900" data-profile="name">{{ $user->name }}</div>
    <div class="text-sm text-slate-500">{{ $user->title ?: $user->role_label }}</div>
    @if($user->badge)
    <div class="badge badge-vip mt-2 inline-flex"><i class="fa-solid fa-crown text-[9px] mr-1"></i>{{ $user->badge }}</div>
    @endif

    <div class="h-px bg-slate-200 my-4"></div>

    <div class="grid grid-cols-3 gap-2 text-center">
      <div><div class="text-lg font-bold text-slate-900">{{ number_format($stats['customers']) }}</div><div class="text-xs text-slate-500">Customers</div></div>
      <div><div class="text-lg font-bold text-slate-900">{{ number_format($stats['orders']) }}</div><div class="text-xs text-slate-500">Orders</div></div>
      <div><div class="text-lg font-bold text-slate-900">{{ number_format($stats['rating'], 1) }}</div><div class="text-xs text-slate-500">Rating</div></div>
    </div>

    <div class="h-px bg-slate-200 my-4"></div>

    <button class="w-full bg-white border border-slate-200 text-slate-500 py-2 rounded-lg text-sm font-medium hover:bg-slate-100 flex items-center justify-center gap-2" onclick="openModal('change-password')"><i class="fa-solid fa-key text-xs"></i> Change Password</button>
  </div>

  <div class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-200 flex justify-between items-center">
      <h3 class="text-base font-semibold text-slate-900">Personal Information</h3>
      <button id="profile-save-btn" class="bg-slate-900 text-white px-3.5 py-1.5 rounded-lg text-xs font-medium hover:bg-slate-800 flex items-center gap-2 transition-colors shadow-sm" onclick="saveProfile(this)"><i class="fa-solid fa-check text-[10px]"></i> Save Changes</button>
    </div>
    <div class="p-6">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Full Name</label>
          <input id="profile-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="{{ $user->name }}">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Display Name</label>
          <input id="profile-display-name" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="{{ $user->display_name ?: $user->short_name }}">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Email Address</label>
          <input id="profile-email" type="email" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="{{ $user->email }}">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Phone Number</label>
          <input id="profile-phone" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900" value="{{ $user->phone }}">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Role</label>
          <input class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-500" value="{{ $user->title ?: $user->role_label }}" disabled>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-500 mb-1.5">Member Since</label>
          <input class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-500" value="{{ $user->created_at?->format('F Y') }}" disabled>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
  window.saveProfile = async function (btn) {
    const payload = {
      name: document.getElementById('profile-name').value.trim(),
      display_name: document.getElementById('profile-display-name').value.trim(),
      email: document.getElementById('profile-email').value.trim(),
      phone: document.getElementById('profile-phone').value.trim(),
    };

    if (!payload.name || !payload.email) {
      toast('Name and email are required', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.put(@json(route('profile.update')), payload);

      // Repaint the parts of the page that show these values.
      const initials = Atelier.initials(payload.name);
      document.querySelectorAll('.avatar').forEach(el => { el.textContent = initials; });
      document.querySelectorAll('[data-profile="name"]').forEach(el => { el.textContent = payload.name; });

      const sidebarName = document.querySelector('aside .text-sm.font-semibold.text-slate-900.truncate');
      if (sidebarName) sidebarName.textContent = payload.name;

      toast(res.message || 'Profile updated successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not update your profile');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  window.updatePassword = async function (btn) {
    const payload = {
      current_password: document.getElementById('pw-current').value,
      password: document.getElementById('pw-new').value,
      password_confirmation: document.getElementById('pw-confirm').value,
    };

    if (!payload.current_password || !payload.password) {
      toast('Please fill in every password field', 'error');
      return;
    }

    if (payload.password !== payload.password_confirmation) {
      toast('New passwords do not match', 'error');
      return;
    }

    Atelier.setBusy(btn, true);
    try {
      const res = await Atelier.api.put(@json(route('profile.password')), payload);
      closeModal();
      toast(res.message || 'Password updated successfully', 'success');
    } catch (err) {
      Atelier.reportError(err, 'Could not update your password');
    } finally {
      Atelier.setBusy(btn, false);
    }
  };

  window.modals = window.modals || {};
  Object.assign(window.modals, {
    'change-password': () => `
      <div class="p-5 border-b border-slate-200 flex justify-between items-center">
        <div class="text-lg font-semibold text-slate-900">Change Password</div>
        <button class="w-8 h-8 rounded-md border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center" onclick="closeModal()"><i class="fa-solid fa-xmark text-sm"></i></button>
      </div>
      <div class="p-6">
        <div class="mb-4"><label class="block text-xs font-semibold text-slate-500 mb-1.5">Current Password</label><input id="pw-current" type="password" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"></div>
        <div class="mb-4"><label class="block text-xs font-semibold text-slate-500 mb-1.5">New Password</label><input id="pw-new" type="password" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"></div>
        <div class="mb-4"><label class="block text-xs font-semibold text-slate-500 mb-1.5">Confirm New Password</label><input id="pw-confirm" type="password" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900"></div>
      </div>
      <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end gap-2">
        <button class="bg-white border border-slate-200 text-slate-500 px-4 py-2 rounded-lg text-sm font-medium hover:bg-slate-100" onclick="closeModal()">Cancel</button>
        <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700" onclick="updatePassword(this)">Update</button>
      </div>
    `
  });
</script>
@endpush
