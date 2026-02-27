<!DOCTYPE html>
<html dir="<?php echo e(app(\App\Settings\LocalizationSettings::class)->default_direction); ?>" lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('title'); ?> - <?php echo e(config('app.name', 'QwikTest')); ?></title>
    <meta name="description" content="<?php echo e(app(\App\Settings\SiteSettings::class)->seo_description); ?>">
    <link rel="icon" href="<?php echo e(url('storage/'.app(\App\Settings\SiteSettings::class)->favicon_path)); ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="<?php echo e(config('qwiktest.default_font_url')); ?>">

    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo e(asset('css/store.css')); ?>">
    <style>
        :root {
            /* Custom Theme Configuration */
            --custom-font: "<?php echo e(config('qwiktest.default_font')); ?>";
            --primary-color: <?php echo e('#'.app(\App\Settings\ThemeSettings::class)->primary_color); ?>;
            --secondary-color: <?php echo e('#'.app(\App\Settings\ThemeSettings::class)->secondary_color); ?>;
        }
    </style>
    <?php echo $__env->yieldPushContent('styles'); ?>
    <!-- Scripts -->
    <script src="<?php echo e(asset('vendor/alpinejs/alpine.min.js')); ?>" defer></script>
</head>
<body class="font-sans antialiased bg-white dark:bg-black">
    <!--TopBar-->
    <?php if (isset($component)) { $__componentOriginal7c8aae1bf9be6220308e251b49f0cfbdf09be43d = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\TopBar::class, []); ?>
<?php $component->withName('top-bar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal7c8aae1bf9be6220308e251b49f0cfbdf09be43d)): ?>
<?php $component = $__componentOriginal7c8aae1bf9be6220308e251b49f0cfbdf09be43d; ?>
<?php unset($__componentOriginal7c8aae1bf9be6220308e251b49f0cfbdf09be43d); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <!--NavBar-->
    <?php if (isset($component)) { $__componentOriginal08d9d46900ea68d5dc06d8728734a2fd47ca153c = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Navbar::class, []); ?>
<?php $component->withName('navbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal08d9d46900ea68d5dc06d8728734a2fd47ca153c)): ?>
<?php $component = $__componentOriginal08d9d46900ea68d5dc06d8728734a2fd47ca153c; ?>
<?php unset($__componentOriginal08d9d46900ea68d5dc06d8728734a2fd47ca153c); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php echo $__env->yieldContent('content'); ?>
    <!--Footer-->
    <?php if (isset($component)) { $__componentOriginal88b1957f21f7f49b400717e8d0a27189798132bf = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Footer::class, []); ?>
<?php $component->withName('footer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal88b1957f21f7f49b400717e8d0a27189798132bf)): ?>
<?php $component = $__componentOriginal88b1957f21f7f49b400717e8d0a27189798132bf; ?>
<?php unset($__componentOriginal88b1957f21f7f49b400717e8d0a27189798132bf); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /home/u664913565/domains/nielitbhubaneswar.in/public_html/__test/resources/views/store/layout.blade.php ENDPATH**/ ?>