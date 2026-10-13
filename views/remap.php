<div class="wrap">
	<?php if ( get_transient( 'cos_message' ) ) { ?>
		<div class="updated">
			<p><?php echo wp_kses_post( get_transient( 'cos_message' ) ); ?></p>
		</div>
		<?php delete_transient( 'cos_message' ); ?>
	<?php } ?>

	<?php $plugin_data = get_plugin_data( __DIR__ . '/../CultureObject.php' ); ?>

	<h2><?php esc_html_e( 'Culture Object Provider Settings', 'culture-object' ); ?> <small><?php printf( /* Translators: %s: Version Number */esc_html__( 'Version %s', 'culture-object' ), esc_html( $plugin_data['Version'] ) ); ?> by <a href="http://www.thirty8.co.uk">Thirty8 Digital</a>.</small></h2>

	<form method="POST" action="options.php">
		<?php
		settings_fields( 'cos_remap_settings' );
		do_settings_sections( 'cos_remap_settings' );
		submit_button();
		?>
	</form>

</div>
