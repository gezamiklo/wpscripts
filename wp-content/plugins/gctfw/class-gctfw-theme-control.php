<?php
if (class_exists('WP_Customize_Control'))
{
	class gctfw_Theme_Control  extends WP_Customize_Control {

		/**
		 * Constructor.
		 *
		 * @since 3.4.0
		 * @uses WP_Customize_Image_Control::__construct()
		 *
		 * @param WP_Customize_Manager $manager
		 */
		public function __construct( $manager, $id, $args = array() ) {
			parent::__construct( $manager, 'gctfw_select_theme', array(
				'label'    => __( 'Switch theme' ),
				'section'  => 'gctfw_theme_options',
				'context'  => 'switch-theme',
			) );
			$this->type = 'gctfw_theme_control';
		}

		public function render_content() {
			$available_themes = wp_get_themes(array('allowed' => true));
			$name = '_customize-radio-' . $this->id;
			?>
			<div  class="customize-control-content" style="height:300px; overflow: scroll;">
			<?php
			foreach ($available_themes as $theme)
			{
				$value = $theme->get_template();
				echo '<b>' . $theme->get('Name') . "</b><br />\n";
				?>
				<label>
						<input type="radio" onclick="theme_changed=true;" value="<?php echo esc_attr( $value ); ?>" name="<?php echo esc_attr( $name ); ?>" <?php $this->link(); checked( $this->value(), $value ); ?> />
						<?php echo $theme->get('Name') . __(' kiválasztása','gctfw'); ?><br/>
						<?php
						if ($ss = $theme->get_screenshot())
						{
							echo '<img src="'.$ss.'" width="150" />';
						}
						?>
				</label><br />
			<?php
			}
			?>
			</div>
			<script>
				var theme_changed = false;
				jQuery('#save').click(
					function ()
					{
						if (theme_changed)
						setTimeout('document.location = document.location',1000);
					}
				);
			</script>
			<?php
		}
	}
}