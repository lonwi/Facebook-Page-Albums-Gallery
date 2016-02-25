<?php

define('FACEBOOK_SDK_V4_SRC_DIR', FACEBOOK_PAGE_ALBUMS_DIR . '/lib/facebook-php-sdk-v4/src/Facebook/');
require_once( FACEBOOK_PAGE_ALBUMS_DIR . '/lib/facebook-php-sdk-v4/autoload.php' );
require_once( FACEBOOK_PAGE_ALBUMS_DIR . '/class-facebook-page-albums-dbmanager.php' );
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookResponse;

class FacebookPageAlbumsAPIManager {
	private $session = null;
	private $page_id = null;
	public $error = array();

	private $db = null;

	public function __construct() {
		$this->init();
	}

	protected function init() {

		if (empty($this->db)) {
			$this->db = new FacebookPageAlbumsDBManager();
		}
		$config = $this->db->get_api_option();
		if (empty($config['appId']) || empty($config['secret'])) {
			return false;
		}
		$this->page_id = $config['pageId'];

		FacebookSession::setDefaultApplication($config['appId'], $config['secret']);
		$this->session = FacebookSession::newAppSession();

		return true;
	}

	public function get($query=null, $params=array()) {
		if (empty($query) ||
			empty($this->session)) {
			return false;
		}

		$slug = $query;
		if (!empty($params)) {
			if (is_array($params)) {
				$params = implode('&', $params);
			}
			$slug .= '?' . $params;
		}

		try {
			$response = (new FacebookRequest($this->session, 'GET', $slug))->execute();
			$results = $response->getResponse();
		} catch (FacebookRequestException $ex) {
			$this->error = $ex->getMessage();
			$results = false;
			error_log($ex);
		} catch (\Exception $ex) {
			$this->error = $ex->getMessage();
			$results = false;
			error_log($ex);
		}

		/** @var FacebookResponse $results*/
		return $results;
	}

	public function get_albums($args=array()) {
		$args = wp_parse_args($args, array(
			'cover_photo' => false,
			'profile' => false,
			'fields' => array(
				'id',
				'name',
				'link',
				'cover_photo',
				'privacy',
				'count',
				'type',
				'created_time',
				'updated_time',
				'can_upload',
				'likes.limit(1).summary(true)',
				'comments.limit(1).summary(true)',
			),
			'after' => null,
			'before' => null,
			'per_page' => 25,
			'paged'    => 1
		));

		$params = array();
		if (!empty($args['per_page'])) {
			$params[] = 'limit=' . $args['per_page'];
			if (!empty($args['paged'])) {
				$params[] = 'offset=' . ($args['paged'] - 1) * $args['per_page'];
			}
		}
		// Fields
		if (!empty($args['fields'])) {
			$params[] = 'fields=' . implode(',', $args['fields']);
		}
		// After
		if (!empty($args['after'])) {
			$params[] = 'after=' . $args['after'];
		}
		// Previous
		if (!empty($args['before'])) {
			$params[] = 'before=' . $args['before'];
		}

		if (!$albums = $this->get('/' . $this->page_id . '/albums', $params)) {
			return false;
		}

		$data = array();
		foreach ($albums->data as $item) {
			
			$item = $this->get_album_data($item);
			
			if ($item['type'] == 'cover' && empty($args['cover_photo'])) {
				continue;
			}
			if ($item['type'] == 'profile' && empty($args['profile'])) {
				continue;
			}

			$data[] = $item;
		}
		
		

		return array(
			'data' => $data,
			'paging' => isset($albums->paging) ? $albums->paging : false
		);
	}

	protected function get_album_data($item) {
		$item = (array) $item;

		// Counts
		$item['likes'] = empty($item['likes']->summary->total_count) ? 0 : $item['likes']->summary->total_count;
		$item['comments'] = empty($item['comments']->summary->total_count) ? 0 : $item['comments']->summary->total_count;
		
		$cover_photo = $item['cover_photo'];
		// Get Cover Photo Data through Facebook API
		if ($cover_id = $cover_photo->id) {
				
			if ($thumb = $this->get('/' . $cover_id, array(
				'fields=link,picture,source,height,width'
			))) {
				$item['cover_photo_data'] = (array) $thumb;
			}
		}

		return $item;
	}

	public function get_album($album_id) {
		$fields = array(
			'id',
			'name',
			'link',
			'cover_photo',
			'privacy',
			'count',
			'type',
			'created_time',
			'updated_time',
			'can_upload',
			'likes.limit(1).summary(true)',
			'comments.limit(1).summary(true)',
		);

		if (!$data = $this->get( '/' . $album_id,  array(
			'fields=' . implode(',', $fields)
		))) {
			return false;
		}

		return $this->get_album_data($data);
	}

	public function get_photos($args=null) {
		$defaults = array(
			'album_id' => null,
			'fields' => array(
				'id',
				'height',
				'width',
				'images',
				'link',
				'picture',
				'source',
				'created_time',
				'updated_time',
				'likes.limit(1).summary(true)',
				'comments.limit(1).summary(true)',
			),
			'per_page' => 25,
			'paged'    => 1
		);
		$args = wp_parse_args($args, $defaults);

		if (empty($args['album_id'])) return false;

		$params = array();
		if (!empty($args['per_page'])) {
			$params[] = 'limit=' . $args['per_page'];
			if (!empty($args['paged'])) {
				$params[] = 'offset=' . ($args['paged'] - 1) * $args['per_page'];
			}
		}
		if (!empty($args['fields'])) {
			$params[] = 'fields=' . implode(',', $args['fields']);
		}

		$photos = $this->get('/' . $args['album_id'] . '/photos', $params);


		$data = array();
		foreach ($photos->data as $item) {
			$item = (array) $item;

			$item['likes'] = empty($item['likes']->summary->total_count) ? 0 : $item['likes']->summary->total_count;
			$item['comments'] = empty($item['comments']->summary->total_count) ? 0 : $item['comments']->summary->total_count;

			$data[] = $item;
		}

		return $data;
	}
}