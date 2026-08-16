/**
 * Diretiva v-tooltip
 * Uso: v-tooltip="'Texto do tooltip'"
 * Posiciona com fixed para evitar clip de overflow:hidden.
 */
export const tooltip = {
    mounted(el, binding) {
        const tip = document.createElement('span');
        tip.textContent = binding.value;

        Object.assign(tip.style, {
            position: 'fixed',
            display: 'none',
            backgroundColor: 'rgba(0,0,0,0.85)',
            color: '#fff',
            padding: '5px 12px',
            borderRadius: '6px',
            fontSize: '12px',
            whiteSpace: 'nowrap',
            pointerEvents: 'none',
            zIndex: '99999',
            transition: 'opacity 0.15s ease',
            opacity: '0',
        });

        document.body.appendChild(tip);
        el._tooltip = tip;

        el._tooltipShow = () => {
            const rect = el.getBoundingClientRect();
            tip.style.display = 'block';
            const tipRect = tip.getBoundingClientRect();
            let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
            let top = rect.top - tipRect.height - 8;

            if (left < 4) left = 4;
            if (left + tipRect.width > window.innerWidth - 4) {
                left = window.innerWidth - tipRect.width - 4;
            }
            if (top < 4) {
                top = rect.bottom + 8;
            }

            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
            tip.style.opacity = '1';
        };

        el._tooltipHide = () => {
            tip.style.opacity = '0';
            setTimeout(() => { tip.style.display = 'none'; }, 150);
        };

        el.addEventListener('mouseenter', el._tooltipShow);
        el.addEventListener('mouseleave', el._tooltipHide);
    },
    updated(el, binding) {
        if (el._tooltip) {
            el._tooltip.textContent = binding.value;
        }
    },
    unmounted(el) {
        if (el._tooltip) {
            el.removeEventListener('mouseenter', el._tooltipShow);
            el.removeEventListener('mouseleave', el._tooltipHide);
            el._tooltip.remove();
            el._tooltip = null;
        }
    },
};
