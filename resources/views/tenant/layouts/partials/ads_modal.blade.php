@if(isset($vc_system_ads_modal) && $vc_system_ads_modal)
    <div class="system-ads-modal"
         id="systemAdsModal"
         data-version="{{ $vc_system_ads_modal->version }}"
         style="display: none;">
        <div class="system-ads-modal__backdrop"></div>
        <div class="system-ads-modal__box" role="dialog" aria-modal="true" aria-label="Publicidad">
            <button type="button" class="system-ads-modal__close" data-system-ads-close aria-label="Cerrar publicidad">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18" />
                    <path d="m6 6 12 12" />
                </svg>
            </button>

            @if($vc_system_ads_modal->link)
                <a href="{{ $vc_system_ads_modal->link }}" target="_blank" rel="noopener noreferrer">
                    <img src="{{ $vc_system_ads_modal->image }}" alt="Publicidad" class="system-ads-modal__img">
                </a>
            @else
                <img src="{{ $vc_system_ads_modal->image }}" alt="Publicidad" class="system-ads-modal__img">
            @endif
        </div>
    </div>

    <style>
        .system-ads-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 2400;
            align-items: center;
            justify-content: center;
            padding: 24px;
            animation: systemAdsPop .2s ease;
        }
        .system-ads-modal__backdrop {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, .6);
        }
        .system-ads-modal__box {
            position: relative;
            max-width: 100%;
            max-height: 100%;
            line-height: 0;
            border-radius: 12px;
            box-shadow: 0 18px 44px -12px rgba(24, 36, 51, .30);
        }
        .system-ads-modal__img {
            display: block;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: calc(100vh - 48px);
            border-radius: 12px;
        }
        .system-ads-modal__close {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            padding: 0;
            line-height: 1;
            color: #fff;
            background: rgba(15, 23, 42, .6);
            border: 0;
            border-radius: 50%;
            cursor: pointer;
            transition: background .15s;
        }
        .system-ads-modal__close:hover {
            background: rgba(15, 23, 42, .85);
        }
        body.system-ads-modal-open {
            overflow: hidden;
        }
        @keyframes systemAdsPop {
            from { opacity: 0; transform: scale(.96); }
            to { opacity: 1; transform: scale(1); }
        }
        @media (max-width: 575.98px) {
            .system-ads-modal {
                padding: 16px;
            }
            .system-ads-modal__img {
                max-height: calc(100vh - 32px);
            }
            .system-ads-modal__close {
                top: 8px;
                right: 8px;
                width: 28px;
                height: 28px;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .system-ads-modal { animation-duration: .01ms; }
        }
    </style>

    <script>
        (function () {
            var modal = document.getElementById('systemAdsModal');

            if (!modal) {
                return;
            }

            var STORAGE_KEY = 'system_ads_modal_dismissed';
            var version = modal.getAttribute('data-version');
            var dismissed = null;

            try {
                dismissed = window.localStorage.getItem(STORAGE_KEY);
            } catch (e) {
                dismissed = null;
            }

            if (dismissed === version) {
                modal.parentNode.removeChild(modal);
                return;
            }

            function close() {
                try {
                    window.localStorage.setItem(STORAGE_KEY, version);
                } catch (e) {}

                document.body.classList.remove('system-ads-modal-open');
                document.removeEventListener('keydown', onKeydown);

                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            }

            function onKeydown(event) {
                if (event.key === 'Escape' || event.keyCode === 27) {
                    close();
                }
            }

            Array.prototype.forEach.call(
                modal.querySelectorAll('[data-system-ads-close]'),
                function (element) {
                    element.addEventListener('click', close);
                }
            );

            document.addEventListener('keydown', onKeydown);

            modal.style.display = 'flex';
            document.body.classList.add('system-ads-modal-open');
        })();
    </script>
@endif
