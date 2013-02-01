<?php
/*
Plugin Name: GC Theme Framework
Plugin URI: 
Description: A modern theme framework. Able to generate new themes, and customize existing ones. Themes may require using this plugin
Version: 1.0
Author: MG & CsG
Author URI: 
License: 
 */

add_action('customize_save', array('gcThemeFramework', 'action_customize_save') );
add_action('customize_register', array('gcThemeFramework', 'customize_register') );
add_action('after_setup_theme', array('gcThemeFramework', 'init') );
add_action('admin_enqueue_script', array('gcThemeFramework','enqueue_script'));

add_action('wp_head', array('gcThemeFrameWork','load_mods'));

class gcThemeFramework{
	static $theme_options = null;
	static $current_theme = null;
	
	public function init()
	{
		add_theme_support('gctfw_select_theme');
		gcThemeFramework::$current_theme = wp_get_theme();
		gcThemeFramework::get_theme_options();
	}

	public function enqueue_script()
	{
		wp_enqueue_script('gctfw_customizer', plugin_dir_url(__FILE__).'js/theme-customizer.js');
	}
	
	public function customize_register()
	{
		global $wp_customize;

		require_once(dirname(__FILE__).'/class-gctfw-theme-control.php');
		require_once(dirname(__FILE__).'/class-gctfw-color-scheme-control.php');
		require_once(dirname(__FILE__).'/class-gctfw-font-scheme-control.php');

		//The base colors
		$wp_customize->add_section( 'gctfw_theme_options' , array(
			'title'      => 'Tema specialis beallitasai',
			'priority'   => 25,
		) );

		$wp_customize->add_setting( 'gctfw_select_theme' , array(
			'default'     => $wp_customize->theme()->get('Name'),
			'transport'   => 'refresh',
			'capabilities' => 'manage_options'
		) );
		
		$myControl = new gctfw_Theme_Control( $wp_customize, 'gctfw_select_theme', array(
			'label'        => __( 'Select theme', 'mytheme' ),
			'section'    => 'gctfw_theme_options',
			'settings'   => 'gctfw_select_theme',
		) ) ;
		$wp_customize->add_control( $myControl );

				
		
		$wp_customize->add_setting( 'gctfw_color1' , array(
			'default'     => '#000000',
			'transport'   => 'refresh',
		) );
		
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'gctfw_color1', array(
			'label'        => __( 'Color 1', 'mytheme' ),
			'section'    => 'gctfw_theme_options',
			'settings'   => 'gctfw_color1',
		) ) );
		
		
		$wp_customize->add_setting( 'header_margin' , array(
			'default'     => '15px',
			'transport'   => 'refresh',
		) );
		
		
		$wp_customize->add_control( new WP_Customize_Control( $wp_customize, 'header_margin', array(
			'label'        => __( 'Header margin (px)', 'mytheme' ),
			'section'    => 'gctfw_theme_options',
			'settings'   => 'header_margin',
		) ) );
		
		
		$wp_customize->add_setting( 'color_scheme',
				array(
					'default' => 'default',
					'transport' => 'refresh'
				)
				
		);
		// Register our individual settings fields
		$wp_customize->add_control( new gctfw_Color_Scheme_Control( $wp_customize, 'color_scheme', array(
			'label'        => __( 'Color Scheme' ),
			'section'    => 'gctfw_theme_options',
			'settings'   => 'color_scheme',
		) ) );
		
		$wp_customize->add_setting( 'font_scheme',
				array(
					'default' => 'default',
					'transport' => 'refresh'
				)
				
		);
		// Register our individual settings fields
		$wp_customize->add_control( new gctfw_Font_Scheme_Control( $wp_customize, 'font_scheme', array(
			'label'        => __( 'Font Scheme' ),
			'section'    => 'gctfw_theme_options',
			'settings'   => 'font_scheme',
		) ) );		
		
	}
	
	public function action_customize_save($cm)
	{	
		foreach ($cm->settings() as $k => $setting)
		{
			if ( $k == 'color_scheme')
			{
				set_theme_mod($k, $setting->value());
			}
			
			if ( ($k == 'gctfw_select_theme') && ( strlen( $setting->post_value() ) ) )
			{
				switch_theme($setting->post_value());
			}
		}
	}
	
	public function get_theme_options()
	{
		return get_option('gctfw_theme_options_'.gcThemeFramework::$current_theme);
	}


	/**
	 * Renders the Color Scheme setting field.
	 */
	function settings_field_color_scheme() {
		
		
		foreach ( twentyeleven_color_schemes() as $scheme ) {
		?>
		<div class="layout image-radio-option color-scheme">
		<?php echo get_theme_root(); ?>
		<label class="description">
			<input type="radio" name="twentyeleven_theme_options[color_scheme]" value="<?php echo esc_attr( $scheme['value'] ); ?>" <?php checked( $options['color_scheme'], $scheme['value'] ); ?> />
			<input type="hidden" id="default-color-<?php echo esc_attr( $scheme['value'] ); ?>" value="<?php echo esc_attr( $scheme['default_link_color'] ); ?>" />
			<span>
				<img src="<?php echo esc_url( $scheme['thumbnail'] ); ?>" width="136" height="122" alt="" />
				<?php echo $scheme['label']; ?>
			</span>
		</label>
		</div>
		<?php
		}
	}
	
	public function load_mods()
	{
		$current_theme = wp_get_theme();
		$theme_uri = $current_theme->get_template_directory_uri();
		$colors_uri = $theme_uri.'/colors/';
		$fonts_uri = $theme_uri.'/fonts/';
		
		$color_scheme = get_theme_mod('color_scheme', '');
		$font_scheme = get_theme_mod('font_scheme', '');
		
		if ($color_scheme)
		{
			?>
			<link rel="stylesheet" type="text/css" href="<?php echo $colors_uri.$color_scheme?>" />
			<?php
		}

		if ($color_scheme)
		{
			?>
			<link rel="stylesheet" type="text/css" href="<?php echo $fonts_uri.$font_scheme?>" />
			<?php
		}
		
	}
}
