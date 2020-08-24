<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://rydoug.com
 * @since      1.0.0
 *
 * @package    Shiftboard_Scheduler
 * @subpackage Shiftboard_Scheduler/admin/partials
 */
?>

<!-- This file should primarily consist of HTML with a little bit of PHP. -->

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<form action="options.php" method="post">
	    <?php
	        settings_fields( 'shiftboard-scheduler' );
	        do_settings_sections( 'shiftboard-scheduler' );
	        submit_button();
	    ?>
	</form>
</div>