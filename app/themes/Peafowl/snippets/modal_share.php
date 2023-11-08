<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php $share_links_array = function_exists('get_share_links_array') ? get_share_links_array() : G\get_global("share_links_array"); ?>
<div id="modal-share" class="hidden">
	<span class="modal-box-title"><?php _se('Share'); ?></span>
    <div class="image-preview"></div>
    <p class="highlight margin-bottom-20 font-size-small text-align-center" data-content="privacy-private">__privacy_notes__</p>
	<ul class="panel-share-networks">
		<?php echo join("\n", $share_links_array ?? []); ?>
	</ul>
	<div class="input-label margin-bottom-0">
        <label for="modal-share-url"><?php _se('Link'); ?></label>
        <div class="position-relative">
            <input type="text" name="modal-share-url" id="modal-share-url" class="text-input" value="__url__" data-focus="select-all" readonly>
            <button class="input-action" data-action="copy" data-action-target="#modal-share-url" value=""><?php _se('copy'); ?></button>
        </div>
    </div>
</div>