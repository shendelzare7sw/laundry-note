// Teks satu baris yang terpotong (judul/subjudul topbar, info sidebar, akun) digeser pelan agar terbaca utuh:
// diam sejenak -> geser sampai akhir teks terlihat -> diam -> memudar kembali ke awal -> ulang.
// Hanya aktif bila teks benar-benar meluber (overflow > 1 px); elemen tetap memakai class `truncate`.
// Pengukuran di-batch per animation frame (baca dulu, tulis kemudian) untuk mencegah layout thrashing.

const TEMPO = {
    normal: { kecepatan: 32, jedaAwal: 1800, jedaAkhir: 1600 }, // px per detik
    tenang: { kecepatan: 16, jedaAwal: 2800, jedaAkhir: 2600 },
};

const PUDAR_MS = 350;
const RUANG_PUDAR = 16;

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const state = new WeakMap();
const pendingElements = new Set();
let rafId = 0;

function stop(el) {
    const s = state.get(el);
    if (!s) {
        return;
    }
    s.animation?.cancel();
    s.animation = null;
    s.shift = 0;
    el.classList.remove('marquee-aktif');
    s.track?.classList.remove('marquee-track');
}

function ensureTrack(el) {
    const s = state.get(el);
    if (s.track) {
        return s.track;
    }
    const track = document.createElement('span');
    while (el.firstChild) {
        track.appendChild(el.firstChild);
    }
    el.appendChild(track);
    s.track = track;
    return track;
}

function syncPlayback(s) {
    if (!s?.animation) {
        return;
    }
    if (s.visible && !s.paused && !s.manualPause) {
        s.animation.play();
    } else {
        s.animation.pause();
    }
}

function flushMeasurements() {
    rafId = 0;
    const batch = Array.from(pendingElements);
    pendingElements.clear();

    // Fase 1: pastikan track ada (DOM write awal hanya sekali seumur elemen)
    const candidates = [];
    for (const el of batch) {
        const s = state.get(el);
        if (!s || !el.isConnected) {
            continue;
        }
        ensureTrack(el);
        candidates.push({ el, s });
    }

    // Fase 2: READ semua dimensi sekaligus (zero layout thrashing)
    const readings = candidates.map(({ el, s }) => {
        const width = el.clientWidth;
        const trackWidth = width > 0 ? Math.ceil(s.track.getBoundingClientRect().width) : 0;
        return { el, s, width, overflow: trackWidth - width };
    });

    // Fase 3: WRITE kelas & Web Animation sekaligus
    const mode = reduceMotion.matches ? 'tenang' : 'normal';
    const { kecepatan, jedaAwal, jedaAkhir } = TEMPO[mode];

    for (const { el, s, width, overflow } of readings) {
        if (width === 0) {
            continue;
        }

        if (overflow <= 1) {
            stop(el);
            continue;
        }

        const shift = overflow + RUANG_PUDAR;
        if (s.animation && s.shift === shift && s.mode === mode) {
            syncPlayback(s);
            continue;
        }

        s.animation?.cancel();
        s.shift = shift;
        s.mode = mode;

        el.classList.add('marquee-aktif');
        s.track.classList.add('marquee-track');

        const move = (shift / kecepatan) * 1000;
        const total = jedaAwal + move + jedaAkhir + PUDAR_MS * 2;
        let t = 0;
        const at = (ms) => (t += ms) / total;
        const start = 'translate3d(0, 0, 0)';
        const end = `translate3d(${-shift}px, 0, 0)`;

        s.animation = s.track.animate(
            [
                { transform: start, opacity: 1, offset: 0 },
                { transform: start, opacity: 1, offset: at(jedaAwal), easing: 'ease-in-out' },
                { transform: end, opacity: 1, offset: at(move) },
                { transform: end, opacity: 1, offset: at(jedaAkhir) },
                { transform: end, opacity: 0, offset: at(PUDAR_MS) },
                { transform: start, opacity: 0, offset: Math.min(1, t / total + 0.0001) },
                { transform: start, opacity: 1, offset: 1 },
            ],
            { duration: total, iterations: Infinity },
        );

        syncPlayback(s);
    }
}

function scheduleMeasure(el) {
    if (!state.has(el)) {
        return;
    }
    pendingElements.add(el);
    if (!rafId) {
        rafId = requestAnimationFrame(flushMeasurements);
    }
}

const resizeObserver =
    typeof ResizeObserver === 'function'
        ? new ResizeObserver((entries) => {
              for (const entry of entries) {
                  scheduleMeasure(entry.target);
              }
          })
        : null;

const visibilityObserver =
    typeof IntersectionObserver === 'function'
        ? new IntersectionObserver((entries) => {
              for (const entry of entries) {
                  const s = state.get(entry.target);
                  if (!s) {
                      continue;
                  }
                  s.visible = entry.isIntersecting;
                  syncPlayback(s);
              }
          })
        : null;

export function initMarquee(root = document) {
    root.querySelectorAll('[data-marquee]').forEach((el) => {
        if (state.has(el)) {
            scheduleMeasure(el);
            return;
        }

        const itemState = {
            visible: !visibilityObserver,
            paused: false,
            manualPause: false,
            animation: null,
            track: null,
            shift: 0,
            mode: null,
        };
        state.set(el, itemState);

        const interactiveHost = el.closest('a, button');
        const host = interactiveHost || el;

        const pause = () => {
            itemState.paused = true;
            syncPlayback(itemState);
        };
        const resume = () => {
            itemState.paused = false;
            syncPlayback(itemState);
        };

        host.addEventListener('mouseenter', pause, { passive: true });
        host.addEventListener('mouseleave', resume, { passive: true });
        host.addEventListener('focusin', pause, { passive: true });
        host.addEventListener('focusout', resume, { passive: true });

        // Pada teks statis (seperti judul/subjudul topbar), ketuk di layar sentuh menjeda/melanjutkan geseran.
        if (!interactiveHost) {
            el.addEventListener(
                'pointerup',
                (event) => {
                    if (event.pointerType === 'touch' && itemState.animation) {
                        itemState.manualPause = !itemState.manualPause;
                        syncPlayback(itemState);
                    }
                },
                { passive: true },
            );
        }

        resizeObserver?.observe(el);
        visibilityObserver?.observe(el);
        scheduleMeasure(el);
    });
}

export function remeasureMarquee(root = document) {
    root.querySelectorAll('[data-marquee]').forEach((el) => {
        if (state.has(el)) {
            scheduleMeasure(el);
        }
    });
}

reduceMotion.addEventListener?.('change', () => remeasureMarquee());
document.fonts?.ready.then(() => remeasureMarquee());
