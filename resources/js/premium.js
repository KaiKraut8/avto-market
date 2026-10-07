// Premium tab: show the total for the chosen billing period
const total = document.getElementById('buy-total');
if (total) {
    document.querySelectorAll('input[name="billing"]').forEach((r) => r.addEventListener('change', () => {
        total.textContent = r.value === 'yearly' ? total.dataset.yearly : total.dataset.monthly;
    }));
}
