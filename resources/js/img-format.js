// Detecta suporte a encoding WebP via canvas.toDataURL.
// Cai para JPEG em navegadores sem suporte a encode webp (ex.: iOS < 14).
let _cached = null;
export function imgType() {
    if (_cached) return _cached;
    try {
        const c = document.createElement('canvas');
        c.width = c.height = 1;
        _cached = c.toDataURL('image/webp').indexOf('image/webp') === 5
            ? 'image/webp'
            : 'image/jpeg';
    } catch (e) {
        _cached = 'image/jpeg';
    }
    return _cached;
}
export function imgExt() {
    return imgType() === 'image/webp' ? 'webp' : 'jpg';
}
// Garante que o nome do arquivo casa com o formato de fato gerado.
export function imgName(name) {
    const base = (name || 'foto').replace(/\.[^.]+$/, '');
    return base + '.' + imgExt();
}
