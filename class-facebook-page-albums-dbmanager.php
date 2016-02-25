<?php

class FacebookPageAlbumsDBManager {
	protected $api_config = 'facebook_page_albums_api_config';

	public function __construct() {
	}

	public function initialize() {
	}

	public function destroy() {
		delete_option( $this->api_config );
	}

	public function get_api_option() {
		$options = get_option( $this->api_config );

		return wp_parse_args( $options, array(
			'appId'     => '',
			'secret'  => '',
			'pageId'  => ''
			) );
	}

	public function set_api_option( $args=array() ) {
		$defaults = array(
			'appId'     => '',
			'secret'  => '',
			'pageId'  => ''
		);
		$args = wp_parse_args( $args, $defaults );
		return update_option( $this->api_config, $args );
	}
}