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

class gcThemeFramework{
	
	public function init()
	{
		add_theme_support('gctfw_select_theme');		
	}

	
	public function customize_register()
	{
		global $wp_customize;

		require_once(dirname(__FILE__).'/class-gctfw-theme-control.php');

		//The base colors
		$wp_customize->add_section( 'gctfw_switch_theme' , array(
			'title'      => 'Tema valtasa',
			'priority'   => 25,
		) );

		$wp_customize->add_setting( 'gctfw_select_theme' , array(
			'default'     => $wp_customize->theme()->get('Name'),
			'transport'   => 'refresh',
			'capabilities' => 'manage_options'
		) );
		
		$myControl = new gctfw_Theme_Control( $wp_customize, 'gctfw_select_theme', array(
			'label'        => __( 'Select theme', 'mytheme' ),
			'section'    => 'gctfw_switch_theme',
			'settings'   => 'gctfw_select_theme',
		) ) ;
		$wp_customize->add_control( $myControl );

		
		
		//The base colors
		$wp_customize->add_section( 'gctfw_basic_colors' , array(
			'title'      => 'Alapszinek',
			'priority'   => 30,
		) );
		
		
		$wp_customize->add_setting( 'gctfw_color1' , array(
			'default'     => '#000000',
			'transport'   => 'refresh',
		) );
		
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'gctfw_color1', array(
			'label'        => __( 'Color 1', 'mytheme' ),
			'section'    => 'gctfw_basic_colors',
			'settings'   => 'gctfw_color1',
		) ) );		

		
		//The margins of parts
		$wp_customize->add_section( 'gctfw_margins' , array(
			'title'      => 'Eltartasok',
			'priority'   => 35,
		) );
		
		
		$wp_customize->add_setting( 'header_margin' , array(
			'default'     => '15px',
			'transport'   => 'refresh',
		) );
		
		
		$wp_customize->add_control( new WP_Customize_Control( $wp_customize, 'header_margin', array(
			'label'        => __( 'Header margin (px)', 'mytheme' ),
			'section'    => 'gctfw_margins',
			'settings'   => 'header_margin',
		) ) );		
		
	}
	
	public function action_customize_save($cm)
	{	
		foreach ($cm->settings() as $k => $setting)
		{
			if ( ($k == 'gctfw_select_theme') && ( strlen( $setting->post_value() ) ) )
			{
				switch_theme($setting->post_value());
			}
		}
	}
}
