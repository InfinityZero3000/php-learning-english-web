<form action="{{ $route }}" method="POST" class="bg-white rounded-lg shadow p-6 space-y-4">
    @csrf
    @if($method === 'PUT' || $method === 'PATCH')
        @method($method)
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Tên Level</label>
        <input type="text" name="name" value="{{ old('name', $level?->name) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
        <input type="text" name="slug" value="{{ old('slug', $level?->slug) }}" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('slug') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự hiển thị</label>
        <input type="number" name="sort_order" value="{{ old('sort_order', $level?->sort_order ?? 0) }}" min="0" class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
        @error('sort_order') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
    </div>

    <div class="flex justify-end space-x-3">
        <a href="{{ route('admin.levels.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">Hủy</a>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Lưu</button>
    </div>
</form>