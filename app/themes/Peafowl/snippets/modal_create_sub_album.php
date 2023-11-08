<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>

<div data-modal="new-sub-album" class="hidden" data-is-xhr data-submit-fn="CHV.fn.submit_create_album" data-ajax-deferred="CHV.fn.complete_create_album">
    <h1><?php _se('Create sub album'); ?></h1>
	<div class="modal-form">
	<?php G\Render\include_theme_file("snippets/form_sub_album.php", ['album-switch' => false]); ?>
	</div>
</div>