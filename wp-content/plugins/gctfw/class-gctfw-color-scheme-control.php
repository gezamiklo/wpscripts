<?php
/*
 * To add color schemes you have to create a colors folder under your themes folder, the rest is done by the plugin 
 */

if (class_exists('WP_Customize_Control'))
{
	class gctfw_Color_Scheme_Control  extends WP_Customize_Control {

		/**
		 * Constructor.
		 *
		 * @since 3.4.0
		 * @uses WP_Customize_Control::__construct()
		 *
		 * @param WP_Customize_Manager $manager
		 */
		public function __construct( $manager, $id, $args = array() ) {
			parent::__construct( $manager, 'color_scheme', array(
				'label'    => __( 'Switch color scheme','gctfw' ),
				'section'  => 'gctfw_theme_options',
				'context'  => 'switch-color-scheme',
			) );
			$this->type = 'color_scheme_control';	
		}

		public function render_content() {
			$current_theme = wp_get_theme();
			$theme_dir = $current_theme->get_template_directory();
			$colors_dir = $theme_dir.'/colors/';
			echo __("Color scheme switch",'gctfw')."<br />";			
			?>
			<div  class="customize-control-content">
			<?php
			if (! is_dir($colors_dir)) return false;
			$color_files = glob($colors_dir.'*.css');
			if (count($color_files))
			{
				$current_scheme = get_theme_mod('color_scheme', 'default');
				?>
				<select  data-customize-setting-link="color_scheme" id="customize-control-color-scheme-select" class="color-scheme-select" name="theme_options[color_scheme]">
					<option value=""><?php echo __("Default",'gctfw');?></option>
<?php
				foreach ( $color_files as $color_file )
				{
					$file = basename($color_file);
					$color_name = str_replace('.css','',$file);
					?>
					<option value="<?php echo $file?>" <?php echo $current_scheme == $file ? 'selected' : ''?>><?php echo $color_name ?></option>
					<?php
				}
				?>
				</select>
<?php
			}
			?>
			</div>
			<?php
		}
	}
}