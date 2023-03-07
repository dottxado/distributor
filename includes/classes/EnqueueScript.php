<?php
/**
 * This class use to register script.
 *
 * This class handles:
 *   - Script dependencies.
 *     This uses asset information to set script dependencies,
 *     and version generated by @wordpress/dependency-extraction-webpack-plugin package.
 *   - Script localization.
 *     It also handles script translation registration.
 *
 * @package distributor
 * @since   x.x.x
 */

namespace Distributor;

use Exception;

/**
 * Class EnqueueScript
 *
 * @since x.x.x
 */
class EnqueueScript {
	/**
	 * Script Handle.
	 *
	 * @since x.x.x
	 */
	private string $script_handle;

	/**
	 * Script path relative to plugin root directory.
	 *
	 * @since x.x.x
	 */
	private string $relative_script_path;

	/**
	 * Script path absolute to plugin root directory.
	 *
	 * @since x.x.x
	 */
	private string $absolute_script_path;

	/**
	 * Script dependencies.
	 *
	 * @since x.x.x
	 */
	private array $script_dependencies = [];

	/**
	 * Script version.
	 *
	 * @since x.x.x
	 */
	private string $version = DT_VERSION;

	/**
	 * Flag to decide whether load script in footer.
	 *
	 * @since x.x.x
	 */
	private bool $load_script_in_footer = false;

	/**
	 * Flag to decide whether register script translation.
	 *
	 * @since x.x.x
	 */
	private bool $register_translations = false;

	/**
	 * Script localization parameter name.
	 *
	 * @since x.x.x
	 */
	private ?string $localize_script_param_name = null;

	/**
	 * Script localization parameter data.
	 *
	 * @since x.x.x
	 */
	private ?array $localize_script_param_data = null;

	/**
	 * Plugin root directory path.
	 *
	 * @since x.x.x
	 */
	private string $plugin_dir_path;

	/**
	 * Plugin root directory URL.
	 *
	 * @since x.x.x
	 */
	private string $plugin_dir_url;

	/**
	 * Plugin text domain.
	 *
	 * @since x.x.x
	 */
	private string $text_domain;

	/**
	 * EnqueueScript constructor.
	 *
	 * @since x.x.x
	 *
	 * @param string $script_handle Script handle.
	 * @param string $script_name   Script name.
	 *
	 * @throws Exception If script file not found.
	 */
	public function __construct( string $script_handle, string $script_name ) {
		$this->plugin_dir_path      = DT_PLUGIN_PATH;
		$this->plugin_dir_url       = trailingslashit( plugin_dir_url( DT_PLUGIN_FULL_FILE ) );
		$this->text_domain          = 'distributor';
		$this->script_handle        = $script_handle;
		$this->relative_script_path = 'dist/js/' . $script_name . '.js';
		$this->absolute_script_path = $this->plugin_dir_path . $this->relative_script_path;

		if ( ! file_exists( $this->absolute_script_path ) ) {
			throw new Exception( 'Script file not found: ' . $this->absolute_script_path );
		}
	}

	/**
	 * Flag to decide whether load script in footer.
	 *
	 * @since x.x.x
	 */
	public function load_in_footer(): EnqueueScript {
		$this->load_script_in_footer = true;

		return $this;
	}

	/**
	 * Set script dependencies.
	 *
	 * @since x.x.x
	 *
	 * @param array $script_dependencies Script dependencies.
	 */
	public function dependencies( array $script_dependencies ): EnqueueScript {
		$this->script_dependencies = $script_dependencies;

		return $this;
	}

	/**
	 * Register script.
	 *
	 * @since x.x.x
	 */
	public function register(): EnqueueScript {
		$script_url   = $this->plugin_dir_url . $this->relative_script_path;
		$script_asset = $this->get_asset_file_data();

		$this->version = $script_asset['version'];

		wp_register_script(
			$this->script_handle,
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			$this->load_script_in_footer
		);

		if ( $this->register_translations ) {
			wp_set_script_translations(
				$this->script_handle,
				$this->text_domain,
				$this->plugin_dir_path . 'lang'
			);
		}

		if ( $this->localize_script_param_data ) {
			wp_localize_script(
				$this->script_handle,
				$this->localize_script_param_name,
				$this->localize_script_param_data
			);
		}

		return $this;
	}

	/**
	 * This function should be called before enqueue or register method.
	 *
	 * @since x.x.x
	 */
	public function register_translations(): EnqueueScript {
		$this->register_translations = true;

		return $this;
	}

	/**
	 * This function should be called after enqueue or register method.
	 *
	 * @since x.x.x
	 *
	 * @param string $js_variable_name JS variable name.
	 * @param array  $data             Data to be localized.
	 */
	public function register_localize_data( string $js_variable_name, array $data ): EnqueueScript {
		$this->localize_script_param_name = $js_variable_name;
		$this->localize_script_param_data = $data;

		return $this;
	}

	/**
	 * Enqueue script.
	 *
	 * @since x.x.x
	 */
	public function enqueue(): EnqueueScript {
		if ( ! wp_script_is( $this->script_handle, 'registered' ) ) {
			$this->register();
		}
		wp_enqueue_script( $this->script_handle );

		return $this;
	}

	/**
	 * Should return script handle.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function get_script_handle(): string {
		return $this->script_handle;
	}

	/**
	 * Get asset file data.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 */
	public function get_asset_file_data(): array {
		$script_asset_path = trailingslashit( dirname( $this->absolute_script_path ) )
			. basename( $this->absolute_script_path, '.js' )
			. '.asset.php';

		if ( file_exists( $script_asset_path ) ) {
			$script_asset = require $script_asset_path;
		} else {
			$script_asset = [
				'dependencies' => [],
				'version'      => $this->version ?: filemtime( $this->absolute_script_path ), // phpcs:ignore
			];
		}

		if ( $this->script_dependencies ) {
			$script_asset['dependencies'] = array_merge( $this->script_dependencies, $script_asset['dependencies'] );
		}

		return $script_asset;
	}

	/**
	 * Should return script version.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public function get_version(): string {
		$script_asset = $this->get_asset_file_data();

		return $script_asset['version'];
	}
}
