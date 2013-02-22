<?php
/*
 * To add color schemes you have to create a colors folder under your themes folder, the rest is done by the plugin 
 */

if (class_exists('WP_Customize_Control'))
{
	class gctfw_Font_Scheme_Control  extends WP_Customize_Control {

		/**
		 * Constructor.
		 *
		 * @since 3.4.0
		 * @uses WP_Customize_Image_Control::__construct()
		 *
		 * @param WP_Customize_Manager $manager
		 */
		public function __construct( $manager, $id, $args = array() ) {
			parent::__construct( $manager, 'font_scheme', array(
				'label'    => __( 'Switch font scheme','gctfw' ),
				'section'  => 'gctfw_theme_options',
				'context'  => 'switch-font-scheme',
			) );
			$this->type = 'font_scheme_control';
		}

		public function render_content() {
			$current_theme = wp_get_theme();
			$theme_dir = $current_theme->get_template_directory();
			$fonts_dir = $theme_dir.'/fonts/';
			echo __("Font scheme switch",'gctfw')."<br />";
			
			$theme_options = get_theme_mod('theme_options');
			?>
			<div  class="customize-control-content">
			<?php
			if (! is_dir($fonts_dir)) return false;
			$font_files = glob($fonts_dir.'*.css');
			if (count($font_files))
			{
				$current_scheme = get_theme_mod('font_scheme', 'default')
				?>
				<select  data-customize-setting-link="font_scheme" name="theme_options[font_scheme]">
					<option value=""><?php echo __("Default",'gctfw');?></option>
<?php
				foreach ( $font_files as $font_file )
				{
					$file = basename($font_file);
					$font_name = str_replace('.css','',$file);
					?>
					<option value="<?php echo $file?>" <?php echo $current_scheme == $file ? 'selected' : ''?>><?php echo $font_name ?></option>
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