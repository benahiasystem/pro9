@php
    $outdoorVisual = $visual ?? null;
    $outdoorDefault = false;
    $outdoorLevelDefault = 'medium';

    if (is_object($outdoorVisual)) {
        $outdoorDefault = property_exists($outdoorVisual, 'outdoor_mode') ? (bool) $outdoorVisual->outdoor_mode : false;
        $outdoorLevelDefault = property_exists($outdoorVisual, 'outdoor_level') && $outdoorVisual->outdoor_level
            ? $outdoorVisual->outdoor_level
            : 'medium';
    } elseif (is_array($outdoorVisual)) {
        $outdoorDefault = (bool) ($outdoorVisual['outdoor_mode'] ?? false);
        $outdoorLevelDefault = $outdoorVisual['outdoor_level'] ?? 'medium';
    }

    if (!in_array($outdoorLevelDefault, ['soft', 'medium', 'strong'], true)) {
        $outdoorLevelDefault = 'medium';
    }
@endphp

<svg class="outdoor-filter-defs" aria-hidden="true" focusable="false" width="0" height="0">
    <filter id="outdoor-soft" color-interpolation-filters="sRGB">
        <feComponentTransfer>
            <feFuncR type="gamma" exponent="1.12" />
            <feFuncG type="gamma" exponent="1.12" />
            <feFuncB type="gamma" exponent="1.12" />
        </feComponentTransfer>
        <feColorMatrix type="saturate" values="1.15" />
    </filter>
    <filter id="outdoor-medium" color-interpolation-filters="sRGB">
        <feComponentTransfer>
            <feFuncR type="gamma" exponent="1.25" />
            <feFuncG type="gamma" exponent="1.25" />
            <feFuncB type="gamma" exponent="1.25" />
        </feComponentTransfer>
        <feColorMatrix type="saturate" values="1.30" />
    </filter>
    <filter id="outdoor-strong" color-interpolation-filters="sRGB">
        <feComponentTransfer>
            <feFuncR type="gamma" exponent="1.42" />
            <feFuncG type="gamma" exponent="1.42" />
            <feFuncB type="gamma" exponent="1.42" />
        </feComponentTransfer>
        <feColorMatrix type="saturate" values="1.40" />
    </filter>

    <filter id="outdoor-soft-dark" color-interpolation-filters="sRGB">
        <feComponentTransfer>
            <feFuncR type="gamma" exponent="0.92" />
            <feFuncG type="gamma" exponent="0.92" />
            <feFuncB type="gamma" exponent="0.92" />
        </feComponentTransfer>
        <feColorMatrix type="saturate" values="1.15" />
    </filter>
    <filter id="outdoor-medium-dark" color-interpolation-filters="sRGB">
        <feComponentTransfer>
            <feFuncR type="gamma" exponent="0.83" />
            <feFuncG type="gamma" exponent="0.83" />
            <feFuncB type="gamma" exponent="0.83" />
        </feComponentTransfer>
        <feColorMatrix type="saturate" values="1.30" />
    </filter>
    <filter id="outdoor-strong-dark" color-interpolation-filters="sRGB">
        <feComponentTransfer>
            <feFuncR type="gamma" exponent="0.72" />
            <feFuncG type="gamma" exponent="0.72" />
            <feFuncB type="gamma" exponent="0.72" />
        </feComponentTransfer>
        <feColorMatrix type="saturate" values="1.45" />
    </filter>
</svg>

<style id="outdoor-mode-styles">
    .outdoor-filter-defs {
        position: absolute;
        width: 0;
        height: 0;
        overflow: hidden;
    }

    html.outdoor-mode.outdoor-soft {
        filter: url(#outdoor-soft);
    }

    html.outdoor-mode.outdoor-medium {
        filter: url(#outdoor-medium);
    }

    html.outdoor-mode.outdoor-strong {
        filter: url(#outdoor-strong);
    }

    html.dark.outdoor-mode.outdoor-soft {
        filter: url(#outdoor-soft-dark);
    }

    html.dark.outdoor-mode.outdoor-medium {
        filter: url(#outdoor-medium-dark);
    }

    html.dark.outdoor-mode.outdoor-strong {
        filter: url(#outdoor-strong-dark);
    }

    @media (forced-colors: active) {
        html.outdoor-mode {
            filter: none !important;
        }
    }

    @media print {
        html.outdoor-mode {
            filter: none !important;
        }
    }
</style>

<script>
    (function () {
        var LEVELS = ['soft', 'medium', 'strong'];
        var defaults = {
            mode: @json($outdoorDefault),
            level: @json($outdoorLevelDefault)
        };

        window.vc_outdoor = window.vc_outdoor || {};
        window.vc_outdoor.defaults = defaults;

        window.vc_outdoor.apply = function (mode, level) {
            var html = document.documentElement;
            var lvl = LEVELS.indexOf(level) !== -1 ? level : 'medium';

            LEVELS.forEach(function (l) {
                html.classList.remove('outdoor-' + l);
            });

            if (mode) {
                html.classList.add('outdoor-mode', 'outdoor-' + lvl);
            } else {
                html.classList.remove('outdoor-mode');
            }
        };

        var mode = defaults.mode;
        var level = defaults.level;
        var hasExplicitMode = false;

        try {
            var storedMode = localStorage.getItem('outdoor_mode');
            var storedLevel = localStorage.getItem('outdoor_level');

            if (storedMode !== null) {
                mode = storedMode === 'true';
                hasExplicitMode = true;
            }
            if (LEVELS.indexOf(storedLevel) !== -1) {
                level = storedLevel;
            }
        } catch (e) {

        }

        if (!hasExplicitMode && !mode) {
            try {
                if (window.matchMedia && window.matchMedia('(prefers-contrast: more)').matches) {
                    mode = true;
                }
            } catch (e) {
                
            }
        }

        window.vc_outdoor.apply(mode, level);
    })();
</script>
