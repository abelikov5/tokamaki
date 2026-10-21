const form = document.querySelector('#generation-form');
const imageInput = document.querySelector('#image');
const sourcePreview = document.querySelector('#source-preview');
const placeholder = document.querySelector('#upload-placeholder');
const submitButton = document.querySelector('#submit-button');
const errorBox = document.querySelector('#error');
const resultSection = document.querySelector('#result');

document.querySelectorAll('input[type="range"]').forEach((input) => {
    const output = document.querySelector(`output[data-for="${input.name}"]`);
    const render = () => output.textContent = `${input.value}${input.dataset.unit ?? ''}`;
    input.addEventListener('input', render);
    render();
});

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if (!file) return;
    sourcePreview.src = URL.createObjectURL(file);
    sourcePreview.hidden = false;
    placeholder.hidden = true;
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    resultSection.hidden = true;
    submitButton.disabled = true;
    submitButton.textContent = 'Строим схему…';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: new FormData(form),
        });
        const payload = await response.json();
        if (!response.ok) {
            const validationMessage = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(validationMessage ?? payload.message ?? `Ошибка ${response.status}`);
        }
        renderResult(payload);
    } catch (error) {
        errorBox.textContent = error.message || 'Не удалось обработать изображение.';
        errorBox.hidden = false;
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = 'Создать картину';
    }
});

function renderResult(payload) {
    const diagnostics = payload.diagnostics;
    document.querySelector('#stats').innerHTML = [
        [diagnostics.actual_colors, 'цветов'],
        [diagnostics.segments, 'сегментов'],
        [diagnostics.labels_placed, 'номеров'],
        [`${(diagnostics.processing_ms / 1000).toFixed(1)} с`, 'обработка'],
    ].map(([value, label]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('');

    document.querySelector('#result-preview').src = payload.preview_data_url;
    document.querySelector('#result-scheme').src = payload.svg_data_url;
    document.querySelector('#downloads').innerHTML = [
        ['svg', 'Скачать SVG'], ['pdf', 'Скачать PDF'],
        ['palette', 'Палитра JSON'], ['diagnostics', 'Диагностика'],
    ].map(([key, label]) => `<a href="${payload.downloads[key]}" target="_blank">${label}</a>`).join('');

    document.querySelector('#palette').innerHTML = payload.palette.map((color) => `
        <div class="swatch">
            <i style="background:${color.hex}"></i><strong>${color.number}</strong>
            <span>${color.hex}<small>${color.percentage.toFixed(1)}%</small></span>
        </div>`).join('');

    resultSection.hidden = false;
    resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
