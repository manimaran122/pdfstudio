import pageGrid from './tools/page-grid';
import pageEditor, { signaturePad } from './tools/page-editor';
import imageOption from './tools/image-option';
import palette from './tools/palette';

// Livewire bundles and starts Alpine; register components before it starts.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('pageGrid', pageGrid);
    window.Alpine.data('pageEditor', pageEditor);
    window.Alpine.data('signaturePad', signaturePad);
    window.Alpine.data('imageOption', imageOption);
    window.Alpine.data('palette', palette);
});
