<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php G\Render\include_theme_header(); ?>

<div class="content-width">
	
	<div class="header margin-top-10 margin-bottom-10">
        <h1><span class="fas fa-search"></span><strong><?php if (!get_safe_html_search()['q']) {
    ?>
			<?php _se('Search results'); ?>
			<?php
} else {
        ?>
			<?php echo get_safe_html_search()["d"]; ?>
			<?php
    } ?></strong></h1>
    </div>

	<div class="header header-tabs follow-scroll no-select">
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