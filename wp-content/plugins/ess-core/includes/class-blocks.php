<?php
/**
 * Block registration.
 *
 * Both blocks are dynamic and server-rendered, and their front-end behaviour is
 * a native ES module that imports `@wordpress/interactivity` through the import
 * map WordPress already ships. That means no bundler: the sources in src/ are
 * the files the browser loads, so `docker compose up` is the whole setup and a
 * clone never needs a Node step.
 *
 * The editor scripts are registered with explicit dependencies rather than the
 * `file:` shorthand, because without a build there is no generated asset file
 * to declare them.
 *
 * @package ESS\Core
 */

declare( strict_types = 1 );

namespace ESS\Core;

defined( 'ABSPATH' ) || exit;

final class Blocks {

	/** @var list<string> */
	private const BLOCKS = [ 'booking-form', 'contact-form' ];

	public static function init(): void {
		add_action( 'init', [ self::class, 'register' ], 20 );
	}

	public static function register(): void {
		// One stylesheet for both blocks: they share the field, button and
		// panel styles, and splitting them would ship the same rules twice on
		// any page carrying both.
		wp_register_style( 'ess-forms', URL . 'src/forms.css', [], self::asset_version( 'src/forms.css' ) );

		foreach ( self::BLOCKS as $block ) {
			$dir = DIR . 'src/' . $block;

			if ( ! file_exists( $dir . '/block.json' ) ) {
				log( 'Block directory missing block.json', [ 'block' => $block ] );
				continue;
			}

			$handle = 'ess-' . $block . '-editor';
			wp_register_script(
				$handle,
				URL . 'src/' . $block . '/editor.js',
				[ 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ],
				self::asset_version( 'src/' . $block . '/editor.js' ),
				true
			);

			register_block_type( $dir );
		}
	}

	/**
	 * Cache-busting version for an asset.
	 *
	 * The plugin version alone means an edited stylesheet keeps its old URL and
	 * browsers serve the cached copy, so a CSS fix appears not to work. Locally
	 * the file's own mtime is used instead; in production the plugin version is
	 * the right cache key, because it changes exactly when the files do.
	 */
	private static function asset_version( string $relative ): string {
		if ( 'local' !== wp_get_environment_type() ) {
			return VERSION;
		}
		$path = DIR . $relative;
		return file_exists( $path ) ? (string) filemtime( $path ) : VERSION;
	}

	/**
	 * Values every form block needs in order to talk to the REST API.
	 *
	 * The signed stamp is what the anti-spam time trap checks; it is generated
	 * per render so it cannot be lifted from an old page and replayed.
	 *
	 * @return array{root:string,nonce:string,stamp:string}
	 */
	public static function form_config(): array {
		return [
			'root'  => esc_url_raw( rest_url( REST_NS ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'stamp' => Anti_Spam::stamp(),
		];
	}
}
