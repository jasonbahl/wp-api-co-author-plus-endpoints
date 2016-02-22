<?php
/**
 * Class Name: WP_REST_CoAuthors_AuthorPosts
 * Author: Michael Jacobsen
 * Author URI: https://mjacobsen4dfm.wordpress.com/
 * License: GPL2+
 *
 * CoAuthors_AuthorPosts base class.
 */

class WP_REST_CoAuthors_AuthorPosts extends WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace;

	/**
	 * Associated object type.
	 *
	 * @var string ("post")
	 */
	protected $parent_type = null;

	/**
	 * Base path for post type endpoints.
	 *
	 * @var string
	 */
	protected $parent_base;

	/**
	 * Associated object type.
	 *
	 * @var string ("post")
	 */
	protected $rest_base = null;

	public function __construct( $namespace, $rest_base, $parent_base, $parent_type )
	{
		$this->namespace = $namespace;
		$this->rest_base = $rest_base;
		$this->parent_base = $parent_base;
		$this->parent_type = $parent_type;
	}


	/**
	 * Retrieve guest-authors posts for object.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error, List of co-author objects data on success, WP_Error otherwise
	 */
	public function get_items( $request ) {
		if ( !empty ( $request['parent_id'] ) ) {
			$parent_id = (int) $request['parent_id'];

			//Get the 'author' terms for this post
			$terms = wp_get_object_terms( $parent_id, 'author' );
		}
		else {
			//Get all 'author' terms
			$terms = get_terms('author' );
		}

		foreach ( $terms as $term ) {
			//create a map to look up the metadata in the term->description
			//$searchmap = $this->set_searchmap($term); //Fail: see function

			//Since the co-authors method didn't work, trying regex for the int value of the ID
			$regex = "/\\b(\\d+)\\b/";
			preg_match( $regex, $term->description, $matches );
			$id = $matches[1];

			//Get the post for this 'author' term
			$author_post = get_post( $id );

			// Make sure $author_post is a post and that it is an author
			if ( 'WP_Post' == get_Class( $author_post ) && $author_post->post_type == 'guest-author' ) {
				// Enhance the object attributes for JSON
				$author_post_item = $this->prepare_item_for_response( $author_post, $request );

				if ( is_wp_error( $author_post_item ) ) {
					continue;
				}

				$author_posts[] = $this->prepare_response_for_collection( $author_post_item );
			}
		}

		if ( ! empty( $author_posts ) ) {
			return rest_ensure_response( $author_posts );
		}

		return new WP_Error( 'rest_co_authors_get_posts', __( 'Invalid authors id.' ), array( 'status' => 404 ) );
	}

	/**
	 * Retrieve guest-authors object.
	 * (used by create_item() to immediately confirm creation)
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error, co-authors object data on success, WP_Error otherwise
	 */
	public function get_item( $request ) {
		$co_authors_id = (int) $request['coauthor_id'];
		$id = null;
		$terms = null;
		$author_type = null;

		// See if this request has a parent
		if ( !empty ( $request['parent_id'] ) ) {

			$parent_id = (int) $request['parent_id'];

			//Get the 'author' terms for this post
			$terms = wp_get_object_terms( $parent_id, 'author' );
		}
		else {
			//Get all 'author' terms
			$terms = get_terms( 'author' );
		}

		// Ensure that the request co_authors_id is a co-author
		// if none of its author terms has this ID it is invalid
		foreach ( $terms as $term ) {
			//create a map to look up the metadata in the term->description
			//$searchmap = $this->set_searchmap($term); //Fail: see function

			//Since the $searchmap method didn't work, trying regex for the int value of the ID
			$regex = "/\\b(" . $co_authors_id . ")\\b/";
			preg_match( $regex, $term->description, $matches );
			$id = $matches[1];

			if( !empty( $id ) ) {
				//This id matches the co_authors_id
				break;
			}
		}

		if( !empty( $id ) ) {
			//Get the post for this 'author' term
			$author_post = get_post( $id );

			// Ensure $author_post is a post and that it is an author
			if ( 'WP_Post' == get_Class( $author_post ) || $author_post->post_type == 'guest-author') {
				// Enhance the object attributes for JSON
				$author_post_item = $this->prepare_item_for_response( $author_post, $request );

				if ( is_wp_error( $author_post_item ) ) {
					return new WP_Error( 'rest_co_authors_get_post', __( 'Invalid authors id.' ), array( 'status' => 404 ) );
				}

				if ( !empty( $author_post_item ) ) {
					return rest_ensure_response( $author_post_item );
				}
			}
		}

		return new WP_Error( 'rest_co_authors_get_post', __( 'Invalid authors id.' ), array( 'status' => 404 ) );
	}



	/**
	 * Create a map to search the description field
	 *
	 * $ajax_search_fields was taken from Automattic/Co-Authors-Plus/../co-authors-plus.php
	 *
	 * @param WP_TERM $term
	 * @return array $searchmap
	 */
	public function set_searchmap($term) {
		//This didn't work, some names break the pattern (i.e. "salisbury William S. Salisbury salisbury 87 bsalisbury@pioneerpress.com")
		$ajax_search_fields = array( 'display_name', 'first_name', 'last_name', 'user_login', 'ID', 'user_email' );
		$co_authors_values = explode(' ', $term->description);
		if (count($co_authors_values) == 5) {
			//Sometimes the user doesn't have an email
			//avoid index out of bounds error below
			$co_authors_values[] = null;
		}
		$searchmap = array(
			$ajax_search_fields[0] => $co_authors_values[0],
			$ajax_search_fields[1] => $co_authors_values[1],
			$ajax_search_fields[2] => $co_authors_values[2],
			$ajax_search_fields[3] => $co_authors_values[3],
			$ajax_search_fields[4] => $co_authors_values[4],
			$ajax_search_fields[5] => $co_authors_values[5]
		);
		return $searchmap;
	}

	/**
	 * Prepares co-authors data for return as an object.
	 * Used to prepare the guest-authors object
	 *
	 * @param WP_Post $data guest-authors post_type post row from database
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error, co-authors object data on success, WP_Error otherwise
	 */
	public function prepare_item_for_response( $data, $request ) {
		$author_post = array();

		if ( 'WP_Post' == get_Class( $data )  ) {
			$author_post = array(
				'id'    => (int) $data->ID,
				'post_name'    => (string) $data->post_name,
				'post_type'    => (string) $data->post_type,
				'post_title'    => (string) $data->post_title,
				'post_date'    => (string) $data->post_date
			);
		}

		$response = rest_ensure_response( $author_post );

		/**
		 * Add information links about the object
		 */
		$response->add_link( 'about', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $author_post['id'] ), array( 'embeddable' => true ) );

		/**
		 * Filter a co-authors value returned from the API.
		 *
		 * Allows modification of the co-authors value right before it is returned.
		 *
		 * @param array           $response array of co-authors data: id.
		 * @param WP_REST_Request $request  Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_co_authors_value', $response, $request );
	}

	/**
	 * Check if the data provided is valid data.
	 *
	 * Excludes serialized data from being sent via the API.
	 *
	 * @param mixed $data Data to be checked
	 * @return boolean Whether the data is valid or not
	 */
	protected function is_valid_authors_data( $data ) {
		if ( is_array( $data ) || is_object( $data ) || is_serialized( $data ) ) {
			return false;
		}

		return true;
	}
}