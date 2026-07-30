/**
 * fix(ui)/#50 – QuizAuthoringForm: keyboard/focus, error state, aria accessibility
 *
 * Theo checklist Issue #50:
 *  - Modal dùng AccessibleDialog (đã test riêng)
 *  - Form có label/htmlFor cho mọi input
 *  - Icon-only button có aria-label (Remove answer, Mark answer correct)
 *  - Error message dùng role="alert"
 *  - Validation: mỗi question cần ≥2 answers và 1 correct
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import QuizAuthoringForm from './QuizAuthoringForm';
import type { AdminLesson, QuizWrite } from '@/lib/api';

const mockLessons: AdminLesson[] = [
  {
    id: 1,
    title: 'Introduction to English',
    slug: 'intro-english',
    sort_order: 1,
    status: 'published',
    vocabularies_count: 10,
    quizzes_count: 0,
    course: { id: 1, title: 'English A1' },
  },
];

const initialForm: QuizWrite = {
  lesson_id: 1,
  title: 'Grammar Quiz',
  passing_score: 70,
  status: 'draft',
  questions: [
    {
      content: 'What is the capital of England?',
      explanation: 'London is the capital',
      answers: [
        { content: 'London', is_correct: true },
        { content: 'Paris', is_correct: false },
      ],
    },
  ],
};

describe('QuizAuthoringForm – renders with role="dialog"', () => {
  it('hiển thị dialog với title "Quiz authoring"', () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    expect(screen.getByRole('dialog')).toBeInTheDocument();
    expect(screen.getByText('Quiz authoring')).toBeInTheDocument();
  });
});

describe('QuizAuthoringForm – form field accessibility', () => {
  it('Lesson select có label', () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    // Label "Lesson" và select
    expect(screen.getByText(/^Lesson$/)).toBeInTheDocument();
    expect(screen.getByRole('combobox', { name: /Lesson/i })).toBeInTheDocument();
  });

  it('Title input có label', () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    expect(screen.getByText(/^Title$/)).toBeInTheDocument();
  });

  it('icon-only buttons có aria-label', () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    // Radio "Mark answer correct" có aria-label
    const radioBtn = screen.getByRole('radio', { name: /Mark answer 1 correct/i });
    expect(radioBtn).toBeInTheDocument();

    // Button "Remove answer" có aria-label
    const removeAnswerBtn = screen.getByRole('button', { name: /Remove answer 1/i });
    expect(removeAnswerBtn).toBeInTheDocument();
  });

  it('question input có label qua sr-only', () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    // Question 1 input có aria-label via id và label
    const questionInput = screen.getByDisplayValue('What is the capital of England?');
    expect(questionInput).toBeInTheDocument();
    expect(questionInput.id).toBeTruthy();
    const label = document.querySelector(`label[for="${questionInput.id}"]`);
    expect(label).not.toBeNull();
  });
});

describe('QuizAuthoringForm – Escape closes dialog', () => {
  it('gọi onClose khi nhấn Escape', async () => {
    const onClose = vi.fn();
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={onClose}
      />
    );

    // Focus vào dialog trước khi test Escape
    const dialog = screen.getByRole('dialog');
    dialog.focus();
    await userEvent.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalled();
  });
});

describe('QuizAuthoringForm – validation error', () => {
  it('hiển thị role="alert" khi question không đủ đáp án', async () => {
    const badForm: QuizWrite = {
      ...initialForm,
      questions: [
        {
          content: 'Bad question',
          answers: [{ content: 'Only one', is_correct: true }], // chỉ 1 đáp án
        },
      ],
    };

    render(
      <QuizAuthoringForm
        initial={badForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    const saveBtn = screen.getByRole('button', { name: /Save quiz/i });
    await userEvent.click(saveBtn);

    await waitFor(() => {
      const alertEl = screen.getByRole('alert');
      expect(alertEl.textContent).toMatch(/at least two answers/i);
    });
  });

  it('hiển thị role="alert" khi không có đáp án đúng', async () => {
    const noCorrectForm: QuizWrite = {
      ...initialForm,
      questions: [
        {
          content: 'No correct answer',
          answers: [
            { content: 'Option A', is_correct: false },
            { content: 'Option B', is_correct: false },
          ],
        },
      ],
    };

    render(
      <QuizAuthoringForm
        initial={noCorrectForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    await userEvent.click(screen.getByRole('button', { name: /Save quiz/i }));

    await waitFor(() => {
      const alertEl = screen.getByRole('alert');
      expect(alertEl.textContent).toMatch(/one correct answer/i);
    });
  });
});

describe('QuizAuthoringForm – save error state', () => {
  it('hiển thị role="alert" khi onSave throw error', async () => {
    const onSave = vi.fn().mockRejectedValue(new Error('Server error'));

    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={onSave}
        onClose={vi.fn()}
      />
    );

    await userEvent.click(screen.getByRole('button', { name: /Save quiz/i }));

    await waitFor(() => {
      const alertEl = screen.getByRole('alert');
      expect(alertEl.textContent).toMatch(/Server error/i);
    });
  });

  it('nút Save bị disabled khi đang saving', async () => {
    // onSave không bao giờ resolve
    const onSave = vi.fn().mockReturnValue(new Promise(() => {}));

    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={onSave}
        onClose={vi.fn()}
      />
    );

    const saveBtn = screen.getByRole('button', { name: /Save quiz/i });
    await userEvent.click(saveBtn);

    await waitFor(() =>
      expect(screen.getByRole('button', { name: /Saving/i })).toBeDisabled()
    );
  });
});

describe('QuizAuthoringForm – Add question / Add answer', () => {
  it('Add question thêm question mới vào form', async () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    // Kiểm tra Question 1 tồn tại (sử dụng getAllByText vì có cả legend và sr-only label)
    expect(screen.getAllByText('Question 1').length).toBeGreaterThan(0);
    expect(screen.queryAllByText('Question 2').length).toBe(0);

    await userEvent.click(screen.getByRole('button', { name: /Add question/i }));

    expect(screen.queryAllByText('Question 2').length).toBeGreaterThan(0);
  });

  it('Add answer thêm answer mới vào question', async () => {
    render(
      <QuizAuthoringForm
        initial={initialForm}
        lessons={mockLessons}
        onSave={vi.fn()}
        onClose={vi.fn()}
      />
    );

    // Ban đầu có 2 answer inputs (Answer 1, Answer 2)
    expect(screen.getByRole('textbox', { name: /Answer 1/i })).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: /Answer 2/i })).toBeInTheDocument();
    expect(screen.queryByRole('textbox', { name: /Answer 3/i })).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', { name: /Add answer/i }));

    expect(screen.getByRole('textbox', { name: /Answer 3/i })).toBeInTheDocument();
  });
});
