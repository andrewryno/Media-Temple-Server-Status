<?php
/*
Plugin Name: Media Temple Server Status
Plugin URI: http://andrewryno.com/plugins/media-temple-server-status/
Description: A dashboard widget that displays server status information for Media Temple servers.
Version: 1.1
Author: Andrew Ryno
Author URI: http://andrewryno.com
License: GPLv2
*/

register_uninstall_hook( __FILE__, 'mtss_uninstall_hook' );

add_action( 'admin_menu', 'mtss_plugin_menu' );
add_action( 'wp_dashboard_setup', 'mtss_add_dashboard_widgets' );

// Display the Google Chart with (mt) server data on the dashboard
function mtss_dashboard_widget() {
	// Get the API Key from the database and make sure they exist
	$mtss_api_key = get_option( 'mtss_api_key' );
	$mtss_service_id = (int) get_option( 'mtss_service_id' );
	if ( empty( $mtss_api_key ) OR empty( $mtss_service_id ) ) {
		echo 'Your (mt) API key and/or service ID are not set. <a href="' . get_admin_url() . 'options-general.php?page=mediatemple-server-stats">Enter them here!</a>';
		return;
	}
	
	// Get the results from the API
	$mt = json_decode( file_get_contents( 'https://api.mediatemple.net/api/v1/stats/' . $mtss_service_id . '/1hour.json?apikey=' . $mtss_api_key ) );
	$range_stats = $mt->statsList;
	?>
	<script type="text/javascript" src="https://www.google.com/jsapi"></script>
	<script type="text/javascript">
	google.load('visualization', '1.0', {packages: ['corechart']});
	google.setOnLoadCallback(drawChart);
	function drawChart() {
		var data = new google.visualization.DataTable();
		data.addColumn('string', 'Time');
		data.addColumn('number', 'CPU');
		data.addColumn('number', 'Memory');
		data.addColumn('number', 'Processes');
		data.addRows([
		<?php foreach ($range_stats->stats as $stat): ?>
			['<?php echo date('g:ia', $stat->timeStamp); ?>', <?php echo $stat->cpu / 100; ?>, <?php echo $stat->memory / 100; ?>, <?php echo $stat->processes / 100; ?>],
		<?php endforeach; ?>
		]);
		
		var options = {
			backgroundColor: '#f5f5f5',
			chartArea: {
				top: 20,
				right: 0,
				bottom: 0,
				left: 40,
			},
			colors: ['#21759B', '#D54E21', '#777777'],
			fontSize: '10',
			title: 'Last Hour',
			vAxis: {
				format: '#%'
			}
		};
		var chart = new google.visualization.LineChart(document.getElementById('mtss_chart'));
		var formatter = new google.visualization.NumberFormat({
			pattern: '#.##%',
			fractionDigits: 2
		});
		formatter.format(data, 1);
		formatter.format(data, 2);
		chart.draw(data, options);
	}
    </script>
    <div id="mtss_chart"></div>
	<?php
}

// Add the widget to the dashboard
function mtss_add_dashboard_widgets() {
	add_meta_box( 'mtss_dashboard_widget', '(mt) Server Status', 'mtss_dashboard_widget', 'dashboard', 'side', 'high' );	
}

// Create a options page under the 'Settings' page
function mtss_plugin_menu() {
	add_options_page( '(mt) Server Stats Options', '(mt) Server Stats', 'manage_options', 'mediatemple-server-stats', 'mtss_plugin_options' );
}

// Create the plugins page, which handles the form for adding API information
function mtss_plugin_options() {
	if ( ! current_user_can( 'manage_options' ))  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	
	// Check to see if the form was submitted
    if ( isset( $_POST['submit'] )) {
        update_option( 'mtss_api_key', $_POST['mtss_api_key'] );
        update_option( 'mtss_service_id', $_POST['mtss_service_id'] );
		?>
		<div class="updated"><p><strong>Settings updated.</strong></p></div>
		<?php
    }
    
    // If the form isn't submitted, get inputted values
    // otherwise get the just updated values
    $mtss_api_key = get_option( 'mtss_api_key' );
    $mtss_service_id = get_option( 'mtss_service_id' );
    
    // Allow the user to select services from a dropdown instead of inputting it themselves
	if ( ! empty( $mtss_api_key ) ) {
		$mt = json_decode( file_get_contents( 'https://api.mediatemple.net/api/v1/services.json?apikey=' .$mtss_api_key ) );
		$services = $mt->services;
    }
	?>
	<div class="wrap">
		<div id="icon-options-general" class="icon32"><br></div>
		<h2>(mt) Server Stats Options</h2>
		<form name="mtss-form" method="post" action="">
			<table class="form-table">
				<tbody>
					<tr>
						<th><label for="mtss_api_key">API Key</label></th>
						<td>
							<input name="mtss_api_key" id="mtss_api_key" type="text" value="<?php echo $mtss_api_key; ?>" class="regular-text code">
							<span class="description">Visit <a href="https://ac.mediatemple.net/api/">your (mt) account</a> to find or create an API key</span>
						</td>
					</tr>
					<tr>
						<th><label for="mtss_service_id">Service</label></th>
						<td>
							<?php if ( ! empty($mtss_api_key)): ?>
							<select name="mtss_service_id" id="mtss_service_id">
								<option value="">Select a service</option>
								<?php foreach ($services as $s): ?>
								<option value="<?php echo $s->id; ?>"<?php if ($mtss_service_id == $s->id) echo ' selected="selected"'; ?>><?php echo $s->primaryDomain; ?> - <?php echo $s->serviceTypeName; ?></option>
								<?php endforeach; ?>
							</select>
							<?php else: ?>
							<select name="mtss_service_id" id="mtss_service_id" disabled="disabled">
								<option>Please enter an API Key first</option>
							</select>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"></p>
		</form>
	</div>
	<?php
}

// Simple uninstall hook
function mtss_uninstall_hook() {
	delete_option( 'mtss_api_key' );
	delete_option( 'mtss_service_id' );
}