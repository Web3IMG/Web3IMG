<div class="list-item-image-tools" data-action="list-tools">
	<div class="tool-select" data-action="select">
		<span data-icon-selected="fa-check-square" data-icon-unselected="fa-square" class="btn-icon far fa-square" title="<?php _se('Select'); ?>"></span>
	</div>
	<div class="tool-edit" data-action="edit"><span class="btn-icon fas fa-edit"></span><span class="label label-edit"><?php _se('Edit'); ?></span></div>
	<div class="tool-move" data-action="move">
		<span class="btn-icon fas fa-images" title="<?php _ne('Album', 'Albums', 1); ?>"></span>
	</div>
	<?php
		if(G\Handler::getCond('allowed_to_delete_content')) {
	?>
	<div class="tool-delete" data-action="delete">
		<span class="btn-icon fas fa-trash-alt" title="<?php _se('Delete'); ?>"></span>
	</div>
	<?php
		}
	?>
</div>