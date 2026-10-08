// Premium page: each plan's total follows the billing period chosen in its own form
document.querySelectorAll('[data-buy-total]').forEach((total) => {
    total.closest('form').querySelectorAll('input[name="billing"]').forEach((r) => r.addEventListener('change', () => {
        total.textContent = total.dataset[r.value] ?? total.dataset.monthly;
    }));
});
