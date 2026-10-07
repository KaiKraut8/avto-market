{{-- A car seen from the side; the wheels turn with --wheel, the body is teal-blue with amber lights --}}
<svg class="car-drawing" viewBox="0 0 340 120" aria-hidden="true">
    <defs>
        <linearGradient id="car-body" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#5ee0c6"/>
            <stop offset=".55" stop-color="#2bb3d9"/>
            <stop offset="1" stop-color="#1c6fa8"/>
        </linearGradient>
        <linearGradient id="car-glass" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#e8f6ff" stop-opacity=".95"/>
            <stop offset="1" stop-color="#8fc9ee" stop-opacity=".6"/>
        </linearGradient>
        <linearGradient id="beam" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0" stop-color="#ffe9a8" stop-opacity=".75"/>
            <stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/>
        </linearGradient>
    </defs>
    <ellipse class="car-shadow" cx="170" cy="108" rx="150" ry="8" fill="#000" opacity=".45"/>
    <polygon class="car-beam" points="318,56 340,40 340,78 318,66" fill="url(#beam)"/>
    {{-- body --}}
    <path fill="url(#car-body)" d="M14,86 L14,74 C14,66 20,62 30,60 L82,52 C110,30 142,20 190,20 L232,20 C262,20 288,36 306,54 L322,58 C330,60 334,66 334,74 L334,84 C334,88 330,90 326,90 L22,90 C17,90 14,89 14,86 Z"/>
    {{-- windows --}}
    <path fill="url(#car-glass)" d="M100,52 C122,34 150,27 190,27 L186,52 Z"/>
    <path fill="url(#car-glass)" d="M196,27 L236,27 C258,27 276,38 290,52 L194,52 Z"/>
    {{-- door line, handle, side stripe --}}
    <path d="M192,27 L190,86" stroke="#0e3d5c" stroke-width="2" fill="none" opacity=".6"/>
    <rect x="200" y="60" width="16" height="4" rx="2" fill="#0e3d5c" opacity=".7"/>
    <path d="M24,72 L324,72" stroke="#ffffff" stroke-width="2" opacity=".25"/>
    {{-- lights --}}
    <path class="car-headlight" d="M306,56 L324,60 C328,61 328,66 324,66 L306,66 Z" fill="#fff1b8"/>
    <path d="M14,70 L26,68 L26,80 L14,80 Z" fill="#ff4d4d"/>
    {{-- wheels --}}
    <g class="wheel" style="transform-origin: 84px 88px">
        <circle cx="84" cy="88" r="22" fill="#111519"/>
        <circle cx="84" cy="88" r="13" fill="#2a3340" stroke="#9fb0c4" stroke-width="2"/>
        <path d="M84,76 L84,100 M72,88 L96,88 M75.5,79.5 L92.5,96.5 M92.5,79.5 L75.5,96.5" stroke="#d7e1ec" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="84" cy="88" r="3.5" fill="#f2b544"/>
    </g>
    <g class="wheel" style="transform-origin: 266px 88px">
        <circle cx="266" cy="88" r="22" fill="#111519"/>
        <circle cx="266" cy="88" r="13" fill="#2a3340" stroke="#9fb0c4" stroke-width="2"/>
        <path d="M266,76 L266,100 M254,88 L278,88 M257.5,79.5 L274.5,96.5 M274.5,79.5 L257.5,96.5" stroke="#d7e1ec" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="266" cy="88" r="3.5" fill="#f2b544"/>
    </g>
</svg>
