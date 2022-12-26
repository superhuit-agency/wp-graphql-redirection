<?php

namespace WPGraphQLRedirection\Model;

use Red_Item;
use Exception;
use WPGraphQL\Model\Model;

/**
 * Class RedirectionItem - Models the data for redirection items
 *
 * @property int    $code
 * @property string $target
 * @property string $url
 *
 * @package WPGraphQLRedirection\Model
 */
class RedirectionItem extends Model {

	/**
	 * Stores the incoming redirection item to be modeled
	 *
	 * @var Red_Item $item
	 */
	protected $data;

	/**
	 * Source uri to redirect from.
	 */
	private $uri;

	/**
	 * Redirection Item constructor.
	 *
	 * @param array $item The incoming redirection item to be modeled
	 *
	 * @throws \Exception Throws Exception.
	 */
	public function __construct( $item, $uri = null ) {
		$this->data = $item;
		$this->uri = $uri;
		parent::__construct();
	}

	/**
	 * Initializes the object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'code' => function() {
					return $this->data->get_action_code();
				},
				'target' => function() {
					return $this->data->get_action_data();
				},
				'url' => function() {
					return $this->data->get_url();
				},
			];

		}
	}
}
