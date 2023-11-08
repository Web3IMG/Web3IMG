<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php G\Render\include_theme_header(); ?>

<div class="content-width">
	<div class="c24 center-box margin-top-40 margin-bottom-40">
        <div class="header default-margin-bottom">
            <h1>Example page</h1>
        </div>
        <div class="text-content">
            <p>This is an example page for your Chevereto site.</p>
			<h2>Creating and editing pages</h2>
			<p>To learn how add or modify a page go to our <a href="https://v3-docs.chevereto.com/settings/pages.html" target="_blank">Pages documentation</a>.</p>
			<p><a href="https://v3-docs.chevereto.com/settings/pages.html" class="btn btn-capsule default" target="_blank"><span class="btn-icon fas fa-book"></span> Documentation</a></p>
		</div>
	</div>
</div>

<?php G\Render\include_theme_footer(); ?>