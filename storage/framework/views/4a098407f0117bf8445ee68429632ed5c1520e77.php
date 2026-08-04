
<?php
// Los items ya llegan filtrados desde el ViewComposer.
$banners = $items;
?>

<?php if($banners->isNotEmpty()): ?>
<div class="banner-slider-wrapper" style="position: relative;">
    <div class="home-slider ecommerce owl-carousel owl-carousel-lazy owl-theme owl-theme-light <?php echo e($full_width_banner ? 'full-width-banner' : ''); ?>">
        <?php $__currentLoopData = $banners; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="home-slide">
            <?php
            $bannerHref = null;
            if (!empty($item->item_id)) {
            $itemSlug = $item->item
            ? \Illuminate\Support\Str::slug($item->item->description)
            : '';
            $bannerHref = url('/ecommerce/item/' . $item->item_id . '/' . $itemSlug) . '?promotion=' . $item->id;
            }
            ?>

            <?php if($bannerHref): ?>
            <a href="<?php echo e($bannerHref); ?>" class="banner-slide-link" title="Ver producto">
                <?php endif; ?>

                <?php
                $bannerVersion = optional($item->updated_at)->timestamp ?: time();
                $bannerSrc = asset('storage/uploads/promotions/'.$item->image).'?v='.$bannerVersion;
                ?>
                <div class="owl-lazy slide-bg" data-src="<?php echo e($bannerSrc); ?>"></div>
                <div class="home-slide-content text-white">
                    
                </div>

                <?php if($bannerHref): ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <?php if($banners->count() > 1): ?>
    <button type="button" class="banner-nav-btn banner-nav-prev" onclick="navigateEcommerceBanner('prev')">
        <span>
            <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-left">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M15 6l-6 6l6 6" />
            </svg>
        </span>
    </button>
    <button type="button" class="banner-nav-btn banner-nav-next" onclick="navigateEcommerceBanner('next')">
        <span>
            <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-chevron-right">
                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                <path d="M9 6l6 6l-6 6" />
            </svg>
        </span>
    </button>
    <?php endif; ?>
</div>

<script>
    function navigateEcommerceBanner(direction) {
        var owl = $('.home-slider.ecommerce');
        if (direction === 'next') {
            owl.trigger('next.owl.carousel');
        } else {
            owl.trigger('prev.owl.carousel');
        }
    }

    $(document).ready(function() {
        $('.banner-nav-btn').hover(
            function() {
                $(this).css('background', 'rgba(0,0,0,0.8)');
            },
            function() {
                $(this).css('background', 'rgba(0,0,0,0.5)');
            }
        );

        var $owl = $('.home-slider.ecommerce');

        function numberOwlDots() {
            var $dots = $owl.find('.owl-dots .owl-dot span');
            if (!$dots.length) return;

            $owl.attr('data-dots-numbered', '1');
            $dots.each(function(index) {
                $(this).text(index + 1);
            });
        }

        $owl.on('initialized.owl.carousel refreshed.owl.carousel', function() {
            numberOwlDots();
        });
        setTimeout(numberOwlDots, 0);
    });
</script>

<style>
    .banner-slider-wrapper {
        position: relative;
    }

    .banner-slide-link {
        display: block;
        width: 100%;
        height: 100%;
        color: inherit;
        text-decoration: none;
    }
</style>
<?php endif; ?><?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/partials_ecommerce/home_slider.blade.php ENDPATH**/ ?>