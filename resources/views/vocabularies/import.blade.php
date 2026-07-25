@extends('layouts.app')

@section('title', 'Nhập từ vựng - LexiLingo')
@section('top', 'Nhập từ vựng')
@section('subtitle', 'Tải lên file CSV để thêm nhiều từ vựng cùng lúc')

@section('content')
<div class="wrap">
    {{-- Hero Banner --}}
    <div class="hero" style="padding: 32px 36px; margin-bottom: 24px;">
        <div style="display:flex; align-items:center; gap:20px; position:relative; z-index:1;">
            <div style="width:56px; height:56px; background:rgba(255,255,255,.15); border-radius:16px; display:flex; align-items:center; justify-content:center;">
                <i data-lucide="upload" style="width:28px; height:28px; color:#fff;"></i>
            </div>
            <div>
                <h1 style="margin:0; font-size:24px;">Nhập từ vựng từ CSV</h1>
                <p style="margin:4px 0 0; opacity:.85; font-size:14px;">Tải lên file CSV với các cột: word, meaning, pronunciation, example</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div style="background:#dcfce7;color:#166534;padding:14px 20px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;">
            <i data-lucide="check-circle" style="width:20px;height:20px;color:#16a34a;"></i>
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div style="background:#fee2e2;color:#991b1b;padding:14px 20px;border-radius:12px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;font-weight:600;">
            <i data-lucide="alert-circle" style="width:20px;height:20px;color:#dc2626;flex-shrink:0;margin-top:1px;"></i>
            <div>
                <p style="margin:0 0 6px;">Có lỗi xảy ra:</p>
                <ul style="margin:0;padding-left:18px;font-weight:400;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 320px; gap:20px; align-items:start;">
        {{-- Main Form --}}
        <div class="card" style="padding:28px;">
            <form action="{{ route('vocabularies.import') }}" method="POST" enctype="multipart/form-data" id="importForm">
                @csrf

                {{-- File Upload Zone --}}
                <div style="margin-bottom:24px;">
                    <label style="display:block; font-size:14px; font-weight:700; color:var(--ink); margin-bottom:8px;">
                        <i data-lucide="file-text" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>
                        Chọn file CSV
                    </label>
                    <div id="dropZone"
                         style="border:2px dashed var(--line); border-radius:16px; padding:40px 24px; text-align:center; cursor:pointer; transition:all .2s ease; background:#fafbfc; position:relative; overflow:hidden;"
                         ondrop="event.preventDefault(); this.style.borderColor='var(--brand)'; this.style.background='var(--brand-soft)'; document.getElementById('file').files=event.dataTransfer.files; updateFileName();"
                         ondragover="event.preventDefault(); this.style.borderColor='var(--brand)'; this.style.background='var(--brand-soft)';"
                         ondragleave="this.style.borderColor='var(--line)'; this.style.background='#fafbfc';"
                         onclick="document.getElementById('file').click();">
                        <input type="file" name="file" id="file" accept=".csv,.txt" required
                               style="display:none;"
                               onchange="updateFileName();">
                        <div id="uploadPlaceholder">
                            <div style="width:56px;height:56px;background:var(--brand-soft);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                                <i data-lucide="file-up" style="width:26px;height:26px;color:var(--brand);"></i>
                            </div>
                            <p style="font-size:15px;font-weight:700;color:var(--ink);margin:0 0 4px;">Kéo thả file CSV vào đây</p>
                            <p style="font-size:13px;color:var(--gray);margin:0;">hoặc nhấn để chọn file &middot; .csv, .txt &middot; Tối đa 2MB</p>
                        </div>
                        <div id="fileSelected" style="display:none;">
                            <div style="width:56px;height:56px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;">
                                <i data-lucide="file-check" style="width:26px;height:26px;color:#16a34a;"></i>
                            </div>
                            <p style="font-size:15px;font-weight:700;color:#166534;margin:0 0 2px;" id="fileNameDisplay"></p>
                            <p style="font-size:12px;color:var(--gray);">Nhấn để chọn file khác</p>
                        </div>
                    </div>
                </div>

                {{-- Topic Selection --}}
                <div style="margin-bottom:24px;">
                    <label for="topic_id" style="display:block; font-size:14px; font-weight:700; color:var(--ink); margin-bottom:8px;">
                        <i data-lucide="folder-tree" style="width:16px;height:16px;vertical-align:-3px;margin-right:6px;"></i>
                        Chủ đề
                        <span style="font-weight:400;color:var(--gray);font-size:12px;">(tùy chọn)</span>
                    </label>
                    <select name="topic_id" id="topic_id"
                            style="width:100%;padding:10px 14px;border:2px solid var(--line);border-radius:12px;font-size:14px;color:var(--ink);background:var(--surface);outline:none;transition:border-color .2s;appearance:none;background-image:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%23637b87%22 stroke-width=%222%22><path d=%22m6 9 6 6 6-6%22/></svg>');background-repeat:no-repeat;background-position:right 12px center;padding-right:36px;"
                            onfocus="this.style.borderColor='var(--brand)';" onblur="this.style.borderColor='var(--line)';">
                        <option value="">-- Tất cả chủ đề --</option>
                        @foreach($topics as $topic)
                            <option value="{{ $topic->id }}">{{ $topic->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Actions --}}
                <div style="display:flex; gap:12px; align-items:center;">
                    <button type="submit" class="btn" style="padding:10px 24px; font-size:14px;">
                        <i data-lucide="upload" style="width:17px;height:17px;"></i>
                        Nhập từ vựng
                    </button>
                    <a href="{{ route('vocabularies.index') }}" class="btn white" style="padding:10px 24px; font-size:14px;">
                        <i data-lucide="arrow-left" style="width:17px;height:17px;"></i>
                        Quay lại
                    </a>
                </div>
            </form>
        </div>

        {{-- Sidebar Help --}}
        <div style="display:flex;flex-direction:column;gap:16px;">
            {{-- Format Guide --}}
            <div class="card" style="padding:22px;">
                <h3 style="font-size:15px;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;color:var(--ink);">
                    <i data-lucide="info" style="width:18px;height:18px;color:var(--brand);"></i>
                    Định dạng CSV
                </h3>
                <div style="background:#f8f9fb;border-radius:10px;padding:14px;font-family:'SF Mono','Consolas','Monaco',monospace;font-size:12px;line-height:1.8;color:var(--ink);overflow-x:auto;">
                    <div style="color:var(--gray);margin-bottom:6px;font-weight:700;font-family:inherit;">Cột 1 → Cột 2 → Cột 3 → Cột 4</div>
                    <div><span style="color:var(--brand);font-weight:700;">word</span>, meaning, pronunciation, example</div>
                    <div style="margin-top:8px;color:var(--gray);">
                        <div style="color:#64748b;">Chỉ cột <b>word</b> và <b>meaning</b> là bắt buộc</div>
                        <div style="margin-top:4px;">pronunciation và example có thể để trống</div>
                    </div>
                </div>
            </div>

            {{-- Example --}}
            <div class="card" style="padding:22px;">
                <h3 style="font-size:15px;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;color:var(--ink);">
                    <i data-lucide="file-code" style="width:18px;height:18px;color:var(--brand);"></i>
                    Ví dụ mẫu
                </h3>
                <div style="background:#f8f9fb;border-radius:10px;padding:14px;font-family:'SF Mono','Consolas','Monaco',monospace;font-size:12px;line-height:1.8;color:var(--ink);overflow-x:auto;">
                    <div style="color:#16a34a;">apple, quả táo, /ˈæp.əl/, I eat an apple every day</div>
                    <div style="color:#16a34a;margin-top:2px;">hello, xin chào, /həˈloʊ/, Hello, how are you?</div>
                    <div style="color:#16a34a;margin-top:2px;">book, quyển sách, /bʊk/,</div>
                </div>
            </div>

            {{-- Quick Tips --}}
            <div class="card" style="padding:22px; background:linear-gradient(135deg,#eef2ff 0%,#faf5ff 100%); border-color:#c7d2fe;">
                <h3 style="font-size:15px;font-weight:800;margin:0 0 14px;display:flex;align-items:center;gap:8px;color:var(--ink);">
                    <i data-lucide="lightbulb" style="width:18px;height:18px;color:#f59e0b;"></i>
                    Mẹo nhỏ
                </h3>
                <ul style="margin:0;padding-left:18px;font-size:13px;color:#475569;line-height:1.8;">
                    <li>Lưu file Excel dưới dạng <b>CSV UTF-8</b> để tránh lỗi tiếng Việt</li>
                    <li>Dòng đầu tiên có thể là tiêu đề cột (sẽ tự bỏ qua)</li>
                    <li>Không dùng dấu phẩy trong nội dung ô</li>
                    <li>Tối đa <b>2MB</b> mỗi file</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    function updateFileName() {
        const input = document.getElementById('file');
        const placeholder = document.getElementById('uploadPlaceholder');
        const selected = document.getElementById('fileSelected');
        const nameDisplay = document.getElementById('fileNameDisplay');
        const dropZone = document.getElementById('dropZone');

        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            nameDisplay.textContent = file.name;
            placeholder.style.display = 'none';
            selected.style.display = 'block';
            dropZone.style.borderColor = '#16a34a';
            dropZone.style.background = '#f0fdf4';
        } else {
            placeholder.style.display = 'block';
            selected.style.display = 'none';
            dropZone.style.borderColor = 'var(--line)';
            dropZone.style.background = '#fafbfc';
        }
    }
</script>
@endsection