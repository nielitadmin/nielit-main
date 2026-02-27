<?php $__env->startSection('title', $siteSettings->tag_line); ?>

<?php $__env->startSection('content'); ?>
    <!--Hero-->
    <?php if($homePageSettings->enable_hero): ?>
        <?php if (isset($component)) { $__componentOriginalbd6d4d0fcd4660b0447ec3a000aa6bf28aea6570 = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Hero::class, []); ?>
<?php $component->withName('hero'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginalbd6d4d0fcd4660b0447ec3a000aa6bf28aea6570)): ?>
<?php $component = $__componentOriginalbd6d4d0fcd4660b0447ec3a000aa6bf28aea6570; ?>
<?php unset($__componentOriginalbd6d4d0fcd4660b0447ec3a000aa6bf28aea6570); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php endif; ?>

    <!--Features-->
    <?php if($homePageSettings->enable_features): ?>
        <?php if (isset($component)) { $__componentOriginal9896791f17a81069298d61b559ac9f1149c1ecfc = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Features::class, []); ?>
<?php $component->withName('features'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal9896791f17a81069298d61b559ac9f1149c1ecfc)): ?>
<?php $component = $__componentOriginal9896791f17a81069298d61b559ac9f1149c1ecfc; ?>
<?php unset($__componentOriginal9896791f17a81069298d61b559ac9f1149c1ecfc); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php endif; ?>

    <!--Categories-->
    <?php if($homePageSettings->enable_categories): ?>
        <?php if (isset($component)) { $__componentOriginal18c84cce4a0aa799358027651cc2fcff0a147f1d = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Categories::class, []); ?>
<?php $component->withName('categories'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal18c84cce4a0aa799358027651cc2fcff0a147f1d)): ?>
<?php $component = $__componentOriginal18c84cce4a0aa799358027651cc2fcff0a147f1d; ?>
<?php unset($__componentOriginal18c84cce4a0aa799358027651cc2fcff0a147f1d); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php endif; ?>

    <!--Stats-->
    <?php if($homePageSettings->enable_stats): ?>
        <?php if (isset($component)) { $__componentOriginal8365f75f4f90ed1df159db6f78dcfa5518b6b568 = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Stats::class, []); ?>
<?php $component->withName('stats'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal8365f75f4f90ed1df159db6f78dcfa5518b6b568)): ?>
<?php $component = $__componentOriginal8365f75f4f90ed1df159db6f78dcfa5518b6b568; ?>
<?php unset($__componentOriginal8365f75f4f90ed1df159db6f78dcfa5518b6b568); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php endif; ?>

    <!--Testimonials-->
    <?php if($homePageSettings->enable_testimonials): ?>
        <?php if (isset($component)) { $__componentOriginal99843580f067249f9ed6ac259729d2bf4b8605e4 = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Testimonials::class, []); ?>
<?php $component->withName('testimonials'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginal99843580f067249f9ed6ac259729d2bf4b8605e4)): ?>
<?php $component = $__componentOriginal99843580f067249f9ed6ac259729d2bf4b8605e4; ?>
<?php unset($__componentOriginal99843580f067249f9ed6ac259729d2bf4b8605e4); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php endif; ?>

    <!--CTA-->
    <?php if($homePageSettings->enable_cta): ?>
        <?php if (isset($component)) { $__componentOriginald677f82e54e42f61b391c66e66511c5bb1c2da69 = $component; } ?>
<?php $component = $__env->getContainer()->make(App\View\Components\Cta::class, []); ?>
<?php $component->withName('cta'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php $component->withAttributes([]); ?>
<?php if (isset($__componentOriginald677f82e54e42f61b391c66e66511c5bb1c2da69)): ?>
<?php $component = $__componentOriginald677f82e54e42f61b391c66e66511c5bb1c2da69; ?>
<?php unset($__componentOriginald677f82e54e42f61b391c66e66511c5bb1c2da69); ?>
<?php endif; ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
    <?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('store.layout', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/u664913565/domains/nielitbhubaneswar.in/public_html/__test/resources/views/store/index.blade.php ENDPATH**/ ?>