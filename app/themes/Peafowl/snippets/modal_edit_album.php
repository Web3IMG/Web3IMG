<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>

<div id="form-modal" class="hidden" data-before-fn="CHV.fn.before_album_edit" data-submit-fn="CHV.fn.submit_album_edit" data-ajax-deferred="CHV.fn.complete_album_edit" data-ajax-url="<?php echo G\get_base_url("json"); ?>">
    <h1><?php _se('Edit'); ?></h1>
    <div class="modal-form">
        <?php G\Render\include_theme_file('snippets/form_album'); ?>
    </div>
</div>