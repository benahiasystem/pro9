
<?php
    use Illuminate\Support\Str;
    $path = explode('/', request()->path());
    // Para rutas tipo: ecommerce/{slug} → $path[1]
    $currentCategorySlug = $path[1] ?? '';
?>
<div class="container">
    <div class="row">
        <nav class="main-nav flex-grow-1">
            <ul class="all-category my-0 pb-4 mx-0 px-0">
                <li class="title-category">Nuestras Categorias</li>
                <li style="cursor:pointer;" onclick="window.location='<?php echo e(route('tenant.ecommerce.index')); ?>'">
                    <a href="<?php echo e(route('tenant.ecommerce.index')); ?>" class="<?php echo e($currentCategorySlug == '' ? 'bg-success text-light' : ''); ?>">Ver todos</a>
                </li>
            </ul>
            <div class="container">
                <ul id="scrollContainer" class="menu restaurante sf-arrows sf-js-enabled" style="touch-action: pan-y;">
                <?php $__currentLoopData = $categories_list; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php $categorySlug = \Illuminate\Support\Str::slug($category->name, '-'); ?>
                    <li class="menu-item ecommerce <?php echo e($currentCategorySlug == $categorySlug ? 'selected-category' : ''); ?>" style="cursor:pointer;" onclick="window.location='<?php echo e(route('tenant.ecommerce.category', $categorySlug)); ?>'">
                        <a href="<?php echo e(route('tenant.ecommerce.category', $categorySlug)); ?>">
                            <?php if($category->image && file_exists(public_path('storage/uploads/categories/'. $category->image))): ?>
                                <img class="category-logo" src="<?php echo e(asset('storage/uploads/categories/'. $category->image)); ?>" alt="<?php echo e($category->name); ?>" draggable="false">
                            <?php else: ?>
                                <img class="category-logo" src="<?php echo e(asset('logo/Image_not_available.png')); ?>" alt="<?php echo e($category->name); ?>" draggable="false">
                            <?php endif; ?>
                            <?php echo e($category->name); ?>

                        </a>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </ul>
            </div>
        </nav>
    </div>
</div>
<!-- codigo para el scroll de las categorias -->
<script>
  const container = document.getElementById('scrollContainer');

let isDragging = false;
let startX;
let scrollLeft;

// Evento de mouse down
container.addEventListener('mousedown', (e) => {
    isDragging = true;
    container.classList.add('active');
    startX = e.pageX - container.offsetLeft; // Punto de partida relativo al contenedor
    scrollLeft = container.scrollLeft;      // Desplazamiento actual
});

// Evento de mouse move
container.addEventListener('mousemove', (e) => {
    if (!isDragging) return; // Si no está arrastrando, no hacer nada
    e.preventDefault(); // Evitar selección de texto mientras arrastras
    const x = e.pageX - container.offsetLeft; // Posición actual
    const walk = (x - startX) * 2; // Distancia movida, ajustada para mayor sensibilidad
    container.scrollLeft = scrollLeft - walk;
});

// Evento de mouse up / mouse leave
['mouseup', 'mouseleave'].forEach(event => {
    container.addEventListener(event, () => {
        isDragging = false;
    });
});
// //arrar de imagenes de categorias
// const images = {
//     'Bebidas': `<?php echo e(asset('images/bebidas_cat.png')); ?>`,
//     'Brasas': `<?php echo e(asset('images/brasas_cat.png')); ?>`,
//     'Comida rápida': `<?php echo e(asset('images/comida_rapida_cat.png')); ?>`,
//     'Pizzas': `<?php echo e(asset('images/pizzas_cat.png')); ?>`,
//     'Makis': `<?php echo e(asset('images/makis_cat.png')); ?>`,
//     'Ensaladas': `<?php echo e(asset('images/ensaladas_cat.png')); ?>`,
//     'Salmones': `<?php echo e(asset('images/salmones_cat.png')); ?>`,
//     'Hamburguesas': `<?php echo e(asset('images/hamburguesa_cat.png')); ?>`,
//     'Caldos': `<?php echo e(asset('images/caldos_cat.png')); ?>`,
// };
// // console.log(images);

// //mostar las imagenes del array images dentro de una etiqueta img que esta dentro de un li
// const lis = document.querySelectorAll('.menu li a');
// lis.forEach((li, index) => {
//     const category = li.textContent.trim();
//     // console.log(category);
//     const img = document.createElement('img');
//     img.src = images[category];
//     // console.log(img.src);
//     img.style.width = '75px';
//     img.style.height = 'auto';
//     img.draggable = false;
//     li.prepend(img);
// });

</script>
<?php /**PATH C:\laragon\www\Pro9\modules\Ecommerce\Providers/../Resources/views/layouts/partials_ecommerce/categories.blade.php ENDPATH**/ ?>