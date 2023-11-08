<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>

<?php
$list = function_exists('get_list') ? get_list() : G\get_global('list');
$tabs = (array) (G\get_global('tabs') ? G\get_global('tabs') : (function_exists('get_tabs') ? get_tabs() : null));
foreach ($tabs as $tab) {
    if ((isset($tab['list']) && $tab['list'] === false) || isset($tab['tools']) && $tab['tools'] === false) {
        continue;
    } ?>
<div data-content="list-selection" data-tab="<?php echo $tab['id']; ?>" class="header--centering list-selection <?php $class = [];
    if (isset($list) && (is_array($list->output) == false || count($list->output) == 0)) {
        $class[] = 'disabled';
    }
    if (!$tab['current']) {
        $class[] = 'hidden';
    }
    echo implode(' ', $class); ?>">
	<div class="display-inline-block"><a data-action="list-select-all" class="header-link" data-text-select-all="<?php _se('Select all'); ?>" data-text-clear-all="<?php _se('Clear'); ?>"><?php _se('Select all'); ?></a></div>
	
	<div data-content="pop-selection" class="disabled sort-listing pop-btn header-link display-inline-block">
		<span class="selection-count" data-text="selection-count"></span><span class="pop-btn-text no-select margin-left-5" data-content="label"><span class="far fa-check-square margin-right-5"></span><?php _se('Actions'); ?><span class="arrow-down"></span></span>
		<div class="pop-box anchor-right arrow-box arrow-box-top">
			<div class="pop-box-inner pop-box-menu">
				<ul>
					<?php
                        if ($tab['type'] == 'images') {
                            ?>
					<li class="with-icon"><a data-action="get-embed-codes"><span class="btn-icon fas fa-code"></span><?php _se('Get embed codes'); ?></a></li>
					<?php
                        } ?>
					<?php
                        if (in_array(G\get_route_name(), ['user', 'album']) and (array_key_exists('tools_available', $tab) ? in_array('album', $tab['tools_available']) : true)) {
                            ?>
					<li class="with-icon"><a data-action="create-album"><span class="btn-icon fas fa-images"></span><?php _se('Create album'); ?></a></li>
					<li class="with-icon"><a data-action="move"><span class="btn-icon fas fa-exchange-alt"></span><?php _se('Move to album'); ?></a></li>
					<?php
                        } ?>
                    <?php
                        if ($tab['type'] == 'images') {
                            ?>
					<?php
                        if ((array_key_exists('tools_available', $tab) ? in_array('category', $tab['tools_available']) : true) and get_categories()) {
                            ?>
					<li class="with-icon"><a data-action="assign-category"><span class="btn-icon fas fa-columns"></span><?php _se('Assign category'); ?></a></li>
					<?php
                        } ?>
					<?php
                        if (is_allowed_nsfw_flagging() && (array_key_exists('tools_available', $tab) ? (in_array('flag', $tab['tools_available'])) : true)) {
                            ?>
					<li class="with-icon"><a data-action="flag-safe" class="hidden"><span class="btn-icon far fa-flag"></span><?php _se('Flag as safe'); ?></a></li>
					<li class="with-icon"><a data-action="flag-unsafe" class="hidden"><span class="btn-icon fas fa-flag"></span><?php _se('Flag as unsafe'); ?></a></li>
					<?php
                        }
                            if (G\Handler::getRouteName() == 'moderate') { ?>
                    <li class="with-icon"><a data-action="approve"><span class="btn-icon fas fa-check-double"></span><?php _se('Approve'); ?></a></li>
                    <?php
                            }
                        } // images?>
                    <li class="with-icon"><a data-action="clear"><span class="btn-icon fas fa-times-circle"></span><?php _se('Clear selection'); ?></a></li>
					<?php
                        if (is_allowed_to_delete_content() && (array_key_exists('tools_available', $tab) ? in_array('delete', $tab['tools_available']) : true)) {
                            ?>
                    <div class="or-separator margin-top-5 margin-bottom-5"></div>
					<li class="with-icon"><a data-action="delete" class="link--delete"><span class="btn-icon fas fa-trash-alt"></span><?php _se('Delete'); ?></a></li>
					<?php
                        } ?>
				</ul>
			</div>
		</div>
	</div>
</div>
<?php
}
?>