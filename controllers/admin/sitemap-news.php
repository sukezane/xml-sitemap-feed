<?php

class XMLSF_Admin_Sitemap_News extends XMLSF_Admin_Controller
{
	/**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
		// META
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_metadata' ) );

		// SETTINGS
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		// advanced tab options
		add_action( 'xmlsf_news_settings_advanced_before', 'xmlsf_news_section_advanced_intro' );
		add_action( 'xmlsf_news_add_settings', array( $this, 'advanced_settings_fields' ) );

		// TOOLS ACTIONS
		add_action( 'admin_init', array( $this, 'tools_actions' ) );
    }

	/**
	* TOOLS ACTIONS
	*/

	public function tools_actions()
	{
		if ( isset( $_POST['xmlsf-ping-sitemap-news'] ) ) {
			if ( isset( $_POST['_xmlsf_help_nonce'] ) && wp_verify_nonce( $_POST['_xmlsf_help_nonce'], XMLSF_BASENAME.'-help' ) ) {

				$sitemaps = get_option( 'xmlsf_sitemaps' );
				$result = xmlsf_ping( 'google', $sitemaps['sitemap-news'], 5 * MINUTE_IN_SECONDS );

				switch( $result ) {
					case 200:
					$msg = sprintf( /* Translators: Search engine / Service name */ __( 'Pinged %s with success.', 'xml-sitemap-feed' ), __( 'Google News', 'xml-sitemap-feed' ) );
					$type = 'updated';
					break;

					case 999:
					$msg = sprintf( /* Translators: Search engine / Service name, interval number */ __( 'Ping %s skipped: Sitemap already sent within the last %d minutes.', 'xml-sitemap-feed' ), __( 'Google News', 'xml-sitemap-feed' ), 5 );
					$type = 'notice-warning';
					break;

					case '':
					$msg = sprintf( translate('Oops: %s'), translate('Something went wrong.') );
					$type = 'error';
					break;

					default:
					$msg = sprintf( /* Translators: Search engine / Service name, response code number */ __( 'Ping %s failed with response code: %d', 'xml-sitemap-feed' ), __( 'Google News', 'xml-sitemap-feed' ), $result );
					$type = 'error';
				}

				add_settings_error( 'ping_sitemap', 'ping_sitemap', $msg, $type );

			} else {
				add_settings_error( 'ping_sitemap', 'ping_sitemap', translate('Security check failed.') );
			}
		}
	}

	/**
	* META BOXES
	*/

	/* Adds a News Sitemap box to the side column */
	public function add_meta_box()
	{
		$news_tags = get_option('xmlsf_news_tags');
		$news_post_types = !empty($news_tags['post_type']) && is_array($news_tags['post_type']) ? $news_tags['post_type'] : array('post');

		// Only include metabox on post types that are included
		foreach ( $news_post_types as $post_type ) {
			add_meta_box(
				'xmlsf_news_section',
				__( 'Google News', 'xml-sitemap-feed' ),
				array( $this, 'meta_box' ),
				$post_type,
				'side'
			);
		}
	}

	public function meta_box( $post )
	{
		// Use nonce for verification
		wp_nonce_field( XMLSF_BASENAME, '_xmlsf_news_nonce' );

		// Use get_post_meta to retrieve an existing value from the database and use the value for the form
		$exclude = 'private' == $post->post_status || get_post_meta( $post->ID, '_xmlsf_news_exclude', true );
		$disabled = 'private' == $post->post_status;

		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/meta-box-news.php';
	}

	/* When the post is saved, save our meta data */
	public function save_metadata( $post_id )
	{
		if ( !isset($post_id) )
			$post_id = (int)$_REQUEST['post_ID'];

		if ( !current_user_can( 'edit_post', $post_id ) || !isset($_POST['_xmlsf_news_nonce']) || !wp_verify_nonce($_POST['_xmlsf_news_nonce'], XMLSF_BASENAME) )
			return;

		// _xmlsf_news_exclude
		if ( empty($_POST['xmlsf_news_exclude']) )
			delete_post_meta($post_id, '_xmlsf_news_exclude');
		else
			update_post_meta($post_id, '_xmlsf_news_exclude', $_POST['xmlsf_news_exclude']);
	}

	/**
	* SETTINGS
	*/

	/**
     * Add options page
     */
    public function add_settings_page()
	{
        // This page will be under "Settings"
        $screen_id = add_options_page(
			__('Google News Sitemap','xml-sitemap-feed'),
            __('Google News','xml-sitemap-feed'),
            'manage_options',
            'xmlsf_news',
            array( $this, 'settings_page' )
        );

		// Help tab
		add_action( 'load-'.$screen_id, array( $this, 'help_tab' ) );
    }

    /**
     * Options page callback
     */
    public function settings_page()
    {
		$this->options = get_option( 'xmlsf_news_tags', array() );

		// GENERAL SECTION
		add_settings_section( 'news_sitemap_general_section', /* '<a name="xmlnf"></a>'.__('Google News Sitemap','xml-sitemap-feed') */ '', '', 'xmlsf_news_general' );

		// SETTINGS
		add_settings_field( 'xmlsf_news_name', '<label for="xmlsf_news_name">'.__('Publication name','xml-sitemap-feed').'</label>', array($this,'name_field'), 'xmlsf_news_general', 'news_sitemap_general_section' );
		add_settings_field( 'xmlsf_news_post_type', __('Post type','xml-sitemap-feed'), array($this,'post_type_field'), 'xmlsf_news_general', 'news_sitemap_general_section' );

		global $wp_taxonomies;
		$news_post_type = isset( $this->options['post_type'] ) && !empty( $this->options['post_type'] ) ? (array) $this->options['post_type'] : array('post');
		$post_types = ( isset( $wp_taxonomies['category'] ) ) ? $wp_taxonomies['category']->object_type : array();

		foreach ( $news_post_type as $post_type ) {
			if ( in_array( $post_type, $post_types ) ) {
				add_settings_field( 'xmlsf_news_categories', translate('Categories'), array($this,'categories_field'), 'xmlsf_news_general', 'news_sitemap_general_section' );
				break;
			}
		}

		// Images
		add_settings_field( 'xmlsf_news_image', translate('Images'), array( $this,'image_field' ), 'xmlsf_news_general', 'news_sitemap_general_section' );

		// Source labels - deprecated
		add_settings_field( 'xmlsf_news_labels', __('Source labels', 'xml-sitemap-feed' ), array($this,'labels_field'), 'xmlsf_news_general', 'news_sitemap_general_section' );

		// ADVANCED SECTION
		add_settings_section( 'news_sitemap_advanced_section', /* '<a name="xmlnf"></a>'.__('Google News Sitemap','xml-sitemap-feed') */ '', '', 'xmlsf_news_advanced' );

		do_action('xmlsf_news_add_settings');

		$options = (array) get_option( 'xmlsf_sitemaps' );
		$url = trailingslashit(get_bloginfo('url')) . ( xmlsf()->plain_permalinks() ? '?feed=sitemap-news' : $options['sitemap-news'] );

		$active_tab = isset( $_GET[ 'tab' ] ) ? $_GET[ 'tab' ] : 'general';

		include XMLSF_DIR . '/views/admin/page-sitemap-news.php';
	}

    /**
     * Add advanced settings
     */
    public function advanced_settings_fields()
	{
		// Keywords
		add_settings_field( 'xmlsf_news_keywords', __('Keywords', 'xml-sitemap-feed' ), array( $this,'keywords_field' ), 'xmlsf_news_advanced', 'news_sitemap_advanced_section' );

		// Stock tickers
		add_settings_field( 'xmlsf_news_stock_tickers', __('Stock tickers', 'xml-sitemap-feed' ), array( $this,'stock_tickers_field' ), 'xmlsf_news_advanced', 'news_sitemap_advanced_section' );
	}

	/**
	 * Register settings
	 */
	public function register_settings()
    {
		register_setting( 'xmlsf_news_general', 'xmlsf_news_tags', array('XMLSF_Admin_Sitemap_News_Sanitize','news_tags_settings') );
    }

	/**
	* GOOGLE NEWS SITEMAP SECTION
	*/

	public function help_tab() {
		$screen = get_current_screen();

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-settings',
			'title'   => __( 'Google News Sitemap', 'xml-sitemap-feed' ),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-name.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-name',
			'title'   => __( 'Publication name', 'xml-sitemap-feed' ),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-categories.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-categories',
			'title'   => translate('Categories'),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-images.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-images',
			'title'   => translate('Images'),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-keywords.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-keywords',
			'title'   => __( 'Keywords', 'xml-sitemap-feed' ),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-stocktickers.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-stocktickers',
			'title'   => __( 'Stock tickers', 'xml-sitemap-feed' ),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-labels.php';
		include XMLSF_DIR . '/views/admin/help-tab-support.php';
		$content = ob_get_clean();

		$screen->add_help_tab( array(
			'id'      => 'sitemap-news-labels',
			'title'   => __( 'Source labels', 'xml-sitemap-feed' ),
			'content' => $content
		) );

		ob_start();
		include XMLSF_DIR . '/views/admin/help-tab-news-sidebar.php';
		$content = ob_get_clean();

		$screen->set_help_sidebar( $content );
	}

	public function name_field()
	{
		$name = !empty($this->options['name']) ? $this->options['name'] : '';

		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/field-news-name.php';
	}

	public function post_type_field()
	{
		global $wp_taxonomies;
		$post_types = apply_filters( 'xmlsf_news_post_types', get_post_types( array( 'public' => true ) /*,'objects'*/) );

		if ( is_array($post_types) && !empty($post_types) ) :

			$news_post_type = isset($this->options['post_type']) && !empty( $this->options['post_type'] ) ? (array) $this->options['post_type'] : array('post');

			$type = apply_filters( 'xmlsf_news_post_type_field_type', 1 == count( $news_post_type ) ? 'radio' : 'checkbox' );

			$allowed = ( !empty( $this->options['categories'] ) && isset( $wp_taxonomies['category'] ) ) ? $wp_taxonomies['category']->object_type : $post_types;

			$do_warning = !empty( $this->options['categories'] ) && count($post_types) > 1 ? true : false;

			// The actual fields for data entry
			include XMLSF_DIR . '/views/admin/field-news-post-type.php';

		else :

			echo '<p class="description warning">'.__('There appear to be no post types available.','xml-sitemap-feed').'</p>';

		endif;
	}

	public function categories_field()
	{
		$selected_categories = isset( $this->options['categories'] ) && is_array( $this->options['categories'] ) ? $this->options['categories'] : array();

		$cat_list = str_replace('name="post_category[]"','name="'.'xmlsf_news_tags[categories][]"', wp_terms_checklist( null, array( 'taxonomy' => 'category', 'selected_cats' => $selected_categories, 'echo' => false ) ) );

		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/field-news-categories.php';
	}

	public function image_field() {
		$image = !empty( $this->options['image'] ) ? $this->options['image'] : '';

		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/field-news-image.php';
	}

	public function keywords_field() {
		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/field-news-keywords.php';
	}

	public function stock_tickers_field() {
		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/field-news-stocktickers.php';
	}

	public function labels_field() {
		// The actual fields for data entry
		include XMLSF_DIR . '/views/admin/field-news-labels.php';
	}

}

new XMLSF_Admin_Sitemap_News();

function xmlsf_news_section_advanced_intro() {
	include XMLSF_DIR . '/views/admin/section-advanced-intro.php';
}
