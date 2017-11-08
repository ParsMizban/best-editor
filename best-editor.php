<?php
/**
 * Plugin Name: WordPress Editor
 * Plugin URI: https://dedidata.com.com/wordpress-editor
 * Description: This plugin coverts your WordPress editor to an advanced visual editor
 * Version: 1.5
 * Author: DediData
 * Author URI: https://dedidata.com
 * Requires at least: 4.4
 * Tested up to: 4.9
 *
 * Text Domain: best-editor
 * Domain Path: /languages/
 */

namespace{
	defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

	/**
	 * Class BestEditor
     */
	class BestEditor {

		protected $plugin_name;
		protected $plugin_slug;
		protected $plugin_url;
		protected $plugin_version;
		// wp_adv = toolbar toggle
		public $row1_buttons = array('formatselect','fontselect','fontsizeselect','styleselect','alignleft','aligncenter','alignright','alignjustify','outdent','indent','bullist','numlist');
		public $row2_buttons = array('forecolor','backcolor','fontawesome','link','emoticons', 'codesample','toc','template','wp_page','wp_more','hr','anchor','fullscreen','wp_help','bootstrap');
		public $row3_buttons = array();
		public $row4_buttons = array();
		public $all_buttons;
		public $exception_buttons = array('newdocument','cut','copy','paste','help','visualaid','undo','redo','media','charmap','removeformat','pastetext','blockquote','link','unlink','bold','italic','underline','strikethrough','visualchars','code');
		/* TinyMCE menus :	'Insert'	'File'	'Edit'	'Tools'	'View'	'Table'	'Format'	*/

		function __construct() {
			$this->set_plugin_info();
			register_activation_hook( __FILE__, array( $this , 'activate') );
			register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
			register_uninstall_hook( __FILE__, __CLASS__ . '::uninstall' );
			add_action( 'plugins_loaded', array( $this, 'load_languages' ) );
			spl_autoload_register(array($this, 'autoloader'));
			add_action( 'wp_enqueue_scripts', array( $this, 'load_frontend_scripts' ), 11 );
			if( is_admin() ){
				add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ), 110000000 );
				$this->admin();
			}else{
				$this->run();
			}
		}

		function set_plugin_info(){
			$this->plugin_slug = basename(__FILE__, ".php");
			$this->plugin_url = plugins_url( NULL, __FILE__ );
			if ( ! function_exists( 'get_plugins' ) ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_folder = get_plugins( '/' . plugin_basename( dirname( __FILE__ ) ) );
			$plugin_file = basename( ( __FILE__ ) );
			$this->plugin_version = $plugin_folder[$plugin_file]['Version'];
			$this->plugin_name = $plugin_folder[$plugin_file]['Name'];
		}

		function activate(){

		}

		function deactivate(){

		}

		static function uninstall(){
			delete_option('besteditor');
		}

		function load_languages() {
			load_plugin_textdomain( $this->plugin_slug, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		function autoloader($class){
			$class_file = realpath(dirname( __FILE__ ) . '/inc/classes/' . $class . '.php');
			//var_dump($class_file);
			if(file_exists($class_file)){
				include($class_file);
			}
		}

		function load_frontend_scripts(){
			$fontawesome = get_option($this->plugin_slug)['fontawesome'];
			if($fontawesome === true /* or !isset($fontawesome) */){
				wp_enqueue_style( 'font-awesome' , $this->plugin_url . 'inc/font-awesome/css/font-awesome.min.css', NULL , '4.7.0' );
				//wp_enqueue_script( $this->plugin_slug , $this->plugin_url . '/js/script.js', array(), $this->plugin_version, true );
			}
			$bootstrap = get_option($this->plugin_slug)['bootstrap'];
			$rtl = is_rtl() ? '-rtl' : '';
			$rtl_ext = is_rtl() ? '.rtl.full' : '';
			if($bootstrap === true /* or !isset($bootstrap) */){
				wp_enqueue_style( 'bootstrap' . $rtl , $this->plugin_url . '/inc/bootstrap' . $rtl . '/css/bootstrap' . $rtl_ext . '.min.css', NULL, '3.3.7' );
				wp_enqueue_script( 'bootstrap', $this->plugin_url . '/inc/bootstrap/js/bootstrap.min.js', array(), '3.3.7', true );
			}
		}

		// Styles for Admin
		function load_admin_scripts(){
			//$backend_font = get_option('persianfont')['backend-font'];
			/*
			if(!is_rtl()){
				wp_enqueue_style( $this->plugin_slug , $this->plugin_url . '/css/admin.css', NULL, $this->plugin_version );
			}else{
				wp_enqueue_style( $this->plugin_slug , $this->plugin_url . '/css/admin-rtl.css', NULL, $this->plugin_version );
			}
			*/

			// Load Font-Awesome
			wp_enqueue_style( 'font-awesome', $this->plugin_url . '/inc/font-awesome/css/font-awesome.min.css', NULL , '4.7.0' );

			$rtl_ext = is_rtl() ? '.rtl.full' : '';
			$rtl = is_rtl() ? '-rtl' : '';
			// Load editor styles for both rtl and ltr
			add_editor_style( array(
					'css/editor-style.css',
					$this->plugin_url . '/inc/font-awesome/css/font-awesome.min.css',
					$this->plugin_url . '/inc/bootstrap' . $rtl . '/css/bootstrap'. $rtl_ext .'.min.css',
				)
			);
		}

		function admin(){
			add_action('admin_head', array($this, 'add_buttons'));
			include( plugin_dir_path( __FILE__ ) . 'options.php');
		}

		function add_buttons(){
			global $typenow;
			// We check that the user has edit posts / pages
			if ( !current_user_can('edit_posts') && !current_user_can('edit_pages') ) {
				return;
			}
			// We verify the type of post
			if( ! in_array( $typenow, array( 'post', 'page' ) ) )
				return;
			// We check that the user has WYSIWYG enabled
			if ( get_user_option('rich_editing') == 'true') {
				add_filter('mce_external_plugins', array($this, 'add_plugins_to_tinymce'));
			}
			$this->all_buttons = array_merge($this->row1_buttons, $this->row2_buttons, $this->row3_buttons, $this->row4_buttons, $this->exception_buttons);
			add_filter( 'mce_buttons', array( $this, 'mce_buttons_1' ), 999999, 2 );
			add_filter( 'mce_buttons_2', array( $this, 'mce_buttons_2' ), 9999999 );
			//add_filter( 'mce_buttons_3', array( $this, 'mce_buttons_3' ), 999 );
			//add_filter( 'mce_buttons_4', array( $this, 'mce_buttons_4' ), 999 );

			add_filter('tiny_mce_before_init', array($this, 'tinymce_init'));
		}

		function tinymce_init( $init ) {
			if(isset($init['extended_valid_elements'])){
				$init['extended_valid_elements'] .= ',i[style|id|name|class|lang],span[style|id|name|class|lang]';
			}else{
				$init['extended_valid_elements'] = 'i[style|id|name|class|lang],span[style|id|name|class|lang]';
			}

			$init['image_advtab'] = true;
			$init['menubar'] = true;
			$init['wordpress_adv_hidden'] = false; // disable advanced row
			$init['fontsize_formats'] =  '8px 10px 12px 14px 16px 20px 24px 28px 32px 36px 48px 60px 72px 96px';
			$init['paste_data_images'] = true;
			$init['contextmenu'] = 'copy cut paste selectall link media';
			//$init['font_formats'] = 'Lato=Lato;Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;Wingdings=wingdings,zapf dingbats';
			$init['importcss_append'] = true;
			$init['templates'] = "[
				{title: 'Alert Success', description: 'Insert a success alert message box', url: '" . plugins_url( 'plugins/template/templates/alert-success.html', __FILE__ ) . "'},
				{title: 'Alert Info', description: 'Insert an info alert message box', url: '" . plugins_url( 'plugins/template/templates/alert-info.html', __FILE__ ) . "'},
				{title: 'Alert Warning', description: 'Insert a warning alert message box', url: '" . plugins_url( 'plugins/template/templates/alert-warning.html', __FILE__ ) . "'},
				{title: 'Alert Danger', description: 'Insert a danger alert message box', url: '" . plugins_url( 'plugins/template/templates/alert-danger.html', __FILE__ ) . "'},
			]";
			$init['verify_html'] = false;
			//$init['noneditable_noneditable_class'] = 'fa';


			/*
			// Add new styles to the TinyMCE "formats" menu dropdown
			// Create array of new styles
			$new_styles = array(
				array(
					'title'	=> __( 'Custom Styles', 'wpex' ),
					'items'	=> array(
						array(
							'title'		=> __('Theme Button','wpex'),
							'selector'	=> 'a',
							'classes'	=> 'theme-button'
						),
						array(
							'title'		=> __('Highlight','wpex'),
							'inline'	=> 'span',
							'classes'	=> 'text-highlight',
						),
					),
				),
			);
			// Merge old & new styles
			$init['style_formats_merge'] = true;
			// Add new styles
			$init['style_formats'] = json_encode( $new_styles );
			*/
			return $init;
		}

		public function mce_buttons_1( $original, $editor_id ) {
			$buttons_1 = $this->row1_buttons;
			if ( is_array( $original ) && ! empty( $original ) ) {
				$original = array_diff( $original, $this->all_buttons );
				$buttons_1 = array_merge( $buttons_1, $original );
			}
			return $buttons_1;
		}

		public function mce_buttons_2( $original ) {
			$buttons_2 = $this->row2_buttons;
			if ( is_array( $original ) && ! empty( $original ) ) {
				$original = array_diff( $original, $this->all_buttons );
				$buttons_2 = array_merge( $buttons_2, $original );
			}
			return $buttons_2;
		}

		function add_plugins_to_tinymce($plugin_array){
			$active_plugins = array('fontawesome', 'visualchars', 'advlist', 'anchor', 'code', 'contextmenu', 'emoticons', 'importcss', 'insertdatetime',
												'print', 'searchreplace', 'table', 'visualblocks', 'autolink', 'autoresize', 'codesample', 'preview', 'template', 'toc', 'noneditable', 'bootstrap');
			foreach($active_plugins as $active_plugin){
				$plugin_array[$active_plugin] = plugins_url( 'plugins/' . $active_plugin . '/plugin.js', __FILE__ );
			}
			return $plugin_array;
		}

		function run(){
		}
	}
	new BestEditor;

	/*
	function fb_mce_before_init( $settings ) {
		$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		//$jsonContent = $serializer->deserialize($data, 'json');
		//echo $jsonContent; // or return it in a Response

		$formats['bootstrap'] = array (
			//'selector' => 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li',
			'block' => 'div',
			'wrapper' => true,
			'classes' => 'fa',
			'merge_siblings' => false,
		);

		$settings['formats'] = $json->encodeUnsafe( array_merge( $json->decode($settings['formats']), $formats ) );
		return $settings;
	}
	add_filter( 'tiny_mce_before_init', 'fb_mce_before_init' );
	*/
}
