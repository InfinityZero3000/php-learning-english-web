<form action="{{ $route }}" method="POST" class="bg-white rounded-lg shadow p-6 space-y-4">
    @csrf
    @if($method === 'PUT' || $method === 'PATCH') @method($method) @endif
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề</label>
        <input type="text" name="title" value="{{ old('title', $lesson?->title) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Khóa học</label>
        <select name="course_id" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Chọn khóa học --</option>
            @foreach($courses as $course)
                <option value="{{ $course->id }}" {{ old('course_id', $lesson?->course_id) == $course->id ? 'selected' : '' }}>{{ $course->name }}</option>
            @endforeach
        </select>
        @error('course_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
        <select name="level_id" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Chọn level --</option>
            @foreach($levels as $level)
                <option value="{{ $level->id }}" {{ old('level_id', $lesson?->level_id) == $level->id ? 'selected' : '' }}>{{ $level->name }}</option>
            @endforeach
        </select>
        @error('level_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nội dung</label>
        <textarea name="content" rows="6" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('content', $lesson?->content) }}</textarea>
        @error('content') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">URL Video</label>
        <input type="url" name="video_url" value="{{ old('video_url', $lesson?->video_url) }}" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('video_url') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự hiển thị</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $lesson?->sort_order ?? 0) }}" min="0" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('sort_order') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    <div class="flex justify-end space-x-3">
        <a href="{{ route('admin.lessons.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Hủy</a>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Lưu</button>
    </div>
</form>