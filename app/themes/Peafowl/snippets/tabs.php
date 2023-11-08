<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
$tabs = (array) (G\get_global('tabs') ? G\get_global('tabs') : (function_exists('get_tabs') ? get_tabs() : null));
$current;
foreach ($tabs as $tab) {
    if ($tab["current"]) {
        $current = $tab;
        break;
    }
}
?><div class="phone-display-inline-block phablet-display-inline-block hidden tab-menu current" data-action="tab-menu">
    <span class="btn-icon <?php echo $current['icon'] ?? ''; ?>" data-content="tab-icon"></span><span class="btn-text" data-content="current-tab-label"><?php echo $current["label"]; ?></span><span class="btn-icon fas fa-angle-down --show"></span><span class="btn-icon fas fa-angle-up --hide"></span>
</div><ul class="content-tabs phone-hide phablet-hide">
	<?php
        foreach ($tabs as $tab) {
            $tabClass = $tab['class'] ?? '';
            if(($tab["current"] ?? false)) {
                $tabClass .= ' current';
            }
            $tabClass = trim($tabClass);
            $echo = [
                '<li class="' .  $tabClass . '">',
                '<a ',
                isset($tab['id']) ? ('id="' .  $tab['id'] . '-link" data-tab="' . $tab["id"] . '" ') : '',
                'href="' . ($tab['url'] ?? '') . '">',
                '<span class="btn-icon ' . ($tab['icon'] ?? '') . '"></span>',
                '<span class="btn-text">' . $tab["label"] . '</span>',
                '</a></li>'."\n"
            ];
            echo implode('', $echo);
        }
    ?>
</ul>