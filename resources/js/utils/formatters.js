const currencyFormatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

const quantityFormatter = new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 2,
});

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
});

export function formatCurrency(value) {
    return currencyFormatter.format(Number(value ?? 0));
}

export function formatQuantity(value) {
    return quantityFormatter.format(Number(value ?? 0));
}

export function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return dateFormatter.format(date);
}
