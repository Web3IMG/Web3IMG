<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php
global $modal_dealing_with, $disable_album_toggle;
$user_items_editor = function_exists('get_user_items_editor') ? get_user_items_editor() : G\get_global('user_items_editor');
$modal_dealing_with = $user_items_editor["type"] ?? 'none';

if (!in_array($modal_dealing_with, array("images", "albums"))) {
    $modal_dealing_with = "images";
}
if ($user_items_editor !== false) {
    ?>
<div data-modal="form-edit-single" class="hidden">
    <span class="modal-box-title">
	<?php
        _se('Edit');
    if ($modal_dealing_with != 'images') {
        $disable_album_toggle = true;
    } ?>
	</span>
    <div class="image-preview"></div>
    <div class="modal-form">
		<?php
            G\Render\include_theme_file('snippets/form_' . ($modal_dealing_with == 'images' ? 'image' : 'album')); ?>
    </div>
</div>

<div data-modal="form-create-album" class="hidden">
	<span class="modal-box-title"><?php _se('Create album'); ?></span>
    <div class="image-preview"></div>
    <div class="modal-form">
		<div id="move-existing-album" data-view="switchable" class="c7 input-label soft-hidden">
			<?php G\Render\include_theme_file("snippets/form_move_existing_album"); ?>
		</div>
		<div id="move-new-album" data-content="form-new-album" data-view="switchable">
        	<?php
                G\Render\include_theme_file("snippets/form_album"); ?>
		</div>
	</div>
</div>

<div data-modal="form-move-single" class="hidden">
	<span class="modal-box-title"><?php _se('Move to album'); ?></span>
    <div class="image-preview"></div>
	<div class="modal-form">
		<div id="move-existing-album" data-view="switchable" class="c7 input-label">
			<?php G\Render\include_theme_file("snippets/form_move_existing_album"); ?>
		</div>
		<div id="move-new-album" data-content="form-new-album" data-view="switchable" class="soft-hidden">
			<?php
                $disable_album_toggle = false;
    G\Render\include_theme_file("snippets/form_album"); ?>
		</div>
	</div>
</div>

<div data-modal="form-move-multiple" class="hidden">
	<span class="modal-box-title"><?php _se('Move to album'); ?></span>
    <div class="image-preview"></div>
	<div class="modal-form">
		<div id="move-existing-album" data-view="switchable" class="c7 input-label">
			<?php G\Render\include_theme_file("snippets/form_move_existing_album"); ?>
		</div>
		<div id="move-new-album" data-content="form-new-album" data-view="switchable" class="soft-hidden">
			<?php G\Render\include_theme_file("snippets/form_album"); ?>
		</div>
	</div>
</div>

<?php
} // full editor?>

<div data-modal="form-assign-category" class="hidden">
	<span class="modal-box-title"><?php _se('Assign category'); ?></span>
	<?php if (get_categories()) { ?>
    <div class="image-preview"></div>
	<p><?php _se('All the selected images will be assigned to this category.'); ?></p>
	<div class="input-label c7">
		<?php G\Render\include_theme_file('snippets/form_category'); ?>
	</div>
	<?php } else { ?>
	<p><?php _se('There is no categories.'); ?></p>
	<?php } ?>
</div>

<div data-modal="form-flag-safe" class="hidden">
	<span class="modal-box-title"><?php _se('Confirm flag content as safe'); ?></span>
    <div class="image-preview"></div>
	<p><?php _se("Do you really want to flag this content as safe?"); ?></p>
</div>
<div data-modal="form-flag-unsafe" class="hidden">
	<span class="modal-box-title"><?php _se('Confirm flag content as unsafe'); ?></span>
    <div class="image-preview"></div>
	<p><?php _se("Do you really want to flag this content as unsafe?"); ?></p>
</div>

<div data-modal="form-approve-single" class="hidden">
	<span class="modal-box-title"><?php _se('Confirm approval'); ?></span>
    <div class="image-preview"></div>
	<p><?php _se("Do you really want to approve this content? This can't be undone."); ?></p>
</div>
<div data-modal="form-delete-single" class="hidden">
	<span class="modal-box-title"><?php _se('Confirm deletion'); ?></span>
    <div class="image-preview"></div>
	<p><?php _se("Do you really want to remove this content? This can't be undone."); ?></p>
</div>
<div data-modal="form-approve-multiple" class="hidden">
	<span class="modal-box-title"><?php _se('Confirm approval'); ?></span>
    <div class="image-preview"></div>
	<p><?php _se("Do you really want to approve all the selected content? This can't be undone."); ?></p>
</div>
<div data-modal="form-delete-multiple" class="hidden">
	<span class="modal-box-title"><?php _se('Confirm deletion'); ?></span>
    <div class="image-preview"></div>
	<p><?php _se("Do you really want to remove all the selected content? This can't be undone."); ?></p>
</div>