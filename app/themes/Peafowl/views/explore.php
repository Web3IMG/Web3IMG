<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php G\Render\include_theme_header(); ?>

<?php CHV\Render\show_banner('explore_after_top', get_list()->sfw); ?>

<div class="content-width">

<?php 
$isCat = false;
if(function_exists('get_category') && isset(get_category()['name'])) {
?>
    <div class="header margin-top-20 margin-bottom-10">
        <h1><?php echo get_category()['name']; ?></h1>
    </div>
<?php } ?>
	<div class="header header-tabs follow-scroll no-select">
<?php if(function_exists('get_listing')) { ?>
        <h1><strong><?php echo '<span class="' . get_listing()['icon'] . '"></span><span class="phone-hide margin-left-5">' . get_listing()['label']; ?></span></strong>
        </h1>
<?php } ?>
    	<?php G\Render\include_theme_file("snippets/tabs"); ?>
		<?php
            if (is_content_manager()) {
                G\Render\include_theme_file("snippets/user_items_editor"); ?>
        <div class="header-content-right">
			<?php G\Render\include_theme_file("snippets/listing_tools_editor"); ?>
        </div>
		<?php
            }
        ?>
    </div>

    <div id="content-listing-tabs" class="tabbed-listing">
        <div id="tabbed-content-group">
            <?php
                G\Render\include_theme_file("snippets/listing");
            ?>
        </div>
    </div>

</div>

<?php G\Render\include_theme_footer(); ?>