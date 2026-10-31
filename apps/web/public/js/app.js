const form = document.querySelector('#generation-form');
const imageInput = document.querySelector('#image');
const sourcePreview = document.querySelector('#source-preview');
const placeholder = document.querySelector('#upload-placeholder');
const changePhoto = document.querySelector('#change-photo');
const focusCanvas = document.querySelector('#focus-canvas');
const focusContext = focusCanvas.getContext('2d');
const focusRegionsInput = document.querySelector('#focus-regions');
const focusModeButton = document.querySelector('#focus-mode');
const focusUndoButton = document.querySelector('#focus-undo');
const focusClearButton = document.querySelector('#focus-clear');
const focusStatus = document.querySelector('#focus-status');
const submitButton = document.querySelector('#submit-button');
const errorBox = document.querySelector('#error');
const formatControls = document.querySelector('#format-controls');
const imagePreparing = document.querySelector('#image-preparing');
const fitDescription = document.querySelector('#fit-description');
const fitModeButtons = document.querySelectorAll('[data-fit-mode]');
const resultSection = document.querySelector('#result');
const canvas = document.querySelector('#editor-canvas');
const context = canvas.getContext('2d');
const stage = document.querySelector('#editor-stage');
const loader = document.querySelector('#editor-loader');
const editorStatus = document.querySelector('#editor-status');
const brushSize = document.querySelector('#brush-size');
const zoomValue = document.querySelector('#zoom-value');
const applyEditsButton = document.querySelector('#apply-edits');
const undoChangeButton = document.querySelector('#undo-change');
const discardEditsButton = document.querySelector('#discard-edits');
const eraserOptions = document.querySelector('#eraser-options');
const eraserModeHelp = document.querySelector('#eraser-mode-help');
const colorCount = document.querySelector('#colors');
const autoColors = document.querySelector('#auto-colors');
const colorRange = document.querySelector('#color-range');
const savedProjectsSection = document.querySelector('#saved-projects');
const savedProjectList = document.querySelector('#saved-project-list');
const resumeLoading = document.querySelector('#resume-loading');
const saveProjectButton = document.querySelector('#save-project');
const projectName = document.querySelector('#project-name');
const projectSaveStatus = document.querySelector('#project-save-status');
const designerProcessing = document.querySelector('#designer-processing');
const orderDialogDesignerProcessing = document.querySelector('#order-dialog-designer-processing');
const orderTotal = document.querySelector('#order-total');
const orderButtons = document.querySelectorAll('.order-button');
const orderDialog = document.querySelector('#order-dialog');
const orderForm = document.querySelector('#order-form');
const orderFormPanel = document.querySelector('#order-form-panel');
const orderFormError = document.querySelector('#order-form-error');
const orderSubmit = document.querySelector('#order-submit');
const orderDialogTotal = document.querySelector('#order-dialog-total');
const orderSuccess = document.querySelector('#order-success');
const orderSuccessMessage = document.querySelector('#order-success-message');
const freePdfButton = document.querySelector('#free-pdf-button');
const uploadArea = document.querySelector('#upload-area');
const uploadComparison = document.querySelector('#upload-comparison');
const comparisonGenerated = document.querySelector('#comparison-generated');
const comparisonRange = document.querySelector('#comparison-range');
const guestResultActions = document.querySelector('#guest-result-actions');
const guestSettings = document.querySelector('.guest-settings');
const generationProgress = document.querySelector('#generation-progress');
const generationProgressPercent = document.querySelector('#generation-progress-percent');
const generationProgressFill = document.querySelector('#generation-progress-fill');
const generationProgressTime = document.querySelector('#generation-progress-time');
const samplerCanvas = document.createElement('canvas');
const samplerContext = samplerCanvas.getContext('2d', { willReadFrequently: true });
const canCustomize = document.body.dataset.canCustomize === '1';
const orderBasePrice = Number(document.body.dataset.orderBasePrice);
const orderDesignerPrice = Number(document.body.dataset.orderDesignerPrice);

let currentResult = null;
let targetNumber = null;
let currentTool = 'pan';
let currentView = 'scheme';
let zoom = 1;
let baseImage = null;
let unlabeledImage = null;
let unlabeledLoading = false;
let pointerDown = false;
let strokePoints = [];
let panStart = null;
let pendingOperations = [];
let samplerReady = false;
let samplerImageData = null;
let selectedRegions = [];
let eraserMode = 'color';
let sourceImage = null;
let preparedImageBlob = null;
let preparedImageUrl = null;
let fitMode = 'crop';
let imagePreparationRevision = 0;
let focusMode = false;
let focusRegions = [];
let focusDraft = null;
let editorBusyTimer = null;
let generationProgressTimer = null;
let generationStartedAt = null;

const PREPARED_IMAGE_WIDTH = 1600;
const PREPARED_IMAGE_HEIGHT = 2000;
const SAVED_PROJECTS_KEY = 'tokamaki.saved-projects.v1';
const MAX_SAVED_PROJECTS = 3;

comparisonRange?.addEventListener('input', () => {
    uploadComparison.style.setProperty('--comparison-position', `${comparisonRange.value}%`);
});

document.querySelectorAll('input[type="range"]').forEach((input) => {
    const key = input.name || input.id;
    const output = document.querySelector(`output[data-for="${key}"]`);
    if (!output) return;
    const render = () => {
        if (input === colorCount && autoColors.checked) {
            output.textContent = 'Авто';
            return;
        }
        output.textContent = `${input.value}${input.dataset.unit ?? ''}`;
    };
    input.addEventListener('input', render);
    render();
});

function syncColorMode() {
    if (!canCustomize || !autoColors || !colorRange) return;
    colorCount.disabled = autoColors.checked;
    colorRange.classList.toggle('muted', autoColors.checked);
    document.querySelector('output[data-for="colors"]').textContent = autoColors.checked ? 'Авто' : colorCount.value;
}

autoColors?.addEventListener('change', syncColorMode);
syncColorMode();

imageInput.addEventListener('change', async () => {
    resetPublicResultPresentation();
    const file = imageInput.files[0];
    if (!file) {
        resetPreparedImage();
        return;
    }

    const revision = ++imagePreparationRevision;
    selectFitMode('crop');
    clearFocusRegions();
    setImagePreparing(true);
    errorBox.hidden = true;

    try {
        const decodedImage = await loadSourceImage(file);
        if (revision !== imagePreparationRevision) return;
        sourceImage = decodedImage;
        formatControls.hidden = !canCustomize;
        await prepareImage(revision);
    } catch (error) {
        if (revision !== imagePreparationRevision) return;
        resetPreparedImage();
        errorBox.textContent = 'Не удалось прочитать фотографию. Выберите другой JPG или PNG.';
        errorBox.hidden = false;
    } finally {
        if (revision === imagePreparationRevision) setImagePreparing(false);
    }
});

fitModeButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        if (!sourceImage || button.dataset.fitMode === fitMode) return;
        selectFitMode(button.dataset.fitMode);
        clearFocusRegions();
        const revision = ++imagePreparationRevision;
        setImagePreparing(true);
        try {
            await prepareImage(revision);
        } catch (error) {
            if (revision !== imagePreparationRevision) return;
            errorBox.textContent = 'Не удалось подготовить фотографию.';
            errorBox.hidden = false;
        } finally {
            if (revision === imagePreparationRevision) setImagePreparing(false);
        }
    });
});

focusModeButton.addEventListener('click', () => setFocusMode(!focusMode));
focusUndoButton.addEventListener('click', () => {
    focusRegions.pop();
    syncFocusRegions();
});
focusClearButton.addEventListener('click', clearFocusRegions);
focusCanvas.addEventListener('click', (event) => event.preventDefault());
focusCanvas.addEventListener('pointerdown', startFocusRegion);
focusCanvas.addEventListener('pointermove', resizeFocusRegion);
focusCanvas.addEventListener('pointerup', finishFocusRegion);
focusCanvas.addEventListener('pointercancel', cancelFocusRegion);

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    resultSection.hidden = true;
    submitButton.disabled = true;
    submitButton.textContent = 'Строим схему…';
    try {
        if (!preparedImageBlob) throw new Error('Дождитесь подготовки фотографии.');
        startGenerationProgress();
        const formData = new FormData(form);
        formData.set('image', preparedImageBlob, preparedFileName(imageInput.files[0]?.name));
        const response = await fetch(form.action, {
            method: 'POST',
            headers: requestHeaders(),
            body: formData,
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        await completeGenerationProgress();
        zoom = 1;
        renderResult(payload, true);
    } catch (error) {
        stopGenerationProgress();
        errorBox.textContent = error.message || 'Не удалось обработать изображение.';
        errorBox.hidden = false;
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = canCustomize ? 'Создать картину' : 'Создать бесплатный макет';
    }
});

function loadSourceImage(file) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(file);
        image.onload = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(image);
        };
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Image decoding failed'));
        };
        image.src = objectUrl;
    });
}

async function prepareImage(revision) {
    const preparationCanvas = document.createElement('canvas');
    preparationCanvas.width = PREPARED_IMAGE_WIDTH;
    preparationCanvas.height = PREPARED_IMAGE_HEIGHT;
    const preparationContext = preparationCanvas.getContext('2d');
    preparationContext.imageSmoothingEnabled = true;
    preparationContext.imageSmoothingQuality = 'high';
    preparationContext.fillStyle = '#ffffff';
    preparationContext.fillRect(0, 0, PREPARED_IMAGE_WIDTH, PREPARED_IMAGE_HEIGHT);

    if (fitMode === 'stretch') {
        preparationContext.drawImage(sourceImage, 0, 0, PREPARED_IMAGE_WIDTH, PREPARED_IMAGE_HEIGHT);
    } else {
        const scale = Math.max(
            PREPARED_IMAGE_WIDTH / sourceImage.naturalWidth,
            PREPARED_IMAGE_HEIGHT / sourceImage.naturalHeight,
        );
        const width = sourceImage.naturalWidth * scale;
        const height = sourceImage.naturalHeight * scale;
        preparationContext.drawImage(
            sourceImage,
            (PREPARED_IMAGE_WIDTH - width) / 2,
            (PREPARED_IMAGE_HEIGHT - height) / 2,
            width,
            height,
        );
    }

    const blob = await canvasToBlob(preparationCanvas);
    if (revision !== imagePreparationRevision) return;
    preparedImageBlob = blob;
    if (preparedImageUrl) URL.revokeObjectURL(preparedImageUrl);
    preparedImageUrl = URL.createObjectURL(blob);
    sourcePreview.src = preparedImageUrl;
    sourcePreview.hidden = false;
    focusCanvas.width = PREPARED_IMAGE_WIDTH;
    focusCanvas.height = PREPARED_IMAGE_HEIGHT;
    focusCanvas.hidden = false;
    drawFocusRegions();
    placeholder.hidden = true;
    changePhoto.hidden = false;
}

function canvasToBlob(canvas) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => blob ? resolve(blob) : reject(new Error('Canvas export failed')), 'image/jpeg', 0.92);
    });
}

function preparedFileName(originalName = 'photo.jpg') {
    const stem = originalName.replace(/\.[^.]+$/, '') || 'photo';
    return `${stem}-40x50.jpg`;
}

function selectFitMode(mode) {
    fitMode = mode;
    fitModeButtons.forEach((item) => {
        const selected = item.dataset.fitMode === mode;
        item.classList.toggle('selected', selected);
        item.setAttribute('aria-pressed', String(selected));
    });
    fitDescription.textContent = mode === 'crop'
        ? 'Лишнее по краям обрезается автоматически, без искажения пропорций.'
        : 'Вся фотография помещается в формат 40 × 50″ с изменением пропорций.';
}

function setImagePreparing(preparing) {
    imagePreparing.hidden = !preparing;
    submitButton.disabled = preparing;
}

function resetPreparedImage() {
    ++imagePreparationRevision;
    sourceImage = null;
    preparedImageBlob = null;
    if (preparedImageUrl) URL.revokeObjectURL(preparedImageUrl);
    preparedImageUrl = null;
    sourcePreview.removeAttribute('src');
    sourcePreview.hidden = true;
    focusCanvas.hidden = true;
    clearFocusRegions();
    setFocusMode(false);
    placeholder.hidden = false;
    changePhoto.hidden = true;
    formatControls.hidden = true;
    setImagePreparing(false);
}

function setFocusMode(enabled) {
    focusMode = enabled && Boolean(sourceImage);
    focusModeButton.classList.toggle('selected', focusMode);
    focusModeButton.setAttribute('aria-pressed', String(focusMode));
    focusModeButton.textContent = focusMode ? 'Готово' : 'Выделить области';
    focusCanvas.classList.toggle('active', focusMode);
    if (focusMode) {
        focusStatus.textContent = 'Обведите прямоугольником глаза, губы или другую важную область.';
    } else {
        updateFocusStatus();
    }
}

function startFocusRegion(event) {
    if (!focusMode || focusRegions.length >= 8) return;
    event.preventDefault();
    event.stopPropagation();
    const [x, y] = focusPoint(event);
    focusDraft = { x0: x, y0: y, x1: x, y1: y };
    focusCanvas.setPointerCapture(event.pointerId);
    drawFocusRegions();
}

function resizeFocusRegion(event) {
    if (!focusDraft) return;
    event.preventDefault();
    const [x, y] = focusPoint(event);
    focusDraft.x1 = x;
    focusDraft.y1 = y;
    drawFocusRegions();
}

function finishFocusRegion(event) {
    if (!focusDraft) return;
    event.preventDefault();
    event.stopPropagation();
    const rectangle = normalizedFocusRectangle(focusDraft);
    focusDraft = null;
    if (focusCanvas.hasPointerCapture(event.pointerId)) focusCanvas.releasePointerCapture(event.pointerId);
    if (rectangle.width >= 0.015 && rectangle.height >= 0.015) {
        focusRegions.push(rectangle);
        syncFocusRegions();
        return;
    }
    focusStatus.textContent = 'Область слишком мала. Обведите нужный фрагмент прямоугольником.';
    drawFocusRegions();
}

function cancelFocusRegion(event) {
    focusDraft = null;
    if (focusCanvas.hasPointerCapture(event.pointerId)) focusCanvas.releasePointerCapture(event.pointerId);
    drawFocusRegions();
}

function focusPoint(event) {
    const bounds = focusCanvas.getBoundingClientRect();
    return [
        Math.min(PREPARED_IMAGE_WIDTH, Math.max(0, (event.clientX - bounds.left) * PREPARED_IMAGE_WIDTH / bounds.width)),
        Math.min(PREPARED_IMAGE_HEIGHT, Math.max(0, (event.clientY - bounds.top) * PREPARED_IMAGE_HEIGHT / bounds.height)),
    ];
}

function normalizedFocusRectangle(rectangle) {
    const left = Math.min(rectangle.x0, rectangle.x1);
    const top = Math.min(rectangle.y0, rectangle.y1);
    const right = Math.max(rectangle.x0, rectangle.x1);
    const bottom = Math.max(rectangle.y0, rectangle.y1);
    return {
        x: roundCoordinate(left / PREPARED_IMAGE_WIDTH),
        y: roundCoordinate(top / PREPARED_IMAGE_HEIGHT),
        width: roundCoordinate((right - left) / PREPARED_IMAGE_WIDTH),
        height: roundCoordinate((bottom - top) / PREPARED_IMAGE_HEIGHT),
    };
}

function roundCoordinate(value) {
    return Math.round(value * 10_000) / 10_000;
}

function clearFocusRegions() {
    focusRegions = [];
    focusDraft = null;
    syncFocusRegions();
}

function syncFocusRegions() {
    focusRegionsInput.value = JSON.stringify(focusRegions);
    focusUndoButton.disabled = focusRegions.length === 0;
    focusClearButton.disabled = focusRegions.length === 0;
    updateFocusStatus();
    drawFocusRegions();
}

function updateFocusStatus() {
    const count = focusRegions.length;
    focusStatus.textContent = count
        ? `Выбрано важных областей: ${count}. В них будут точнее сохранены детали и исходные цвета.`
        : 'Выделите глаза, губы или другие места, где важны детали и точный цвет.';
}

function drawFocusRegions() {
    if (!focusCanvas.width || !focusCanvas.height) return;
    focusContext.clearRect(0, 0, focusCanvas.width, focusCanvas.height);
    focusRegions.forEach((region, index) => {
        drawFocusRectangle(
            region.x * PREPARED_IMAGE_WIDTH,
            region.y * PREPARED_IMAGE_HEIGHT,
            region.width * PREPARED_IMAGE_WIDTH,
            region.height * PREPARED_IMAGE_HEIGHT,
            index + 1,
        );
    });
    if (focusDraft) {
        const left = Math.min(focusDraft.x0, focusDraft.x1);
        const top = Math.min(focusDraft.y0, focusDraft.y1);
        drawFocusRectangle(
            left,
            top,
            Math.abs(focusDraft.x1 - focusDraft.x0),
            Math.abs(focusDraft.y1 - focusDraft.y0),
            focusRegions.length + 1,
        );
    }
}

function drawFocusRectangle(x, y, width, height, number) {
    focusContext.save();
    focusContext.fillStyle = 'rgba(245, 89, 66, .18)';
    focusContext.strokeStyle = '#f55942';
    focusContext.lineWidth = 8;
    focusContext.setLineDash([24, 14]);
    focusContext.fillRect(x, y, width, height);
    focusContext.strokeRect(x, y, width, height);
    focusContext.setLineDash([]);
    focusContext.beginPath();
    focusContext.fillStyle = '#f55942';
    focusContext.arc(x + 26, y + 26, 22, 0, Math.PI * 2);
    focusContext.fill();
    focusContext.fillStyle = '#fff';
    focusContext.font = '700 26px Arial, sans-serif';
    focusContext.textAlign = 'center';
    focusContext.textBaseline = 'middle';
    focusContext.fillText(String(number), x + 26, y + 27);
    focusContext.restore();
}

document.querySelectorAll('[data-tool]').forEach((button) => {
    button.addEventListener('click', () => selectTool(button.dataset.tool));
});
document.querySelectorAll('[data-eraser-mode]').forEach((button) => {
    button.addEventListener('click', () => {
        eraserMode = button.dataset.eraserMode;
        document.querySelectorAll('[data-eraser-mode]').forEach((item) => {
            item.classList.toggle('selected', item === button);
        });
        updateToolOptions();
    });
});
document.querySelectorAll('[data-view]').forEach((button) => {
    button.addEventListener('click', () => {
        currentView = button.dataset.view;
        document.querySelectorAll('[data-view]').forEach((item) => item.classList.toggle('selected', item === button));
        loadEditorImage(false);
    });
});

document.querySelector('#zoom-in').addEventListener('click', () => setZoom(zoom * 1.25));
document.querySelector('#zoom-out').addEventListener('click', () => setZoom(zoom / 1.25));
document.querySelector('#zoom-fit').addEventListener('click', fitEditor);
applyEditsButton.addEventListener('click', applyPendingEdits);
undoChangeButton.addEventListener('click', undoLastChange);
discardEditsButton.addEventListener('click', discardPendingEdits);
saveProjectButton.addEventListener('click', saveProject);
document.addEventListener('keydown', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const isEditable = target?.matches('input, textarea, select, [contenteditable="true"]');
    if (event.ctrlKey && !event.altKey && event.key.toLowerCase() === 'z' && !isEditable) {
        event.preventDefault();
        undoLastChange();
        return;
    }
    if (event.key !== 'Enter' || !pendingOperations.length || loader.hidden === false) return;
    if (target?.closest('#generation-form')) return;
    event.preventDefault();
    applyPendingEdits();
});
stage.addEventListener('wheel', (event) => {
    if (!event.ctrlKey) return;
    event.preventDefault();
    setZoom(zoom * (event.deltaY < 0 ? 1.12 : 1 / 1.12));
}, { passive: false });

canvas.addEventListener('pointerdown', (event) => {
    if (!currentResult || loader.hidden === false) return;
    if (currentTool === 'eyedropper') {
        pickColor(eventPoint(event));
        return;
    }
    if (currentTool === 'select') {
        selectRegion(eventPoint(event), event.altKey);
        return;
    }
    if (currentTool === 'label') {
        queueLabel(eventPoint(event));
        return;
    }
    if (currentTool === 'unlabeled') return;
    pointerDown = true;
    canvas.setPointerCapture(event.pointerId);
    if (currentTool === 'pan') {
        panStart = { x: event.clientX, y: event.clientY, left: stage.scrollLeft, top: stage.scrollTop };
        canvas.classList.add('grabbing');
        return;
    }
    strokePoints = [eventPoint(event)];
    drawEditor();
});

canvas.addEventListener('pointermove', (event) => {
    if (!pointerDown) return;
    if (currentTool === 'pan') {
        stage.scrollLeft = panStart.left - (event.clientX - panStart.x);
        stage.scrollTop = panStart.top - (event.clientY - panStart.y);
        return;
    }
    const point = eventPoint(event);
    const previous = strokePoints[strokePoints.length - 1];
    if (!previous || Math.hypot(point[0] - previous[0], point[1] - previous[1]) >= 1.5) {
        strokePoints.push(point);
        drawEditor();
    }
});

canvas.addEventListener('pointerup', finishPointer);
canvas.addEventListener('pointercancel', finishPointer);

function finishPointer(event) {
    if (!pointerDown) return;
    pointerDown = false;
    canvas.classList.remove('grabbing');
    if (canvas.hasPointerCapture(event.pointerId)) canvas.releasePointerCapture(event.pointerId);
    if (currentTool === 'pan') return;
    queueStroke();
}

function queueStroke() {
    const operation = {
        brush: 'paint_stroke',
        eraser: 'erase_stroke',
        smooth: 'smooth_stroke',
        magic: 'magic_stroke',
    }[currentTool];
    if (!operation || !strokePoints.length) return;
    if (strokePoints.length === 1) strokePoints.push(strokePoints[0]);
    const pendingOperation = {
        operation,
        points: strokePoints.map((point) => [...point]),
        radius: Number(brushSize.value),
    };
    if (operation === 'paint_stroke') {
        pendingOperation.target_number = targetNumber;
    }
    if (operation === 'erase_stroke') {
        pendingOperation.erase_mode = eraserMode;
        if (eraserMode === 'color') pendingOperation.target_number = targetNumber;
    }
    pendingOperations.push(pendingOperation);
    strokePoints = [];
    updatePendingUi();
    drawEditor();
}

async function applyPendingEdits() {
    if (!pendingOperations.length || !currentResult) return true;
    setEditorBusy(true, 'Перестраиваем контуры и номера…');
    try {
        const response = await fetch(currentResult.edit_url, {
            method: 'POST',
            headers: { ...requestHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ operations: pendingOperations }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        pendingOperations = [];
        renderResult(payload, false);
        editorStatus.textContent = 'Все правки применены. SVG и PDF обновлены.';
        return true;
    } catch (error) {
        editorStatus.textContent = error.message || 'Не удалось применить правку.';
        editorStatus.classList.add('failed');
        drawEditor();
        return false;
    } finally {
        setEditorBusy(false);
        updatePendingUi();
    }
}

function discardPendingEdits() {
    pendingOperations = [];
    strokePoints = [];
    editorStatus.textContent = 'Неприменённые правки отменены.';
    editorStatus.classList.remove('failed');
    updatePendingUi();
    drawEditor();
}

async function undoLastChange() {
    if (!currentResult || loader.hidden === false) return;
    if (pendingOperations.length) {
        pendingOperations.pop();
        strokePoints = [];
        updatePendingUi();
        drawEditor();
        editorStatus.textContent = 'Последняя неприменённая правка отменена.';
        editorStatus.classList.remove('failed');
        return;
    }
    if (!currentResult.can_undo) {
        editorStatus.textContent = 'Нет сохранённого изменения для отмены.';
        editorStatus.classList.remove('failed');
        return;
    }

    setEditorBusy(true, 'Возвращаем предыдущую версию…');
    try {
        const response = await fetch(currentResult.undo_url, {
            method: 'POST',
            headers: requestHeaders(),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        renderResult(payload, false);
        editorStatus.textContent = 'Последнее сохранённое изменение отменено.';
    } catch (error) {
        editorStatus.textContent = error.message || 'Не удалось отменить последнее изменение.';
        editorStatus.classList.add('failed');
    } finally {
        setEditorBusy(false);
        updatePendingUi();
    }
}

function renderResult(payload, fitAfterLoad) {
    hideGenerationProgress();
    currentResult = payload;
    unlabeledImage = null;
    unlabeledLoading = false;
    pendingOperations = [];
    strokePoints = [];
    selectedRegions = [];
    targetNumber = payload.palette.some((color) => color.number === targetNumber)
        ? targetNumber
        : payload.palette[0]?.number ?? null;
    if (canCustomize) {
        editorStatus.textContent = '';
        editorStatus.classList.remove('failed');
        projectName.textContent = payload.original_name || 'Сохранённая картина';
        projectSaveStatus.textContent = 'Применённые изменения сохраняются автоматически.';
    }
    const diagnostics = payload.diagnostics;
    document.querySelector('#stats').innerHTML = [
        [diagnostics.actual_colors, 'цветов'],
        [diagnostics.segments, 'сегментов'],
        [diagnostics.labels_placed, 'номеров'],
        [`${(diagnostics.processing_ms / 1000).toFixed(1)} с`, 'обработка'],
    ].map(([value, label]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('');
    const downloadItems = canCustomize
        ? [
            ['svg', 'Скачать SVG'], ['pdf', 'Скачать PDF'],
            ['palette', 'Палитра JSON'], ['diagnostics', 'Диагностика'],
        ]
        : [];
    document.querySelector('#downloads').innerHTML = downloadItems
        .map(([key, label]) => `<a href="${payload.downloads[key]}" target="_blank">${label}</a>`)
        .join('');
    document.querySelector('#palette').innerHTML = payload.palette.map((color) => `
        <div class="swatch"><i style="background:${color.hex}"></i><strong>${color.number}</strong>
        <span>${color.hex}<small>${color.percentage.toFixed(1)}%</small></span></div>`).join('');
    if (canCustomize) {
        renderEditorPalette();
        loadSamplerImage();
        updatePendingUi();
        rememberProject(payload);
    } else {
        renderPublicComparison(payload);
        updateOrderLink(payload);
    }
    if (window.location.href !== payload.resume_url) {
        window.history.replaceState({}, '', payload.resume_url);
    }
    resultSection.hidden = false;
    if (canCustomize) loadEditorImage(fitAfterLoad);
    if (fitAfterLoad) {
        (canCustomize ? resultSection : form).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function renderPublicComparison(payload) {
    if (!uploadArea || !uploadComparison || !comparisonGenerated || !comparisonRange || !freePdfButton || !guestResultActions) return;
    const preparedSource = sourcePreview.src;
    comparisonRange.value = '50';
    uploadComparison.style.setProperty('--comparison-position', '50%');
    sourcePreview.src = payload.downloads.original;
    sourcePreview.onerror = () => {
        sourcePreview.onerror = null;
        sourcePreview.src = preparedSource || payload.downloads.preview;
    };
    sourcePreview.hidden = false;
    placeholder.hidden = true;
    changePhoto.hidden = false;
    comparisonGenerated.src = payload.downloads.svg;
    uploadComparison.hidden = false;
    uploadArea.classList.add('showing-comparison');
    focusCanvas.hidden = true;
    submitButton.hidden = true;
    if (guestSettings) guestSettings.hidden = true;
    guestResultActions.hidden = false;
    freePdfButton.href = payload.downloads.pdf;
    freePdfButton.target = '_blank';
}

function resetPublicResultPresentation() {
    if (canCustomize || !uploadComparison || !guestResultActions) return;
    uploadComparison.hidden = true;
    uploadArea.classList.remove('showing-comparison');
    guestResultActions.hidden = true;
    if (guestSettings) guestSettings.hidden = false;
    submitButton.hidden = false;
    changePhoto.hidden = !preparedImageBlob;
}

function startGenerationProgress() {
    if (!generationProgress) return;
    clearInterval(generationProgressTimer);
    generationStartedAt = performance.now();
    generationProgress.hidden = false;
    if (!canCustomize) submitButton.hidden = true;
    updateGenerationProgress(4, 0);
    generationProgressTimer = window.setInterval(() => {
        const elapsedSeconds = Math.max(0, (performance.now() - generationStartedAt) / 1000);
        const estimatedPercent = Math.min(92, Math.floor(8 + 84 * (1 - Math.exp(-elapsedSeconds / 30))));
        updateGenerationProgress(estimatedPercent, elapsedSeconds);
    }, 350);
}

async function completeGenerationProgress() {
    if (!generationProgress) return;
    clearInterval(generationProgressTimer);
    const elapsedSeconds = generationStartedAt
        ? Math.max(0, (performance.now() - generationStartedAt) / 1000)
        : 0;
    updateGenerationProgress(100, elapsedSeconds);
    await new Promise((resolve) => window.setTimeout(resolve, 280));
}

function updateGenerationProgress(percent, elapsedSeconds) {
    generationProgress.setAttribute('aria-valuenow', String(percent));
    generationProgressPercent.textContent = `${percent}%`;
    generationProgressFill.style.width = `${percent}%`;
    generationProgressTime.textContent = `Прошло: ${Math.floor(elapsedSeconds)} сек`;
}

function stopGenerationProgress() {
    clearInterval(generationProgressTimer);
    generationProgressTimer = null;
    generationStartedAt = null;
    hideGenerationProgress();
    if (!canCustomize) submitButton.hidden = false;
}

function hideGenerationProgress() {
    if (!generationProgress) return;
    generationProgress.hidden = true;
}

function updateOrderLink(payload = currentResult) {
    if (!orderButtons.length || !orderTotal || !payload) return;
    const withDesigner = Boolean(designerProcessing?.checked);
    const total = orderBasePrice + (withDesigner ? orderDesignerPrice : 0);
    const formattedTotal = new Intl.NumberFormat('ru-RU').format(total);
    const formattedPrice = `${formattedTotal}\u00a0₽`;
    orderTotal.textContent = formattedPrice;
    if (orderDialogTotal) orderDialogTotal.textContent = formattedPrice;
    orderButtons.forEach((button) => {
        button.textContent = `Заказать полный набор за ${formattedPrice}`;
    });
}

designerProcessing?.addEventListener('change', () => {
    if (orderDialogDesignerProcessing) orderDialogDesignerProcessing.checked = designerProcessing.checked;
    updateOrderLink();
});

orderDialogDesignerProcessing?.addEventListener('change', () => {
    if (designerProcessing) designerProcessing.checked = orderDialogDesignerProcessing.checked;
    updateOrderLink();
});

orderButtons.forEach((button) => {
    button.addEventListener('click', () => {
        if (!currentResult?.order_url || !orderDialog) return;
        orderFormPanel.hidden = false;
        orderSuccess.hidden = true;
        orderFormError.hidden = true;
        if (orderDialogDesignerProcessing) {
            orderDialogDesignerProcessing.checked = Boolean(designerProcessing?.checked);
        }
        orderDialog.showModal();
    });
});

document.querySelectorAll('[data-order-dialog-close]').forEach((button) => {
    button.addEventListener('click', () => orderDialog?.close());
});

orderDialog?.addEventListener('click', (event) => {
    if (event.target === orderDialog) orderDialog.close();
});

orderForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!currentResult?.order_url || orderSubmit.disabled) return;

    orderSubmit.disabled = true;
    orderSubmit.textContent = 'Сохраняем заказ…';
    orderFormError.hidden = true;

    const fields = new FormData(orderForm);
    try {
        const response = await fetch(currentResult.order_url, {
            method: 'POST',
            headers: { ...requestHeaders(), 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: fields.get('email'),
                city: fields.get('city'),
                street: fields.get('street'),
                designer_processing: Boolean(orderDialogDesignerProcessing?.checked),
            }),
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));

        orderFormPanel.hidden = true;
        orderSuccess.hidden = false;
        orderSuccessMessage.textContent = `Заказ №${payload.order_id}. ${payload.message}`;
        orderForm.reset();
    } catch (error) {
        orderFormError.textContent = error.message;
        orderFormError.hidden = false;
    } finally {
        orderSubmit.disabled = false;
        orderSubmit.textContent = 'Оформить заказ';
    }
});

function loadEditorImage(fitAfterLoad) {
    if (!currentResult) return;
    const image = new Image();
    image.onload = () => {
        baseImage = image;
        canvas.width = currentResult.diagnostics.processing_width;
        canvas.height = currentResult.diagnostics.processing_height;
        drawEditor();
        if (fitAfterLoad) {
            requestAnimationFrame(() => requestAnimationFrame(fitEditor));
        } else {
            applyZoom();
        }
    };
    image.src = currentView === 'scheme' ? currentResult.svg_data_url : currentResult.preview_data_url;
}

function drawEditor() {
    if (!baseImage) return;
    context.clearRect(0, 0, canvas.width, canvas.height);
    context.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
    if (currentTool === 'unlabeled' && unlabeledImage) {
        context.drawImage(unlabeledImage, 0, 0, canvas.width, canvas.height);
    }
    selectedRegions.forEach((region) => {
        context.drawImage(region.overlay.canvas, region.overlay.x, region.overlay.y);
    });
    pendingOperations.forEach((operation) => {
        if (operation.operation === 'add_label') {
            drawLabelOverlay(operation);
            return;
        }
        drawStrokeOverlay(
            operation.points,
            operation.radius,
            operation.operation,
            operation.target_number,
            operation.erase_mode,
        );
    });
    if (strokePoints.length) {
        const operation = {
            brush: 'paint_stroke',
            eraser: 'erase_stroke',
            smooth: 'smooth_stroke',
            magic: 'magic_stroke',
        }[currentTool];
        drawStrokeOverlay(strokePoints, Number(brushSize.value), operation, targetNumber, eraserMode);
    }
}

function drawStrokeOverlay(points, radius, operation, colorNumber, operationEraserMode = null) {
    if (!points.length || !operation) return;
    const paletteColor = currentResult?.palette.find((color) => color.number === colorNumber)?.hex;
    context.save();
    context.strokeStyle = operation === 'smooth_stroke'
        ? 'rgba(42, 132, 211, .65)'
        : operation === 'magic_stroke'
            ? 'rgba(145, 74, 201, .68)'
        : operation === 'erase_stroke'
            ? operationEraserMode === 'local'
                ? 'rgba(255, 255, 255, .96)'
                : 'rgba(225, 61, 48, .60)'
            : hexToRgba(paletteColor ?? '#1f624b', .62);
    context.fillStyle = context.strokeStyle;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.lineWidth = radius * 2;
    context.beginPath();
    context.moveTo(points[0][0], points[0][1]);
    points.slice(1).forEach(([x, y]) => context.lineTo(x, y));
    context.stroke();
    if (points.length === 1) {
        context.beginPath();
        context.arc(points[0][0], points[0][1], radius, 0, Math.PI * 2);
        context.fill();
    }
    context.restore();
}

function drawLabelOverlay(operation) {
    const fontSize = Math.max(8, Math.min(18, canvas.width * 0.012));
    context.save();
    context.font = `700 ${fontSize}px Arial, sans-serif`;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.lineWidth = Math.max(2, fontSize * 0.28);
    context.strokeStyle = 'rgba(255,255,255,.96)';
    context.fillStyle = '#111';
    context.strokeText(String(operation.target_number), operation.x, operation.y);
    context.fillText(String(operation.target_number), operation.x, operation.y);
    context.restore();
}

function loadSamplerImage() {
    samplerReady = false;
    samplerImageData = null;
    const image = new Image();
    image.onload = () => {
        samplerCanvas.width = currentResult.diagnostics.processing_width;
        samplerCanvas.height = currentResult.diagnostics.processing_height;
        samplerContext.clearRect(0, 0, samplerCanvas.width, samplerCanvas.height);
        samplerContext.drawImage(image, 0, 0, samplerCanvas.width, samplerCanvas.height);
        samplerImageData = samplerContext.getImageData(0, 0, samplerCanvas.width, samplerCanvas.height);
        samplerReady = true;
    };
    image.src = currentResult.preview_data_url;
}

function loadUnlabeledOverlay() {
    const url = currentResult?.unlabeled_data_url;
    if (!url || unlabeledImage || unlabeledLoading) return;
    unlabeledLoading = true;
    updateToolOptions();
    const image = new Image();
    image.onload = () => {
        if (currentResult?.unlabeled_data_url !== url) return;
        unlabeledLoading = false;
        unlabeledImage = image;
        updateToolOptions();
        if (currentTool === 'unlabeled') drawEditor();
    };
    image.onerror = () => {
        if (currentResult?.unlabeled_data_url !== url) return;
        unlabeledLoading = false;
        unlabeledImage = null;
        if (currentTool === 'unlabeled') {
            editorStatus.textContent = 'Не удалось загрузить подсветку областей без номеров.';
            editorStatus.classList.add('failed');
        }
    };
    image.src = url;
}

function pickColor([x, y]) {
    if (!samplerReady) {
        editorStatus.textContent = 'Цветное превью ещё загружается.';
        return;
    }
    const selected = paletteColorAt(x, y);
    if (!selected) return;
    targetNumber = selected.number;
    renderEditorPalette();
    editorStatus.textContent = `Пипетка выбрала цвет №${selected.number} (${selected.hex}).`;
    editorStatus.classList.remove('failed');
}

function paletteColorAt(x, y) {
    if (!samplerReady) return null;
    const [red, green, blue] = samplerContext.getImageData(Math.round(x), Math.round(y), 1, 1).data;
    return currentResult.palette.reduce((closest, color) => {
        const distance = Math.hypot(red - color.rgb[0], green - color.rgb[1], blue - color.rgb[2]);
        return !closest || distance < closest.distance ? { color, distance } : closest;
    }, null)?.color;
}

function selectRegion([x, y], addToSelection) {
    if (!samplerReady) {
        editorStatus.textContent = 'Цветное превью ещё загружается.';
        return;
    }
    const pointX = Math.round(x);
    const pointY = Math.round(y);
    const selectedIndex = selectedRegions.findIndex((region) => regionContainsPoint(region, pointX, pointY));
    if (addToSelection && selectedIndex >= 0) {
        selectedRegions.splice(selectedIndex, 1);
        updateRegionSelectionStatus();
        drawEditor();
        return;
    }
    const selected = paletteColorAt(x, y);
    if (!selected) return;
    const region = {
        x: pointX,
        y: pointY,
        sourceNumber: selected.number,
        overlay: createRegionOverlay(pointX, pointY, '#1f624b', 105),
    };
    selectedRegions = addToSelection ? [...selectedRegions, region] : [region];
    updateRegionSelectionStatus();
    drawEditor();
}

function regionContainsPoint(region, x, y) {
    const overlay = region.overlay;
    if (x < overlay.x || x >= overlay.x + overlay.canvas.width
        || y < overlay.y || y >= overlay.y + overlay.canvas.height) return false;
    return overlay.canvas.getContext('2d').getImageData(x - overlay.x, y - overlay.y, 1, 1).data[3] > 0;
}

function updateRegionSelectionStatus() {
    const count = selectedRegions.length;
    editorStatus.textContent = count
        ? `${pluralizeRegions(count) === 'область' ? 'Выбрана' : 'Выбрано'} ${count} ${pluralizeRegions(count)}. Alt + клик добавляет или убирает области. Теперь выберите цвет.`
        : 'Выделение снято. Щёлкните по области, чтобы выбрать её.';
    editorStatus.classList.remove('failed');
}

function recolorSelectedRegions(color) {
    if (!selectedRegions.length) return;
    let changedCount = 0;
    selectedRegions.forEach((region) => {
        let operationIndex = -1;
        for (let index = pendingOperations.length - 1; index >= 0; index--) {
            const operation = pendingOperations[index];
            if (operation.operation === 'recolor_region'
                && operation.x === region.x && operation.y === region.y) {
                operationIndex = index;
                break;
            }
        }
        if (color.number === region.sourceNumber) {
            if (operationIndex >= 0) pendingOperations.splice(operationIndex, 1);
            region.overlay = createRegionOverlay(region.x, region.y, '#1f624b', 105);
            return;
        }
        changedCount++;
        if (operationIndex >= 0) {
            pendingOperations[operationIndex].target_number = color.number;
        } else {
            pendingOperations.push({
                operation: 'recolor_region',
                x: region.x,
                y: region.y,
                target_number: color.number,
            });
        }
        region.overlay = createRegionOverlay(region.x, region.y, color.hex, 190);
    });
    updatePendingUi();
    if (changedCount) {
        const recolorVerb = pluralizeRegions(changedCount) === 'область'
            ? 'будет перекрашена'
            : 'будут перекрашены';
        editorStatus.textContent = `${changedCount} ${pluralizeRegions(changedCount)} ${recolorVerb} в цвет №${color.number} (${color.hex}).`;
    } else {
        editorStatus.textContent = 'Для всех выбранных областей оставлен исходный цвет.';
    }
    drawEditor();
}

function createRegionOverlay(startX, startY, color, alpha) {
    const width = samplerCanvas.width;
    const height = samplerCanvas.height;
    const source = samplerImageData.data;
    const startOffset = (startY * width + startX) * 4;
    const sourceColor = [source[startOffset], source[startOffset + 1], source[startOffset + 2]];
    const overlayContext = document.createElement('canvas').getContext('2d');
    const overlay = overlayContext.createImageData(width, height);
    const target = hexToRgb(color);
    const stack = [[startX, startY]];
    let minX = startX;
    let maxX = startX;
    let minY = startY;
    let maxY = startY;

    const matchesSource = (x, y) => {
        if (x < 0 || x >= width || y < 0 || y >= height) return false;
        const offset = (y * width + x) * 4;
        return overlay.data[offset + 3] === 0
            && source[offset] === sourceColor[0]
            && source[offset + 1] === sourceColor[1]
            && source[offset + 2] === sourceColor[2];
    };

    while (stack.length) {
        const [seedX, seedY] = stack.pop();
        let left = seedX;
        while (matchesSource(left - 1, seedY)) left--;
        let spanAbove = false;
        let spanBelow = false;
        for (let currentX = left; matchesSource(currentX, seedY); currentX++) {
            const offset = (seedY * width + currentX) * 4;
            overlay.data[offset] = target[0];
            overlay.data[offset + 1] = target[1];
            overlay.data[offset + 2] = target[2];
            overlay.data[offset + 3] = alpha;
            minX = Math.min(minX, currentX);
            maxX = Math.max(maxX, currentX);
            minY = Math.min(minY, seedY);
            maxY = Math.max(maxY, seedY);
            if (matchesSource(currentX, seedY - 1)) {
                if (!spanAbove) stack.push([currentX, seedY - 1]);
                spanAbove = true;
            } else {
                spanAbove = false;
            }
            if (matchesSource(currentX, seedY + 1)) {
                if (!spanBelow) stack.push([currentX, seedY + 1]);
                spanBelow = true;
            } else {
                spanBelow = false;
            }
        }
    }
    const croppedCanvas = document.createElement('canvas');
    croppedCanvas.width = maxX - minX + 1;
    croppedCanvas.height = maxY - minY + 1;
    croppedCanvas.getContext('2d').putImageData(
        overlay,
        -minX,
        -minY,
        minX,
        minY,
        croppedCanvas.width,
        croppedCanvas.height,
    );
    return { canvas: croppedCanvas, x: minX, y: minY };
}

function queueLabel([x, y]) {
    if (!samplerReady) {
        editorStatus.textContent = 'Цветное превью ещё загружается.';
        return;
    }
    const selected = paletteColorAt(x, y);
    if (!selected) return;
    pendingOperations.push({
        operation: 'add_label',
        x: Math.round(x),
        y: Math.round(y),
        target_number: selected.number,
    });
    updatePendingUi();
    drawEditor();
}

function updatePendingUi() {
    const count = pendingOperations.length;
    applyEditsButton.disabled = count === 0;
    discardEditsButton.disabled = count === 0;
    undoChangeButton.disabled = count === 0 && !currentResult?.can_undo;
    applyEditsButton.firstChild.textContent = count ? `Применить изменения (${count}) ` : 'Применить изменения ';
    if (count) {
        const verb = count === 1 ? 'ожидает' : 'ожидают';
        editorStatus.textContent = `${count} ${pluralizeEdits(count)} ${verb} применения.`;
        editorStatus.classList.remove('failed');
        projectSaveStatus.textContent = 'Есть неприменённые изменения.';
    } else if (currentResult) {
        projectSaveStatus.textContent = 'Все изменения сохранены.';
    }
}

async function saveProject() {
    if (!currentResult) return;
    saveProjectButton.disabled = true;
    saveProjectButton.textContent = pendingOperations.length ? 'Сохраняем…' : 'Копируем ссылку…';
    try {
        if (pendingOperations.length && !(await applyPendingEdits())) return;
        rememberProject(currentResult);
        await copyText(currentResult.resume_url);
        projectSaveStatus.textContent = 'Проект сохранён. Ссылка скопирована.';
    } catch (error) {
        projectSaveStatus.textContent = 'Проект сохранён, но ссылку не удалось скопировать.';
    } finally {
        saveProjectButton.disabled = false;
        saveProjectButton.textContent = 'Сохранить проект';
    }
}

async function copyText(value) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
        return;
    }
    const temporaryInput = document.createElement('textarea');
    temporaryInput.value = value;
    temporaryInput.style.position = 'fixed';
    temporaryInput.style.opacity = '0';
    document.body.appendChild(temporaryInput);
    temporaryInput.select();
    document.execCommand('copy');
    temporaryInput.remove();
}

function rememberProject(payload) {
    if (!payload?.resume_url) return;
    const project = {
        url: payload.resume_url,
        name: payload.original_name || 'Картина без названия',
        updatedAt: payload.updated_at || new Date().toISOString(),
    };
    const projects = readSavedProjects().filter((item) => item.url !== project.url);
    projects.unshift(project);
    try {
        localStorage.setItem(SAVED_PROJECTS_KEY, JSON.stringify(projects.slice(0, MAX_SAVED_PROJECTS)));
    } catch (error) {
        return;
    }
    renderSavedProjects();
}

function readSavedProjects() {
    try {
        const projects = JSON.parse(localStorage.getItem(SAVED_PROJECTS_KEY) || '[]');
        return Array.isArray(projects) ? projects.filter((item) => item?.url && item?.name) : [];
    } catch (error) {
        return [];
    }
}

function renderSavedProjects() {
    if (!canCustomize) {
        savedProjectsSection.hidden = true;
        return;
    }
    const storedProjects = readSavedProjects();
    const projects = storedProjects.slice(0, MAX_SAVED_PROJECTS);
    if (storedProjects.length !== projects.length) {
        try {
            localStorage.setItem(SAVED_PROJECTS_KEY, JSON.stringify(projects));
        } catch (error) {
            // The list can still be rendered when storage is unavailable.
        }
    }
    savedProjectList.replaceChildren();
    savedProjectsSection.hidden = projects.length === 0;
    projects.forEach((project) => {
        const card = document.createElement('div');
        card.className = 'saved-project-card';
        const link = document.createElement('a');
        link.href = project.url;
        const name = document.createElement('strong');
        name.textContent = project.name;
        const date = document.createElement('span');
        date.textContent = `Изменено ${formatProjectDate(project.updatedAt)}`;
        link.append(name, date);
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'saved-project-remove';
        removeButton.setAttribute('aria-label', `Удалить ${project.name} из истории`);
        removeButton.title = 'Удалить из истории';
        removeButton.textContent = '×';
        removeButton.addEventListener('click', () => removeSavedProject(project.url));
        card.append(link, removeButton);
        savedProjectList.append(card);
    });
}

function removeSavedProject(url) {
    const projects = readSavedProjects().filter((project) => project.url !== url).slice(0, MAX_SAVED_PROJECTS);
    try {
        localStorage.setItem(SAVED_PROJECTS_KEY, JSON.stringify(projects));
    } catch (error) {
        return;
    }
    renderSavedProjects();
}

function formatProjectDate(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? 'недавно'
        : new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
}

async function loadResumedProject() {
    const endpoint = document.body.dataset.resumeEndpoint;
    if (!endpoint) return;
    resumeLoading.hidden = false;
    try {
        const response = await fetch(endpoint, { headers: requestHeaders() });
        const payload = await response.json();
        if (!response.ok) throw new Error(responseMessage(payload, response.status));
        renderResult(payload, true);
    } catch (error) {
        errorBox.textContent = error.message || 'Не удалось загрузить сохранённую картину.';
        errorBox.hidden = false;
    } finally {
        resumeLoading.hidden = true;
    }
}

function pluralizeEdits(count) {
    const lastTwo = count % 100;
    const last = count % 10;
    if (last === 1 && lastTwo !== 11) return 'правка';
    if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return 'правки';
    return 'правок';
}

function pluralizeRegions(count) {
    const lastTwo = count % 100;
    const last = count % 10;
    if (last === 1 && lastTwo !== 11) return 'область';
    if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) return 'области';
    return 'областей';
}

function hexToRgba(hex, alpha) {
    const value = Number.parseInt(hex.slice(1), 16);
    return `rgba(${value >> 16}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
}

function hexToRgb(hex) {
    const value = Number.parseInt(hex.slice(1), 16);
    return [value >> 16, (value >> 8) & 255, value & 255];
}

function renderEditorPalette() {
    if (!currentResult) return;
    document.querySelector('#editor-palette').innerHTML = currentResult.palette.map((color) => `
        <button type="button" class="palette-choice${color.number === targetNumber ? ' selected' : ''}"
            data-number="${color.number}" title="Цвет №${color.number}: ${color.hex}"
            style="--swatch:${color.hex}"><span>${color.number}</span></button>`).join('');
    document.querySelectorAll('.palette-choice').forEach((button) => {
        button.addEventListener('click', () => {
            targetNumber = Number(button.dataset.number);
            renderEditorPalette();
            if (currentTool === 'select' && selectedRegions.length) {
                const color = currentResult.palette.find((item) => item.number === targetNumber);
                if (color) recolorSelectedRegions(color);
            }
        });
    });
}

function selectTool(tool) {
    currentTool = tool;
    document.querySelectorAll('[data-tool]').forEach((button) => button.classList.toggle('selected', button.dataset.tool === tool));
    canvas.dataset.tool = tool;
    updateToolOptions();
    if (tool === 'unlabeled') loadUnlabeledOverlay();
    drawEditor();
}

function updateToolOptions() {
    eraserOptions.hidden = currentTool !== 'eraser';
    const usesColor = ['select', 'eyedropper', 'brush'].includes(currentTool)
        || (currentTool === 'eraser' && eraserMode === 'color');
    document.querySelector('#brush-colors').classList.toggle(
        'muted',
        !usesColor,
    );
    eraserModeHelp.textContent = eraserMode === 'color'
        ? 'Удаляет весь пересечённый сегмент выбранного цвета.'
        : 'Стирает проведённый мазок, продолжая через него преобладающую соседнюю область.';
    const help = {
        pan: 'Перетаскивайте увеличенный холст. Ctrl + колёсико меняет масштаб.',
        select: 'Щёлкните внутри области. Удерживайте Alt, чтобы добавить или убрать области, затем выберите новый цвет.',
        eyedropper: 'Щёлкните по цветному участку, чтобы выбрать его для кисти или ластика.',
        brush: 'Рисует выбранным цветом. Подходит для ручного исправления формы области.',
        eraser: eraserMode === 'color'
            ? 'Удаляет целиком пересечённый сегмент выбранного цвета и объединяет его с лучшим соседом.'
            : 'Удаляет линию под мазком и заполняет это место преобладающей соседней областью.',
        smooth: 'Локально выравнивает границы. Перестроение произойдёт после применения правок.',
        magic: 'Убирает затронутые области без номера, присоединяя их к самому большому соседнему сегменту.',
        label: 'Щёлкните внутри области — номер её цвета и подходящий размер определятся автоматически.',
        unlabeled: unlabeledLoading
            ? 'Готовим подсветку областей без номеров…'
            : Number.isInteger(currentResult?.diagnostics.unlabeled_segments)
                ? `Красным выделены области без номеров: ${currentResult.diagnostics.unlabeled_segments}.`
                : 'Красным выделены области, в которых нет номера.',
    };
    document.querySelector('#tool-help').textContent = help[currentTool];
}

function eventPoint(event) {
    const bounds = canvas.getBoundingClientRect();
    return [
        Math.min(canvas.width - 1, Math.max(0, (event.clientX - bounds.left) * canvas.width / bounds.width)),
        Math.min(canvas.height - 1, Math.max(0, (event.clientY - bounds.top) * canvas.height / bounds.height)),
    ];
}

function fitEditor() {
    if (!canvas.width) return;
    const availableHeight = Math.max(1, stage.clientHeight - 36);
    setZoom(availableHeight / canvas.height);
    const overflowWidth = canvas.width * zoom - (stage.clientWidth - 36);
    stage.scrollLeft = Math.max(0, overflowWidth / 2);
    stage.scrollTop = 0;
}

function setZoom(value) {
    zoom = Math.min(5, Math.max(0.1, value));
    applyZoom();
}

function applyZoom() {
    canvas.style.width = `${canvas.width * zoom}px`;
    canvas.style.height = `${canvas.height * zoom}px`;
    zoomValue.textContent = `${Math.round(zoom * 100)}%`;
}

function setEditorBusy(busy, message = '') {
    if (editorBusyTimer) {
        clearInterval(editorBusyTimer);
        editorBusyTimer = null;
    }
    loader.hidden = !busy;
    if (busy) {
        const startedAt = Date.now();
        const renderMessage = () => {
            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
            loader.textContent = elapsed > 0 ? `${message} ${elapsed} с` : message;
        };
        renderMessage();
        editorBusyTimer = setInterval(renderMessage, 1000);
        editorStatus.textContent = '';
        editorStatus.classList.remove('failed');
    }
}

function requestHeaders() {
    return {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'X-Generator-Mode': canCustomize ? 'editor' : 'public',
    };
}

function responseMessage(payload, status) {
    return payload.errors ? Object.values(payload.errors).flat()[0] : payload.message ?? `Ошибка ${status}`;
}

window.addEventListener('beforeunload', (event) => {
    if (!pendingOperations.length) return;
    event.preventDefault();
    event.returnValue = '';
});

renderSavedProjects();
loadResumedProject();
if (canCustomize) selectTool('pan');
