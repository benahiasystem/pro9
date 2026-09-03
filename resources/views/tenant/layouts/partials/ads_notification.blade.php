@if(isset($vc_system_ads_notification) && $vc_system_ads_notification)
    @php
        $adsNotificationAvoidWs = $vc_system_ads_notification->position === 'bottom-right'
            && !empty($show_ws)
            && strlen((string) ($phone_whatsapp ?? '')) > 0;
    @endphp

    <div class="system-ads-notification system-ads-notification--{{ $vc_system_ads_notification->position }} @if($adsNotificationAvoidWs) system-ads-notification--avoid-ws @endif"
         id="systemAdsNotification"
         data-version="{{ $vc_system_ads_notification->version }}"
         data-duration="{{ $vc_system_ads_notification->duration }}"
         role="status"
         style="display: none;">

        @if($vc_system_ads_notification->icon_type === 'tabler' && $vc_system_ads_notification->icon_svg)
            <span class="system-ads-notification__icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="22" height="22" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    {!! $vc_system_ads_notification->icon_svg !!}
                </svg>
            </span>
        @elseif($vc_system_ads_notification->icon_type === 'emoji' && $vc_system_ads_notification->emoji)
            <span class="system-ads-notification__icon system-ads-notification__icon--emoji">{{ $vc_system_ads_notification->emoji }}</span>
        @endif

        <div class="system-ads-notification__body">
            @if($vc_system_ads_notification->title)
                <div class="system-ads-notification__title">{{ $vc_system_ads_notification->title }}</div>
            @endif

            @if($vc_system_ads_notification->description)
                <div class="system-ads-notification__description">{{ $vc_system_ads_notification->description }}</div>
            @endif

            @if($vc_system_ads_notification->link)
                <a class="system-ads-notification__link"
                   href="{{ $vc_system_ads_notification->link }}"
                   target="_blank"
                   rel="noopener noreferrer">Ver más</a>
            @endif
        </div>

        <button type="button"
                class="system-ads-notification__close"
                data-system-ads-notification-close
                aria-label="Cerrar notificación">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
            </svg>
        </button>
    </div>

    <style>
        .system-ads-notification {
            position: fixed;
            z-index: 2300;
            align-items: flex-start;
            gap: 12px;
            width: 340px;
            max-width: calc(100vw - 40px);
            padding: 14px 36px 14px 14px;
            color: #182433;
            background: #fff;
            border: 1px solid var(--border, #e4e8ee);
            border-radius: 12px;
            box-shadow: 0 18px 44px -12px rgba(24, 36, 51, .30);
            opacity: 0;
            transition: opacity .25s ease, transform .25s ease;
        }
        .system-ads-notification.is-visible {
            opacity: 1;
            transform: translateX(0);
        }

        .system-ads-notification--top-left,
        .system-ads-notification--top-right {
            top: calc(20px + var(--system-ads-toolbar-h, 0px));
        }
        .system-ads-notification--bottom-left,
        .system-ads-notification--bottom-right {
            bottom: 20px;
        }
        .system-ads-notification--top-left,
        .system-ads-notification--bottom-left {
            left: 20px;
            transform: translateX(-16px);
        }
        .system-ads-notification--top-right,
        .system-ads-notification--bottom-right {
            right: 20px;
            transform: translateX(16px);
        }
        .system-ads-notification--avoid-ws {
            bottom: 85px;
        }

        .system-ads-notification__icon {
            display: flex;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: var(--primary, #3d6bf5);
            background: color-mix(in srgb, var(--primary, #3d6bf5) 12%, #ffffff);
            border-radius: 11px;
        }
        .system-ads-notification__icon--emoji {
            font-size: 21px;
            line-height: 1;
        }
        .system-ads-notification__body {
            flex: 1 1 auto;
            min-width: 0;
        }
        .system-ads-notification__title {
            font-size: 14px;
            font-weight: 600;
            line-height: 1.3;
        }
        .system-ads-notification__description {
            margin-top: 3px;
            font-size: 13px;
            line-height: 1.4;
            color: #67758b;
            overflow-wrap: break-word;
        }
        .system-ads-notification__link {
            display: inline-block;
            margin-top: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary, #3d6bf5);
        }
        .system-ads-notification__close {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            padding: 0;
            color: #94a1b3;
            background: transparent;
            border: 0;
            border-radius: 50%;
            cursor: pointer;
        }
        .system-ads-notification__close:hover {
            color: #182433;
            background: #f2f5f9;
        }

        @media (max-width: 575.98px) {
            .system-ads-notification {
                left: 12px;
                right: 12px;
                width: auto;
                max-width: none;
                transform: none;
            }
        }
    </style>

    <script>
        (function () {
            var notification = document.getElementById('systemAdsNotification');

            if (!notification) {
                return;
            }

            var STORAGE_KEY = 'system_ads_notification_dismissed';
            var version = notification.getAttribute('data-version');
            var duration = parseInt(notification.getAttribute('data-duration'), 10) || 0;
            var dismissed = null;
            var hideTimer = null;

            try {
                dismissed = window.localStorage.getItem(STORAGE_KEY);
            } catch (e) {
                dismissed = null;
            }

            if (dismissed === version) {
                notification.parentNode.removeChild(notification);
                return;
            }

            function remove() {
                notification.classList.remove('is-visible');

                window.setTimeout(function () {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 250);
            }

            function close() {
                try {
                    window.localStorage.setItem(STORAGE_KEY, version);
                } catch (e) {}

                window.clearTimeout(hideTimer);
                remove();
            }

            Array.prototype.forEach.call(
                notification.querySelectorAll('[data-system-ads-notification-close]'),
                function (element) {
                    element.addEventListener('click', close);
                }
            );

            notification.style.display = 'flex';

            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () {
                    notification.classList.add('is-visible');
                });
            });

            if (duration > 0) {
                hideTimer = window.setTimeout(remove, duration * 1000);
            }
        })();
    </script>
@endif
