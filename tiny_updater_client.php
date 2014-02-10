<?php
/*
Plugin Name: tinyUpdater (client)
Plugin URI: https://github.com/ideag/tiny_updater_client
Description: A set of methods to update WordPress plugin from a custom source, not Plugin Directory. Based on work by Jeremy Clark https://github.com/jeremyclark13/automatic-theme-plugin-update
Version: 0.1
Author: Arūnas Liuiza
Author URI: http://wordofpress.com
*/

if (isset($_GET['force-plugin'])&&$_GET['force-plugin']) {
  set_site_transient('update_plugins', null);
}

$tiny_updater_client = new tiny_updater_client(
  'http://wordofpress.com/api/updates',
  "tiny_updater_client"
);

class tiny_updater_client {
  function __construct($api,$slug=false){
    $this->api_url = $api;
    $this->plugin_slug = $slug?$slug:basename(dirname(__FILE__));
    $this->wp_version = get_bloginfo('version');
    // Take over the update check
    add_filter('pre_set_site_transient_update_plugins', array($this,'check'));
    // Take over the Plugin info screen
    add_filter('plugins_api', array($this,'call'), 10, 3);
  }
  function check($checked_data) {
    if (empty($checked_data->checked))
      return $checked_data;
    $args = array(
      'slug' => $this->plugin_slug,
      'version' => isset($checked_data->checked[$this->plugin_slug .'/'. $this->plugin_slug .'.php'])?$checked_data->checked[$this->plugin_slug .'/'. $this->plugin_slug .'.php']:'0.0',
    );
    $request_string = array(
      'body' => array(
        'action' => 'basic_check', 
        'request' => serialize($args),
        'api-key' => md5(get_bloginfo('url'))
      ),
      'user-agent' => 'WordPress/' . $this->wp_version . '; ' . get_bloginfo('url')
    );
    
    // Start checking for an update
    $raw_response = wp_remote_post($this->api_url, $request_string);
    if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200))
      $response = unserialize($raw_response['body']);
    
    if (is_object($response) && !empty($response)) // Feed the update data into WP updater
      $checked_data->response[$this->plugin_slug .'/'. $this->plugin_slug .'.php'] = $response;
    
    return $checked_data;
  }

  function call($def, $action, $args) {
    
    if (!isset($args->slug) || ($args->slug != $this->plugin_slug))
      return false;
    
    // Get the current version
    $plugin_info = get_site_transient('update_plugins');
    $current_version = $plugin_info->checked[$this->plugin_slug .'/'. $this->plugin_slug .'.php'];
    $args->version = $current_version;
    
    $request_string = array(
        'body' => array(
          'action' => $action, 
          'request' => serialize($args),
          'api-key' => md5(get_bloginfo('url'))
        ),
        'user-agent' => 'WordPress/' . $this->wp_version . '; ' . get_bloginfo('url')
      );
    
    $request = wp_remote_post($this->api_url, $request_string);
    
    if (is_wp_error($request)) {
      $res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
    } else {
      $res = unserialize($request['body']);
      
      if ($res === false)
        $res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
    }
    
    return $res;
  } 
}
?>