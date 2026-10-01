{{--
    Picture cropper shared by admin pages that upload a picture (the same flow as Products).
    Mark up each picture field as:
      <div class="pm-photo-field" data-pm-crop-default="standard|fit|fill" data-pm-crop-help="Optional guidance">
        <div class="pm-photo-preview" data-pm-preview>…</div>
        <input class="pm-file-input" type="file" data-pm-image-input>
        <label class="pm-upload-button" for="…">Choose Picture</label>
        <button type="button" class="pm-adjust-button" data-pm-crop-open data-source-url="current picture URL (optional)" hidden>…</button>
        <small class="pm-hint" data-pm-file-name>…</small>
      </div>
    Every newly chosen picture opens in the cropper, and the framed square JPEG replaces the chosen file before the form is sent.
--}}
<div class="pm-cropper" data-pm-cropper role="dialog" aria-modal="true" aria-labelledby="pm-cropper-title" aria-describedby="pm-cropper-help" hidden>
    <div class="pm-cropper-backdrop" data-pm-crop-cancel></div>
    <div class="pm-cropper-panel">
        <header class="pm-cropper-head">
            <div><h2 id="pm-cropper-title">Adjust picture</h2><p id="pm-cropper-help" data-pm-crop-help>Drag to move. Scroll, pinch or use the slider to zoom. Keep the subject inside the dashed guide.</p></div>
            <button type="button" class="pm-icon-button" data-pm-crop-cancel aria-label="Cancel"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"></path></svg></button>
        </header>
        <div class="pm-crop-stage" data-pm-crop-stage tabindex="0" role="img" aria-label="Picture framing. Use the arrow keys to move and the plus and minus keys to zoom.">
            <canvas data-pm-crop-canvas></canvas>
            <span class="pm-crop-guide" aria-hidden="true"></span>
            <span class="pm-crop-status" data-pm-crop-status>Loading picture…</span>
        </div>
        <div class="pm-crop-controls">
            <button type="button" class="pm-icon-button" data-pm-crop-rotate="-1" aria-label="Rotate left" title="Rotate left"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"></path><path d="M3 3v5h5"></path></svg></button>
            <label class="pm-crop-zoom"><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="M8 11h6M20 20l-4-4"></path></svg><span class="pm-sr">Zoom</span><input type="range" min="0" max="1" step="0.001" value="0.5" data-pm-crop-zoom><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"></circle><path d="M8 11h6M11 8v6M20 20l-4-4"></path></svg></label>
            <button type="button" class="pm-icon-button" data-pm-crop-rotate="1" aria-label="Rotate right" title="Rotate right"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"></path><path d="M21 3v5h-5"></path></svg></button>
        </div>
        <div class="pm-crop-presets" role="group" aria-label="Quick framing">
            <button type="button" data-pm-crop-preset="standard">Standard</button>
            <button type="button" data-pm-crop-preset="fit">Whole picture</button>
            <button type="button" data-pm-crop-preset="fill">Fill frame</button>
        </div>
        <footer class="pm-cropper-foot">
            <button type="button" class="logout" data-pm-crop-cancel>Cancel</button>
            <button type="button" class="button pm-primary" data-pm-crop-apply disabled>Apply</button>
        </footer>
    </div>
</div>

@push('styles')
<style>
.pm-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}
.pm-hint{display:block;margin-top:5px;color:#80857c;font-size:12px}
.pm-photo-field{display:grid;gap:8px;align-content:start}
.pm-photo-preview{position:relative;aspect-ratio:1;border:1px dashed #c9ccbf;border-radius:14px;overflow:hidden;background:#fff;display:grid;place-items:center}
.pm-photo-preview img{width:100%;height:100%;object-fit:cover}
.pm-photo-empty{display:grid;justify-items:center;gap:6px;color:#80857c;font-size:12px}
.pm-photo-empty svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.pm-file-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.pm-upload-button{height:38px;display:inline-flex;align-items:center;justify-content:center;padding:0 14px;border:1px solid #d2d5cb;border-radius:10px;background:#fff;color:#252724;font-size:13px;font-weight:700;cursor:pointer}
.pm-upload-button:hover{background:#eff0ea}
.pm-file-input:focus-visible+.pm-upload-button{outline:2px solid #98a20f;outline-offset:2px}
.pm-photo-row{grid-template-columns:128px minmax(0,1fr);gap:16px;align-items:center}
.pm-photo-actions{display:grid;gap:8px;justify-items:start}
.pm-photo-actions .pm-hint{margin:0}
.pm-icon-button{flex:0 0 auto;width:38px;height:38px;display:grid;place-items:center;padding:0;border:0;border-radius:10px;background:transparent;color:#555b52;cursor:pointer}
.pm-icon-button:hover{background:#eff0ea;color:#171817}
.pm-icon-button svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.pm-adjust-button{height:34px;display:inline-flex;align-items:center;gap:7px;padding:0 12px;border:1px solid #c6cc8d;border-radius:9px;background:#edf0cf;color:#3f4500;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-adjust-button[hidden]{display:none}
.pm-adjust-button:hover{background:#e2e7b5}
.pm-adjust-button svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
body.pm-cropper-open{overflow:hidden}
body.pm-cropper-open .admin-workspace{overflow:hidden!important}
.pm-cropper{position:fixed;inset:0;z-index:3500;display:grid;place-items:center;padding:16px}
.pm-cropper[hidden]{display:none}
.pm-cropper-backdrop{position:absolute;inset:0;background:rgba(16,18,15,.6);backdrop-filter:blur(3px);animation:pm-fade .18s ease}
.pm-cropper-panel{position:relative;width:min(470px,100%);max-height:calc(100dvh - 32px);overflow-y:auto;display:grid;gap:14px;padding:20px;border-radius:18px;background:#fffefa;box-shadow:0 28px 80px rgba(10,12,9,.35);animation:pm-fade .18s ease}
.pm-cropper-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.pm-cropper-head h2{margin:0;font-size:19px;letter-spacing:-.02em}
.pm-cropper-head p{margin:4px 0 0;color:#6c7068;font-size:13px;line-height:1.45}
.pm-crop-stage{position:relative;aspect-ratio:1;border-radius:12px;overflow:hidden;background:#fff;box-shadow:inset 0 0 0 1px #d8d9cf;cursor:grab;touch-action:none;user-select:none}
.pm-crop-stage:active{cursor:grabbing}
.pm-crop-stage:focus-visible{outline:2px solid #98a20f;outline-offset:3px}
.pm-crop-stage canvas{display:block;width:100%;height:100%}
.pm-crop-guide{position:absolute;inset:10%;border:1.5px dashed rgba(23,24,23,.38);border-radius:8px;pointer-events:none}
.pm-crop-status{position:absolute;inset:0;display:grid;place-items:center;padding:20px;background:rgba(255,255,255,.9);color:#6c7068;font-size:14px;text-align:center}
.pm-crop-status[hidden]{display:none}
.pm-crop-controls{display:flex;align-items:center;gap:8px}
.pm-crop-zoom{flex:1;display:flex;align-items:center;gap:8px;color:#6c7068}
.pm-crop-zoom svg{width:18px;height:18px;flex:0 0 18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
.pm-crop-zoom input{flex:1;min-width:0;padding:0;border:0;box-shadow:none;accent-color:#171817}
.pm-crop-presets{display:flex;gap:8px}
.pm-crop-presets button{flex:1;height:36px;border:1px solid #d8d9cf;border-radius:9px;background:#fff;color:#171817;font:inherit;font-size:13px;font-weight:700;cursor:pointer}
.pm-crop-presets button:hover{background:#eff0ea}
.pm-cropper-foot{display:flex;justify-content:flex-end;gap:10px}
.pm-cropper-foot .logout,.pm-cropper-foot .button{min-height:44px;width:auto}
.pm-cropper-foot .button:disabled{opacity:.5}
@keyframes pm-fade{from{opacity:0}to{opacity:1}}
@media(max-width:520px){.pm-photo-row{grid-template-columns:96px minmax(0,1fr)}.pm-cropper{padding:0;place-items:end stretch}.pm-cropper-panel{width:100%;max-height:100dvh;border-radius:18px 18px 0 0;padding:16px}.pm-cropper-foot .logout,.pm-cropper-foot .button{flex:1}}
</style>
@endpush

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
(() => {
// The admin frames the picture, and the square result is uploaded ready-made.
// Previews use data: URLs because the Content-Security-Policy does not allow blob: images.
const CROP_OUTPUT = 1200;
const CROP_FILL = {{ \App\Services\ProductImageProcessor::FILL }};
const CROP_TOLERANCE = 28;
const cropSources = new WeakMap();
const crop = { input: null, source: null, image: null, work: null, box: null, turns: 0, scale: 1, x: 0, y: 0, fresh: false, previous: null, trigger: null, pointers: new Map(), pinch: 0, frame: 0, token: 0 };
const cropperRoot = () => document.querySelector('[data-pm-cropper]');
const defaultHelp = cropperRoot()?.querySelector('[data-pm-crop-help]')?.textContent ?? '';

const imagePreviewParts = input => {
    const field = input.closest('.pm-photo-field');
    return {
        field,
        preview: field?.querySelector('[data-pm-preview]'),
        fileName: field?.querySelector('[data-pm-file-name]'),
        adjust: field?.querySelector('[data-pm-crop-open]'),
        framed: field?.querySelector('[data-pm-image-framed]'),
    };
};
const defaultPreset = input => {
    const preset = imagePreviewParts(input).field?.dataset.pmCropDefault;
    return ['standard', 'fit', 'fill'].includes(preset) ? preset : 'standard';
};
const showImagePreview = (input, source, label) => {
    const { preview, fileName } = imagePreviewParts(input);
    if (preview) {
        if (preview.dataset.original === undefined) preview.dataset.original = preview.innerHTML;
        const image = document.createElement('img');
        image.className = 'product-image';
        image.alt = '';
        image.src = source;
        preview.replaceChildren(image);
    }
    if (fileName) {
        if (fileName.dataset.original === undefined) fileName.dataset.original = fileName.textContent;
        fileName.textContent = label;
    }
};
const resetImagePreview = input => {
    const { preview, fileName, adjust, framed } = imagePreviewParts(input);
    if (preview?.dataset.original !== undefined) {
        preview.innerHTML = preview.dataset.original;
        delete preview.dataset.original;
    }
    if (fileName?.dataset.original !== undefined) {
        fileName.textContent = fileName.dataset.original;
        delete fileName.dataset.original;
    }
    if (framed) framed.value = '0';
    if (adjust) adjust.hidden = !adjust.dataset.sourceUrl;
    cropSources.delete(input);
};
const setInputFile = (input, file) => {
    try {
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        return input.files?.length === 1;
    } catch (error) {
        return false;
    }
};

const readFileAsDataUrl = file => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(new Error('This picture could not be read.'));
    reader.readAsDataURL(file);
});
const loadCropImage = source => new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error('This picture could not be opened. Try a JPG, PNG or WebP file.'));
    image.src = source;
});

// Mirrors ProductImageProcessor: a plain studio background is shifted to pure white and the subject's box is measured.
const whitenCropBackground = (canvas, context) => {
    const { width, height } = canvas;
    const pixels = context.getImageData(0, 0, width, height);
    const data = pixels.data;
    const at = (x, y) => {
        const index = (y * width + x) * 4;
        return [data[index], data[index + 1], data[index + 2]];
    };
    const distance = (a, b) => Math.max(Math.abs(a[0] - b[0]), Math.abs(a[1] - b[1]), Math.abs(a[2] - b[2]));
    const corners = [at(0, 0), at(width - 1, 0), at(0, height - 1), at(width - 1, height - 1)];
    if (corners.some(corner => distance(corner, corners[0]) > CROP_TOLERANCE || Math.min(...corner) < 200)) return null;

    const background = [0, 1, 2].map(channel => Math.round(corners.reduce((sum, corner) => sum + corner[channel], 0) / 4));
    const step = Math.max(1, Math.floor(Math.max(width, height) / 300));
    let minX = width;
    let minY = height;
    let maxX = -1;
    let maxY = -1;
    for (let y = 0; y < height; y += step) {
        for (let x = 0; x < width; x += step) {
            if (distance(at(x, y), background) <= CROP_TOLERANCE) continue;
            minX = Math.min(minX, x);
            maxX = Math.max(maxX, x);
            minY = Math.min(minY, y);
            maxY = Math.max(maxY, y);
        }
    }
    if (background.some(value => value < 255)) {
        const curves = background.map(channel => Array.from({ length: 256 }, (_, value) => Math.min(255, Math.round(value * 255 / channel))));
        for (let index = 0; index < data.length; index += 4) {
            data[index] = curves[0][data[index]];
            data[index + 1] = curves[1][data[index + 1]];
            data[index + 2] = curves[2][data[index + 2]];
        }
        context.putImageData(pixels, 0, 0);
    }
    if (maxX < 0) return null;
    const x = Math.max(0, minX - step);
    const y = Math.max(0, minY - step);
    return { x, y, width: Math.min(width, maxX + 2 * step) - x, height: Math.min(height, maxY + 2 * step) - y };
};

// The working copy is capped at 2400px, rotated in quarter turns and flattened onto white.
const rebuildCropWork = () => {
    const { image, turns } = crop;
    const ratio = Math.min(1, 2400 / Math.max(image.naturalWidth, image.naturalHeight));
    const width = Math.max(1, Math.round(image.naturalWidth * ratio));
    const height = Math.max(1, Math.round(image.naturalHeight * ratio));
    const canvas = document.createElement('canvas');
    canvas.width = turns % 2 ? height : width;
    canvas.height = turns % 2 ? width : height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.fillStyle = '#fff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.translate(canvas.width / 2, canvas.height / 2);
    context.rotate(turns * Math.PI / 2);
    context.drawImage(image, -width / 2, -height / 2, width, height);
    context.setTransform(1, 0, 0, 1, 0, 0);
    crop.work = canvas;
    crop.box = whitenCropBackground(canvas, context);
};

// Views are in frame units: the square frame is 1 wide, so the same view draws on screen and in the output.
const cropPresetView = preset => {
    const { width, height } = crop.work;
    if (preset === 'standard' && crop.box) {
        const scale = CROP_FILL / Math.max(crop.box.width, crop.box.height);
        return { scale, x: 0.5 - (crop.box.x + crop.box.width / 2) * scale, y: 0.5 - (crop.box.y + crop.box.height / 2) * scale };
    }
    const scale = preset === 'fit' ? 1 / Math.max(width, height) : 1 / Math.min(width, height);
    return { scale, x: (1 - width * scale) / 2, y: (1 - height * scale) / 2 };
};
const cropScaleLimits = () => {
    const fit = 1 / Math.max(crop.work.width, crop.work.height);
    const fill = 1 / Math.min(crop.work.width, crop.work.height);
    const standard = crop.box ? CROP_FILL / Math.max(crop.box.width, crop.box.height) : fill;
    return { min: fit * 0.5, max: Math.max(fill, standard) * 4 };
};
const setCropView = ({ scale, x, y }) => {
    const { min, max } = cropScaleLimits();
    crop.scale = Math.min(max, Math.max(min, scale));
    const width = crop.work.width * crop.scale;
    const height = crop.work.height * crop.scale;
    // Keep the picture's center inside the frame so it can never be dragged out of reach.
    crop.x = Math.min(1 - width / 2, Math.max(-width / 2, x));
    crop.y = Math.min(1 - height / 2, Math.max(-height / 2, y));
    scheduleCropRender();
};
const zoomCropAt = (scale, pointX = 0.5, pointY = 0.5) => {
    const { min, max } = cropScaleLimits();
    const next = Math.min(max, Math.max(min, scale));
    const ratio = next / crop.scale;
    setCropView({ scale: next, x: pointX - (pointX - crop.x) * ratio, y: pointY - (pointY - crop.y) * ratio });
};
const cropSliderValue = () => {
    const { min, max } = cropScaleLimits();
    return Math.log(crop.scale / min) / Math.log(max / min);
};

const drawCrop = (context, size) => {
    context.setTransform(1, 0, 0, 1, 0, 0);
    context.fillStyle = '#fff';
    context.fillRect(0, 0, size, size);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(crop.work, crop.x * size, crop.y * size, crop.work.width * crop.scale * size, crop.work.height * crop.scale * size);
};
const renderCrop = () => {
    crop.frame = 0;
    const root = cropperRoot();
    const canvas = root?.querySelector('[data-pm-crop-canvas]');
    if (!canvas || !crop.work) return;
    const size = Math.round(canvas.clientWidth * (window.devicePixelRatio || 1));
    if (!size) return;
    if (canvas.width !== size) {
        canvas.width = size;
        canvas.height = size;
    }
    drawCrop(canvas.getContext('2d'), size);
    const slider = root.querySelector('[data-pm-crop-zoom]');
    if (slider && document.activeElement !== slider) slider.value = String(cropSliderValue());
};
const scheduleCropRender = () => {
    if (!crop.frame) crop.frame = window.requestAnimationFrame(renderCrop);
};

const openCropper = async (input, source, { fresh = false, trigger = null } = {}) => {
    const root = cropperRoot();
    if (!root) return;
    const token = ++crop.token;
    Object.assign(crop, { input, source, fresh, trigger, image: null, work: null, box: null, turns: 0 });
    const status = root.querySelector('[data-pm-crop-status]');
    const apply = root.querySelector('[data-pm-crop-apply]');
    const help = root.querySelector('[data-pm-crop-help]');
    if (help) help.textContent = imagePreviewParts(input).field?.dataset.pmCropHelp || defaultHelp;
    status.textContent = 'Loading picture…';
    status.hidden = false;
    apply.disabled = true;
    root.hidden = false;
    document.body.classList.add('pm-cropper-open');
    root.querySelector('[data-pm-crop-stage]').focus({ preventScroll: true });
    try {
        const image = await loadCropImage(source instanceof File ? await readFileAsDataUrl(source) : source);
        if (token !== crop.token) return;
        crop.image = image;
        rebuildCropWork();
        setCropView(cropPresetView(defaultPreset(input)));
        status.hidden = true;
        apply.disabled = false;
    } catch (error) {
        if (token === crop.token) status.textContent = error.message;
    }
};

const closeCropper = ({ applied = false } = {}) => {
    const root = cropperRoot();
    if (root) root.hidden = true;
    crop.token++;
    document.body.classList.remove('pm-cropper-open');
    const { input, fresh, previous, trigger } = crop;
    // Cancelling a newly chosen file puts back whatever was there before it.
    if (!applied && fresh && input) {
        if (previous && setInputFile(input, previous.file)) {
            showImagePreview(input, previous.previewUrl, previous.label);
            const { framed } = imagePreviewParts(input);
            if (framed) framed.value = '1';
            cropSources.set(input, previous);
        } else {
            input.value = '';
            resetImagePreview(input);
        }
    }
    (trigger?.isConnected ? trigger : input)?.focus({ preventScroll: true });
    Object.assign(crop, { input: null, source: null, image: null, work: null, box: null, previous: null, trigger: null, fresh: false, pinch: 0 });
    crop.pointers.clear();
};

const applyCrop = async () => {
    const { input, source } = crop;
    if (!input || !crop.work) return;
    const output = document.createElement('canvas');
    output.width = CROP_OUTPUT;
    output.height = CROP_OUTPUT;
    drawCrop(output.getContext('2d'), CROP_OUTPUT);
    const blob = await new Promise(resolve => output.toBlob(resolve, 'image/jpeg', 0.92));
    const baseName = source instanceof File ? source.name.replace(/\.[^.]+$/, '') : 'picture';
    const file = blob ? new File([blob], `${baseName}-framed.jpg`, { type: 'image/jpeg' }) : null;
    if (!file || !setInputFile(input, file)) {
        closeCropper({ applied: true });
        window.KermitsAlert?.error("This browser can't save your framing, so the picture will be uploaded as chosen.");
        return;
    }
    const previewUrl = output.toDataURL('image/jpeg', 0.85);
    const label = source instanceof File ? `${source.name} · framed by you` : 'Current picture · re-framed by you';
    const { adjust, framed } = imagePreviewParts(input);
    showImagePreview(input, previewUrl, label);
    if (framed) framed.value = '1';
    if (adjust) adjust.hidden = false;
    cropSources.set(input, { source, file, previewUrl, label });
    closeCropper({ applied: true });
};

const trapFocus = (event, container) => {
    const focusable = [...container.querySelectorAll('button, [href], input:not([type="hidden"]), textarea, select, [tabindex]:not([tabindex="-1"])')]
        .filter(element => !element.disabled && element.getClientRects().length);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
    }
};

document.addEventListener('click', event => {
    const opener = event.target.closest('[data-pm-crop-open]');
    if (opener) {
        const input = opener.closest('.pm-photo-field')?.querySelector('[data-pm-image-input]');
        const state = input ? cropSources.get(input) : null;
        const source = state?.source ?? opener.dataset.sourceUrl;
        if (input && source) openCropper(input, source, { trigger: opener });
        return;
    }
    if (!crop.input) return;
    if (event.target.closest('[data-pm-crop-cancel]')) {
        closeCropper();
        return;
    }
    if (event.target.closest('[data-pm-crop-apply]')) {
        applyCrop();
        return;
    }
    if (!crop.work) return;
    const rotate = event.target.closest('[data-pm-crop-rotate]');
    if (rotate) {
        crop.turns = (crop.turns + Number(rotate.dataset.pmCropRotate) + 4) % 4;
        rebuildCropWork();
        setCropView(cropPresetView(defaultPreset(crop.input)));
        return;
    }
    const preset = event.target.closest('[data-pm-crop-preset]');
    if (preset) setCropView(cropPresetView(preset.dataset.pmCropPreset));
});

document.addEventListener('input', event => {
    if (!event.target.matches?.('[data-pm-crop-zoom]') || !crop.work) return;
    const { min, max } = cropScaleLimits();
    zoomCropAt(min * (max / min) ** Number(event.target.value));
});

document.addEventListener('pointerdown', event => {
    const stage = event.target.closest?.('[data-pm-crop-stage]');
    if (!stage || !crop.work) return;
    stage.setPointerCapture(event.pointerId);
    crop.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    crop.pinch = 0;
});
document.addEventListener('pointermove', event => {
    const previous = crop.pointers.get(event.pointerId);
    const stage = document.querySelector('[data-pm-crop-stage]');
    if (!previous || !stage || !crop.work) return;
    const rect = stage.getBoundingClientRect();
    crop.pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
    if (crop.pointers.size >= 2) {
        const [a, b] = [...crop.pointers.values()];
        const spread = Math.hypot(a.x - b.x, a.y - b.y);
        if (crop.pinch) zoomCropAt(crop.scale * spread / crop.pinch, ((a.x + b.x) / 2 - rect.left) / rect.width, ((a.y + b.y) / 2 - rect.top) / rect.height);
        crop.pinch = spread;
        return;
    }
    setCropView({ scale: crop.scale, x: crop.x + (event.clientX - previous.x) / rect.width, y: crop.y + (event.clientY - previous.y) / rect.height });
});
const releaseCropPointer = event => {
    crop.pointers.delete(event.pointerId);
    crop.pinch = 0;
};
document.addEventListener('pointerup', releaseCropPointer);
document.addEventListener('pointercancel', releaseCropPointer);
document.addEventListener('wheel', event => {
    const stage = event.target.closest?.('[data-pm-crop-stage]');
    if (!stage || !crop.work) return;
    event.preventDefault();
    const rect = stage.getBoundingClientRect();
    zoomCropAt(crop.scale * Math.exp(-event.deltaY * 0.0015), (event.clientX - rect.left) / rect.width, (event.clientY - rect.top) / rect.height);
}, { passive: false });
window.addEventListener('resize', () => { if (crop.work) scheduleCropRender(); });

// Runs in the capture phase so Escape and Tab stay inside the cropper.
document.addEventListener('keydown', event => {
    const root = document.querySelector('[data-pm-cropper]:not([hidden])');
    if (!root || document.querySelector('.app-alert-layer')) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopImmediatePropagation();
        closeCropper();
        return;
    }
    if (event.key === 'Tab') {
        event.stopImmediatePropagation();
        trapFocus(event, root.querySelector('.pm-cropper-panel'));
        return;
    }
    if (!event.target.matches?.('[data-pm-crop-stage]') || !crop.work) return;
    const step = event.shiftKey ? 0.05 : 0.01;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    if (moves[event.key]) {
        event.preventDefault();
        setCropView({ scale: crop.scale, x: crop.x + moves[event.key][0], y: crop.y + moves[event.key][1] });
    } else if (['+', '='].includes(event.key)) {
        event.preventDefault();
        zoomCropAt(crop.scale * 1.1);
    } else if (['-', '_'].includes(event.key)) {
        event.preventDefault();
        zoomCropAt(crop.scale / 1.1);
    }
}, true);

// Every newly chosen picture opens in the cropper.
document.addEventListener('change', event => {
    const input = event.target.closest?.('[data-pm-image-input]');
    if (!input) return;
    const file = input.files?.[0];
    if (!file) {
        resetImagePreview(input);
        return;
    }
    crop.previous = cropSources.get(input) ?? null;
    openCropper(input, file, { fresh: true });
});

window.PictureCropper = Object.freeze({ reset: resetImagePreview });
})();
</script>
@endpush
