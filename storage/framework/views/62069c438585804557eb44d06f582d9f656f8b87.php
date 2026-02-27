<section id="explore" class="border-b border-gray-100">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:py-28 sm:px-6 lg:px-8">
        <div class="lg:text-center">
            <h2 class="text-base text-secondary font-semibold tracking-wide uppercase"><?php echo e(__('Categories')); ?></h2>
            <p class="mt-2 text-3xl leading-8 font-extrabold tracking-tight text-primary sm:text-4xl">
                <?php echo e($category['title']); ?>

            </p>
            <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">
                <?php echo e($category['subtitle']); ?>

            </p>
        </div>

        <div class="mt-10 flex flex-wrap items-center justify-center gap-7">
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="w-full sm:w-64 p-4 rounded border hover:shadow-lg">
                    <div class="flex justify-center items-center flex-col">
                        <div class="flex justify-center items-center flex-col mt-3">
                            <p class="font-medium leading-none text-gray-800"><?php echo e($category->name); ?></p>
                            <p class="text-sm font-medium leading-none text-gray-600 mt-2"><?php echo e($category->category->name); ?></p>
                        </div>
                    </div>
                    <div class="mt-8 w-full sm:w-56 h-9">
                        <a href="<?php echo e(route('explore', $category->slug)); ?>" class="focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 hover:opacity-90 flex items-center justify-center flex-1 h-full py-3 px-20 bg-primary border rounded border-primary">
                            <p class="text-sm font-medium leading-none text-white"><?php echo e(__('Explore')); ?></p>
                        </a>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
</section>
<?php /**PATH /home/u664913565/domains/nielitbhubaneswar.in/public_html/__test/resources/views/components/categories.blade.php ENDPATH**/ ?>