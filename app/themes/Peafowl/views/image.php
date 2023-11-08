<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<?php G\Render\include_theme_header(); ?>

<div id="image-viewer" class="image-viewer full-viewer">
    <?php
    if (get_image()['is_approved']) {
        CHV\Render\show_banner('image_image-viewer_top', !get_image()['nsfw']);
    }
    ?>
    <?php
    $image_url = isset(get_image()['medium']) ? get_image()['medium']['url'] : get_image()['url'];
    ?>
    <div id="image-viewer-container" class="image-viewer-main image-viewer-container<?php echo isset(get_image()['album'], get_image_album_slice()['images']) ? ' --thumbs' : '';?>">
        <img src="<?php echo $image_url; ?>" <?php if (!CHV\getSetting('theme_download_button')) {
        ?> class="no-select" <?php
    } ?> alt="<?php echo get_image()['alt']; ?>" width="<?php echo get_image()['width']; ?>" height="<?php echo get_image()['height']; ?>" data-is360="<?php echo get_image()['is_360']; ?>" <?php if (isset(get_image()['medium'])) {
        ?> data-load="full"<?php
    } ?>>
        <?php if (get_image()['is_use_loader']) {
        ?>
        <div id="image-viewer-loader" data-size="<?php echo get_image()['size']; ?>"><?php if (get_image()['is_animated']) {
            ?><span class="btn-icon icon fas fa-play-circle"></span><?php
        } ?><span class="btn-text"><?php
            switch (true) {
                case get_image()['is_animated']:
                    _se('Play GIF');
                break;
                case get_image()['is_360']:
                    _se('Load 360° view');
                break;
                default:
                    _se('Load full resolution');
                break;
            }; ?> <?php echo get_image()['size_formatted']; ?></span></div>
    <?php
    } if (get_image()['is_360']) { ?>
    <div id="image-viewer-360" class="soft-hidden"></div>
    <?php } ?>
    </div>
    <?php
    if (get_image()['is_approved']) {
        CHV\Render\show_banner('image_image-viewer_foot', !get_image()['nsfw']);
    }
    ?>
</div>

<?php
if (isset(get_image()['album'], get_image_album_slice()['images'])) {
?>
<div class="panel-thumbs follow-scroll">
    <div class="content-width">
        <ul id="panel-thumb-list" class="panel-thumb-list" data-content="album-slice"><?php G\Render\include_theme_file('snippets/image_album_slice'); ?></ul>
        <div class="image-viewer-navigation arrow-navigator">
            <?php
            if (isset(get_image_album_slice()['prev'])) {
                ?>
                <a class="left-0" data-action="prev" href="<?php echo get_image_album_slice()['prev']['url_viewer']; ?>" title="<?php _se('%s image', _s('Previous')); ?>"><span class="fas fa-angle-left"></span></a>
            <?php
            }
        if (isset(get_image_album_slice()['next'])) {
            ?>
                <a class="right-0" data-action="next" href="<?php echo get_image_album_slice()['next']['url_viewer']; ?>" title="<?php _se('%s image', _s('Next')); ?>"><span class="fas fa-angle-right"></span></a>
            <?php
        } ?>
        </div>
    </div>
</div>
<?php
}
?>

<?php CHV\Render\show_theme_inline_code('snippets/image.js'); ?>

<?php
CHV\Render\show_banner('image_after_image-viewer', !get_image()['nsfw']);
?>

<div class="content-width margin-top-10">
    <div class="header header-content margin-bottom-10">
        <div class="header-content-left">
            <div class="header-content-breadcrum">
                <div class="breadcrum-item">
                    <span class="breadcrum-text"><span class="icon fas fa-eye-slash <?php if (!isset(get_image()['album']) or get_image()['album']['privacy'] == 'public') {
                    echo 'soft-hidden';
                } ?>" data-content="privacy-private" title="<?php _se('This content is private'); ?>" rel="tooltip"></span>
                </div>
                <?php
                    if (is_owner() or is_content_manager()) {
                ?>
                <div class="breadcrum-item">
                    <a class="link link--edit" data-modal="edit"><span class="icon fas fa-edit"></span><span class="phone-hide margin-left-5"><?php _se('Edit'); ?></span></a>
                </div>
                <?php
                if (!get_image()['is_approved'] && is_content_manager()) { ?>
                <div class="breadcrum-item">
                    <a class="link link--approve" data-confirm="<?php _se("Do you really want to approve this image? The image will go public if you approve it."); ?>" data-submit-fn="CHV.fn.submit_resource_approve" data-ajax-deferred="CHV.fn.complete_resource_approve" data-ajax-url="<?php echo G\get_base_url('json'); ?>"><span class="icon fas fa-check-double"></span><span><?php _se('Approve'); ?></span></a>
                </div>
                <?php
                }
                if (is_allowed_to_delete_content()) {
                    ?>
                <div class="breadcrum-item">
                    <a class="link link--delete" data-confirm="<?php _se("Do you really want to delete this image? This can't be undone."); ?>" data-submit-fn="CHV.fn.submit_resource_delete" data-ajax-deferred="CHV.fn.complete_resource_delete" data-ajax-url="<?php echo G\get_base_url('json'); ?>"><span class="icon fas fa-trash-alt"></span><span  class="phone-hide margin-left-5"><?php _se('Delete'); ?></span></a>
                </div>
            <?php
                }
            }
            ?>
            </div>
        </div>

        <div class="header-content-right">
            <?php if (CHV\getSetting('theme_download_button')) {
                    ?>
                <a href="<?php echo get_image()['url']; ?>" download="<?php echo get_image()['filename']; ?>" class="btn btn-download default" rel="tooltip" title="<?php echo get_image()['width'] . ' x ' . get_image()['height'] . ' - ' . strtoupper(get_image()['extension']) . ' ' . get_image()['size_formatted']; ?>"><span class="btn-icon fas fa-download"></span></a>
            <?php
                } ?>
            <?php if (isset(get_image()['album']['id']) && (is_owner() or is_content_manager())) {
                    ?>
                <a class="btn-album-cover" data-album-id="<?php echo get_image()['album']['id_encoded']; ?>" data-id="<?php echo get_image()['id_encoded']; ?>" data-cover="<?php echo (int) is_album_cover(); ?>" data-action="album-cover">
                    <span class="btn btn-album-is-cover default" rel="tooltip" title="<?php _se('This is the album cover'); ?>"><span class="btn-icon fas fa-check-square"></span><span class="btn-text phone-hide"><?php _se('Cover'); ?></span></span>
                    <span class="btn btn-album-not-cover default outline"><span class="btn-icon far fa-square"></span><span class="btn-text phone-hide"><?php _se('Cover'); ?></span></span>
                </a>
            <?php
                } ?>
            <?php if (CHV\getSetting('enable_likes')) {
                ?>
                <a class="btn-like" data-type="image" data-id="<?php echo get_image()['id_encoded']; ?>" data-liked="<?php echo (int) (get_image()['liked'] ?? false); ?>" data-action="like">
                    <span class="btn btn-liked red" rel="tooltip" title="<?php _se('You like this'); ?>"><span class="btn-icon fas fa-heart"></span><span class="btn-text" data-text="likes-count"><?php echo (int) (get_image()['likes'] ?? false); ?></span></span>
                    <span class="btn btn-unliked red outline"><span class="btn-icon far fa-heart"></span><span class="btn-text" data-text="likes-count"><?php echo (int) (get_image()['likes'] ?? false); ?></span></span>
                </a>
            <?php
            }
            ?>
            <?php if (CHV\getSetting('theme_show_social_share')) {
                ?>
                <a class="btn green" data-action="share"><span class="btn-icon fas fa-share-alt"></span><span class="btn-text phone-hide"><?php _se('Share'); ?></span></a>
            <?php
            } ?>
            
        </div>
    </div>

    <div class="header header-content margin-bottom-10">
        <div class="header-content-left">
            <div class="header-content-breadcrum">
<?php if (isset(get_image()['user']['id'])) {
    G\Render\include_theme_file('snippets/breadcrum_owner_card');
} else { ?>
                <div class="breadcrum-item">
                    <div class="user-image default-user-image"><span class="icon fas fa-meh"></span></div>
                </div>
<?php } ?>
            </div>
        </div>
        <div class="header-content-right phone-margin-bottom-20">
            <div class="number-figures display-inline-block"><?php echo get_image()['views']; ?> <span><?php echo get_image()['views_label']; ?></span></div>
        </div>
    </div>

    <?php
    if (get_image()['is_approved']) {
        CHV\Render\show_banner('image_before_header', !get_image()['nsfw']);
    }
    ?>

    <div class="header margin-bottom-10">
    <?php
    if (!get_image()['title']) {
        ?>
        <h1 class="phone-float-none viewer-title soft-hidden"><a data-text="image-title" href="<?php echo get_image()['url_viewer']; ?>"><?php echo get_pre_doctitle(); ?></a></h1>
    <?php } else { ?>
        <h1 class="phone-float-none viewer-title"><a data-text="image-title" href="<?php echo get_image()['url_viewer']; ?>"><?php echo nl2br(get_image_safe_html()['title']); ?></a></h1>
    <?php } ?>
    </div>

    <p class="description-meta margin-bottom-20">
        <?php
        if(isset(get_image()['category_id'])) {
            $category = get_categories()[get_image()['category_id']] ?? null;
        }
        if(isset($category)) {
            $category_link = '<a href="' . $category['url'] . '" rel="tag">' . $category['name'] . '</a>';
        }
        $time_elapsed_string = '<span title="' . get_image()['date_fixed_peer'] . '">' . CHV\time_elapsed_string(get_image()['date_gmt']) . '</span>';
        if (isset(get_image()['album']['id']) and (get_image()['album']['privacy'] !== 'private_but_link' or is_owner() or is_content_manager())) {
            $album_link = '<a href="' . get_image()['album']['url'] . '"' . (get_image()['album']['name'] !== get_image()['album']['name_truncated'] ? (' title="' . get_image()['album']['name_html'] . '"') : null) . '>' . get_image()['album']['name_truncated_html'] . '</a>';
            if (isset($category)) {
                echo _s('Added to %a and categorized in %c', ['%a' => $album_link, '%c' => $category_link]);
            } else {
                echo _s('Added to %s', $album_link);
            }
            echo ' — ' . $time_elapsed_string;
        } else {
            if (isset($category)) {
                echo _s('Uploaded to %s', $category_link) . ' — ' . $time_elapsed_string;
            } else {
                _se('Uploaded %s', $time_elapsed_string);
            }
        }
        ?>
    </p>

    <div class="header margin-bottom-10 no-select">
        <?php G\Render\include_theme_file('snippets/tabs'); ?>
    </div>

    <?php
    if (get_image()['is_approved']) {
        CHV\Render\show_banner('image_after_header', !get_image()['nsfw']);
    }
    ?>

    <div id="tabbed-content-group">

        <div id="tab-about" class="tabbed-content<?php echo get_current_tab() == 'about' ? ' visible' : ''; ?>">
            <div class="c9 phablet-c1 fluid-column grid-columns">
                <div class="panel-description default-margin-bottom">
                    <p class="description-text margin-bottom-5" data-text="image-description"><?php echo nl2br(get_image_safe_html()['description']); ?></p>
                    <?php
                    if (CHV\getSetting('theme_show_exif_data')) {
                        $image_exif = CHV\Render\getFriendlyExif(get_image()['original_exifdata']);
                        if ($image_exif) {
                            ?>
                            <p class="exif-meta margin-top-20">
                                <span class="camera-icon fas fa-camera"></span><?php echo $image_exif->Simple->Camera; ?>
                                <span class="exif-data"><?php echo $image_exif->Simple->Capture; ?> — <a class="font-size-small" data-toggle="exif-data" data-html-on="<?php _se('Less Exif data'); ?>" data-html-off="<?php _se('More Exif data'); ?>"><?php _se('More Exif data'); ?></a></span>
                            </p>
                            <div data-content="exif-data" class="soft-hidden">
                                <ul class="tabbed-content-list table-li">
                                    <?php
                                    foreach ($image_exif->Full as $k => $v) {
                                        $label = preg_replace('/(?<=\\w)(?=[A-Z])/', ' $1', $k);
                                        if (ctype_upper(preg_replace('/\s+/', '', $label))) {
                                            $label = $k;
                                        } ?>
                                        <li><span class="c5 display-table-cell padding-right-10"><?php echo $label; ?></span> <span class="display-table-cell"><?php echo $v; ?></span></li>
                                    <?php
                                    } ?>
                                </ul>
                            </div>
                    <?php
                        } // $image_exif
                    } // theme_show_exif_data
                    ?>
                </div>

                <?php
                if (is_content_manager()) {
                    ?>
                    <div class="tabbed-content-section">
                        <ul class="tabbed-content-list table-li">
                            <?php
                            $image_admin_list_values = get_image_admin_list_values();
                    if (isset(get_image()['album']['id'])) {
                        $album_values = [
                                    'label' => _s('Album ID'),
                                    'content' => get_image()['album']['id'] . ' (' . get_image()['album']['id_encoded'] . ')',
                                ];
                        $image_admin_list_values = array_slice($image_admin_list_values, 0, 1, true) +
                                    [
                                        'album' => [
                                            'label' => _s('Album ID'),
                                            'content' => get_image()['album']['id'] . ' (' . get_image()['album']['id_encoded'] . ')',
                                        ],
                                    ] +
                                    array_slice($image_admin_list_values, 1, count($image_admin_list_values) - 1, true);
                    }

                    foreach ($image_admin_list_values as $v) {
                        ?>
                                <li><span class="c5 display-table-cell padding-right-10 phone-display-block font-weight-bold"><?php echo $v['label']; ?></span><span class="display-table-cell phone-display-block word-break-break-all"><?php echo $v['content']; ?></span></li>
                            <?php
                    } ?>
                        </ul>
                        <div data-modal="modal-add-ip_ban" class="hidden" data-submit-fn="CHV.fn.ip_ban.add.submit" data-before-fn="CHV.fn.ip_ban.add.before" data-ajax-deferred="CHV.fn.ip_ban.add.complete">
                            <span class="modal-box-title"><?php _se('Add IP ban'); ?></span>
                            <div class="modal-form">
                                <?php G\Render\include_theme_file('snippets/form_ip_ban_edit'); ?>
                            </div>
                        </div>
                    </div>
                <?php
                }
                ?>

                <?php
                if (get_image()['is_approved']) {
                    CHV\Render\show_banner('content_before_comments', !get_image()['nsfw']);
                }
                ?>

                <div class="comments">
                    <?php CHV\Render\showComments(); ?>
                </div>
            </div>

            <div class="c15 phablet-c1 fluid-column grid-columns margin-left-10 phablet-margin-left-0">
                <?php
                if (get_image()['is_approved']) {
                    CHV\Render\show_banner('content_tab-about_column', !get_image()['nsfw']);
                }
                ?>
            </div>

        </div>

        <?php if (CHV\isShowEmbedContent()) {
                    ?>
            <div id="tab-embeds" class="tabbed-content<?php echo get_current_tab() == 'embeds' ? ' visible' : ''; ?>">
                <div class="c24 margin-left-auto margin-right-auto">
                    <div class="margin-bottom-30 growl static text-align-center clear-both" data-content="privacy-private"><?php echo get_image()['album']['privacy_notes'] ?? ''; ?></div>
                </div>
                <div class="panel-share c16 phone-c1 phablet-c1 grid-columns margin-right-10">
                    <?php
                    foreach (get_embed() as $embed) {
                        ?>
                        <div class="panel-share-item">
                            <h4 class="pre-title"><?php echo $embed['label']; ?></h4>
                            <?php foreach ($embed['entries'] as $entry) {
                            ?>
                                <div class="panel-share-input-label copy-hover-display">
                                    <h4 class="title c5 grid-columns"><?php echo $entry['label']; ?></h4>
                                    <div class="c10 phablet-c1 grid-columns">
                                        <input id="<?php echo $entry['id']; ?>" type="text" class="text-input" value="<?php echo $entry['value']; ?>" data-focus="select-all" readonly>
                                        <button class="input-action" data-action="copy" data-action-target="#<?php echo $entry['id']; ?>"><?php _se('copy'); ?></button>
                                    </div>
                                </div>
                            <?php
                        } ?>
                        </div>
                    <?php
                    } ?>
                </div>

            </div>
        <?php
                } ?>

        <?php
        if (is_admin()) {
            ?>
            <div id="tab-info" class="tabbed-content<?php echo get_current_tab() == 'info' ? ' visible' : ''; ?>">
                <?php echo CHV\Render\arr_printer(get_image_safe_html(), '<li><div class="c4 display-table-cell padding-right-10 font-weight-bold">%K</div> <div class="display-table-cell">%V</div></li>', ['<ul class="tabbed-content-list table-li">', '</ul>']); ?>
            </div>
        <?php
        }
        ?>

    </div>
    <?php
    if (get_image()['is_approved']) {
        CHV\Render\show_banner('image_footer', !get_image()['nsfw']);
    }
    ?>
</div>


<?php
if (is_owner() or is_content_manager()) {
        ?>
    <div data-modal="form-modal" class="hidden" data-submit-fn="CHV.fn.submit_image_edit" data-before-fn="CHV.fn.before_image_edit" data-ajax-deferred="CHV.fn.complete_image_edit" data-ajax-url="<?php echo G\get_base_url('json'); ?>">
        <span class="modal-box-title"><?php _se('Edit'); ?></span>
        <div class="modal-form">
            <?php
            G\Render\include_theme_file('snippets/form_image'); ?>
        </div>
    </div>
<?php
    }

G\Render\include_theme_footer(); ?>