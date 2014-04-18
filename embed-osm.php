<?php
/*
	Plugin Name: Embed OSM
	Plugin URI: http://midoriit.com/works/embed-osm.html
	Description: Embed OpenStreetMap on the page/post.
	Version: 1.1
	Author: Midori IT Office, LLC
	Author URI: http://midoriit.com/
	License: GPLv2 or later
	Text Domain: embed-osm
	Domain Path: /languages/
*/

$embed_osm = new Embed_OSM();

class Embed_OSM {

	/** OpenStreetMap tile server definition */
	const MAPNIK_URL = 
		'["http://a.tile.openstreetmap.org/${z}/${x}/${y}.png",
		"http://b.tile.openstreetmap.org/${z}/${x}/${y}.png",
		"http://c.tile.openstreetmap.org/${z}/${x}/${y}.png"]';
	const CYCLE_URL = 
		'["http://a.tile.opencyclemap.org/cycle/${z}/${x}/${y}.png",
		"http://b.tile.opencyclemap.org/cycle/${z}/${x}/${y}.png",
		"http://c.tile.opencyclemap.org/cycle/${z}/${x}/${y}.png"]';
	const TRANSPORT_URL = 
		'["http://a.tile2.opencyclemap.org/transport/${z}/${x}/${y}.png",
		"http://b.tile2.opencyclemap.org/transport/${z}/${x}/${y}.png",
		"http://c.tile2.opencyclemap.org/transport/${z}/${x}/${y}.png"]';

	static $layers = array( '-', 'mapnik', 'cycle', 'transport' );

	/*
	 * Constructor
	 */
	public function __construct() {
		register_activation_hook( __FILE__, array( &$this, 'embed_osm_activate' ) );
		register_uninstall_hook( __FILE__, 'Embed_OSM::embed_osm_uninstall' );
		add_shortcode( 'embed_osm', array( &$this, 'embed_osm_handler' ) );
		add_action( 'admin_menu', array( &$this, 'embed_osm_menu' ) );
		add_action( 'admin_init', array( &$this, 'embed_osm_init' ) );
		add_action( 'plugins_loaded', array( &$this, 'embed_osm_loaded' ) );
	}

	/*
	 * Callback for plugins_loaded
	 */
	function embed_osm_loaded() {
		$ret = load_plugin_textdomain( 'embed-osm', false,
			basename( dirname(__FILE__) ).'/languages/' );
	}

	/*
	 * Activation hook
	 */
	public function embed_osm_activate() {
		add_option( 'embed_osm_width', '400' );
		add_option( 'embed_osm_height', '300' );
		add_option( 'embed_osm_layer', '-' );
		add_option( 'embed_osm_lat', '35.32395' );
		add_option( 'embed_osm_lon', '139.55598' );
		add_option( 'embed_osm_zoom', '15' );
		add_option( 'embed_osm_marker', 'show' );
		add_option( 'embed_osm_link', 'show' );
	}

	/*
	 * Uninstall hook
	 */
	public static function embed_osm_uninstall() {
		delete_option( 'embed_osm_width' );
		delete_option( 'embed_osm_height' );
		delete_option( 'embed_osm_layer' );
		delete_option( 'embed_osm_lat' );
		delete_option( 'embed_osm_lon' );
		delete_option( 'embed_osm_zoom' );
		delete_option( 'embed_osm_marker' );
		delete_option( 'embed_osm_link' );
	}

	/*
	 * Callback for admin_init
	 */
	public function embed_osm_init() {
		add_meta_box( 'embed_osm', __( 'Embed OSM shortcode', 'embed-osm' ),
			array( &$this, 'embed_osm_box' ), 'post' );
		add_meta_box( 'embed_osm', __( 'Embed OSM shortcode', 'embed-osm' ),
			array( &$this, 'embed_osm_box' ), 'page' );
	}

	/*
	 * Display and process metabox
	 */
	public function embed_osm_box() {
		$width = get_option( 'embed_osm_width' );
		$height = get_option( 'embed_osm_height' );
		$layer = get_option( 'embed_osm_layer' );
		$lat = get_option( 'embed_osm_lat' );
		$lon = get_option( 'embed_osm_lon' );
		$zoom = get_option( 'embed_osm_zoom' );

		switch( $layer ) {
			case 'cycle':
				$tileurl = self::CYCLE_URL;
				break;
			case 'transport':
				$tileurl = self::TRANSPORT_URL;
				break;
			default:
				$tileurl = self::MAPNIK_URL;
		}

		echo __( 'Map Layer', 'embed-osm' ).
			' : <select id="embed_osm_layer" onChange="embed_osm_showmap();">';
		foreach ( self::$layers as $ly ) {
			if( strcmp( $ly, '-' ) == 0 ) {
				echo '<option value="'.$ly.'" selected>'.$ly.'</option>';
			} else {
				echo '<option value="'.$ly.'">'.$ly.'</option>';
			}
		}
		echo '</select> ';
		echo '<a class="button" onClick="embed_osm_get_cur_pos();">'.__( 'Get Current Position', 'embed-osm' ).'</a><br /><br />';
		echo '<div id="embmapdiv" style="width:'.$width.'px;height:'.$height.'px;">';
		echo '</div><br />';
		echo '<textarea id="embed_osm_shortcode" rows="2" style="max-width:100%;min-width:100%" onClick="this.select();" readonly>';
		echo '</textarea><br />';
		echo '<script type="text/javascript" src="'.plugins_url().
			'/embed-osm/openlayers/OpenLayers.js"></script>';
		echo '<script type="text/javascript" src="'.plugins_url().
			'/embed-osm/openlayers4jgsi/Crosshairs.js"></script>';
		echo '<script type="text/javascript">
			var map;
			var tileurl;
			var lat = '.$lat.';
			var lon = '.$lon.';
			var zoom = '.$zoom.';
			var lonLat;

			function embed_osm_showmap() {
				var lonLat;
				switch( embed_osm_layer.value ) {
					case "mapnik":
						tileurl = '.self::MAPNIK_URL.';
						break;
					case "cycle":
						tileurl = '.self::CYCLE_URL.';
						break;
					case "transport":
						tileurl = '.self::TRANSPORT_URL.';
						break;
					default:
						tileurl = '.$tileurl.';
				}
				if( map ) {
					zoom = map.getZoom();
					lonLat = map.getCenter().transform(
						map.getProjectionObject(),
						new OpenLayers.Projection( "EPSG:4326" ) );
					lon = lonLat.lon;
					lat = lonLat.lat;
					map.destroy();
				}
				map = new OpenLayers.Map( "embmapdiv" );
				map.addLayer(new OpenLayers.Layer.OSM( "", tileurl ) );
				lonLat = new OpenLayers.LonLat( lon, lat ).transform(
					new OpenLayers.Projection( "EPSG:4326" ),
					map.getProjectionObject() );
				map.setCenter( lonLat, zoom );
				var center = new OpenLayers.Pixel(
					map.getCurrentSize().w / 2,
					map.getCurrentSize().h / 2);
				var cross = new OpenLayers.Control.Crosshairs( {
					imgUrl: "'.plugins_url().'/embed-osm/openlayers4jgsi/crosshairs.png",
					size: new OpenLayers.Size( 32, 32 ),
					position: center
				} );
				map.addControl( cross ); 
				map.events.register( "moveend", map, embed_osm_moveend );
				embed_osm_genshortcode();
			};

			function embed_osm_genshortcode() {
				var lonLat = map.getCenter().transform(
					map.getProjectionObject(),
					new OpenLayers.Projection( "EPSG:4326" ) );

				var layer = embed_osm_layer.value == "-" ?
					"" : " layer=\"" + embed_osm_layer.value + "\"";

				embed_osm_shortcode.value = "[embed_osm" +
					" lat=\"" + Math.round( lonLat.lat * 100000 ) / 100000 + "\"" +
					" lon=\"" + Math.round( lonLat.lon * 100000 ) / 100000 + "\"" +
					" zoom=\"" + zoom + "\"" +
					layer + "]";
				embed_osm_shortcode.select();
			};

			function embed_osm_moveend() {
				zoom = map.getZoom();
				embed_osm_genshortcode();
			}

			function embed_osm_get_cur_pos() {
				if( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition(
						function(pos) {
							lonLat = new OpenLayers.LonLat( pos.coords.longitude, pos.coords.latitude ).transform(
								new OpenLayers.Projection( "EPSG:4326" ),
								map.getProjectionObject() );
							map.setCenter( lonLat, zoom );
						} );
				}
			}

			embed_osm_showmap();
		</script>';
	}

	/*
	 * Callback for shortcode
	 */
	public function embed_osm_handler( $atts ) {
		$width = get_option( 'embed_osm_width' );
		$height = get_option( 'embed_osm_height' );
		$option_layer = get_option( 'embed_osm_layer' );
		$marker = get_option( 'embed_osm_marker' );
		$link = get_option( 'embed_osm_link' );
		$uniq = uniqid( "", 1 );

		$tileurl = '';

		extract( shortcode_atts( array(
			'lon' => '0',
			'lat' => '0',
			'zoom' => '1',
			'layer' => ''),
				$atts ) );

		switch( $layer ) {
			case 'cycle':
				$tileurl = self::CYCLE_URL;
				$add_param = '&amp;layers=C';
				break;
			case 'transport':
				$tileurl = self::TRANSPORT_URL;
				$add_param = '&amp;layers=T';
				break;
			case 'mapnik':
				$tileurl = self::MAPNIK_URL;
				$add_param = '';
		}

		if(empty( $tileurl ) ) {
			switch( $option_layer ) {
				case 'cycle':
					$tileurl = self::CYCLE_URL;
					$add_param = '&amp;layers=C';
					break;
				case 'transport':
					$tileurl = self::TRANSPORT_URL;
					$add_param = '&amp;layers=T';
					break;
				default:
					$tileurl = self::MAPNIK_URL;
					$add_param = '';
			}
		}

		switch( $marker ) {
			case 'green':
				$icon = '-green';
				break;
			case 'blue':
				$icon = '-blue';
				break;
			case 'gold':
				$icon = '-gold';
				break;
			default:
				$icon = '';
		}

		$script = 
			'<div id="mapdiv'.$uniq.'" style="width:'.$width.'px; height:'.$height.'px;"></div>
			<script type="text/javascript" src="'.plugins_url().
				'/embed-osm/openlayers/OpenLayers.js"></script>
			<script type="text/javascript">
				OpenLayers.IMAGE_RELOAD_ATTEMPTS = 5;
				var map = new OpenLayers.Map( "mapdiv'.$uniq.'" );
				map.addLayer( new OpenLayers.Layer.OSM( "", '.$tileurl.' ) );
				var lonLat = new OpenLayers.LonLat( '.$lon.' , '.$lat.' ).transform(
					new OpenLayers.Projection( "EPSG:4326" ),
					map.getProjectionObject() );
				map.setCenter( lonLat, '.$zoom.' );';
				if( $marker !== 'hide' ) {
					$script = $script.
					'var markers = new OpenLayers.Layer.Markers( "Markers" );
					map.addLayer( markers );
					var mkIcon = new OpenLayers.Icon( "'.plugins_url().
						'/embed-osm/openlayers/img/marker'.$icon.
						'.png", { w: 21, h: 25 }, { x: -10.5, y: -25 } );
					var marker = new OpenLayers.Marker( lonLat, mkIcon );
					markers.addMarker( marker );';
				}
		$script = $script.'</script>';
		if( $link === 'show' ) {
			$script = $script.
				'<small>
				<a href="http://www.openstreetmap.org/?mlat='.$lat.'&amp;mlon='.$lon.
					'#map='.$zoom.'/'.$lat.'/'.$lon.$add_param.'" target="_blank">'.
					__( 'View Larger Map', 'embed-osm' ).'</a>
				</small>';
		}
		return $script;
	}

	/*
	 * Callback for admin_menu
	 */
	function embed_osm_menu() {
		add_options_page( __( 'Embed OSM Settings', 'embed-osm' ), 'Embed OSM',
			'manage_options', 'embed_osm', array( &$this, 'embed_osm_options' ) );
	}

	/*
	 * Display and process settings page
	 */
	function embed_osm_options() {
		if ( !current_user_can( 'manage_options' ) )	{
			wp_die( __( 'insufficient permissions.' ) );
		}

		if ( isset( $_POST['update_option'] ) ) {
			check_admin_referer( 'embed_osm_options' );
			$width = $_POST['embed_osm_width'];
			if( is_numeric( $width ) ) {
				update_option( 'embed_osm_width', $width );
			}
			$height = $_POST['embed_osm_height'];
			if( is_numeric( $height ) ) {
				update_option( 'embed_osm_height', $height );
			}
			$layer = $_POST['embed_osm_layer'];
			update_option( 'embed_osm_layer', $layer );
			$lat = $_POST['embed_osm_lat'];
			update_option( 'embed_osm_lat', $lat );
			$lon = $_POST['embed_osm_lon'];
			update_option( 'embed_osm_lon', $lon );
			$zoom = $_POST['embed_osm_zoom'];
			update_option( 'embed_osm_zoom', $zoom );
			$marker = $_POST['embed_osm_marker'];
			update_option( 'embed_osm_marker', $marker );
			$link = $_POST['embed_osm_link'];
			update_option( 'embed_osm_link', $link );
		}

		$width = get_option( 'embed_osm_width' );
		$height = get_option( 'embed_osm_height' );
		$layer = get_option( 'embed_osm_layer' );
		$lat = get_option( 'embed_osm_lat' );
		$lon = get_option( 'embed_osm_lon' );
		$zoom = get_option( 'embed_osm_zoom' );
		$marker = get_option( 'embed_osm_marker' );
		$link = get_option( 'embed_osm_link' );

		echo '<div><h2>'.__( 'Embed OSM Settings', 'embed-osm' ).'</h2>';
		echo '<form name="form" method="post" action="">';
		wp_nonce_field( 'embed_osm_options' );
		echo '<table class="form-table"><tbody>';
		echo '<tr><td>'.__( 'Map Width', 'embed-osm' ).'</td>';
		echo '<td><input type="text" name="embed_osm_width" value="'.
			$width.'" size="20"></td></tr>';
		echo '<tr><td>'.__( 'Map Height', 'embed-osm').'</td>';
		echo '<td><input type="text" name="embed_osm_height" value="'.
			$height.'" size="20"></td></tr>';
		echo '<tr><td>'.__( 'Map Layer', 'embed-osm').'</td>';
		echo '<td><select name="embed_osm_layer" id="embed_osm_layer" onChange="embed_osm_showmap2();">';
		foreach ( self::$layers as $ly ) {
			if( strcmp( $ly, $layer ) == 0) {
				echo '<option value="'.$ly.'" selected>'.$ly.'</option>';
			} else {
				echo '<option value="'.$ly.'">'.$ly.'</option>';
			}
		}
		echo '</select></td></tr>';
		echo '<tr><td>'.__( 'Home Position', 'embed-osm' ).'</td><td>';
		echo __( 'Latitude', 'embed-osm' ).
			' : <input type="text" id="embed_osm_lat" name="embed_osm_lat" value="'.
			$lat.'" size="10" readonly> ';
		echo __( 'Longitude', 'embed-osm' ).
			' : <input type="text" id="embed_osm_lon" name="embed_osm_lon" value="'.
			$lon.'" size="10" readonly> ';
		echo __( 'Zoom', 'embed-osm' ).
			' : <input type="text" id="embed_osm_zoom" name="embed_osm_zoom" value="'.
			$zoom.'" size="5" readonly><br /><br />';
		echo '<a class="button" onClick="embed_osm_get_cur_pos();">'.__( 'Get Current Position', 'embed-osm' ).'</a><br /><br />';
		echo '<div id="defmapdiv" style="width:'.$width.'px;height:'.$height.
			'px;"></div></td></tr>';
		echo '<tr><td>'.__( 'Marker', 'embed-osm' ).
			'</td><td>'.
				'<table><tbody><tr>'.
					'<td><input type="radio" name="embed_osm_marker" value="hide"'.
						( $marker === 'hide' ? ' checked' : '' ).'>'.
						__( 'Hide', 'embed-osm' ).'</td>'.
					'<td><input type="radio" name="embed_osm_marker" value="show"'.
						( $marker === 'show' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-osm/openlayers/img/marker.png" style="vertical-align:middle;"></td>'.
					'<td><input type="radio" name="embed_osm_marker" value="green"'.
						( $marker === 'green' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-osm/openlayers/img/marker-green.png" style="vertical-align:middle;"></td>'.
					'<td><input type="radio" name="embed_osm_marker" value="gold"'.
						( $marker === 'gold' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-osm/openlayers/img/marker-gold.png" style="vertical-align:middle;"></td>'.
					'<td><input type="radio" name="embed_osm_marker" value="blue"'.
						( $marker === 'blue' ? ' checked' : '' ).
						'><img src="'.plugins_url().
						'/embed-osm/openlayers/img/marker-blue.png" style="vertical-align:middle;"></td>'.
				'</tr></tbody></table>'.
			'</td></tr>';
		echo '<tr><td>'.__( 'Link to Larger Map', 'embed-osm' ).
			'</td><td>'.
				'<table><tbody><tr>'.
					'<td><input type="radio" name="embed_osm_link" value="show"'.
						( $link === 'show' ? ' checked' : '' ).'>'.
						__( 'Show', 'embed-osm' ).'</td>'.
					'<td><input type="radio" name="embed_osm_link" value="hide"'.
						( $link === 'hide' ? ' checked' : '' ).'>'.
						__( 'Hide', 'embed-osm' ).'</td>'.
				'</tr></tbody></table>'.
			'</td></tr>';
		echo '</tbody></table>';
		echo '<input type="submit" name="update_option" class="button button-primary" value="'.
			esc_attr__( 'Save Changes' ).'" />';
		echo '</form>';
		echo '</div>';

		echo '<script type="text/javascript" src="'.plugins_url().
			'/embed-osm/openlayers/OpenLayers.js"></script>';
		echo '<script type="text/javascript" src="'.plugins_url().
			'/embed-osm/openlayers4jgsi/Crosshairs.js"></script>';
		echo '<script type="text/javascript">
			var map;
			var tileurl;
			var lat = '.$lat.';
			var lon = '.$lon.';
			var zoom = '.$zoom.';
			var lonLat;

			function embed_osm_showmap2() {
				var lonLat;
				switch( embed_osm_layer.value ) {
					case "cycle":
						tileurl = '.self::CYCLE_URL.';
						break;
					case "transport":
						tileurl = ',self::TRANSPORT_URL.';
						break;
					default:
						tileurl = ',self::MAPNIK_URL.';
				}
				if( map ) {
					zoom = map.getZoom();
					lonLat = map.getCenter().transform(
						map.getProjectionObject(),
						new OpenLayers.Projection( "EPSG:4326" ) );
					lon = lonLat.lon;
					lat = lonLat.lat;
					map.destroy();
				}
				map = new OpenLayers.Map( "defmapdiv" );
				map.addLayer( new OpenLayers.Layer.OSM( "", tileurl ) );
				var lonLat = new OpenLayers.LonLat( lon, lat ).transform(
					new OpenLayers.Projection( "EPSG:4326" ),
					map.getProjectionObject() );
				map.setCenter( lonLat, zoom );
				var center = new OpenLayers.Pixel(
					map.getCurrentSize().w / 2,
					map.getCurrentSize().h / 2 );
				var cross = new OpenLayers.Control.Crosshairs( {
					imgUrl: "'.plugins_url().'/embed-osm/openlayers4jgsi/crosshairs.png",
					size: new OpenLayers.Size( 32, 32 ),
					position: center
				});
				map.addControl( cross ); 
				map.events.register( "moveend", map, embed_osm_moveend2 );
			}

			function embed_osm_getvalue() {
				var lonLat = map.getCenter().transform(
					map.getProjectionObject(),
					new OpenLayers.Projection( "EPSG:4326" ) );
				embed_osm_lat.value = Math.round( lonLat.lat * 100000 ) / 100000;
				embed_osm_lon.value = Math.round( lonLat.lon * 100000 ) / 100000;
				embed_osm_zoom.value = zoom;
			}

			function embed_osm_moveend2() {
				zoom = map.getZoom();
				embed_osm_getvalue();
			}

			function embed_osm_get_cur_pos() {
				if( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition(
						function(pos) {
							lonLat = new OpenLayers.LonLat( pos.coords.longitude, pos.coords.latitude ).transform(
								new OpenLayers.Projection( "EPSG:4326" ),
								map.getProjectionObject() );
							map.setCenter( lonLat, zoom );
						} );
				}
			}

			embed_osm_showmap2();
		</script>';
	}
}

?>
