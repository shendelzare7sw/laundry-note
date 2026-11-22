// Penanganan strip tab/filter horizontal (`.tab-scroller`):
// 1. Menggulirkan tab aktif ke area terlihat tanpa mengganggu scroll vertikal halaman.
// 2. Fallback petunjuk fade tepi berbasis IntersectionObserver untuk browser tanpa CSS `animation-timeline: scroll()`.

export function initTabScrollers(root = document) {
    const scrollers = Array.from(root.querySelectorAll('.tab-scroller'));
    if (scrollers.length === 0) {
        return;
    }

    const supportsScrollTimeline =
        typeof CSS !== 'undefined' &&
        typeof CSS.supports === 'function' &&
        CSS.supports('animation-timeline', 'scroll()');

    // Baca & sesuaikan posisi scroll horizontal setelah layout siap agar tab aktif tidak tersembunyi di tepi kanan.
    requestAnimationFrame(() => {
        const adjustments = [];
        for (const scroller of scrollers) {
            if (scroller.scrollWidth <= scroller.clientWidth + 1) {
                continue;
            }
            const activeTab = scroller.querySelector('[aria-current="page"], [aria-current="true"], [aria-selected="true"]');
            if (!activeTab) {
                continue;
            }
            const targetLeft =
                activeTab.offsetLeft - (scroller.clientWidth - activeTab.offsetWidth) / 2;
            const maxScroll = scroller.scrollWidth - scroller.clientWidth;
            const clamped = Math.max(0, Math.min(maxScroll, targetLeft));
            if (Math.abs(scroller.scrollLeft - clamped) > 2) {
                adjustments.push({ scroller, left: clamped });
            }
        }
        for (const { scroller, left } of adjustments) {
            scroller.scrollLeft = left;
        }
    });

    if (supportsScrollTimeline || typeof IntersectionObserver !== 'function') {
        return;
    }

    for (const scroller of scrollers) {
        const first = scroller.firstElementChild;
        const last = scroller.lastElementChild;
        if (!first || !last || first === last) {
            continue;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const { target, intersectionRatio } of entries) {
                    if (target === first) {
                        scroller.classList.toggle('more-start', intersectionRatio < 1);
                    } else if (target === last) {
                        scroller.classList.toggle('more-end', intersectionRatio < 1);
                    }
                }
            },
            { root: scroller, threshold: 1, rootMargin: '1px' },
        );

        observer.observe(first);
        observer.observe(last);
    }
}
