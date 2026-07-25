@extends('layouts.app')

@section('title', 'Hồ sơ · LexiLingo')
@section('top', 'Hồ sơ của tôi')
@section('subtitle', 'Quản lý thông tin cá nhân & bảo mật')

@section('content')
<div style="max-width:900px;margin:0 auto;display:flex;flex-direction:column;gap:24px;">
    <!-- Profile Banner -->
    <div style="background:linear-gradient(135deg,#312e81 0%,#4f46e5 50%,#6366f1 100%);border-radius:var(--radius);padding:36px 32px;color:#fff;position:relative;overflow:hidden;">
        <div style="position:absolute;right:-30px;top:-30px;width:180px;height:180px;background:rgba(255,255,255,.04);border-radius:50%;"></div>
        <div style="position:absolute;right:80px;bottom:-40px;width:120px;height:120px;background:rgba(255,255,255,.03);border-radius:50%;"></div>
        <div style="display:flex;align-items:center;gap:24px;position:relative;z-index:1;flex-wrap:wrap;">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#a5b4fc,#818cf8);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:900;border:4px solid rgba(255,255,255,.3);flex-shrink:0;">
                {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
            </div>
            <div style="flex:1;min-width:200px;">
                <h1 style="font-size:26px;font-weight:900;margin:0 0 4px;">{{ $user->name }}</h1>
                <p style="font-size:14px;opacity:.85;margin:0;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="display:inline-flex;align-items:center;gap:4px;">📧 {{ $user->email }}</span>
                </p>
                <div style="display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;">
                    <span style="background:rgba(255,255,255,.15);padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px;">
                        🗓️ Tham gia {{ $user->created_at->format('d/m/Y') }}
                    </span>
                    @if($user->provider)
                    <span style="background:rgba(255,255,255,.15);padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px;">
                        🔗 {{ ucfirst($user->provider) }}
                    </span>
                    @endif
                </div>
            </div>
            <div style="text-align:center;position:relative;z-index:1;">
                <div style="font-size:36px;font-weight:900;line-height:1;">🔥 {{ $user->streak_days ?? 0 }}</div>
                <div style="font-size:11px;opacity:.8;text-transform:uppercase;letter-spacing:.06em;">Ngày streak</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
        <!-- Left Column: Personal Info -->
        <div class="card" style="padding:28px;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid var(--brand-soft);">
                <div style="width:38px;height:38px;background:var(--brand-soft);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--brand);">
                    <i data-lucide="user-pen" style="width:20px;height:20px;"></i>
                </div>
                <h3 style="font-size:17px;font-weight:800;margin:0;">Thông tin cá nhân</h3>
            </div>

            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray);margin-bottom:6px;display:block;">Họ tên</label>
                    <div style="position:relative;">
                        <input type="text" name="name" value="{{ old('name', $user->name) }}"
                            style="width:100%;padding:12px 14px 12px 40px;border:2px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;transition:border-color .15s;background:#fff;"
                            onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='var(--line)'">
                        <i data-lucide="user" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8;"></i>
                    </div>
                    @error('name')
                        <div class="field-error" style="color:var(--red);font-size:12px;margin-top:4px;font-weight:600;">⚠️ {{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray);margin-bottom:6px;display:block;">Email</label>
                    <div style="position:relative;">
                        <input type="email" value="{{ $user->email }}" readonly
                            style="width:100%;padding:12px 14px 12px 40px;border:2px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;background:#f8fafc;color:var(--gray);cursor:not-allowed;">
                        <i data-lucide="mail" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8;"></i>
                    </div>
                    @if($user->provider)
                        <span style="display:inline-flex;align-items:center;gap:4px;margin-top:6px;font-size:11px;color:var(--brand);font-weight:600;background:var(--brand-soft);padding:4px 10px;border-radius:999px;">
                            🔗 Đăng nhập qua {{ ucfirst($user->provider) }}
                        </span>
                    @endif
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;justify-content:center;font-size:14px;">
                    <i data-lucide="check" style="width:16px;height:16px;"></i> Lưu thay đổi
                </button>

                @if(session('success'))
                    <div style="margin-top:14px;background:var(--green-soft);color:#065f46;padding:10px 14px;border-radius:var(--radius-sm);font-weight:700;font-size:13px;display:flex;align-items:center;gap:8px;border:1px solid #a7f3d0;">
                        ✅ {{ session('success') }}
                    </div>
                @endif
            </form>
        </div>

        <!-- Right Column: Password & Danger Zone -->
        <div style="display:flex;flex-direction:column;gap:24px;">
            <!-- Password Change -->
            <div class="card" style="padding:28px;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid var(--amber-soft);">
                    <div style="width:38px;height:38px;background:var(--amber-soft);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--amber);">
                        <i data-lucide="lock-keyhole" style="width:20px;height:20px;"></i>
                    </div>
                    <h3 style="font-size:17px;font-weight:800;margin:0;">Đổi mật khẩu</h3>
                </div>

                <form action="{{ route('profile.update') }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray);margin-bottom:6px;display:block;">Mật khẩu hiện tại</label>
                        <div style="position:relative;">
                            <input type="password" name="current_password"
                                style="width:100%;padding:12px 14px 12px 40px;border:2px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;transition:border-color .15s;background:#fff;"
                                onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='var(--line)'"
                                placeholder="••••••••">
                            <i data-lucide="key-round" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8;"></i>
                        </div>
                        @error('current_password')
                            <div style="color:var(--red);font-size:12px;margin-top:4px;font-weight:600;">⚠️ {{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label style="font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--gray);margin-bottom:6px;display:block;">Mật khẩu mới</label>
                        <div style="position:relative;">
                            <input type="password" name="new_password"
                                style="width:100%;padding:12px 14px 12px 40px;border:2px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;transition:border-color .15s;background:#fff;"
                                onfocus="this.style.borderColor='var(--brand)'" onblur="this.style.borderColor='var(--line)'"
                                placeholder="Ít nhất 8 ký tự">
                            <i data-lucide="lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8;"></i>
                        </div>
                        @error('new_password')
                            <div style="color:var(--red);font-size:12px;margin-top:4px;font-weight:600;">⚠️ {{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn" style="width:100%;margin-top:8px;justify-content:center;font-size:14px;background:var(--amber);color:#fff;">
                        <i data-lucide="shield-check" style="width:16px;height:16px;"></i> Cập nhật mật khẩu
                    </button>
                </form>
            </div>

            <!-- Danger Zone -->
            <div class="card" style="padding:24px;border:2px solid #fecaca;background:#fff5f5;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                    <div style="width:38px;height:38px;background:var(--red-soft);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--red);">
                        <i data-lucide="triangle-alert" style="width:20px;height:20px;"></i>
                    </div>
                    <div>
                        <h3 style="font-size:16px;font-weight:800;margin:0;color:#991b1b;">Vùng nguy hiểm</h3>
                        <p style="font-size:12px;color:#b91c1c;margin:2px 0 0;">Hành động không thể hoàn tác</p>
                    </div>
                </div>
                <p style="font-size:13px;color:#7f1d1d;margin-bottom:16px;line-height:1.5;">
                    Khi bạn xóa tài khoản, tất cả dữ liệu của bạn sẽ bị xóa vĩnh viễn — bao gồm tiến độ học tập, từ vựng đã lưu, và điểm quiz.
                </p>
                <form action="{{ route('profile.destroy') }}" method="POST"
                      onsubmit="return confirm('⚠️ Bạn có chắc chắn muốn xóa tài khoản?\n\nHành động này không thể hoàn tác. Tất cả dữ liệu của bạn sẽ bị xóa vĩnh viễn.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center;font-size:14px;">
                        <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Xóa tài khoản của tôi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    lucide.createIcons();
</script>
@endpush