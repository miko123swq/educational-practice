'use strict';

document.addEventListener('DOMContentLoaded', function () {
    initLikeButtons();
    initAjaxCommentForm();
});

function initLikeButtons() {
    const buttons = document.querySelectorAll('.like-button');

    buttons.forEach(function (button) {
        const key = button.dataset.likeKey;
        const countEl = document.querySelector('.like-count[data-like-key="' + key + '"]');
        const storageKey = 'likes_' + key;

        let likes = Number(localStorage.getItem(storageKey) || 0);
        if (countEl) {
            countEl.textContent = String(likes);
        }

        button.addEventListener('click', function () {
            likes += 1;
            localStorage.setItem(storageKey, String(likes));
            if (countEl) {
                countEl.textContent = String(likes);
            }
        });
    });
}

function initAjaxCommentForm() {
    const form = document.getElementById('commentForm');
    if (!form) {
        return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const feedback = document.getElementById('commentFeedback');
    const commentsList = document.getElementById('commentsList');

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const formData = new FormData(form);
        const originalText = submitBtn.textContent;

        submitBtn.disabled = true;
        submitBtn.textContent = 'Отправка...';
        feedback.innerHTML = '';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Не удалось добавить комментарий.');
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'card mb-3 comment-item';
            wrapper.innerHTML = '' +
                '<div class="card-body">' +
                    '<div class="d-flex justify-content-between align-items-center mb-2">' +
                        '<strong>' + escapeHtml(data.comment.user_name) + '</strong>' +
                        '<small class="text-muted">' + escapeHtml(data.comment.created_at) + '</small>' +
                    '</div>' +
                    '<p class="mb-2">' + escapeHtml(data.comment.comment) + '</p>' +
                    '<button type="button" class="btn btn-sm btn-outline-primary like-button" data-like-key="comment-' + data.comment.id + '">Нравится</button> ' +
                    '<span class="like-count" data-like-key="comment-' + data.comment.id + '">0</span>' +
                '</div>';

            commentsList.prepend(wrapper);
            initLikeButtons();
            form.reset();
            feedback.innerHTML = '<div class="alert alert-success">Комментарий успешно добавлен.</div>';
        } catch (error) {
            feedback.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message) + '</div>';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
}

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}
