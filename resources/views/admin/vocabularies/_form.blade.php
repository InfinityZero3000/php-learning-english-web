<form action="{{ $route }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-lg shadow p-6 space-y-4">
    @csrf
    @if(in_array($method, ['PUT', 'PATCH'])) @method($method) @endif
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Từ vựng</label>
            <input type="text" name="word" value="{{ old('word', $vocabulary?->word) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('word') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Phiên âm</label>
            <input type="text" name="pronunciation" value="{{ old('pronunciation', $vocabulary?->pronunciation) }}" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('pronunciation') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nghĩa</label>
        <input type="text" name="meaning" value="{{ old('meaning', $vocabulary?->meaning) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('meaning') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Ví dụ</label>
        <textarea name="example" rows="3" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('example', $vocabulary?->example) }}</textarea>
        @error('example') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Chủ đề</label>
        <select name="topic_id" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Chọn chủ đề --</option>
            @foreach(\App\Models\Topic::orderBy('name')->get() as $topic)
                <option value="{{ $topic->id }}" {{ old('topic_id', $vocabulary?->topic_id) == $topic->id ? 'selected' : '' }}>{{ $topic->name }}</option>
            @endforeach
        </select>
        @error('topic_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Hình ảnh</label>
            <input type="file" name="image" accept="image/*" class="w-full border rounded px-3 py-2">
            @if($vocabulary?->image_url)
                <img src="{{ asset('storage/' . $vocabulary->image_url) }}" class="mt-2 h-20 rounded">
            @endif
            @error('image') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Audio</label>
            <input type="file" name="audio" accept="audio/*" class="w-full border rounded px-3 py-2">
            @if($vocabulary?->audio_url)
                <audio controls class="mt-2 w-full"><source src="{{ asset('storage/' . $vocabulary->audio_url) }}"></audio>
            @endif
            @error('audio') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="flex justify-end space-x-3">
        <a href="{{ route('admin.vocabularies.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Hủy</a>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Lưu</button>
    </div>
</form>