<div class="list-item-image-tools" data-action="list-tools">
	<div class="tool-select" data-action="select">
		<span data-icon-selected="fa-check-square" data-icon-unselected="fa-square" class="btn-icon far fa-square" title="<?php _se('Select'); ?>"></span>
	</div>
    <?php
    if (G\Handler::getCond('allowed_nsfw_flagging')) {
        ?>
	<div class="tool-flag" data-action="flag">
        <span class="btn-icon far fa-flag label-flag-unsafe" title="<?php _se('Toggle unsafe flag'); ?>"></span>
		<span class="btn-icon fas fa-flag label-flag-safe" title="<?php _se('Toggle unsafe flag'); ?>"></span>
	</div>
    <?php
    }
    if (G\Handler::getRouteName() == 'moderate') {
        ?>
    <div class="tool-approve" data-action="approve">
		<span class="btn-icon fas fa-check" title="<?php _se('Approve'); ?>"></span>
	</div>
    <?php
    }
    ?>
	<div class="tool-delete" data-action="delete">
		<span class="btn-icon fas fa-trash-alt" title="<?php _se('Delete'); ?>"></span>
	</div>
</div>