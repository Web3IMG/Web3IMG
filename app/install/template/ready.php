<?php if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
} ?>
<h1>Ready to install</h1>
<p>To proceed with the installation fill this form with the details of the initial admin account you want to use.</p>
<p>You can change this account later on.</p>
<?php if ($error) { ?>
<p class="highlight padding-10"><?php echo $error_message; ?></p>
<?php } ?>
<form method="post">
	<div class="c9">
        <div class="input-label">
            <label for="username">Admin username</label>
            <input type="text" name="username" id="username" class="text-input" value="<?php echo $safe_post['username'] ?? ''; ?>" placeholder="Admin username" rel="tooltip" data-tipTip="right" pattern="<?php echo CHV\getSetting('username_pattern'); ?>" rel="tooltip" title='<?php echo strtr('%i to %f characters<br>Letters, numbers and "_"', ['%i' => CHV\getSetting('username_min_length'), '%f' => CHV\getSetting('username_max_length')]); ?>' maxlength="<?php echo CHV\getSetting('username_max_length'); ?>" required>
            <span class="input-warning red-warning"><?php echo $input_errors['username'] ?? ''; ?></span>
        </div>
        <div class="input-label">
            <label for="email">Admin email</label>
            <input type="email" name="email" id="email" class="text-input" value="<?php echo $safe_post['email'] ?? ''; ?>" placeholder="Admin email" title="Valid email address for your admin account" rel="tooltip" data-tipTip="right" required>
            <span class="input-warning red-warning"><?php echo $input_errors['email'] ?? ''; ?></span>
        </div>
        <div class="input-label input-password">
            <label for="password">Admin password</label>
            <input type="password" name="password" id="password" class="text-input" value="" placeholder="Admin password" title="Password to login" pattern="<?php echo CHV\getSetting('user_password_pattern'); ?>" rel="tooltip" data-tipTip="right" required>
            <div class="input-password-strength"><span style="width: 0%" data-content="password-meter-bar"></span></div>
            <span class="input-warning red-warning" data-text="password-meter-message"><?php echo $input_errors['password'] ?? ''; ?></span>
        </div>
    </div>
	<?php
        if ($is_2X) {
            ?>
    <div class="c9">
        <div class="input-label">
            <label for="crypt_salt">__CHV_CRYPT_SALT__</label>
            <input type="crypt_salt" name="crypt_salt" id="crypt_salt" class="text-input" value="<?php echo $safe_post['crypt_salt'] ?? ''; ?>" placeholder="Example: changeme" title="As defined in includes/definitions.php" rel="tooltip" data-tipTip="right" required>
            <span class="input-below highlight">Value from define("__CHV_CRYPT_SALT__", "changeme");</span>
            <span class="input-warning red-warning"><?php echo $input_errors['crypt_salt'] ?? ''; ?></span>
        </div>
    </div>
	<?php
        }
    ?>
	<div class="btn-container margin-bottom-0">
		<button class="btn btn-input default" type="submit">Install</button>
	</div>
</form>