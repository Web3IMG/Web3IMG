<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php G\Render\include_theme_header(); ?>

<div class="content-width">

	<?php CHV\Render\show_banner('album_before_header', get_list()->sfw); ?>

	<div class="header header-content margin-bottom-10">
		<div class="header-content-left">
			<div class="header-content-breadcrum">
				<div class="breadcrum-item">
					<span class="breadcrum-text"><span class="icon fas fa-eye-slash <?php if (get_album()["privacy"] == "public") {
                    echo "soft-hidden";
                } ?>" data-content="privacy-private" title="<?php _se('This content is private'); ?>" rel="tooltip"></span>
				</div>
				<?php
                if (is_owner() or is_content_manager()) {
                    ?>
					<div class="breadcrum-item">
						<a class="link link--edit" data-modal="edit"><span class="icon fas fa-edit"></span><span class="phone-hide margin-left-5"><?php _se('Edit'); ?></span></a>
                    </div>
                    <div class="breadcrum-item">
                        <a class="link link--edit" data-modal="edit" data-target="new-sub-album"><span class="icon fas fa-level-down-alt"></span><span class="phone-hide margin-left-5"><?php _se('Sub album'); ?></span></a>
                    </div>
					<?php
                    if (is_allowed_to_delete_content()) {
                        ?>
						<div class="breadcrum-item">
							<a class="link link--delete" data-confirm="<?php _se("Do you really want to delete this album and all of its images? This can't be undone."); ?>" data-submit-fn="CHV.fn.submit_resource_delete" data-ajax-deferred="CHV.fn.complete_resource_delete" data-ajax-url="<?php echo G\get_base_url("json"); ?>"><span class="icon fas fa-trash-alt"></span><span class="phone-hide margin-left-5"><?php _se('Delete'); ?></span></a>
						</div>
					<?php
                    } ?>
				<?php
                }
                ?>
			</div>
		</div>
		<div class="header-content-right">
        <?php
            if (is_owner()) {
                if (CHV\getSetting('upload_gui') == 'js' && CHV\getSetting('homepage_style') !== 'route_upload') {
                    $createAlbumTag = 'button';
                    $createAlbumAttr = 'data-trigger="anywhere-upload-input"';
                } else {
                    $createAlbumTag = 'a';
                    $createAlbumAttr = 'href="' . G\get_base_url(sprintf('upload/?toAlbum=%s', get_album()['id_encoded'])) . '"';
                } ?>
				<<?php echo $createAlbumTag; ?> class="btn default" <?php echo $createAlbumAttr; ?>><span class="btn-icon fas fa-cloud-upload-alt"></span><span class="btn-text phone-hide"><?php _se('Upload to album'); ?></span></<?php echo $createAlbumTag; ?>>
			<?php
            }
            ?>
            <?php
            if (CHV\getSetting('enable_likes')) {
                ?>
				<a class="btn-like" data-type="album" data-id="<?php echo get_album()['id_encoded']; ?>" data-liked="<?php echo (int) (get_album()['liked'] ?? '0'); ?>" data-action="like">
					<span class="btn btn-liked red" rel="tooltip" title="<?php _se("You like this"); ?>"><span class="btn-icon fas fa-heart"></span><span class="btn-text" data-text="likes-count"><?php echo get_album()['likes']; ?></span></span>
					<span class="btn btn-unliked red outline"><span class="btn-icon far fa-heart"></span><span class="btn-text" data-text="likes-count"><?php echo get_album()['likes']; ?></span></span>
				</a>
			<?php
            }
            ?>
			<?php
            if (CHV\getSetting('theme_show_social_share')) {
                ?>
				<a class="btn green" data-action="share"><span class="btn-icon fas fa-share-alt"></span><span class="btn-text phone-hide"><?php _se('Share'); ?></span></a>
			<?php
            }
            ?>
		</div>

	</div>

    <div class="header header-content margin-bottom-10">
        <div class="header-content-left">
            <div class="header-content-breadcrum">
    <?php
                if (get_album()['user']['id']) {
                    G\Render\include_theme_file("snippets/breadcrum_owner_card");
                } else {
                    ?>
					<div class="breadcrum-item">
						<div class="user-image default-user-image"><span class="icon fas fa-meh"></span></div>
					</div>
				<?php
                }
                ?>
            </div>
        </div>
        <div class="header-content-right phone-margin-bottom-20">
            <div class="number-figures float-left"><?php echo get_album()['views']; ?> <span><?php echo get_album()['views_label']; ?></span></div>
        </div>
    </div>


	<?php CHV\Render\show_banner('album_after_header', get_list()->sfw); ?>
    
    <div class="header margin-bottom-10">
        <h1 class="text-overflow-ellipsis"><a data-text="album-name" href="<?php echo get_album()["url"]; ?>"><?php echo get_album()["name_truncated_html"]; ?></a></h1>
    </div>
    <div class="description-meta margin-bottom-10" data-text="album-description">
    <?php echo nl2br(trim(get_album_safe_html()['description'])); ?>
    </div>
    <div class="description-meta margin-bottom-10">
        <span data-text="image-count"><?php echo get_album()["image_count"]; ?></span> <span data-text="image-label" data-label-single="<?php _ne('image', 'images', 1); ?>" data-label-plural="<?php _ne('image', 'images', 2); ?>"><?php _ne('image', 'images', get_album()['image_count']); ?></span> — <?php echo '<span title="' . get_album()['date_fixed_peer'] . '">' . CHV\time_elapsed_string(get_album()['date_gmt']) . '</span>'; ?>
    </div>

    <div class="header header-tabs follow-scroll no-select">
        <?php G\Render\include_theme_file("snippets/tabs"); ?>
        <?php
        if (is_owner() or is_content_manager()) {
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

			<?php if (CHV\isShowEmbedContent()) {
                ?>
				<div id="tab-embeds" class="tabbed-content margin-top-30">
						<div class="content-listing-loading"></div>
						<div id="embed-codes" class="input-label margin-bottom-0 margin-top-0 copy-hover-display soft-hidden">
							<label for="album-embed-toggle"><?php _se('Embed codes'); ?></label>
							<div class="c7 margin-bottom-10">
								<select name="album-embed-toggle" id="album-embed-toggle" class="text-input" data-combo="album-embed-toggle-combo">
									<?php
                                    foreach (G\get_global('embed_tpl') as $key => $value) {
                                        echo '<optgroup label="' . $value['label'] . '">' . "\n";
                                        foreach ($value['options'] as $k => $v) {
                                            echo '	<option value="' . $k . '" data-size="' . $v["size"] . '">' . $v["label"] . '</option>' . "\n";
                                        }
                                        echo '</optgroup>';
                                    } ?>
								</select>
							</div>
							<div id="album-embed-toggle-combo" class="position-relative">
								<?php
                                $i = 0;
                foreach (G\get_global('embed_tpl') as $key => $value) {
                    foreach ($value['options'] as $k => $v) {
                        echo '<div data-combo-value="' . $k . '" class="switch-combo' . ($i > 0 ? " soft-hidden" : "") . '">
										<textarea id="album-embed-code-' . $i . '" class="r8 resize-vertical" name="' . $k . '" data-size="' . $v["size"] . '" data-focus="select-all"></textarea>
										<button class="input-action" data-action="copy" data-action-target="#album-embed-code-' . $i . '">' . _s('copy') . '</button>
									</div>' . "\n";
                        $i++;
                    }
                } ?>
							</div>
						</div>
				</div>
			<?php
            } ?>
			<?php
            if (is_admin()) {
                ?>
				<div id="tab-info" class="tabbed-content<?php if (get_current_tab() === 'tab-info') {
                    echo ' visible';
                } ?>">
					<?php echo CHV\Render\arr_printer(get_album_safe_html(), '<li><div class="c4 display-table-cell padding-right-10 font-weight-bold">%K</div> <div class="display-table-cell">%V</div></li>', ['<ul class="tabbed-content-list table-li">', '</ul>']); ?>
				</div>
			<?php
            }
            ?>

		</div>

	</div>

</div>



<?php
if (is_content_manager() or is_owner()) {
                ?>
	
    <?php G\Render\include_theme_file('snippets/modal_edit_album'); ?>
    <?php G\Render\include_theme_file('snippets/modal_create_sub_album'); ?>
<?php
            }
?>

<?php if (is_content_manager() and isset($_REQUEST["deleted"])) { ?>
	<script>
		$(function() {
			PF.fn.growl.expirable("<?php _se('The content has been deleted.'); ?>");
		});
	</script>
<?php } ?>

<?php if (get_current_tab() === 'tab-embeds') { ?>
    <script>
        $(function () {
            CHV.fn.album.showEmbedCodes();
        })
    </script>
<?php } ?>

<?php G\Render\include_theme_footer(); ?>