<?php
if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
if (!is_maintenance()) {
    G\Render\include_theme_file('snippets/embed_tpl');
}
if (is_upload_allowed() && ((CHV\getSetting('upload_gui') == 'js' && CHV\getSetting('homepage_style') !== 'route_upload') || G\is_route('upload'))) {
    G\Render\include_theme_file('snippets/anywhere_upload');
}
if (CHV\getSetting('theme_show_social_share')) {
    G\Render\include_theme_file("snippets/modal_share");
}
G\Render\include_theme_file('custom_hooks/footer');
CHV\Render\include_peafowl_foot();
CHV\Render\show_theme_inline_code('snippets/footer.js');
echo CHV\getSetting('analytics_code');
?>

</body>
</html>