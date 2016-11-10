<?php
/*
Plugin Name: MapMap
Plugin URI: http://wpdeka.com/plugins/mapmap/
Description: MapMap is a Google Map Wizard. Easy to use map generator for your content, widget or template. Enable to use <strong>multiple maps in one page</strong> and as <strong>popup window</strong>.
Version: 1.0
Author: Sugiartha
Author URI: http://wpdeka.com/about/
License: GPLv2 or later
Text Domain: mapmap
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) die;

class mapmap { 
	public $textdomain = 'mapmap';

	public function __construct(){
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));
		
		add_action( 'init', array( $this, 'init_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_post_mapmap_submit', array( $this, 'admin_configuration_submit' ) );
		add_action( 'admin_post_mapmap_submit_shortcode', array( $this, 'admin_shortcode_submit' ) );
		add_action( 'admin_post_mapmap_delete_shortcode', array( $this, 'admin_shortcode_delete' ) );
		add_action( 'admin_notices', array( $this, 'admin_notice') );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue') );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, '_admin_setting_link') );			
	}
	
	public function activate(){
	}

	public function deactivate(){		
		//get mapmap setting
		$options = get_option('mapmap_options');
		if( $options['clear_db'] == 'yes' ){
			delete_option('mapmap_options');
			delete_option('mapmap_shortcodes');
		}
	}
	
	public function init_shortcode(){
		add_shortcode( 'mapmap', array( &$this, 'generate') );
	}
	
	protected function _prepare_text($content){
		$content = trim($content);
		if(!$content) return;
		
		$content = str_replace( array("\n", "\r\n", "\t"), '', $content );
		return $content;
	}

	protected function _get_shortcodes($id = false){
		$list = get_option('mapmap_shortcodes');
		if($id){
			if( isset($list[$id]) ) return $list[$id];
			return false;
		}
		return $list;
	}
	
	protected function _get_google_map_type($map_type = ''){
		$map_type = strtolower($map_type);
		switch($map_type){
			case 'roadmap': $out = 'ROADMAP'; break;
			case 'satellite': $out = 'SATELLITE'; break;
			case 'hybrid': $out = 'HYBRID'; break;
			case 'terrain': $out = 'TERRAIN'; break;
			default: $out = 'HYBRID';
		}
		return $out;
	}

	protected function _get_google_map_icons(){
		$arr_markers = array(
			'http://maps.google.com/mapfiles/kml/paddle/blu-blank.png',
			'http://maps.google.com/mapfiles/kml/paddle/blu-circle.png',
			'http://maps.google.com/mapfiles/kml/paddle/blu-diamond.png',
			'http://maps.google.com/mapfiles/kml/paddle/blu-square.png',
			'http://maps.google.com/mapfiles/kml/paddle/blu-stars.png',
			
			'http://maps.google.com/mapfiles/kml/paddle/grn-blank.png',
			'http://maps.google.com/mapfiles/kml/paddle/grn-circle.png',
			'http://maps.google.com/mapfiles/kml/paddle/grn-diamond.png',
			'http://maps.google.com/mapfiles/kml/paddle/grn-square.png',
			'http://maps.google.com/mapfiles/kml/paddle/grn-stars.png',
			
			'http://maps.google.com/mapfiles/kml/paddle/red-circle.png',
			'http://maps.google.com/mapfiles/kml/paddle/red-diamond.png',
			'http://maps.google.com/mapfiles/kml/paddle/red-square.png',
			'http://maps.google.com/mapfiles/kml/paddle/red-stars.png',
			'http://maps.google.com/mapfiles/kml/paddle/stop.png',
			
			'http://maps.google.com/mapfiles/kml/paddle/wht-blank.png',
			'http://maps.google.com/mapfiles/kml/paddle/wht-circle.png',
			'http://maps.google.com/mapfiles/kml/paddle/wht-diamond.png',
			'http://maps.google.com/mapfiles/kml/paddle/wht-square.png',
			'http://maps.google.com/mapfiles/kml/paddle/wht-stars.png',
			
			'http://maps.google.com/mapfiles/kml/paddle/ylw-blank.png',
			'http://maps.google.com/mapfiles/kml/paddle/ylw-circle.png',
			'http://maps.google.com/mapfiles/kml/paddle/ylw-diamond.png',
			'http://maps.google.com/mapfiles/kml/paddle/ylw-square.png',
			'http://maps.google.com/mapfiles/kml/paddle/ylw-stars.png',
			
			'http://maps.google.com/mapfiles/kml/pushpin/blue-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/grn-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/ltblu-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/pink-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/purple-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/red-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/wht-pushpin.png',
			'http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png',
		);
		
		return $arr_markers;
	}

	protected function _get_google_map_default_setting(){
		$defaults_array = array(
			//API type: static, javascript (default)
			'api_type' => 'javascript',
			
			//latitude and longitude coordinate (format: lat1,long1;lat2,long2;lat3,long3 etc)
			'location' => '37.4224764,-122.0842499', //google office
			
			//Map Type: roadmap, satellite, hybrid (default), terrain
			'map_type' => 'hybrid',
			
			//zoom factor 
			'zoom' => '17',
			
			//map size 
			'size' => '100%x300',
			
			//inline stylesheet
			'style' => false,
			
			//html class
			'class' => false,

			//info window title
			'title' => false,

			//info window description
			'desc' => false,

			//marker
			'icon' => false,
			
			//marker animation
			'animation' => true,
			
			//shortcode id
			'id' => false,			
		);
		
		return $defaults_array;
	}

	protected function _load_google_map_api(){
		//get mapmap setting
		$options = get_option('mapmap_options');
		
		//get 'include pages' option
		$pages = ( isset($options['pages']) && $options['pages'] ) ? explode(',', $options['pages']) : false;
		
		//load api at every page
		if($pages === false) return true;
		
		//get current postID
		$id = get_the_ID();
		
		if( in_array($id, $pages) ) return true;
		
		return false;
	}
	
	public function enqueue_scripts(){
		if( is_admin() ) return;
		if( $this->_load_google_map_api() ){
			$options = get_option('mapmap_options');
			
			if( isset($options['headerfooter']) && $options['headerfooter'] == 'header' ){
				wp_enqueue_script('google_map_api', 'https://maps.googleapis.com/maps/api/js?key=' . $options['google_api_key'], array(), false, false ) ;
				
			}else{
				wp_enqueue_script('google_map_api', 'https://maps.googleapis.com/maps/api/js?key=' . $options['google_api_key'], array(), false, true ) ;
				
			}
		}
	}
	
	/**
	 * Main function to generate and display Google Map
	 *
	 */	
	public function generate($atts, $content = null){
		//get mapmap setting
		$options = get_option('mapmap_options');
		
		//set default setting
		$defaults_array = $this->_get_google_map_default_setting();
		
		//read saved shortcode info
		if( isset($atts['id']) ){
			//get mapmap shortcode data
			$atts = $this->_get_shortcodes($atts['id']);
		}
		
		//fix map size issue
		if( !$atts['size'] ) unset($atts['size']);
		
		//combines attributes & their default value
		$atts = shortcode_atts( $defaults_array, $atts );
		
		//reset output
		$out = '';
		
		//check required info
		if(!$atts['location']) return false;
		
		//generate html id for multiple map instances in a page
		$id = date('his') . rand(1,100);
		
		//get proper syntax for map_type
		$atts['map_type'] = $this->_get_google_map_type($atts['map_type']);

		//do other shortcode
		if($content){
			$content = do_shortcode($content);
		}else{
			if($atts['title']) $content .= '<strong>' . $atts['title'] . '</strong><br>';
			if($atts['desc']) $content .= $atts['desc'];
		}
		
		if($atts['api_type'] == 'static'){
			
			$center = $this->_prepare_text($atts['location']);
			$marker = 'color:blue%7Clabel:S%7C' . $center;
			
			$src = 'https://maps.googleapis.com/maps/api/staticmap?center=' . urlencode($center) . 
				   '&zoom=' . $atts['zoom'] . '&format=JPEG&size=' . $atts['size'] . 
				   '&maptype=' . strtolower($atts['map_type']) . '&markers=' . $marker;

			$out .= '<img src="' . $src . '" class="' . $class . '" style="' . $style . '" alt="' . $content . '" title="' . $content . '">';
			
		}else{
			
			$arr = false;
			$coordinate = trim($atts['location']);
			if($coordinate){
				$tmp = explode(';', $coordinate);
				foreach($tmp as $r){
					$r = trim($r);
					if($r){
						list($lat, $long) = explode(',', $r);
						$arr[] = array('lat' => trim($lat), 'long' => trim($long));
					}
				}
			}
			
			//set div wrapper width & height if not set by html class or inline-style
			list($width, $height) = explode('x', strtolower($atts['size']));
			if( strpos($width, '%') === false && strpos($width, 'px') === false ){
				$width .= 'px';
			}
			if( strpos($height, '%') === false && strpos($height, 'px') === false ){
				$height .= 'px';
			}
			
			if( strpos($atts['style'], 'width:') === false ){
				$atts['style'] = 'width:' . $width . ';' . $atts['style'];
			}
			
			if( strpos($atts['style'], 'height:') === false ){
				$atts['style'] = 'height:' . $height . ';' . $atts['style'];
			}  
				
			//div wrapper for the map
			$out .= '<div id="mapmap-' . $id . '"' . ( $atts['class'] ? ' class="' . $atts['class'] . '" ' : '') . ( $atts['style'] ? ' style="' . $atts['style'] . '" ' : '') . '></div>';
			
			//preparing to call javascript google map api
			$out .= '<script type="text/javascript">';
			$out .= '
				var marker' . $id . ';
				function initMap' . $id . '() {
					var map = new google.maps.Map(document.getElementById("mapmap-' . $id . '"), {
						zoom: ' . $atts['zoom'] . ',
						center: {lat: ' . $arr[0]['lat'] . ', lng: ' . $arr[0]['long'] . '},
						mapTypeId: google.maps.MapTypeId.' . $atts['map_type'] . '
					});';
			
			//***** POLYLINES *****//
			if(sizeof($arr) > 1){		
				$out .= 'var theCoordinates = [';  
				foreach($arr as $x){
					$out .= '{lat: ' . $x['lat'] . ', lng: ' . $x['long'] . '},';
				}
				$out  = trim($out, ',');
				$out .= '];';
				
				$out .= 'var thePath = new google.maps.Polyline({
							path: theCoordinates,
							geodesic: true,
							strokeColor: "#FF0000",
							strokeOpacity: 1.0,
							strokeWeight: 1
							});';
				$out .= 'thePath.setMap(map);';
			}
			
			if($content){
				$out .= '
						var contentString = "' . addslashes($this->_prepare_text($content)) . '";';
				$out .= '
						var infowindow = new google.maps.InfoWindow({
							content: contentString
						});';
			}
			
			$marker_icon = ($atts['icon']) ? 'icon: "' . $atts['icon'] . '",' : '';
			$marker_animation = ($atts['animation']) ? 'animation: google.maps.Animation.BOUNCE,' : '';
	
			$out .= '
					marker' . $id . ' = new google.maps.Marker({
						position: {lat: ' . $arr[0]['lat'] . ', lng: ' . $arr[0]['long'] . '},
						' . $marker_icon . '
						' . $marker_animation . '
						title: "",
						map: map
					});
					marker' . $id . '.addListener("click", function() {
						infowindow.open(map, marker' . $id . ');
					});
					setTimeout(function(){ marker' . $id . '.setAnimation(null); }, 10000);
			';
					
			$out .= '}';
			$out .= 'jQuery(document).ready(function(){initMap' . $id . '();});';						
			$out .= '</script>';

		}//end of api-type	
		
		return $out;
	}
	
	/**
	 * Enqueue admin stylesheet for this plugin
	 *
	 */
	function admin_enqueue($hook) {
		if ( 'settings_page_mapmap' != $hook ) {
			return;
		}				
		wp_register_style( 'mapmap_wp_admin_css', plugin_dir_url( __FILE__ ) . 'assets/css/admin-style.css', false, '1.0.0' );
		wp_enqueue_style( 'mapmap_wp_admin_css' );
	}	
	
	public function admin_menu(){
		add_options_page( __('MapMap', $this->textdomain), __('MapMap', $this->textdomain), 'manage_options', 'mapmap', array( &$this, 'admin_configuration' ));
	}

	/**
	 * Update mapmap options after form submitted
	 *
	 */
	public function admin_configuration_submit(){
		if($_POST && isset($_POST['google_api_key'])){
			$google_api_key = ( isset($_POST['google_api_key']) ) ? wp_kses($_POST['google_api_key'], '') : '';
			$clear_db = ( isset($_POST['clear_db']) && $_POST['clear_db'] == 'yes' ) ? 'yes' : 'no';
			$headerfooter = ( isset($_POST['headerfooter']) && $_POST['headerfooter'] == 'header' ) ? 'header' : 'footer';
			$pages = ( isset($_POST['pages']) ) ? wp_kses($_POST['pages'], '') : '';
			
			//update mapmap options
			$options = array('google_api_key' => $google_api_key, 'clear_db' => $clear_db, 'headerfooter' => $headerfooter, 'pages' => $pages);
			update_option('mapmap_options', $options);
			
			wp_redirect(admin_url('options-general.php?page=mapmap&tab=api&message=update')); exit;
		}
		
		wp_redirect(admin_url('options-general.php?page=mapmap')); exit;	
	}

	/**
	 * Update / create mapmap shortcode after form submitted
	 *
	 */
	public function admin_shortcode_submit(){
		if($_POST && isset($_POST['form_location'])){
			$form_id = ( isset($_POST['form_id']) ) ? $_POST['form_id'] : false;
			$form_location = ( isset($_POST['form_location']) ) ? wp_kses($_POST['form_location'], '') : false;
			$form_icon = ( isset($_POST['form_icon']) ) ? wp_kses($_POST['form_icon'], '') : false;
			$form_zoom = ( isset($_POST['form_zoom']) ) ? (int)$_POST['form_zoom'] : 15;
			$form_maptypeid = ( isset($_POST['form_maptypeid']) ) ? $_POST['form_maptypeid'] : 'hybrid';
			$form_info_window_title = ( isset($_POST['form_info_window_title']) ) ? wp_kses($_POST['form_info_window_title'], '') : false;
			$form_info_window_description = ( isset($_POST['form_info_window_description']) ) ? $_POST['form_info_window_description'] : false;
			
			$form_map_size = ( isset($_POST['form_map_size']) ) ? $_POST['form_map_size'] : false;
			$form_class = ( isset($_POST['form_class']) ) ? $_POST['form_class'] : false;
			$form_style = ( isset($_POST['form_style']) ) ? $_POST['form_style'] : false;
			
			//update or create mapmap shortcode
			$list = get_option('mapmap_shortcodes');				
			if(!is_array($list)) $list = array();
			
			if(!$form_id) $form_id = date('YmdHis');
			
			$list[$form_id] = array('location' => $form_location, 'icon' => $form_icon, 'zoom' => $form_zoom, 'map_type' => $form_maptypeid, 'title' => $form_info_window_title, 'desc' => $form_info_window_description, 'size' => $form_map_size, 'class' => $form_class, 'style' => $form_style);
			update_option('mapmap_shortcodes', $list);
			
			wp_redirect(admin_url('options-general.php?page=mapmap&tab=wizard&message=update_shortcode&id=' . $form_id)); exit;			
		}
		
		wp_redirect(admin_url('options-general.php?page=mapmap')); exit;	
	}
	
	public function admin_shortcode_delete(){
		if($_GET && isset($_GET['id']) && $_GET['id'] > 0){
			$list = get_option('mapmap_shortcodes');
			if( is_array($list) && isset($list[$_GET['id']]) ){
				unset($list[$_GET['id']]);
				update_option('mapmap_shortcodes', $list);
				wp_redirect(admin_url('options-general.php?page=mapmap&tab=shortcode&message=delete_shortcode')); exit;
			}
		}
		wp_redirect(admin_url('options-general.php?page=mapmap&tab=shortcode')); exit;		
	}
	
	/**
	 * Print admin notice after form submitted 
	 *
	 */
	function admin_notice() {
		$screen = get_current_screen();
		if( $screen->id != 'settings_page_mapmap' ) return false;
		if( !isset( $_GET['message'] ) ) return false;
		
		switch($_GET['message']){			
			case 'update': 
				$class = 'notice notice-success is-dismissible';
				$message = __( 'Settings saved.', $this->textdomain );
				break;
			case 'update_shortcode': 
				$class = 'notice notice-success is-dismissible';
				$message = __( 'Shortcode setting saved.', $this->textdomain );
				break;
			case 'delete_shortcode': 
				$class = 'notice notice-success is-dismissible';
				$message = __( 'Shortcode deleted.', $this->textdomain );
				break;
		}
		
		if( isset($message) && isset($class) ){
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message ); 
		}
		return true;
	}	
	
	/**
	 * Mapmap admin configuration page
	 *
	 */
	public function admin_configuration(){
		//get mapmap options
		$options = get_option('mapmap_options');
		
		//get mapmap shortcode
		$list = $this->_get_shortcodes();
		$total_list = (is_array($list)) ? sizeof($list) : 0;
		$total_list = ($total_list) ? '(' . number_format($total_list) . ')' : '';
		
		//available tabs
		$array_tabs = array(
			'api' => 'API Key & Options', 
			'wizard' => 'Map Wizard', 
			'shortcode' => sprintf('Shortcode List %s', $total_list), 
			'help' => 'Help',
		);
		
		//set default active tab
		if( !isset($_GET['tab']) ){
			if( !isset($options['google_api_key']) ){
				$active_tab = 'api';
			}else{
				$active_tab = 'wizard';
			}
		}else{
			$active_tab = ( in_array($_GET['tab'], array_keys($array_tabs) ) ) ? $_GET['tab'] : 'wizard'; 
		}	
?>
		<div class="wrap">
			<h2><?php echo __('MapMap', $this->textdomain);?></h2>
			
			<h2 class="nav-tab-wrapper">
				<?php
				foreach($array_tabs as $key => $value){
					echo '<a href="' . admin_url('options-general.php?page=mapmap&tab=' . $key) . '" class="nav-tab ' . ($active_tab == $key ? 'nav-tab-active' : '') . '">' . $value . '</a>';
				}
				?>
			</h2>			
			
			<div class="mapmap_admin_wrap">
			<?php
			if( $active_tab == 'api' ){
				$this->_admin_setting();
			}elseif( $active_tab == 'shortcode' ){
				$this->_admin_shortcode();
			}elseif( $active_tab == 'wizard' ){
				$this->_admin_wizard();
			}else{
				$this->_admin_help();
			}
			?>
			</div>
		</div>
<?php	
	}

	protected function _admin_help(){
?>
			<p>Todo</p>
<?php		
	}
	
	protected function _admin_setting(){
		//read options
		$options = get_option('mapmap_options');				
?>
		<form method="post" action="admin-post.php">
			<input type="hidden" name="action" value="mapmap_submit">
			<table class="form-table">
				<tr valign="top">
				<th scope="row"><?php echo __('Google API Key:', $this->textdomain);?></th>
				<td><input type="text" class="regular-text" name="google_api_key" value="<?php echo ( isset($options['google_api_key']) ) ? esc_attr( $options['google_api_key'] ) : ''; ?>" />
				<p class="description">To get Google Maps JavaScript API, click <a target="_blank" href="https://console.developers.google.com/flows/enableapi?apiid=maps_backend,geocoding_backend,directions_backend,distance_matrix_backend,elevation_backend&keyType=CLIENT_SIDE&reusekey=true">here</a>.<br>
				Need more help? Follow this <a target="_blank" href="http://wpdeka.com/tutorials/create-google-maps-javascript-api-key">tutorial</a>.
				</p>
				</td>
				</tr>

				<tr valign="top">
				<th scope="row"><?php echo __('Javascript API Load:', $this->textdomain);?></th>
				<td>
				<p><?php echo __('Where to load Google Maps API script?', $this->textdomain);?></p>
				<label><input name="headerfooter" type="radio" value="header" <?php echo ( isset($options['headerfooter']) && $options['headerfooter'] == 'header') ? 'checked' : '';?> /> <?php echo __('Inside <code>&lt;head&gt;</code> tag', $this->textdomain);?></label><br>
				<label><input name="headerfooter" type="radio" value="footer" <?php echo ( (isset($options['headerfooter']) && $options['headerfooter'] == 'footer') || !isset($options['headerfooter'])) ? 'checked' : '';?> /> <?php echo __('At footer (default)', $this->textdomain);?></label>				
				</td>
				</tr>

				<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td>
				<input type="text" class="regular-text" name="pages" value="<?php echo ( isset($options['pages']) ) ? esc_attr( $options['pages'] ) : ''; ?>" />				
				<p class="description"><?php echo __('For site optimization, you may specify which pages or posts the API should be loaded.<br>Put their ID separated by comma or leave it blank to load Google Maps API script on every page.', $this->textdomain);?></p>
				</td>
				</tr>
				
				
				<tr valign="top">
				<th scope="row"><?php echo __('Plugin Deactivation:', $this->textdomain);?></th>
				<td>
				<p><?php echo __('Clear all database records during plugin deactivation?', $this->textdomain);?></p>
				<label><input name="clear_db" type="radio" value="yes" <?php echo ( isset($options['clear_db']) && $options['clear_db'] == 'yes') ? 'checked' : '';?> /> <?php echo __('Yes, please clear them', $this->textdomain);?></label><br>
				<label><input name="clear_db" type="radio" value="no" <?php echo ( (isset($options['clear_db']) && $options['clear_db'] == 'no') || !isset($options['clear_db'])) ? 'checked' : '';?> /> <?php echo __('No, keep the database (default)', $this->textdomain);?></label>
				</td>
				</tr>

				<tr valign="top">
				<th scope="row">&nbsp;</th>
				<td><?php submit_button(); ?></td>
				</tr>					
			</table>
		</form>
<?php		
	}

	function _admin_setting_link($links){
		$settings_link = '<a href="options-general.php?page=mapmap">' . __( 'Settings', $this->textdomain ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}
	
	protected function _admin_shortcode(){
?>
    	<table class="widefat" cellspacing="0">
    		<thead>
			<tr>
				<th scope="col" class="manage-column" style="width:10%;"><?php echo __('ID', $this->textdomain);?></th>
				<th scope="col" class="manage-column" style="width:30%;"><?php echo __('Shortcode', $this->textdomain);?></th>
				<th scope="col" class="manage-column" style=""><?php echo __('Location', $this->textdomain);?></th>
				<th scope="col" class="manage-column" style=""><?php echo __('Icon', $this->textdomain);?></th>
				<th scope="col" class="manage-column" style=""><?php echo __('Info Window', $this->textdomain);?></th>
				<th scope="col" class="manage-column" style="text-align:center"><?php echo __('Actions', $this->textdomain);?></th>
			</tr>
    		</thead>
    		<tbody>
<?php
	$query = get_option('mapmap_shortcodes');
	//echo '<pre>'; print_r($query); die;
    if($query){
        $no = 0;
        foreach ($query as $id => $line) {
            $no++;
            $tr_class = 'mapmap_row';
			$tr_class .= ($no % 2) ? ' row_odd' : '';
			
			$actions = '<a onclick="return confirm(\'Are you sure want to delete shortcode # ' . $id . '?\');" href="' . admin_url('admin-post.php?action=mapmap_delete_shortcode&id=' . $id) . '">' . __('Delete', $this->textdomain) . '</a>';
			$actions .= ' | <a href="' . admin_url('options-general.php?page=mapmap&tab=wizard&id=' . $id) . '">' . __('Edit', $this->textdomain) . '</a>';

?>        
        		<tr class="<?php echo $tr_class;?>">
        			<td><?php echo $id;?></td>
        			<td><?php echo '[mapmap id="' . $id . '"]'; ?></td>
        			<td><?php echo $line['location']; ?></td>
        			<td><?php echo ($line['icon']) ? '<img src="' . $line['icon'] . '" style="max-width:25px;">' : 'Default'; ?></td>
        			<td><?php echo $line['title']; ?></td>
        			<td align="center"><?php echo $actions;?></td>
        		</tr>
<?php        
        }
    }
?>

            </tbody>
        </table>

<?php		
	}

	protected function _admin_wizard(){
		//read options
		$options = get_option('mapmap_options');
		
		//set default value
		$defaults_array = $this->_get_google_map_default_setting();
		
		//get shortcode id
		$id = ( isset($_GET['id']) && $_GET['id'] > 0 ) ? $_GET['id'] : false;
		
		$data = false;
		if($id){
			$data = $this->_get_shortcodes($id);
		}
		if( !is_array($data) ) $data = array();
		$data = shortcode_atts( $defaults_array, $data );

		//get proper syntax for map_type
		$data['map_type'] = $this->_get_google_map_type($data['map_type']);
?>
		<h3 class="mapmap_step">Step 1: Location</h3>
		<p>
		Search your address here or enter latitude,longitude coordinate:<br>
		<input type="text" class="regular-text" name="location" id="location" value="<?php echo $data['location'];?>" /> <input type="button" name="button1" value="view" onclick="updateLocation();" />
		</p>
		
		<h3 class="mapmap_step">Step 2: Marker & Other Settings</h3>
		<div id="mapmap_googlemap"></div>
		<script type="text/javascript">
			var map;
			var marker;
			var marker_src = <?php echo ($data['icon']) ? '"' . $data['icon'] . '"' : 'null';?>;
			
			function isNumeric(n) {
				return !isNaN(parseFloat(n)) && isFinite(n);
			}
			
			function setTimeoutMarkerAnimation(){
				setTimeout(function(){ marker.setAnimation(null); }, 2500);
			}

			function initMarker(map, icon, position){
				var marker = new google.maps.Marker({
					map: map,
					icon: icon,
					draggable: true,
					animation: google.maps.Animation.BOUNCE,
					position: position
				});
				
				return marker;
			}
						
			function printMarkerLocation(){
				//get current marker position
				var lat = marker.getPosition().lat();
				var lng = marker.getPosition().lng();
				document.getElementById('icon_location').innerHTML = lat + ', ' + lng;
				document.getElementById('form_location').value = lat + ', ' + lng;
				document.getElementById('form_icon').value = marker_src;
				document.getElementById('form_zoom').value = map.getZoom();
				document.getElementById('form_maptypeid').value = map.getMapTypeId();
			}
			
			function updateIcon(obj){
				//remove selected class
				jQuery('.mapmap_icon').removeClass('selected');
				jQuery(obj).addClass('selected');
				marker_src = jQuery(obj).attr('src');
				
				//get current marker position
				var lat = marker.getPosition().lat();
				var lng = marker.getPosition().lng();
				var myLatlng = new google.maps.LatLng(lat,lng);
				
				//remove previous marker
				marker.setMap(null);
				//create new marker
				marker = initMarker(map, marker_src, myLatlng);
				setTimeoutMarkerAnimation();				
				printMarkerLocation();
				
				google.maps.event.addListener(marker, 'dragend', function (event) {
					printMarkerLocation();
				});
			}

			function updateLocation(){
				var myLatlng;
				var obj = document.getElementById('location');
				var loc = obj.value;
				if(!loc) return false;
				
				var center = loc.split(',');
				if( !isNumeric(center[0]) || !isNumeric(center[1]) ){
					
					//assume it as human readable address
					jQuery.getJSON('https://maps.googleapis.com/maps/api/geocode/json?address=' + loc, function(obj) {
						if (obj.status === google.maps.GeocoderStatus.OK) {
							var myLatlng = new google.maps.LatLng(obj.results[0].geometry.location.lat,obj.results[0].geometry.location.lng);
							map.setCenter(myLatlng);
							
							//remove previous marker
							marker.setMap(null);
							//create new marker
							marker = initMarker(map, marker_src, myLatlng);
							setTimeoutMarkerAnimation();
							printMarkerLocation();
							
							google.maps.event.addListener(marker, 'dragend', function (event) {
								printMarkerLocation();
							});				

						} else {
							alert('Geocode was not successful for the following reason: ' + obj.status);
							return false;
						}
					});					

				}else{
					//assume it as latitude longitude format
					myLatlng = new google.maps.LatLng(center[0],center[1]);
					map.setCenter(myLatlng);
					
					//remove previous marker
					marker.setMap(null);
					//create new marker
					marker = initMarker(map, marker_src, myLatlng);
					setTimeoutMarkerAnimation();
					printMarkerLocation();
					
					google.maps.event.addListener(marker, 'dragend', function (event) {
						printMarkerLocation();
					});	
				}				
			}
			
			function initMap() {
				//set initial coordinate
				var myLatlng = new google.maps.LatLng(<?php echo $data['location'];?>);
				map = new google.maps.Map(document.getElementById('mapmap_googlemap'), {
					center: myLatlng,
					mapTypeId: google.maps.MapTypeId.<?php echo $data['map_type'];?>,
					zoom: <?php echo $data['zoom'];?>
				});
				//create new marker
				marker = initMarker(map, marker_src, myLatlng);
				setTimeoutMarkerAnimation();
				printMarkerLocation();
				google.maps.event.addListener(marker, 'dragend', function (event) {
					printMarkerLocation();
				});
				
				google.maps.event.addListener(map, "maptypeid_changed", function() {
					document.getElementById('form_maptypeid').value = map.getMapTypeId();
				});
				
				/*if($content){
					$out .= '
							var contentString = "<?php echo addslashes($this->_prepare_text($content));?>";';
					$out .= '
							var infowindow = new google.maps.InfoWindow({
								content: contentString
							});';
				}*/
				
			}
		</script>
		<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $options['google_api_key'];?>&callback=initMap" async defer></script>


		<style>
		.inline-tab-content{display:none;}
		.inline-tab-content{
			border: solid 1px #ccc;
			border-top: none;
			padding: 10px;
			max-width: 1125px;
		}
		</style>
		<script type="text/javascript">
			jQuery(document).ready(function($){
				$('.inline-tab-content').first().show();
				$('.inline-tab').first().addClass('nav-tab-active');
				$('.inline-tab').click(function( event ){
					//prevent normal behaviour
					event.preventDefault();
					
					//get target tab-content 
					var targ = $(this).attr('href');
					//hide all tab-content except the target
					$('.inline-tab-content').hide();
					$(targ).show();
					
					//set active-tab class 
					$('.inline-tab').removeClass('nav-tab-active');
					$(this).addClass('nav-tab-active').blur();
				});
			});
		</script>
		
		<div id="mapmap_attributes">
			<form method="post" action="admin-post.php">
			<input type="hidden" name="action" value="mapmap_submit_shortcode">
			<input type="hidden" name="form_id" value="<?php echo $id;?>">
			<input type="hidden" name="form_location" id="form_location" value="">
			<input type="hidden" name="form_icon" id="form_icon" value="">
			<input type="hidden" name="form_zoom" id="form_zoom" value="">
			<input type="hidden" name="form_maptypeid" id="form_maptypeid" value="">
			
			<h2 class="nav-tab-wrapper">
				<a href="#tab-marker" class="nav-tab inline-tab">#Marker</a>
				<a href="#tab-info" class="nav-tab inline-tab">#Info Window</a>
				<a href="#tab-setting" class="nav-tab inline-tab">#Advanced</a>
			</h2>
			
			<div id="tab-marker" class="inline-tab-content">
				<fieldset>
					<legend>Current Marker Location</legend>
					<div id="icon_location"></div>
				</fieldset>
				
				<fieldset>
					<legend>Change Marker</legend>
					<?php
					$arr_markers = $this->_get_google_map_icons();
					foreach($arr_markers as $img){
						echo '<img class="mapmap_icon" src="' . $img . '" onclick="updateIcon(this);"> ';
					}	
					?>
				</fieldset>
			</div>

			<div id="tab-info" class="inline-tab-content">
				<fieldset>
					<legend>Info Window</legend>
					<p>Title:<br><input type="text" class="regular-text" id="form_info_window_title" name="form_info_window_title" value="<?php echo wp_kses($data['title'], '');?>"></p>
					<p>Description:<br><textarea rows="3" class="large-text code" id="form_info_window_description" name="form_info_window_description"><?php echo $data['desc'];?></textarea></p>
				</fieldset>
			</div>

			<div id="tab-setting" class="inline-tab-content">
				<fieldset>
					<legend>Advanced Settings</legend>
					<p>Map Size:<br><input type="text" class="regular-text" id="form_map_size" name="form_map_size" value="<?php echo wp_kses($data['size'], '');?>"><br>
					<em>Set map size here. You may use % for percentage. Default value is 100%x300 (100% width and 300 pixel height).</em></p>
					
					<p>HTML Class:<br><input type="text" class="regular-text" id="form_class" name="form_class" value="<?php echo wp_kses($data['class'], '');?>"><br>
					<em>Set HTML class. This class will be added at DIV wrapper. Default value is blank.</em></p>
					
					<p>Inline Stylesheet:<br><input type="text" class="regular-text" id="form_style" name="form_style" value="<?php echo wp_kses($data['style'], '');?>"><br>
					<em>You may set inline-stylesheet here for current map. This style will be added at DIV wrapper.</em></p>
<?php /* TODO
					<p>Popup Window:<br><input type="checkbox" id="form_popup" name="form_popup" value="yes" <?php echo (isset($data['popup']) && $data['popup'] == 'yes') ? 'checked' : '';?>> Display map as popup window.<br>
					<em>Fill the anchor text below with text or image url.<br>Default value is 'Map'</em><br>
					<input type="text" class="regular-text" id="form_popup_text" name="form_popup_text" value="<?php echo wp_kses($data['popup_text'], '');?>"></p>
*/ ?>
				</fieldset>
			</div>
			
			<p style="text-align:center;">
			<?php if($id): ?>
			<input type="submit" class="button button-primary" value="Update Shortcode">
			<?php else: ?>
			<input type="submit" class="button button-secondary" value="Create New Shortcode">
			<?php endif;?>
			</p>
			</form>
		</div>
		<br clear="all">
<?php		
	}
}//end of mapmap class

$gmap = new mapmap();