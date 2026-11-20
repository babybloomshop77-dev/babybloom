export function money(value, currency = '֏') {
    if (value === null || value === undefined || value === '') return '—';
    return `${Number(value).toLocaleString('ru-RU').replace(/,/g, ' ')} ${currency}`;
}

export function date(value) {
    if (!value) return '—';
    const [y, m, d] = String(value).slice(0, 10).split('-');
    return `${d}.${m}.${y}`;
}

export function today() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

export const statusColors = {
    ordered: 'bg-violet-100 text-violet-700',
    in_stock: 'bg-green-100 text-green-700',
    processing: 'bg-amber-100 text-amber-700',
    reserved: 'bg-blue-100 text-blue-700',
    sold: 'bg-gray-100 text-gray-500',
};

export const sourceColors = {
    pinduoduo: 'bg-orange-100 text-orange-700',
    wholesale: 'bg-purple-100 text-purple-700',
    other: 'bg-gray-100 text-gray-600',
};

/** Фото товара для нужного цвета: сначала фото этого цвета, потом общее, потом любое */
export function imageFor(product, color) {
    const images = product?.images ?? [];
    if (!images.length) return null;
    const norm = (s) => String(s ?? '').trim().toLowerCase();
    const match = color ? images.find((i) => i.color && norm(i.color) === norm(color)) : null;
    return (match ?? images.find((i) => !i.color) ?? images[0]).url;
}
