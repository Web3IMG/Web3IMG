<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>

<?php
$owner = function_exists('get_owner') ? get_owner() : G\get_global("owner");
?>
<div class="breadcrum-item pop-btn pop-btn-auto pop-keep-click pop-btn-desktop">
	<a href="<?php echo $owner['url']; ?>" class="user-image">
		<?php if (isset($owner['avatar']['url'])) {
    ?>
		<img class="user-image" src="<?php echo $owner['avatar']['url']; ?>" alt="<?php echo $owner['username']; ?>">
		<?php
} else {
        ?>
		<span class="user-image default-user-image"><span class="icon fas fa-meh"></span></span>
		<?php
    } ?>
	</a>
    <span class="breadcrum-text float-left"><a class="user-link" href="<?php echo $owner['url']; ?>"><?php if ($owner['is_private']) {
        ?><span class="user-meta font-size-small"><span class="icon icon--lock fas fa-lock"></span></span><?php
    } ?><strong><?php echo $owner['username']; ?></strong></a></span>
</div>