<?php

if ( !defined( 'IN_MYBB' ) ) {
	die( 'frick off' );
}

error_reporting( 0 );
ini_set( 'display_errors', 0 );

if ( !defined( 'IN_ADMINCP' ) ) {
	$plugins->add_hook( 'global_start', 'iu_init' );
	$plugins->add_hook( 'pre_output_page', 'iu_end' );
} else {
	$plugins->add_hook( 'admin_config_plugins_activate_commit', 'iu_update_stored_templates' );
}

function iu_info() {
	return array(
		'name'			=> 'IU',
		'description'	=> 'Custom Templating System for MyBB',
		'website'		=> 'https://lewd.sx',
		'author'		=> 'Clickbait',
		'authorsite'	=> 'https://twitter.com/clickbaitoce',
		'version'		=> '1.0',
		'compatibility'	=> '18*'
	);
}

function iu_install() {
	global $db, $cache;

	iu_update_stored_templates();

	$cache->delete( 'iu' );
	$cache->update( 'iu', array() );

	// add a new setting group for the plugin
	$new_setting_group = array(
		"name" => "iu",
		'description' => 'iu settings',
		"title" => "IU Settings",
		"disporder" => 1,
		"isdefault" => 0
	);

	$gid = $db->insert_query( 'settinggroups', $new_setting_group );

	$setting = array(
		"name" => "development_mode",
		"title" => "Development mode",
		"description" => "Put IU into development mode so MyBB reads from the template files directly instead of from the cache.",
		"optionscode" => "yesno",
		"disporder" => 1,
		"value" => 0,
		"gid" => $gid
	);

 	$db->insert_query( 'settings', $setting );

	rebuild_settings();
}

function iu_uninstall() {
	global $db, $cache;

	// fetch the settings group id from the settinggroups table
	$query = $db->simple_select( 'settinggroups', 'gid', "name='iu'" );
	$gid = $db->fetch_field( $query, 'gid' );

	// if there's no settings group, stop.
	if ( !$gid ) {
		return;
	}

	// remove all iu settings from the database
	$db->delete_query( 'settinggroups', "name='iu'" );
	$db->delete_query( 'settings', "gid=$gid" );

	$cache->delete( 'iu' );

	rebuild_settings();
}

function iu_is_installed() {
  global $db;

  // check to see whether we have a settings group
  $query = $db->simple_select( 'settinggroups', '*', "name='iu'" );

  if ( $db->num_rows( $query ) ) {
    // if there is, return true
    return true;
  }

  return false;
}

function reload_iu() {
	global $cache;

	$cache->update( 'iu', array() );
}

function iu_init() {
	global $templates, $iu, $cache;

	$templates = new Iu( $cache->read( 'iu' ) );
}

function iu_end( $ok = null ) {
	global $cache, $templates;

	if ( count( $templates->store ) !== count( $templates->old ) ) {
		$cache->update( 'iu', $templates->store );
	}
}

class Iu {
	public $store, $old;

	public function __construct( $store = null ) {
		global $cache;

		if ( is_array( $store ) ) {
			$this->store = $store;
		} else {
			$this->store = array();
		}

		$this->old = $store;
	}

	function cache( $dummy ) {
	  // we want this to do literally nothing
	  // because we don't need to cache stuff
	}

	function get( $template ) {
		global $cache, $mybb;

		if ( !$mybb->settings['development_mode'] ) {
			if ( isset( $this->store[ $template ] ) ) {
				return $this->store[ $template ];
			}
		}

		if ( file_exists( MYBB_ROOT . "inc/plugins/iu/templates/{$template}.mybbtpl" ) ) {
			return $this->fetch( "inc/plugins/iu/templates/{$template}.mybbtpl", $template );
		} else {
			return $this->fetch( "inc/plugins/iu/templates/iu/{$template}.mybbtpl", $template );
		}
	}

	function render( $template) {
		return 'return "' . $this->get( $template ) . '";';
	}

	function fetch($path, $name) {
		global $cache;

		$contents = str_replace( "\\'", "'", addslashes( file_get_contents( MYBB_ROOT . $path ) ) );

		$this->store[ $name ] = $contents;

		return $contents;
	}
}

function iu_update_stored_templates() {
	global $db;

	$query = $db->simple_select( 'templatesets', 'sid', "title='REPLACE THIS LOL'" );
	$default_theme = (Int)$db->fetch_field( $query, 'sid' );

	$query = $db->simple_select( 'templates', "*", "sid='-1' OR sid='{$default_theme}'" );

	while ( $template = $db->fetch_array( $query ) ) {
		$path = MYBB_ROOT . "inc/plugins/iu/templates/{$template['title']}.mybbtpl";

		if ( !file_exists( $path ) ) {
			$file = fopen( $path, 'wb' );
			fwrite( $file, $template['template'] );
			fclose( $file );
		}
	}
}
