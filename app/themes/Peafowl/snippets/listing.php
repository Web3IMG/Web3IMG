<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
$list = function_exists('get_list') ? get_list() : G\get_global('list');
$tabs = (array) (G\get_global('tabs') ? G\get_global('tabs') : (function_exists('get_tabs') ? get_tabs() : null));
$classic = isset($_GET['pagination']) || CHV\getSetting('listing_pagination_mode') == 'classic';
$do_pagination = !isset($list->pagination) or $list->pagination == true ? true : false;
foreach ($tabs as $tab) {
    if (isset($tab['list']) && $tab['list'] === false) {
        continue;
    }
    if ($tab['current']) {
        ?>
        <div id="<?php echo $tab["id"]; ?>" class="tabbed-content content-listing visible list-<?php echo $tab["type"]; ?>" data-action="list" data-list="<?php echo $tab["type"]; ?>" data-params="<?php echo $tab["params"]; ?>" data-params-hidden="<?php echo $tab["params_hidden"] ?? ''; ?>">
            <?php
                    if (isset($list, $list->output) && count($list->output) > 0) {
                        ?>
                <div class="pad-content-listing"><?php echo $list->htmlOutput($list->output_tpl ?? null); ?></div>
                <?php
                            if (count($list->output) >= $list->limit) {
                                ?>
                    <div class="content-listing-loading"></div>
                    <?php
                                }
                                if ($do_pagination and ($classic or count($list->output) >= $list->limit)) { // pagination
                                    if ($classic) {
                                        CHV\Render\show_banner('listing_before_pagination', $list->sfw);
                                    }
                                    if ($list->has_page_prev || $list->has_page_next) {
                                        ?>
                        <ul class="content-listing-pagination<?php if ($classic) {
                                                                                        ?> visible<?php
                                                        } ?>" data-visibility="<?php echo $classic ? 'visible' : 'hidden'; ?>" data-content="listing-pagination" data-type="<?php echo $classic ? 'classic' : 'endless'; ?>">
                            <?php
                                                $currentUrlPath = G\add_ending_slash(preg_replace('/\?.*/', '', CHV\get_current_url()));
                                                $QS = G\filter_string_polyfill($_SERVER['QUERY_STRING']);
                                                parse_str($QS, $current_page_qs);
                                                unset($current_page_qs['lang']); // Get rid of any ?lang=
                                                $current_url = $currentUrlPath . '?' . http_build_query($current_page_qs);
                                                $page = intval(($_GET['page'] ?? $current_page_qs['page'] ?? null) ?: 1);
                                                $pages = [];
                                                foreach (['prev', 'next'] as $v) {
                                                    $params = $current_page_qs;
                                                    $seek = $list->{'seek' . ($v == 'prev' ? 'Start' : 'End')};
                                                    if ($list->{'has_page_' . $v}) {
                                                        $params['page'] = $v == 'prev' ? ($page - 1) : ($page + 1);
                                                        if ($seek) {
                                                            unset($params['peek'], $params['seek']);
                                                            $params[$v == 'prev' ? 'peek' : 'seek'] = $seek;
                                                        }
                                                        ${$v . 'Url'} = $currentUrlPath . '?' . http_build_query($params);
                                                    } else {
                                                        ${$v . 'Url'} = null;
                                                    }
                                                }
                                                $pages['prev'] = [
                                                    'label'        => '<span class="icon fas fa-angle-left"></span>',
                                                    'url'        => $prevUrl,
                                                    'disabled'    => !$list->has_page_prev
                                                ];
                                                $pages[] = [
                                                    'label'        => $page,
                                                    'url'        => null,
                                                    'current'    => true
                                                ];
                                                $pages['next'] = [
                                                    'label'        => '<span class="icon fas fa-angle-right"></span>',
                                                    'url'        => $nextUrl,
                                                    'load-more' => !$classic,
                                                    'disabled'    => !$list->has_page_next,
                                                ];
                                                foreach ($pages as $k => $page) {
                                                    if (is_numeric($k)) {
                                                        $li_class = 'pagination-page';
                                                    } else {
                                                        $li_class = 'pagination-' . $k;
                                                    }
                                                    if (($page['current'] ?? false) == true) {
                                                        $li_class .= ' pagination-current';
                                                    }
                                                    if (($page['disabled'] ?? false) == true) {
                                                        $li_class .= ' pagination-disabled';
                                                    } ?><li class="<?php echo $li_class; ?>"><a data-pagination="<?php echo $k; ?>" <?php
                                                                                                                                    if (!is_null($page['url'])) {
                                                                                                                                        ?>href="<?php echo $page['url']; ?>" <?php
                                                                                            } ?>><?php echo $page['label']; ?></a></li><?php } ?>
                        </ul>
                    <?php
                                    }
                                    if ($classic) {
                                        CHV\Render\show_banner('listing_after_pagination', $list->sfw);
                                    }
                                } // pagination?

                                if ($do_pagination && $classic == false) {
                                    ?>
                    <div class="content-listing-more">
                        <button class="btn btn-big grey" data-action="load-more" data-seek="<?php echo $list->seekEnd; ?>"><?php _se('Load more'); ?></button>
                    </div>
            <?php
                        }
                    } else { // Results?
                        G\Render\include_theme_file("snippets/template_content_empty");
                    } ?>
        </div>
    <?php
        } else { // !current
            ?>
        <div id="<?php echo $tab["id"]; ?>" class="tabbed-content content-listing hidden list-<?php echo $tab["type"]; ?>" data-action="list" data-list="<?php echo $tab["type"]; ?>" data-params="<?php echo $tab["params"]; ?>" data-params-hidden="<?php echo $tab["params_hidden"] ?? ''; ?>" data-load="<?php echo $classic ? 'classic' : 'ajax'; ?>">
        </div>
<?php
    }
} // for
G\Render\include_theme_file("snippets/viewer_template");
G\Render\include_theme_file("snippets/templates_content_listing");
?>