<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php G\Render\include_theme_header(); ?>

<div class="content-width">
	<div class="header header-tabs follow-scroll">
    	<h1><strong><?php echo '<span class="' . get_listing()['icon'] . '"></span><span class="phone-hide margin-left-5">' . get_listing()['label']; ?></span></strong></h1>
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