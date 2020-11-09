<?php
/**
 * BuddyBoss Moderation Forum Replies Classes
 *
 * @since   BuddyBoss 2.0.0
 * @package BuddyBoss\Moderation
 *
<<<<<<< HEAD
=======
 * @since   BuddyBoss 2.0.0
>>>>>>> feature/moderation
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Database interaction class for the BuddyBoss moderation Forum Replies.
 *
 * @since BuddyBoss 2.0.0
 */
class BP_Moderation_Forum_Replies extends BP_Moderation_Abstract {

	/**
	 * Item type
	 *
	 * @var string
	 */
	public static $moderation_type = 'forum_reply';

	/**
	 * BP_Moderation_Group constructor.
	 *
	 * @since BuddyBoss 2.0.0
	 */
	public function __construct() {

		parent::$moderation[ self::$moderation_type ] = self::class;
		$this->item_type                              = self::$moderation_type;

		add_filter( 'bp_moderation_content_types', array( $this, 'add_content_types' ) );

		/**
		 * Moderation code should not add for WordPress backend & IF component is not active
		 */
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! bp_is_active( 'forums' ) ) {
			return;
		}

		$this->alias = $this->alias . 'fr'; // fr: Forum Reply.

		add_filter( 'posts_join', array( $this, 'update_join_sql' ), 10, 2 );
		add_filter( 'posts_where', array( $this, 'update_where_sql' ), 10, 2 );

		add_filter( 'bp_forum_reply_search_join_sql', array( $this, 'update_join_sql' ), 10 );
		add_filter( 'bp_forum_reply_search_where_sql', array( $this, 'update_where_sql' ), 10 );


		// button class.
		add_filter( 'bp_moderation_get_report_button_class', array( $this, 'update_button_class' ), 10, 3 );

		// Blocked template
		add_filter( 'bbp_locate_template_names', array( $this, 'locate_blocked_template' ) );
	}

	/**
	 * Add Moderation content type.
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param array $content_types Supported Contents types.
	 *
	 * @return mixed
	 */
	public function add_content_types( $content_types ) {
		$content_types[ self::$moderation_type ] = __( 'Replies', 'buddyboss' );

		return $content_types;
	}

	/**
	 * Prepare Forum Replies Join SQL query to filter blocked Forum Replies
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param string $join_sql Forum Replies Join sql.
	 * @param object $wp_query WP_Query object.
	 *
	 * @return string Join sql
	 */
	public function update_join_sql( $join_sql, $wp_query = null ) {
		global $wpdb;
		$action_name = current_filter();

		if ( 'bp_forum_reply_search_join_sql' === $action_name ) {
			$join_sql .= $this->exclude_joint_query( 'p.ID' );
		} else {
			if ( false !== $wp_query->get( 'moderation_query' ) ) {
				$reply_slug = bbp_get_reply_post_type();
				$post_types = wp_parse_slug_list( $wp_query->get( 'post_type' ) );
				if ( ! empty( $post_types ) && in_array( $reply_slug, $post_types, true ) ) {
					$join_sql .= $this->exclude_joint_query( "{$wpdb->posts}.ID" );
				}
			}
		}

		return $join_sql;
	}

	/**
	 * Prepare Forum Replies Where SQL query to filter blocked Forum Replies
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param string $where_conditions Forum Replies Where sql.
	 * @param object $wp_query         WP_Query object.
	 *
	 * @return mixed Where SQL
	 */
	public function update_where_sql( $where_conditions, $wp_query = null ) {

		$action_name = current_filter();

		if ( 'bp_forum_reply_search_where_sql' !== $action_name ) {
			$reply_slug = bbp_get_reply_post_type();
			$post_types = wp_parse_slug_list( $wp_query->get( 'post_type' ) );
			if ( false === $wp_query->get( 'moderation_query' ) || empty( $post_types ) || ! in_array( $reply_slug, $post_types, true ) ) {
				return $where_conditions;
			}
		}

		$where                        = array();
		$where['forum_replies_where'] = $this->exclude_where_query();

		/**
		 * Exclude block member forum replies [ it'll Show placeholder for blocked content everywhere except search ]
		 */
		if ( 'bp_forum_reply_search_where_sql' === $action_name ) {
			$members_where = $this->exclude_member_reply_query();
			if ( $members_where ) {
				$where['members_where'] = $members_where;
			}
		}

		/**
		 * Exclude block Topic replies
		 */
		$topics_where = $this->exclude_topic_reply_query();
		if ( $topics_where ) {
			$where['topics_where'] = $topics_where;
		}

		/**
		 * Filters the Forum Replies Moderation Where SQL statement.
		 *
		 * @since BuddyBoss 2.0.0
		 *
		 * @param array $where array of Forum Replies moderation where query.
		 */
		$where = apply_filters( 'bp_moderation_forum_replies_get_where_conditions', $where );

		if ( 'bp_forum_reply_search_where_sql' === $action_name ) {
			$where_conditions['moderation_query'] = '( ' . implode( ' AND ', $where ) . ' )';
		} else {
			$where_conditions .= ' AND ( ' . implode( ' AND ', $where ) . ' )';
		}

		return $where_conditions;
	}

	/**
	 * Function to modify the button class
	 *
	 * @param string $button_class button class.
	 * @param bool   $is_reported  is content reported.
	 * @param string $item_type    content type.
	 *
	 * @return string
	 */
	public function update_button_class( $button_class, $is_reported, $item_type ) {

		if ( ! empty( $item_type ) && $this->item_type === $item_type && true === $is_reported ) {
			$button_class = 'reported-content';
		} elseif ( ! empty( $item_type ) && $this->item_type === $item_type ) {
			$button_class = 'report-content';
		}

		return $button_class;
	}

	/**
	 * Get SQL for Exclude Blocked Members related replies
	 *
	 * @return string|bool
	 */
	private function exclude_member_reply_query() {
		global $wpdb;
		$sql                = false;
		$action_name        = current_filter();
		$hidden_members_ids = BP_Moderation_Members::get_sitewide_hidden_ids();
		if ( ! empty( $hidden_members_ids ) ) {
			$reply_alias = ( 'bp_forum_reply_search_where_sql' === $action_name ) ? 'p' : $wpdb->posts;
			$sql         = "( {$reply_alias}.post_author NOT IN ( " . implode( ',', $hidden_members_ids ) . ' ) )';
		}

		return $sql;
	}

	/**
	 * Get SQL for Exclude Blocked topic related replies
	 *
	 * @return string|bool
	 */
	private function exclude_topic_reply_query() {
		global $wpdb;
		$sql              = false;
		$action_name      = current_filter();
		$hidden_topic_ids = BP_Moderation_Forum_Topics::get_sitewide_hidden_ids();
		if ( ! empty( $hidden_topic_ids ) ) {
			$reply_alias = ( 'bp_forum_reply_search_where_sql' === $action_name ) ? 'p' : $wpdb->posts;
			$sql         = "( {$reply_alias}.post_parent NOT IN ( " . implode( ',', $hidden_topic_ids ) . ' ) )';
		}

		return $sql;
	}

	/**
	 * Update blocked comment template
	 *
	 * @param string $template_names Template name.
	 *
	 * @return string
	 */
	public function locate_blocked_template( $template_names ) {

		if ( 'loop-single-reply.php' !== $template_names ) {
			if ( ! is_array( $template_names ) || ! in_array( 'loop-single-reply.php', $template_names, true ) ) {
				return $template_names;
			}
		}

		$reply_id        = bbp_get_reply_id();
		$reply_author_id = bbp_get_reply_author_id( $reply_id );

		if ( in_array( $reply_id, self::get_sitewide_hidden_ids(), true ) ||
		     bp_moderation_is_user_suspended( $reply_author_id, true ) ) {
			return 'loop-blocked-single-reply.php';
		}

		return $template_names;
	}

	/**
	 * Get blocked Replies that also include Blocked forum & topic replies
	 *
	 * @return array
	 */
	public static function get_sitewide_hidden_ids() {
		$hidden_reply_ids = self::get_sitewide_hidden_item_ids( self::$moderation_type );

		$hidden_topic_ids = BP_Moderation_Forum_Topics::get_sitewide_hidden_ids();
		if ( ! empty( $hidden_topic_ids ) ) {
			$replies_query = new WP_Query(
				array(
					'fields'                 => 'ids',
					'post_type'              => bbp_get_reply_post_type(),
					'post_status'            => 'publish',
					'post_parent__in'        => $hidden_topic_ids,
					'posts_per_page'         => - 1,
					// Need to get all topics id of hidden forums.
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				)
			);

			if ( $replies_query->have_posts() ) {
				$hidden_reply_ids = array_merge( $hidden_reply_ids, $replies_query->posts );
			}
		}

		return $hidden_reply_ids;
	}

	/**
	 * Get Content owner id.
	 *
	 * @param integer $reply_id Reply id.
	 *
	 * @return int
	 */
	public static function get_content_owner_id( $reply_id ) {
		return get_post_field( 'post_author', $reply_id );
	}

	/**
	 * Get Content.
	 *
	 * @param integer $reply_id Reply id.
	 *
	 * @return string
	 */
	public static function get_content_excerpt( $reply_id ) {
		$reply_content = get_post_field( 'post_content', $reply_id );

		return ( ! empty( $reply_content ) ) ? $reply_content : '';
	}

	/**
	 * Report content
	 *
	 * @since BuddyBoss 2.0.0
	 *
	 * @param array $args Content data.
	 *
	 * @return string
	 */
	public static function report( $args ) {
		return parent::report( $args );
	}

}
