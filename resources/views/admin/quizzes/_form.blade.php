<form action="{{ $route }}" method="POST" class="bg-white rounded-lg shadow p-6 space-y-4">
    @csrf
    @if(in_array($method, ['PUT', 'PATCH'])) @method($method) @endif
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề Quiz</label>
        <input type="text" name="title" value="{{ old('title', $quiz?->title) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Bài học</label>
        <select name="lesson_id" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Chọn bài học --</option>
            @foreach($lessons as $lesson)
                <option value="{{ $lesson->id }}" {{ old('lesson_id', $quiz?->lesson_id) == $lesson->id ? 'selected' : '' }}>{{ $lesson->course?->name }} > {{ $lesson->title }}</option>
            @endforeach
        </select>
        @error('lesson_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Điểm đạt (%)</label>
            <input type="number" name="passing_score" value="{{ old('passing_score', $quiz?->passing_score ?? 60) }}" min="0" max="100" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            @error('passing_score') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
            <select name="status" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="draft" {{ old('status', $quiz?->status) === 'draft' ? 'selected' : '' }}>Nháp</option>
                <option value="published" {{ old('status', $quiz?->status) === 'published' ? 'selected' : '' }}>Xuất bản</option>
            </select>
            @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>
    </div>
    <div class="flex justify-end space-x-3">
        <a href="{{ route('admin.quizzes.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Hủy</a>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Lưu</button>
    </div>
</form>