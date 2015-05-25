<?php

class WPML_Compatibility_Test_Tools extends WPML_Compatibility_Test_Tools_Base {

	public function __construct() {

		parent::__construct();

		add_action( 'init', array( $this, 'init' ) );
	}

	public function init(){

        //generate XML
        if (isset($_GET['page']) == WPML_CTT_FOLDER . '/menus/settings/generator.php' && isset($_POST['submit'])) {
            add_action('wp_loaded', array($this, 'generate_xml'));
        }


		//check for WPML
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) || ICL_PLUGIN_INACTIVE ) {
			if ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) {
				add_action( 'admin_notices', array( $this->messages, 'no_wpml_notice' ) );
			}
			return false;
		}

		//check for Translation Management
		if( ! defined( 'WPML_TM_VERSION' ) ){
			add_action( 'admin_notices', array( $this->messages, 'no_tm_notice' ) );
			return false;
		}

		//check for String Translation
		if( ! defined( 'WPML_ST_VERSION' ) ){
			add_action( 'admin_notices', array( $this->messages, 'no_st_notice' ) );
			return false;
		}

		//WPML setup has to be finished
		global $sitepress;
		if( !isset( $sitepress ) ){
			add_action( 'admin_notices', array( $this->messages, 'no_wpml_notice' ) );
			return false;
		}

		if( method_exists( $sitepress, 'get_setting' ) && !$sitepress->get_setting( 'setup_complete' ) ) {
			add_action( 'admin_notices', array( $this->messages, 'not_finished_wpml_setup' ) );
			return false;
		}

		self::install();

		add_action( 'admin_menu', array( $this, 'register_administration_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'add_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'add_styles'  ) );

		//handle admin settings page
		$this->process_request();
		//change WPML behaviour based on selected settings
		$this->modify_wpml_behaviour();

		return true;
	}

	/**
	 * Process admin settings page requests
	 *
	 * @return bool
	 */
	public function process_request() {

		$this->process_strings_auto_translate_action_translate();
		$this->process_save_duplicate_strings_to_translate();

		return true;
	}

	/**
	 * Process action strings_auto_translate_action_translate
	 *
	 * @return bool
	 */
	private function process_strings_auto_translate_action_translate(){

		if ( isset( $_POST['strings_auto_translate_action_save'] ) || isset( $_POST['strings_auto_translate_action_translate'] )  ) {

			$error = false;

			$context   = ( isset( $_POST['strings_auto_translate_context'] ) ) ? $_POST['strings_auto_translate_context'] : '';
			$languages = ( isset( $_POST['active_languages'] ) ) ? $_POST['active_languages'] : array();
			$template  = ( isset( $_POST['strings_auto_translate_template'] ) ) ? $_POST['strings_auto_translate_template'] : '';

			if ( empty( $template ) ) {
				add_action( 'admin_notices', array( $this->messages, 'no_template_notice' ) );
				$error = true;
			}

			if ( $error ) {
				return false;
			}

			self::update_option('string_auto_translate_context', $context);
			self::update_option('string_auto_translate_languages', $languages);
			self::update_option('string_auto_translate_template', $template);

			add_action( 'admin_notices', array( $this->messages, 'settings_updated_notice' ) );

			if ( isset( $_POST['strings_auto_translate_action_translate'] ) ) {

				$context   = self::get_option('string_auto_translate_context');
				$languages = self::get_option('string_auto_translate_languages');
				$template  = self::get_option('string_auto_translate_template');


				if ( empty( $languages ) ) {
					add_action( 'admin_notices', array( $this->messages, 'no_selected_language_notice' ) );
					$error = true;
				}

				if ( empty( $context ) ) {
					add_action( 'admin_notices', array( $this->messages, 'no_context_notice' ) );
					$error = true;
				}

				if ( empty( $template ) ) {
					add_action( 'admin_notices', array( $this->messages, 'no_template_notice' ) );
					$error = true;
				}

				if ( $error ) {
					return false;
				}

				$this->translate_strings( $context, $languages, $template );
				add_action( 'admin_notices', array( $this->messages, 'strings_translated_notice' ) );

			}

		}

		return true;

	}

	/**
	 * Auto translate strings with given context
	 *
	 * @param $context
	 * @param $languages
	 * @param $template
	 */
	private function translate_strings( $context, $languages, $template ) {
		global $wpdb;

		//get all not translated strings (status <> 1)
		if ( 0 === strcmp( $context, 'all_contexts' ) ) {
			$strings = $wpdb->get_results( "SELECT id, language, context, value FROM {$wpdb->prefix}icl_strings" );
		} else {
			$strings = $wpdb->get_results( $wpdb->prepare( "SELECT id, language, context, value FROM {$wpdb->prefix}icl_strings WHERE context=%s", $context ) );
		}

		//for each string add information
		foreach ( $strings as $v ) {
			foreach ( $languages as $lang ) {
				icl_add_string_translation( $v->id, $lang, wpml_ctt_prepare_string($template, $v->value, $lang), ICL_STRING_TRANSLATION_COMPLETE );
				icl_update_string_status( $v->id );
			}

		}

	}

	/**
	 * Process action save_duplicate_strings_to_translate
	 */
	private function process_save_duplicate_strings_to_translate(){

		if ( isset( $_POST['save_duplicate_strings_to_translate'] ) ) {

			$error = false;

			$strings = ( isset( $_POST['duplicate_strings_to_translate'] ) ) ? $_POST['duplicate_strings_to_translate'] : array();
			$template = ( isset( $_POST['duplicate_strings_template'] ) ) ? $_POST['duplicate_strings_template'] : '';

			if ( empty( $template ) ) {
				add_action( 'admin_notices', array( $this->messages, 'no_template_notice' ) );
				$error = true;
			}

			if ( $error ) {
				return false;
			}

			self::update_option('duplicate_strings', $strings);
			self::update_option('duplicate_strings_template', $template);

			if ( !empty( $strings ) ){
				add_action( 'admin_notices', array( $this->messages, 'duplicate_strings_available' ) );
			}
			else{
				add_action( 'admin_notices', array( $this->messages, 'settings_updated_notice' ) );
			}

		}

		return true;

	}

	/**
	 * Modify WPML behaviour based on selected settings
	 */
	public function modify_wpml_behaviour() {

		//Enable adding language information for duplicated posts
		$duplicate_strings = self::get_option('duplicate_strings');
		$duplicate_strings_template = self::get_option('duplicate_strings_template');
		if ( ! empty( $duplicate_strings ) && ! empty( $duplicate_strings_template ) ) {
			new Modify_Duplicate_Strings( $duplicate_strings, $duplicate_strings_template);

			//add information about the plugin settings to Translation Dashboard
			if ( isset( $_GET['page'] ) && ( in_array( $_GET['page'], array( basename(WPML_TM_PATH).'/menu/main.php' ) ) ) ) {
				add_action( 'admin_notices', array( $this->messages, 'wctt_in_action_notice' ) );
			}
		}

	}


	/**
	 * Register settings page
	 */
	public function register_administration_page() {

		add_menu_page( __( 'Settings', 'wpml-compatibility-test-tools' ), __( 'WPML CTT', 'wpml-compatibility-test-tools' ), 'manage_options', WPML_CTT_MENU_SETTINGS_SLUG , null, ICL_PLUGIN_URL . '/res/img/icon16.png' );
		add_submenu_page( WPML_CTT_MENU_SETTINGS_SLUG, __( 'Settings', 'wpml-compatibility-test-tools' ), __( 'Settings', 'wpml-compatibility-test-tools' ), 'manage_options', WPML_CTT_MENU_SETTINGS_SLUG );
		add_submenu_page( WPML_CTT_MENU_SETTINGS_SLUG, __( 'Configuration Generator', 'wpml-compatibility-test-tools' ), __( 'Configuration Generator', 'wpml-compatibility-test-tools' ), 'manage_options', WPML_CTT_FOLDER . '/menus/settings/generator.php' );

	}


	/**
	 * Add scripts only for plugin's settings page
	 */
	public function add_scripts(){

			wp_enqueue_script( 'wctt-scripts', WPML_CTT_PLUGIN_URL . '/res/js/scripts.js', array( 'jquery' ), WPML_CTT_VERSION );

            wp_localize_script( 'wctt-scripts', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' )) );

	}

    /**
     * Add styles only for Configuration Generator settings page
     */
    public function add_styles() {

        $screen = get_current_screen();

        if ( in_array( $screen->id, array( WPML_CTT_FOLDER . '/menus/settings/generator') ) ){
            wp_register_style( 'wctt-generator-style', WPML_CTT_PLUGIN_URL . '/res/css/ctt_style.css', WPML_CTT_VERSION );

            wp_enqueue_style( 'wctt-generator-style' );
        }
    }

    /**
     * Generate XML file
     */
    public function generate_xml() {

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $root = $dom->createElement('wpml-config');
        $root = $dom->appendChild($root);

        $args = array(
            '_builtin' => false
        );

        $post_types = get_post_types( $args, 'names' );
        if ($post_types) {

            //	Create xml node <custom-types>
            if(isset($_POST['cpt'])){

                $cpts = $dom->createElement('custom-types');
                $cpts = $root->appendChild($cpts);

            }

            foreach ( $post_types as $post_type ){

                if(isset($_POST['cpt'][$post_type])){

                    $cpt = $dom->createElement('custom-type', $post_type);
                    $cpt = $cpts->appendChild($cpt);
                    $cptatr = $dom->createAttribute('translate');
                    $cptatr->value = $_POST['radio_cpt'][$post_type];
                    $cpt->appendChild($cptatr);

                }
            }
        }

        $taxonomies = get_taxonomies( $args );
        if ($taxonomies) {

            //	Create xml node <taxonomies>
            if(isset($_POST['tax'])){

                $taxs = $dom->createElement('taxonomies');
                $taxs = $root->appendChild($taxs);

            }

            foreach ( $taxonomies as $taxonomy ){

                if(isset($_POST['tax'][$taxonomy])){

                    $tax = $dom->createElement('taxonomy', $taxonomy);
                    $tax = $taxs->appendChild($tax);
                    $taxatr = $dom->createAttribute('translate');
                    $taxatr->value = $_POST['radio_tax'][$taxonomy];
                    $tax->appendChild($taxatr);

                }
            }
        }

        $custom_fields = wpml_get_custom_fields();
        if ($custom_fields) {

            //	Create xml node <custom-fields>
            if(isset($_POST['cf'])){

                $cfs = $dom->createElement('custom-fields');
                $cfs = $root->appendChild($cfs);

            }

            foreach ( $custom_fields as $custom_field ){

                if(isset($_POST['cf'][$custom_field->meta_key])){

                    $cf = $dom->createElement('custom-field', $custom_field->meta_key);
                    $cf = $cfs->appendChild($cf);
                    $cfatr = $dom->createAttribute('action');
                    $cfatr->value = $_POST['radio_cf'][$custom_field->meta_key];
                    $cf->appendChild($cfatr);

                }
            }
        }

        // If option "none" from the list is selected, node <admin-texts> won't be included in XML file.
        if ($_POST['option_list'] != 'none') {

            $options = null;

            if (isset($_POST['option_list'])) {
                $options = get_option($_POST['option_list']);
            }

            $ats = $dom->createElement('admin-texts');
            $ats = $root->appendChild($ats);

            // If option "all" from the list is selected, parent key with option name won't be included in XML file.
            if ($_POST['option_list'] == 'all') {
                $options = wpml_ctt_options_list();
                $atp = $ats;
            } else {
                $atp = $dom->createElement('key');
                $atp = $ats->appendChild($atp);
                $atpatr = $dom->createAttribute('name');

                if (isset($_POST['option_list']))
                    $atpatr->value = $_POST['option_list'];

                $atp->appendChild($atpatr);
            }

            $this->option2xml($options, $atp, $dom);
        }

        $xml = $dom->saveXML($root);

        // Save options
        switch($_POST['save']){
            case 'file' :
               header( "Content-Description: File Transfer" );
               header( 'Content-Disposition: attachment; filename="wpml-config.xml"' );
               header( "Content-Type: application/xml" );
               echo $xml;
               die();
               break;

            case 'dir' :
               file_put_contents(get_template_directory() . '/wpml-config.xml', $xml);
               break;
        }
    }


    /**
     * Generate XML from option array
     * @param $options
     * @param $node
     * @param $dom
     */
    public function option2xml($options, $node, $dom){

        if (is_array($options)){

            foreach ($options as $option => $value) {

                // Only if parent option is selected, both parent and child will be generated
                if(isset($_POST['at'][$option])) {

                    $at = $node->appendChild($dom->createElement('key'));
                    $atatr = $dom->createAttribute('name');
                    $atatr->value = $option;
                    $at->appendChild($atatr);

                    if (is_array($value)) {

                        $this->option2xml($value, $at, $dom);

                    }
                }
            }
        }
    }


}