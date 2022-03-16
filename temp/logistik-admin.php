<?php


?>
<!-- This file should primarily consist of HTML with a little bit of PHP. -->
<div class="wrap">
	<div id="icon-themes" class="icon32"></div>  
	<h2>Dashboard</h2>  
	 <!--NEED THE settings_errors below so that the errors/success messages are shown after submission - wasn't working once we started using add_menu_page and stopped using add_options_page so needed this-->
	<?php settings_errors(); ?>  
	<form method="POST" action="options.php">  
	    <?php 
	        settings_fields( 'logistik-settings-form' );
	        do_settings_sections( 'logistik-settings-form' ); 
	    ?>             
	    <?php submit_button(); ?>  
	</form> 
</div>