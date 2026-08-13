<?php
/** @var string $libraryCurrent */
/** @var list<array{file:string,label:string,icon:string}> $libraryNav */
?>
<div class="btn-group" role="group" style="flex-wrap:wrap;">
    <?php foreach ($libraryNav as $item): ?>
        <a class="btn btn-sm <?php echo $libraryCurrent === $item['file'] ? 'btn-primary' : 'btn-secondary'; ?>"
           href="<?php echo htmlspecialchars($item['file']); ?>">
            <i class="fas fa-<?php echo htmlspecialchars($item['icon']); ?>"></i>
            <?php echo htmlspecialchars($item['label']); ?>
        </a>
    <?php endforeach; ?>
</div>
