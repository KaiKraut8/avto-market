import { t } from './csrf';

// Photos are shrunk in the browser before upload, each to its share of the request budget,
// so several big phone photos still fit within the server's upload limit.
const REQUEST_BUDGET = 900 * 1024;

document.querySelectorAll('form[data-shrink-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
        const input = form.querySelector('input[type="file"]');
        if (form.dataset.ready || !input || input.files.length === 0) return;
        if (!form.checkValidity()) return;          // let the browser show what's missing first
        e.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const label = button ? button.textContent : '';
        if (button) { button.disabled = true; button.textContent = t('preparing_photos'); }

        const files = [...input.files];
        const total = files.reduce((n, f) => n + f.size, 0);
        const budget = Math.floor(REQUEST_BUDGET / files.length);
        const out = new DataTransfer();
        for (const file of files) {
            const shrinkable = /^image\/(jpeg|png|webp)$/.test(file.type);
            out.items.add(total > REQUEST_BUDGET && shrinkable && file.size > budget ? await shrink(file, budget) : file);
        }
        input.files = out.files;
        if (button) { button.textContent = label; }
        form.dataset.ready = '1';
        form.submit();
    });
});

async function shrink(file, budget) {
    try {
        const bitmap = await createImageBitmap(file);
        for (const side of [1600, 1280, 1024, 800, 640]) {
            const scale = Math.min(1, side / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            for (const quality of [0.85, 0.72, 0.6]) {
                const blob = await new Promise((r) => canvas.toBlob(r, 'image/jpeg', quality));
                if (blob && blob.size <= budget) {
                    return new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
                }
            }
        }
    } catch (err) { /* fall back to the original file */ }
    return file;
}
