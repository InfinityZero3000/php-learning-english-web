@extends('layouts.app')

@section('top', 'Flashcards')
@section('breadcrumb', 'Từ vựng / Flashcards')

@section('content')
<div class="page-header">
    <h2>📇 Học từ vựng bằng Flashcards</h2>
    <p>Nhấp vào thẻ để lật mặt. Dùng mũi tên hoặc nút để chuyển thẻ.</p>
</div>

{{-- Filter --}}
<div class="toolbar">
    <form method="GET" class="flex gap-2 items-center flex-1">
        <select name="topic" class="form-select" style="max-width:280px;" onchange="this.form.submit()">
            <option value="">-- Tất cả chủ đề --</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" {{ request('topic') == $topic->id ? 'selected' : '' }}>{{ $topic->name }}</option>
            @endforeach
        </select>
    </form>
    <div class="toolbar-spacer"></div>
    <span class="text-sm text-gray-500 font-semibold" id="card-counter">
        @if($vocabularies->count() > 0)
            Thẻ <span id="current-card-num">1</span> / {{ $vocabularies->count() }}
        @endif
    </span>
</div>

@if($vocabularies->isEmpty())
    <div class="empty-state">
        <i data-lucide="inbox" style="width:56px;height:56px;color:#cbd5e1;"></i>
        <h3>Chưa có từ vựng nào</h3>
        <p>Hãy thêm từ vựng hoặc chọn chủ đề khác để bắt đầu học.</p>
    </div>
@else
    <div class="flashcard-container" id="flashcard-wrapper">
        <div class="flashcard" id="flashcard" onclick="flipCard()">
            <div class="flashcard-face flashcard-front" id="front">
                <div class="word" id="front-word">{{ $vocabularies[0]->word }}</div>
                <div class="phonetic" id="front-phonetic">{{ $vocabularies[0]->pronunciation ?? '' }}</div>
                <div style="margin-top:16px;font-size:12px;opacity:.7;">Nhấp để lật →</div>
            </div>
            <div class="flashcard-face flashcard-back" id="back">
                <div class="meaning" id="back-meaning">{{ $vocabularies[0]->meaning }}</div>
                @if($vocabularies[0]->example)
                    <div class="example" id="back-example">"{{ $vocabularies[0]->example }}"</div>
                @endif
                @if($vocabularies[0]->part_of_speech)
                    <span class="badge badge-default mt-3" id="back-pos">{{ $vocabularies[0]->part_of_speech }}</span>
                @endif
                @if($vocabularies[0]->topic)
                    <span class="badge badge-{{ strtolower($vocabularies[0]->topic->name ?? 'default') }} mt-1" id="back-topic">{{ $vocabularies[0]->topic->name ?? '' }}</span>
                @endif
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:24px;">
        <button class="btn btn-outline btn-sm" onclick="prevCard()" id="btn-prev">← Trước</button>
        <span style="font-weight:700;font-size:14px;color:var(--gray);min-width:80px;text-align:center;" id="progress-text">1 / {{ $vocabularies->count() }}</span>
        <button class="btn btn-outline btn-sm" onclick="nextCard()" id="btn-next">Tiếp →</button>
    </div>

    <div style="text-align:center;margin-top:12px;">
        <button class="btn btn-ghost btn-sm" onclick="flipCard()">
            <i data-lucide="flip-vertical-2" style="width:16px;height:16px;"></i> Lật thẻ
        </button>
        <button class="btn btn-ghost btn-sm" onclick="shuffleCards()">
            <i data-lucide="shuffle" style="width:16px;height:16px;"></i> Xáo trộn
        </button>
    </div>
@endif
@endsection

@push('scripts')
<script>
    const vocabData = @json($vocabularies);
    let currentIndex = 0;
    let isFlipped = false;
    let shuffled = [...vocabData];

    function updateCard(index) {
        if (!shuffled.length) return;
        isFlipped = false;
        document.getElementById('flashcard').classList.remove('flipped');
        const v = shuffled[index];
        document.getElementById('front-word').textContent = v.word;
        document.getElementById('front-phonetic').textContent = v.pronunciation || '';
        document.getElementById('back-meaning').textContent = v.meaning;
        const exampleEl = document.getElementById('back-example');
        if (exampleEl) {
            exampleEl.textContent = v.example ? `"${v.example}"` : '';
            exampleEl.style.display = v.example ? '' : 'none';
        }
        const posEl = document.getElementById('back-pos');
        if (posEl) {
            posEl.textContent = v.part_of_speech || '';
            posEl.style.display = v.part_of_speech ? '' : 'none';
        }
        const topicEl = document.getElementById('back-topic');
        if (topicEl) {
            topicEl.textContent = v.topic?.name || '';
            topicEl.style.display = v.topic?.name ? '' : 'none';
        }
        document.getElementById('current-card-num').textContent = index + 1;
        document.getElementById('progress-text').textContent = `${index + 1} / ${shuffled.length}`;
        document.getElementById('btn-prev').disabled = index === 0;
        document.getElementById('btn-next').disabled = index === shuffled.length - 1;
    }

    function flipCard() {
        isFlipped = !isFlipped;
        document.getElementById('flashcard').classList.toggle('flipped', isFlipped);
    }

    function nextCard() {
        if (currentIndex < shuffled.length - 1) {
            currentIndex++;
            updateCard(currentIndex);
        }
    }

    function prevCard() {
        if (currentIndex > 0) {
            currentIndex--;
            updateCard(currentIndex);
        }
    }

    function shuffleCards() {
        // Fisher-Yates shuffle
        shuffled = [...vocabData];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        currentIndex = 0;
        updateCard(0);
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowRight') nextCard();
        else if (e.key === 'ArrowLeft') prevCard();
        else if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); flipCard(); }
    });

    // Initial state
    if (vocabData.length > 0) {
        document.getElementById('btn-prev').disabled = true;
        if (vocabData.length === 1) {
            document.getElementById('btn-next').disabled = true;
        }
    }
</script>
@endpush