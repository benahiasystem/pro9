@php
    $currentCategorySlug = request()->route('category');
    $mobileMenuPrefs = optional(\App\Models\Tenant\ConfigurationEcommerce::first())->preferences;
    if (is_string($mobileMenuPrefs)) {
        $mobileMenuPrefs = json_decode($mobileMenuPrefs, true);
    }
    $mobileMenuTheme = data_get($mobileMenuPrefs, 'header_theme', 'light');
    $mobileMenuLogoDark = data_get($company ?? null, 'logo_dark');
    $mobileMenuLogo = ($mobileMenuTheme === 'dark' && $mobileMenuLogoDark)
        ? $mobileMenuLogoDark
        : (data_get($company ?? null, 'logo') ?: data_get($information ?? null, 'logo'));
    $mobileMenuStoreName = data_get($company ?? null, 'trade_name')
        ?: data_get($company ?? null, 'name')
        ?: 'Nuestra tienda';
@endphp

<style>
    .mobile-menu-container {
        width: min(88vw, 340px) !important;
        background: #fff !important;
        box-shadow: 18px 0 48px rgba(15, 23, 42, .18);
        transform: translateX(-100%);
        transition: transform .32s cubic-bezier(.22, 1, .36, 1) !important;
    }
    .mmenu-active .mobile-menu-container {
        transform: translateX(0);
    }
    .mmenu-active .page-wrapper {
        transform: none !important;
    }
    .mobile-menu-wrapper {
        display: flex;
        flex-direction: column;
        height: 100%;
        padding: 0 !important;
        background: #fff !important;
        color: #172033;
    }
    .mobile-app-menu-header {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 112px;
        padding: max(22px, env(safe-area-inset-top)) 58px 20px 20px;
        overflow: hidden;
        background: linear-gradient(135deg, hsl(var(--primary-h), var(--primary-s), 97%), #fff 72%);
        border-bottom: 1px solid #edf0f4;
    }
    .mobile-app-menu-header::after {
        position: absolute;
        right: -34px;
        bottom: -48px;
        width: 120px;
        height: 120px;
        content: '';
        border-radius: 50%;
        background: hsl(var(--primary-h), var(--primary-s), 92%);
        opacity: .7;
    }
    .mobile-app-menu-logo {
        position: relative;
        z-index: 1;
        display: grid;
        flex: 0 0 58px;
        width: 58px;
        height: 58px;
        place-items: center;
        padding: 7px;
        overflow: hidden;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, .07);
        border-radius: 17px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .09);
    }
    .mobile-app-menu-logo img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .mobile-app-menu-welcome {
        position: relative;
        z-index: 1;
        min-width: 0;
    }
    .mobile-app-menu-welcome small {
        display: block;
        margin-bottom: 2px;
        color: #718096;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .mobile-app-menu-welcome strong {
        display: block;
        overflow: hidden;
        color: #172033;
        font-size: 16px;
        line-height: 1.25;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .mobile-menu-wrapper .mobile-menu-close {
        position: absolute;
        z-index: 3;
        top: max(18px, env(safe-area-inset-top));
        right: 16px;
        display: grid;
        width: 38px;
        height: 38px;
        place-items: center;
        padding: 0;
        color: #475569;
        background: rgba(255, 255, 255, .92);
        border: 1px solid #e6eaf0;
        border-radius: 12px;
        box-shadow: 0 5px 16px rgba(15, 23, 42, .08);
        cursor: pointer;
        transition: color .2s, background .2s, transform .2s;
    }
    .mobile-menu-wrapper .mobile-menu-close:hover {
        color: var(--primary-color);
        background: #fff;
        transform: rotate(5deg);
    }
    .mobile-nav {
        flex: 1 1 auto;
        padding: 18px 14px 12px;
        overflow-x: hidden;
        overflow-y: auto;
    }
    .mobile-menu-wrapper .mobile-menu {
        margin: 0;
        padding: 0;
        border: 0;
    }
    .mobile-menu-wrapper .mobile-menu > li {
        margin-bottom: 7px;
        border: 0;
    }
    .mobile-menu-wrapper .mobile-menu > li > a {
        position: relative;
        display: flex;
        min-height: 52px;
        align-items: center;
        gap: 13px;
        padding: 10px 46px 10px 13px;
        color: #344054;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: .01em;
        text-transform: none;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 14px;
        transition: color .2s, background .2s, border-color .2s, transform .2s;
    }
    .mobile-menu-wrapper .mobile-menu > li > a:hover,
    .mobile-menu-wrapper .mobile-menu > li.active > a {
        color: var(--primary-color);
        text-decoration: none;
        background: hsl(var(--primary-h), var(--primary-s), 97%);
        border-color: hsl(var(--primary-h), var(--primary-s), 91%);
        transform: translateX(2px);
    }
    .mobile-menu-link-icon {
        display: grid;
        flex: 0 0 36px;
        width: 36px;
        height: 36px;
        place-items: center;
        color: var(--primary-color);
        background: hsl(var(--primary-h), var(--primary-s), 94%);
        border-radius: 11px;
    }
    .mobile-menu-link-icon svg {
        width: 19px;
        height: 19px;
    }
    .mobile-menu-wrapper .mobile-menu .mmenu-btn {
        right: 8px;
        width: 38px;
        height: 38px;
        color: #667085;
        border-radius: 10px;
    }
    .mobile-menu-wrapper .mobile-menu ul {
        margin: 5px 0 8px 49px;
        padding: 5px;
        background: #f8fafc;
        border: 1px solid #edf0f4;
        border-radius: 13px;
    }
    .mobile-menu-wrapper .mobile-menu ul li {
        border: 0;
    }
    .mobile-menu-wrapper .mobile-menu ul a {
        padding: 10px 12px;
        color: #596579;
        font-size: 13px;
        font-weight: 600;
        text-transform: none;
        border-radius: 9px;
    }
    .mobile-menu-wrapper .mobile-menu ul a:hover,
    .mobile-menu-wrapper .mobile-menu ul li.active > a {
        color: var(--primary-color);
        text-decoration: none;
        background: #fff;
    }
    .mobile-app-menu-footer {
        flex: 0 0 auto;
        padding: 14px 18px calc(18px + env(safe-area-inset-bottom));
        background: #fff;
        border-top: 1px solid #edf0f4;
    }
    .mobile-app-menu-footer-label {
        display: block;
        margin-bottom: 10px;
        color: #98a2b3;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .1em;
        text-align: center;
        text-transform: uppercase;
    }
    .mobile-menu-wrapper .mobile-app-socials {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        margin: 0;
    }
    .mobile-menu-wrapper .mobile-app-socials .social-icon {
        display: grid;
        width: 40px;
        height: 40px;
        place-items: center;
        margin: 0;
        color: #475569;
        background: #f6f8fb;
        border: 1px solid #e8ecf2;
        border-radius: 50%;
        transition: color .2s, background .2s, border-color .2s, transform .2s;
    }
    .mobile-menu-wrapper .mobile-app-socials .social-icon:hover {
        color: #fff;
        background: var(--primary-color);
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }
    .mobile-menu-wrapper .mobile-app-socials svg {
        width: 18px;
        height: 18px;
    }
    .mobile-menu-overlay {
        background: rgba(15, 23, 42, .48) !important;
        backdrop-filter: blur(2px);
    }
</style>

<div class="mobile-menu-wrapper">
    <header class="mobile-app-menu-header">
        <span class="mobile-app-menu-logo">
            <img src="{{ $mobileMenuLogo ? asset('storage/uploads/logos/'.$mobileMenuLogo) : asset('logo/tulogo.png') }}" alt="{{ $mobileMenuStoreName }}">
        </span>
        <span class="mobile-app-menu-welcome">
            <small>Bienvenido</small>
            <strong>{{ $mobileMenuStoreName }}</strong>
        </span>
        <button type="button" class="mobile-menu-close" aria-label="Cerrar menú">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
    </header>
    <nav class="mobile-nav">
        <ul class="mobile-menu">
            <li class="{{ request()->routeIs('tenant.ecommerce.index') ? 'active' : '' }}">
                <a href="{{ route('tenant.ecommerce.index') }}">
                    <span class="mobile-menu-link-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg></span>
                    <span>Home</span>
                </a>
            </li>
            <li class="{{ $currentCategorySlug ? 'active' : '' }}">
                <a href="#">
                    <span class="mobile-menu-link-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg></span>
                    <span>Categorías</span>
                </a>
                <ul>
                    @foreach ($categories as $category)
                        @php($categorySlug = \Illuminate\Support\Str::slug($category->name, '-'))
                        <li class="{{ ($currentCategorySlug == $categorySlug) ? 'active':'' }}">
                            <a href="{{ route('tenant.ecommerce.category', $categorySlug) }}">
                                {{$category->name}}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </li>
            {{-- <li>
                <a href="product.html">Products</a>
                <ul>
                    <li>
                        <a href="#">Variations</a>
                        <ul>
                            <li><a href="product.html">Horizontal Thumbnails</a></li>
                            <li><a href="product-full-width.html">Vertical Thumbnails<span
                                        class="tip tip-hot">Hot!</span></a></li>
                            <li><a href="product.html">Inner Zoom</a></li>
                            <li><a href="product-addcart-sticky.html">Addtocart Sticky</a></li>
                            <li><a href="product-sidebar-left.html">Accordion Tabs</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Variations</a>
                        <ul>
                            <li><a href="product-sticky-tab.html">Sticky Tabs</a></li>
                            <li><a href="product-simple.html">Simple Product</a></li>
                            <li><a href="product-sidebar-left.html">With Left Sidebar</a></li>
                        </ul>
                    </li>
                    <li>
                        <a href="#">Product Layout Types</a>
                        <ul>
                            <li><a href="product.html">Default Layout</a></li>
                            <li><a href="product-extended-layout.html">Extended Layout</a></li>
                            <li><a href="product-full-width.html">Full Width Layout</a></li>
                            <li><a href="product-grid-layout.html">Grid Images Layout</a></li>
                            <li><a href="product-sticky-both.html">Sticky Both Side Info<span
                                        class="tip tip-hot">Hot!</span></a></li>
                            <li><a href="product-sticky-info.html">Sticky Right Side Info</a></li>
                        </ul>
                    </li>
                </ul>
            </li> --}}
            {{-- <li>
                <a href="#">Pages<span class="tip tip-hot">Hot!</span></a>
                <ul>
                    <li><a href="cart.html">Shopping Cart</a></li>
                    <li>
                        <a href="#">Checkout</a>
                        <ul>
                            <li><a href="checkout-shipping.html">Checkout Shipping</a></li>
                            <li><a href="checkout-shipping-2.html">Checkout Shipping 2</a></li>
                            <li><a href="checkout-review.html">Checkout Review</a></li>
                        </ul>
                    </li>
                    <li><a href="about.html">About</a></li>
                    <li><a href="#" class="login-link">Login</a></li>
                    <li><a href="forgot-password.html">Forgot Password</a></li>
                </ul>
            </li> --}}
            {{-- <li><a href="blog.html">Blog</a>
                <ul>
                    <li><a href="single.html">Blog Post</a></li>
                </ul>
            </li> --}}
            <li class="{{ request()->routeIs('tenant_detail_cart') ? 'active' : '' }}">
                <a href="{{ route('tenant_detail_cart') }}">
                    <span class="mobile-menu-link-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/></svg></span>
                    <span>Ver carrito</span>
                </a>
            </li>
            {{-- <li><a href="#">Special Offer!<span class="tip tip-hot">Hot!</span></a></li>
            <li><a href="#">Buy Porto!</a></li> --}}
        </ul>
    </nav><!-- End .mobile-nav -->

    <footer class="mobile-app-menu-footer">
    <span class="mobile-app-menu-footer-label">Síguenos</span>
    <div class="social-icons mobile-app-socials">
        @if($information->link_facebook)
            <a href="{{$information->link_facebook}}" class="social-icon" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 8h3V4h-3c-3 0-5 2-5 5v2H6v4h3v7h4v-7h3l1-4h-4V9c0-.6.4-1 1-1Z"/></svg></a>
        @endif

        @if($information->link_tiktok)
            <a href="{{$information->link_tiktok}}" class="social-icon" target="_blank" rel="noopener" aria-label="TikTok"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 4v10.5a5 5 0 1 1-4-4.9"/><path d="M15 4c.5 2.5 2 4 4.5 4.5"/></svg></a>
        @endif

        @if($information->link_instagram)
            <a href="{{$information->link_instagram}}" class="social-icon" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></a>
        @endif

        @if($information->link_twitter)
            <a href="{{$information->link_twitter}}" class="social-icon" target="_blank" rel="noopener" aria-label="X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 2H22l-6.8 7.8L23.2 22H17l-4.9-6.4L6.5 22H3.4l7.2-8.2L2.8 2h6.4l4.4 5.8L18.9 2Zm-1.1 17.8h1.7L8.3 4H6.5l11.3 15.8Z"/></svg></a>
        @endif

        @if($information->link_youtube)
            <a href="{{$information->link_youtube}}" class="social-icon" target="_blank" rel="noopener" aria-label="YouTube"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2a2.8 2.8 0 0 0-2-2C17.9 4.7 12 4.7 12 4.7s-5.9 0-7.6.5a2.8 2.8 0 0 0-2 2A29 29 0 0 0 2 12a29 29 0 0 0 .4 4.8 2.8 2.8 0 0 0 2 2c1.7.5 7.6.5 7.6.5s5.9 0 7.6-.5a2.8 2.8 0 0 0 2-2A29 29 0 0 0 22 12a29 29 0 0 0-.4-4.8ZM10 15.2V8.8l5.5 3.2-5.5 3.2Z"/></svg></a>
        @endif
    </div><!-- End .social-icons -->
    </footer>
</div><!-- End .mobile-menu-wrapper -->
