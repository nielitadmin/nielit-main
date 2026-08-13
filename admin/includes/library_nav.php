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
<script>
function libraryConfirmReturn(e, form) {
    e.preventDefault();
    var message = 'Mark this book as returned? The return date will be recorded as today.';
    if (typeof showConfirm !== 'function') {
        if (window.confirm(message)) {
            form.submit();
        }
        return false;
    }
    showConfirm({
        title: 'Return book',
        message: message,
        type: 'warning',
        confirmText: 'Return',
        cancelText: 'Cancel'
    }).then(function (ok) {
        if (ok) {
            form.submit();
        }
    });
    return false;
}
</script>
