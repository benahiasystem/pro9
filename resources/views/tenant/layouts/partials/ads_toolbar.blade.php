@if(isset($vc_system_ads_toolbar) && $vc_system_ads_toolbar)
    <div class="system-ads-toolbar"
         id="systemAdsToolbar"
         data-version="{{ $vc_system_ads_toolbar->version }}"
         data-dismissible="{{ $vc_system_ads_toolbar->dismissible ? '1' : '0' }}"
         style="display: none; background-color: {{ $vc_system_ads_toolbar->background_color }}; color: {{ $vc_system_ads_toolbar->text_color }};">

        @if($vc_system_ads_toolbar->link)
            <a class="system-ads-toolbar__text system-ads-toolbar__link"
               href="{{ $vc_system_ads_toolbar->link }}"
               target="_blank"
               rel="noopener noreferrer">{{ $vc_system_ads_toolbar->text }}</a>
        @else
            <span class="system-ads-toolbar__text">{{ $vc_system_ads_toolbar->text }}</span>
        @endif

        @if($vc_system_ads_toolbar->dismissible)
            <button type="button"
                    class="system-ads-toolbar__close"
                    data-system-ads-toolbar-close
                    aria-label="Cerrar publicidad">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </button>
        @endif
    </div>

    <style>
        .system-ads-toolbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 12px 44px;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.35;
            text-align: center;
            box-shadow: 0 4px 16px -4px rgba(24, 36, 51, .14);
            animation: systemAdsSlideDown .25s ease;
        }
        .system-ads-toolbar__text {
            color: inherit;
        }
        .system-ads-toolbar__link {
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .system-ads-toolbar__link:hover,
        .system-ads-toolbar__link:focus {
            color: inherit;
            opacity: .85;
            text-decoration: underline;
        }
        .system-ads-toolbar__close {
            position: absolute;
            top: 50%;
            right: 12px;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            padding: 0;
            color: inherit;
            background: transparent;
            border: 0;
            border-radius: 50%;
            opacity: .75;
            cursor: pointer;
            transition: opacity .15s, background .15s;
        }
        .system-ads-toolbar__close:hover {
            opacity: 1;
            background: rgba(0, 0, 0, .14);
        }
        @keyframes systemAdsSlideDown {
            from { transform: translateY(-100%); }
            to { transform: translateY(0); }
        }
        html.has-system-ads-toolbar body {
            padding-top: var(--system-ads-toolbar-h, 44px);
        }
        html.has-system-ads-toolbar .sidebar-left {
            margin-top: var(--system-ads-toolbar-h, 44px);
        }
        @media only screen and (min-width: 768px) {
            html.has-system-ads-toolbar .header,
            html.has-system-ads-toolbar .page-header {
                margin-top: var(--system-ads-toolbar-h, 44px);
            }
        }
        @media only screen and (max-width: 767px) {
            /* En movil .header es estatico; lo fijo es su logo-container. */
            html.has-system-ads-toolbar .header .logo-container {
                margin-top: var(--system-ads-toolbar-h, 44px);
            }
            .system-ads-toolbar {
                padding: 10px 38px;
                font-size: 13px;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .system-ads-toolbar { animation-duration: .01ms; }
        }
    </style>

    <script>
        (function () {
            var toolbar = document.getElementById('systemAdsToolbar');

            if (!toolbar) {
                return;
            }

            var STORAGE_KEY = 'system_ads_toolbar_dismissed';
            var root = document.documentElement;
            var version = toolbar.getAttribute('data-version');
            var dismissible = toolbar.getAttribute('data-dismissible') === '1';

            if (dismissible) {
                var dismissed = null;

                try {
                    dismissed = window.localStorage.getItem(STORAGE_KEY);
                } catch (e) {
                    dismissed = null;
                }

                if (dismissed === version) {
                    toolbar.parentNode.removeChild(toolbar);
                    return;
                }
            }

            function syncHeight() {
                root.style.setProperty('--system-ads-toolbar-h', toolbar.offsetHeight + 'px');
            }

            function close() {
                try {
                    window.localStorage.setItem(STORAGE_KEY, version);
                } catch (e) {}

                window.removeEventListener('resize', syncHeight);
                root.classList.remove('has-system-ads-toolbar');
                root.style.removeProperty('--system-ads-toolbar-h');

                if (toolbar.parentNode) {
                    toolbar.parentNode.removeChild(toolbar);
                }
            }

            toolbar.style.display = 'flex';
            root.classList.add('has-system-ads-toolbar');
            syncHeight();

            if (window.ResizeObserver) {
                new ResizeObserver(syncHeight).observe(toolbar);
            } else {
                window.addEventListener('resize', syncHeight);
            }

            Array.prototype.forEach.call(
                toolbar.querySelectorAll('[data-system-ads-toolbar-close]'),
                function (element) {
                    element.addEventListener('click', close);
                }
            );
        })();
    </script>
@endif
