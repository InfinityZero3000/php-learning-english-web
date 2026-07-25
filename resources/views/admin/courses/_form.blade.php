<form action="{{ $route }}" method="POST" class="bg-white rounded-lg shadow p-6 space-y-4">
    @csrf
    @if($method === 'PUT' || $method === 'PATCH')
        @method($method)
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tiêu đề</label>
        <input type="text" name="title" value="{{ old('title', $course?->title) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('title') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $course?->slug) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('slug') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
        <select name="level_id" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">-- Chọn Level --</option>
            @foreach($levels as $level)
                <option value="{{ $level->id }}" {{ old('level_id', $course?->level_id) == $level->id ? 'selected' : '' }}>{{ $level->name }}</option>
            @endforeach
        </select>
        @error('level_id') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Mô tả</label>
        <textarea name="description" rows="4" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $course?->description) }}</textarea>
        @error('description') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Trạng thái</label>
        <select name="status" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="draft" {{ old('status', $course?->status) === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="published" {{ old('status', $course?->status) === 'published' ? 'selected' : '' }}>Published</option>
            <option value="archived" {{ old('status', $course?->status) === 'archived' ? 'selected' : '' }}>Archived</option>
        </select>
        @error('status') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    @if(isset($topics))
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Chủ đề</label>
        <div class="grid grid-cols-2 gap-2">
            @foreach($topics as $topic)
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="topic_ids[]" value="{{ $topic->id }}"
                        {{ in_array($topic->id, old('topic_ids', $course?->topics?->pluck('id')?->toArray() ?? [])) ? 'checked' : '' }}
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm">{{ $topic->name }}</span>
                </label>
            @endforeach
        </div>
        @error('topic_ids') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>
    @endif

    <div class="flex justify-end space-x-3">
        <a href="{{ route('admin.courses.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Hủy</a>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Lưu</button>
    </div>
</form>