@extends('layouts.app')
@section('title','Words')
@section('top','Words')
@section('subtitle','Quản lý từ vựng')

@section('content')
<div class="hero">
    <h1>My Vocabulary</h1>
    <p>{{ $count }} từ vựng</p>
</div>

<div class="wrap">
    <div style="display:flex;gap:12px;margin-bottom:20px;align-items:center;flex-wrap:wrap">
        <input type="search" id="searchInput" placeholder="🔍 Search words..." style="flex:1;min-width:240px;padding:12px 16px;border:2px solid #e5e7eb;border-radius:8px;font-size:14px">
        
        <select id="topicFilter" style="padding:12px 16px;border:2px solid #e5e7eb;border-radius:8px;min-width:180px">
            <option value="">All Categories</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}">{{ $topic->name }}</option>
            @endforeach
        </select>

        <select style="padding:12px 16px;border:2px solid #e5e7eb;border-radius:8px;min-width:140px">
            <option>All Levels</option>
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Advanced</option>
        </select>

        <select style="padding:12px 16px;border:2px solid #e5e7eb;border-radius:8px;min-width:130px">
            <option>All Topics</option>
        </select>

        <select style="padding:12px 16px;border:2px solid #e5e7eb;border-radius:8px;min-width:130px">
            <option>All CEFR</option>
            <option>A1</option>
            <option>A2</option>
            <option>B1</option>
            <option>B2</option>
        </select>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:20px;margin-bottom:30px">
        @forelse($vocabularies as $vocabulary)
            <div class="vocabulary-card card" data-word="{{ $vocabulary->word }}" data-topic="{{ $vocabulary->topic_id }}">
                <div style="position:relative;height:200px;background:#f3f4f6;border-radius:8px;overflow:hidden;margin-bottom:16px">
                    @if($vocabulary->image_path)
                        <img src="{{ asset('storage/' . $vocabulary->image_path) }}" alt="{{ $vocabulary->word }}" style="width:100%;height:100%;object-fit:cover">
                    @else
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-size:14px">📷 No image</div>
                    @endif
                </div>

                <div style="flex:1">
                    <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:8px">
                        <h3 style="margin:0;font-size:18px;font-weight:bold">{{ $vocabulary->word }}</h3>
                        @if($vocabulary->example)
                            <span style="color:#6b7280;font-size:13px">/{{ $vocabulary->example }}</span>
                        @endif
                    </div>

                    <p style="margin:0 0 12px;color:#374151;font-size:14px">{{ $vocabulary->meaning }}</p>

                    @if($vocabulary->example)
                        <p style="margin:0 0 12px;color:#6b7280;font-size:13px;font-style:italic">{{ $vocabulary->example }}</p>
                    @endif

                    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
                        @if($vocabulary->topic)
                            <span style="display:inline-block;padding:4px 8px;background:#f3f4f6;border-radius:4px;font-size:11px;font-weight:600;color:#374151">{{ $vocabulary->topic->name }}</span>
                        @endif
                        <span style="display:inline-block;padding:4px 8px;background:#f3f4f6;border-radius:4px;font-size:11px;font-weight:600;color:#374151">A1</span>
                        @if($vocabulary->created_at->isToday())
                            <span style="display:inline-block;padding:4px 8px;background:#ecfdf5;border-radius:4px;font-size:11px;font-weight:600;color:#059669">ENRICHED</span>
                        @endif
                    </div>
                </div>

                <div style="display:flex;gap:8px;padding-top:12px;border-top:1px solid #e5e7eb">
                    <button onclick="openEditModal({{ $vocabulary->id }})" class="btn white" style="flex:1">✏️ EDIT</button>
                    <form action="{{ route('vocabulary.delete', $vocabulary) }}" method="POST" style="flex:1" onsubmit="return confirm('Bạn có chắc muốn xóa?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn white" style="width:100%">🗑️ DELETE</button>
                    </form>
                </div>
            </div>
        @empty
            <div style="grid-column:1/-1;padding:40px;text-align:center;color:#9ca3af">Không có từ vựng nào</div>
        @endforelse
    </div>

    @if($vocabularies->hasPages())
        <div style="display:flex;justify-content:center;gap:8px;align-items:center;margin-top:30px;flex-wrap:wrap">
            @if($vocabularies->onFirstPage())
                <span style="padding:8px 12px;color:#d1d5db;cursor:not-allowed">← Trước</span>
            @else
                <a href="{{ $vocabularies->previousPageUrl() }}" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:4px;color:#374151;text-decoration:none">← Trước</a>
            @endif

            @foreach($vocabularies->getUrlRange(max(1, $vocabularies->currentPage() - 2), min($vocabularies->lastPage(), $vocabularies->currentPage() + 2)) as $page => $url)
                @if($page === $vocabularies->currentPage())
                    <span style="padding:8px 12px;background:#3b82f6;color:white;border-radius:4px;font-weight:600">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:4px;color:#374151;text-decoration:none">{{ $page }}</a>
                @endif
            @endforeach

            @if($vocabularies->hasMorePages())
                <a href="{{ $vocabularies->nextPageUrl() }}" style="padding:8px 12px;border:1px solid #e5e7eb;border-radius:4px;color:#374151;text-decoration:none">Sau →</a>
            @else
                <span style="padding:8px 12px;color:#d1d5db;cursor:not-allowed">Sau →</span>
            @endif
        </div>
    @endif
</div>

<div id="editModal" class="modal">
    <div class="dialog">
        <h2>Edit Word</h2>
        <form id="editForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            
            <div style="margin-bottom:16px">
                <label style="font-weight:600;display:block;margin-bottom:8px">Word</label>
                <input type="text" id="editWord" name="word" required style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:4px">
            </div>

            <div style="margin-bottom:16px">
                <label style="font-weight:600;display:block;margin-bottom:8px">Meaning (Vietnamese)</label>
                <input type="text" id="editMeaning" name="meaning" required style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:4px">
            </div>

            <div style="margin-bottom:16px">
                <label style="font-weight:600;display:block;margin-bottom:8px">Example / Pronunciation</label>
                <input type="text" id="editExample" name="example" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:4px">
            </div>

            <div style="margin-bottom:16px">
                <label style="font-weight:600;display:block;margin-bottom:8px">Upload Image</label>
                <input type="file" name="image" accept="image/*" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:4px">
            </div>

            <div style="display:flex;gap:8px">
                <button type="submit" class="btn">Save Changes</button>
                <button type="button" class="btn white" onclick="modal('editModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const search = this.value.toLowerCase();
        document.querySelectorAll('.vocabulary-card').forEach(card => {
            const word = card.dataset.word.toLowerCase();
            card.style.display = word.includes(search) ? '' : 'none';
        });
    });

    document.getElementById('topicFilter').addEventListener('change', function() {
        const topicId = this.value;
        document.querySelectorAll('.vocabulary-card').forEach(card => {
            const cardTopic = card.dataset.topic;
            card.style.display = (!topicId || cardTopic === topicId) ? '' : 'none';
        });
    });

    function openEditModal(vocabId) {
        fetch(`/api/vocabulary/${vocabId}`)
            .then(r => r.json())
            .then(vocab => {
                document.getElementById('editWord').value = vocab.word;
                document.getElementById('editMeaning').value = vocab.meaning;
                document.getElementById('editExample').value = vocab.example || '';
                document.getElementById('editForm').action = `/vocabulary/${vocabId}`;
                modal('editModal');
            });
    }

    function modal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = m.style.display === 'flex' ? 'none' : 'flex';
    }
</script>

@endsection
