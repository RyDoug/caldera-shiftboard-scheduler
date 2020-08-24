<?php
/**
 * @package shiftboard-scheduler
 * @version 1.0.0
 */
/*
Plugin Name: Shiftboard Scheduler New
Description: Submits an API shift.create request to shiftboard when the selected caldera form is submitted.
Author: Ryan Douglass
Version: 1.0.0
*/
 /**
 * Logs messages/variables/data to browser console from within php
 *
 * @param $name: message to be shown for optional data/vars
 * @param $data: variable (scalar/mixed) arrays/objects, etc to be logged
 * @param $jsEval: whether to apply JS eval() to arrays/objects
 *
 * @return none
 * @author Ryan Douglass
 */


/*

*/
#region Configuration of settings page.

	define('OPTION_NAME', 'shiftboard_scheduler');
	define('PLUGIN_NAME', 'shiftboard-scheduler');

    // Add menu item
    add_action( 'admin_menu', 'add_plugin_admin_menu' );

    // // Add Settings link to the plugin
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_$plugin", 'my_plugin_settings_link' );

    // Register settings		
	add_action( 'admin_init', 'settings_reg' );


	// Adds settings link for plugin inside of Plugins page
	function my_plugin_settings_link($links) { 
		$settings_link = '<a href="options-general.php?page=shiftboard-scheduler">Settings</a>'; 
		array_unshift($links, $settings_link); 
		return $links; 
	  }

    // Adds admin menu inside of settings
	function add_plugin_admin_menu() {
		add_options_page( 
			__('ShiftBoard Scheduler Settings', 'shiftboard-scheduler'),
			__('ShiftBoard Scheduler', 'shiftboard-scheduler'),
			'manage_options',
			'shiftboard-scheduler',
			'display_plugin_setup_page'
		);
	}

	// Includes the settings page for display
	function display_plugin_setup_page() {
		include_once( 'shiftboard-scheduler-admin-display.php' );
	}

	// Settings page registration
	function settings_reg() {
		add_settings_section(
			OPTION_NAME . '_general',
			__('General', PLUGIN_NAME),
			OPTION_NAME . '_general_cb',
			PLUGIN_NAME
		);
		add_settings_field(
			OPTION_NAME . '_access_key',
			__('ShiftBoard Access Key', PLUGIN_NAME),
			OPTION_NAME . '_access_key_cb',
			PLUGIN_NAME,
			OPTION_NAME . '_general',
			array( 'label_for' => OPTION_NAME . '_access_key')
		);
		add_settings_field( 
			OPTION_NAME . '_secret_key', 
			__('ShiftBoard Secret Key', PLUGIN_NAME), 
			OPTION_NAME . '_secret_key_cb', 
			PLUGIN_NAME, 
			OPTION_NAME . '_general', 
			array( 'label_for' => OPTION_NAME . '_secret_key')
		);	
		add_settings_field( 
			OPTION_NAME . '_workgroup', 
			__('ShiftBoard Workgroup', PLUGIN_NAME), 
			OPTION_NAME . '_workgroup_cb', 
			PLUGIN_NAME, 
			OPTION_NAME . '_general', 
			array( 'label_for' => OPTION_NAME . '_workgroup')
		);			

		register_setting(PLUGIN_NAME, OPTION_NAME . '_access_key');
		register_setting(PLUGIN_NAME, OPTION_NAME . '_secret_key');
		register_setting(PLUGIN_NAME, OPTION_NAME . '_workgroup');

	}

	function shiftboard_scheduler_general_cb() {
		echo '<p>' . __( 'Please change the settings accordingly.', PLUGIN_NAME ) . '</p>';
	}
	function shiftboard_scheduler_access_key_cb() {
		$value = get_option( OPTION_NAME . '_access_key');
		echo '<input type="text" name="' . OPTION_NAME . '_access_key' . '" id="' . OPTION_NAME . '_access_key' . '" value="' . $value . '"> ';
	}
	function shiftboard_scheduler_secret_key_cb() {
		$value = get_option( OPTION_NAME . '_secret_key');
		echo '<input type="text" name="' . OPTION_NAME . '_secret_key' . '" id="' . OPTION_NAME . '_secret_key' . '" value="' . $value . '"> ';
	}
	function shiftboard_scheduler_workgroup_cb() {
		$value = get_option( OPTION_NAME . '_workgroup');
		echo '<input type="text" name="' . OPTION_NAME . '_workgroup' . '" id="' . OPTION_NAME . '_workgroup' . '" value="' . $value . '"> ';
	}

#endregion
/*

*/
#region Caldera form configuration.
	
	// Creates the processor inside of caldera forms.
	add_filter( 'caldera_forms_get_form_processors', function( $processors ) {
        $processors['check_time'] = array(
                'name'          =>  'Shiftboard Parameters',
                'description'   =>  'Marks this form as one that should be posted to shiftboard, and ',
				'pre_processor' =>  'check_start_time',
				'template'		=>	__DIR__ . '/shiftboard-scheduler-caldera-processor.php'
        );
        return $processors;
	} );	

	// Creates the parameters for the processor settings page.
	function shiftboard_params_fields(){
		return array(
			array(
				'id' => 'shift-subject',
				'type' => 'text',
				'required' => true,
				'magic' => true,
				'label' => __( 'Title *', 'shiftboard-scheduler' )
			),     
			array(
				'id' => 'start-time',
				'type' => 'text',
				'required' => true,
				'magic' => true,
				'label' => __( 'Start Time *', 'shiftboard-scheduler' )
			),
			array(
				'id' => 'end-time',
				'type' => 'text',
				'required' => false,
				'magic' => true,
				'label' => __( 'End Time', 'shiftboard-scheduler' )
			),     
			array(
				'id' => 'date-picker',
				'type' => 'text',
				'required' => true,
				'magic' => true,
				'label' => __( 'Date Picker *', 'shiftboard-scheduler' )
			),   
		);
	}

	// Callback function that runs validation and api call to shiftboard.
	function check_start_time ($config, $form, $process_id){	
		// Define the processor fields that have been 
		$processorFields = new Caldera_Forms_Processor_Get_Data( $config, $form, shiftboard_params_fields() );

		// Set the start and end times of shifts for the shiftboard api.  End time is incremented 1 hour if not set by processor.
		$startShift = date("H:i:s", strtotime($processorFields->get_value('start-time')));
		$endShift	= is_null($processorFields->get_value('end-time')) ? date("H:i:s", strtotime($startShift) + 60*60) : date("H:i:s", strtotime($processorFields->get_value('end-time')));
		
		// Setting our dates for shiftboard api.  End gets set to start date to signify that the shift is not ongoing.
		$dateRange	= $processorFields->get_value('date-picker');
		$startDate	= date("Y-m-d", strtotime(substr($dateRange, 0, 10)));
		$endDate	= strlen($dateRange) > 10 ? date("Y-m-d", strtotime(substr($dateRange, 14))) : $startDate;

		// Grab information from plugin settings.
		$accessKey	= get_option( OPTION_NAME . '_access_key' );
		$secretKey	= get_option( OPTION_NAME . '_secret_key' );
		$workgroup	= get_option( OPTION_NAME . '_workgroup' );

		// Subject for the shift that will appear in shiftboard.
		$subject = 'New Request: ' . $processorFields->get_value('shift-subject');



		if(empty($accessKey) || empty($secretKey) || empty($workgroup)) {
			return array(
				'note' => 'Unable to communicate with the destination server.  If you receive this message please contact the website owner directly by phone or email.',
				'type' => 'error'
			);
		}
		
		if ($startShift > $endShift) {
			return array(
				'note' => 'Your start time cannot be after your end time.  Veirfy your field entries then resubmit. <br>Start Time:' . $startShift . ' End Time:' . $endShift,
				'type' => 'error'
			);
		}

		
		// This string will be passed in our parameters to the shiftboard api and displayed as the details of the shift.		
		$details;

		// Used for easily grabbing the labels of each form item.
		$formFields = Caldera_Forms_Forms::get_fields( $form );

		foreach( $form[ 'fields' ] as $field_id => $field ) {
			$label = $formFields[$field_id]['label'];
			$type  = $formFields[$field_id]['type'];
			$value = Caldera_Forms::get_field_data( $field_id, $form );

			// If the item is left blank we do not send it to the destination.
			if(!is_null($value) && $type != 'button'){

				// for checkboxes or input that returns values as an array
				if(is_array($value)){
					$value = implode(', ', $value);				
				}

				$value = remove_special_characters($value);
				
				$details .= $label . ': ' . $value . '<br>';				
			}
		}		 	

		// api call
		$Method	= "shift.create";		
    	$Params;
    	//Checks to see how to process the order.  If more than a day shiftboard needs to receive the data differently.
    	if($endDate > $startDate) {
    		$Params = "{
				\"workgroup\" : \"${workgroup}\",
				\"subject\" : \"${subject}\",
				\"details\" : \"${details}\",
				\"start_date\" : \"${startDate}T${startShift}\",
				\"end_date\" : \"${startDate}T${endShift}\",
				\"repeating_shift\" : true,
				\"repeating_shift_type\" : \"frequency\",
				\"repeating_shift_interval\" : \"every\",
				\"repeating_shift_frequency\" : \"day\",
				\"repeating_shift_end_date\" : \"${endDate}\"
    		}";                
    	}
    	else {
			$Params = "{
				\"workgroup\" : \"${workgroup}\",
				\"subject\" : \"${subject}\",
				\"details\" : \"${details}\",
				\"start_date\" : \"${startDate}T${startShift}\",
				\"end_date\" : \"${endDate}T${endShift}\"                
			}";
    	}
		$args = array(
			'timeout'     => 10000
		);

		
		//After setting up the payload, the following configures the API req.
		$Request = "method".$Method."params".$Params;
		$Params = urlencode(base64_encode($Params));
		$Sig = urlencode(base64_encode(hash_hmac('sha1', $Request, $secretKey, true)));        
		$URL = "https://api.shiftdata.com/servola/api/api.cgi?&access_key_id=".$accessKey."&jsonrpc=2.0&id=1&method=".$Method."&params=".$Params."&signature=".$Sig;
		
		//Submits the API req to shiftboard.  The response is saved as a variable that can be read.
		$response = wp_remote_get($URL,$args);		
		
		// Used to debug the API response in the event of an error.
		if(strpos($response['body'], 'error')){
			dump($response['body']);			
			return array(
				'note' => 'Your form was unable to be processed by the server.  Please verify your entries and attempt to resubmit.  If this error continues please contact us directly by phone or email.',
				'type' => 'error'
			);
		}		
	}

#endregion
/*

*/
#region Helper functions

	//Removes specified characters located in any string value the array.
	function remove_special_characters ( $value ) {
		$charactersToRemove = array(
			'doublequote'   => '"',
			'backslash'     => '\\'
		);
			
		foreach ($charactersToRemove as $character) {
			if(strpos($value, $character) !== FALSE) {
				$value = str_replace($character, '', $value);
				$fieldData[$key] = $value;
			}
		}
		
		return $value;
	}

	//Dumps data to a logfile in the root of the plugin directory
	function dump ($data){
		date_default_timezone_set("America/New_York");
	    ob_start();
	    var_dump($data);
	    $data = ob_get_clean();
	    $logFile = fopen(__DIR__ . './' . PLUGIN_NAME . '_log.txt', 'a');
	    fwrite($logFile, date('Y-m-d h:i:s') . "(EST): " . $data);
	    fclose($logFile);
	}


#endregion

?>