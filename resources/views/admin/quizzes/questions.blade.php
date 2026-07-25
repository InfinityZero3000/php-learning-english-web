@extends('layouts.app')
@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Câu hỏi: {{ $quiz->title }}</h1>
        <a href="{{ route('admin.quizzes.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; Quay lại</a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <!-- Add Question Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Thêm câu hỏi</h2>
        <form action="{{ route('admin.quizzes.questions.store', $quiz) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nội dung câu hỏi</label>
                    <textarea name="content" rows="2" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('content') }}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Loại</label>
                        <select name="type" class="w-full border rounded px-3 py-2">
                            <option value="multiple_choice">Trắc nghiệm</option>
                            <option value="true_false">Đúng/Sai</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự</label>
                        <input type="number" name="sort_order" value="{{ old('sort_order', $quiz->questions->count() + 1) }}" min="0" class="w-full border rounded px-3 py-2">
                    </div>
                </div>
                <div id="options-container" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Đáp án (nhập ít nhất 2 đáp án)</label>
                    @for($i = 0; $i < 4; $i++)
                    <div class="flex items-center gap-2">
                        <input type="radio" name="correct_index" value="{{ $i }}" {{ old('correct_index') == $i ? 'checked' : '' }} class="mr-2">
                        <input type="text" name="options[]" value="{{ old('options.'.$i) }}" placeholder="Đáp án {{ chr(65 + $i) }}" class="flex-1 border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    @endfor
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Giải thích (cho đáp án đúng)</label>
                    <input type="text" name="explanation" value="{{ old('explanation') }}" class="w-full border rounded px-3 py-2">
                </div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Thêm câu hỏi</button>
            </div>
        </form>
    </div>

    <!-- Import Form -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Import câu hỏi từ CSV</h2>
        <form action="{{ route('admin.quizzes.import', $quiz) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1">File CSV (question,option_a,option_b,option_c,option_d,correct_answer,explanation)</label>
                    <input type="file" name="file" accept=".csv,.txt" class="w-full border rounded px-3 py-2">
                    @error('file') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700">Import</button>
            </div>
        </form>
    </div>

    <!-- Questions List -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Câu hỏi</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loại</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Số đáp án</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($quiz->questions->sortBy('sort_order') as $q)
                <tr>
                    <td class="px-6 py-4">{{ $q->sort_order }}</td>
                    <td class="px-6 py-4">{{ $q->content }}</td>
                    <td class="px-6 py-4">{{ $q->type === 'multiple_choice' ? 'Trắc nghiệm' : 'Đúng/Sai' }}</td>
                    <td class="px-6 py-4">{{ $q->answers->count() }}</td>
                    <td class="px-6 py-4 space-x-2">
                        <button onclick="editQuestion({{ $q->toJson() }})" class="text-blue-600 hover:text-blue-900">Sửa</button>
                        <form action="{{ route('admin.quizzes.questions.destroy', $q) }}" method="POST" class="inline" onsubmit="return confirm('Xóa câu hỏi?')">
                            @csrf @method('DELETE')
                            <button class="text-red-600 hover:text-red-900">Xóa</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Chưa có câu hỏi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Edit Question Modal -->
<div id="edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow p-6 w-full max-w-lg mx-4 max-h-screen overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">Sửa câu hỏi</h2>
        <form id="edit-form" method="POST">
            @csrf @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nội dung</label>
                    <textarea name="content" id="edit-content" rows="2" required class="w-full border rounded px-3 py-2"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Loại</label>
                        <select name="type" id="edit-type" class="w-full border rounded px-3 py-2">
                            <option value="multiple_choice">Trắc nghiệm</option>
                            <option value="true_false">Đúng/Sai</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thứ tự</label>
                        <input type="number" name="sort_order" id="edit-sort" min="0" class="w-full border rounded px-3 py-2">
                    </div>
                </div>
                <div id="edit-options" class="space-y-2"></div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Giải thích</label>
                    <input type="text" name="explanation" id="edit-explanation" class="w-full border rounded px-3 py-2">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="bg-gray-300 hover:bg-gray-400 px-4 py-2 rounded">Hủy</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Cập nhật</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function editQuestion(q) {
    const answers = q.answers || [];
    document.getElementById('edit-content').value = q.content;
    document.getElementById('edit-type').value = q.type;
    document.getElementById('edit-sort').value = q.sort_order;
    document.getElementById('edit-form').action = '/admin/quizzes/questions/' + q.id;
    const correctAnswer = answers.find(a => a.is_correct);
    document.getElementById('edit-explanation').value = correctAnswer?.explanation || '';
    let optsHtml = '<label class="block text-sm font-medium text-gray-700">Đáp án</label>';
    answers.forEach((a, i) => {
        optsHtml += `<div class="flex items-center gap-2">
            <input type="radio" name="correct_index" value="${i}" ${a.is_correct ? 'checked' : ''} class="mr-2">
            <input type="text" name="options[]" value="${a.content}" class="flex-1 border rounded px-3 py-2">
        </div>`;
    });
    document.getElementById('edit-options').innerHTML = optsHtml;
    document.getElementById('edit-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}
</script>
@endsection