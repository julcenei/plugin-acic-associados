<?php
/**
 * Atualizações automáticas a partir das releases do GitHub.
 *
 * O repositório vem do cabeçalho "Update URI" do plugin (https://github.com/usuario/repositorio).
 * Ao publicar uma release com a tag vX.Y.Z (e o arquivo acic-associados.zip anexado pelo
 * workflow), os sites mostram "atualização disponível" no painel.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integra o plugin ao sistema de atualizações do WordPress.
 */
class ACICA_Updater {

	const CACHE = 'acica_github_release';
	const SLUG  = 'acic-associados';
	const ASSET = 'acic-associados.zip';

	/**
	 * Ganchos.
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 4 );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'purge' ), 10, 0 );
	}

	/**
	 * "usuario/repositorio" a partir do Update URI.
	 *
	 * @return string
	 */
	public static function repo() {
		$data = get_file_data( ACICA_FILE, array( 'uri' => 'Update URI' ) );
		$path = trim( (string) wp_parse_url( (string) $data['uri'], PHP_URL_PATH ), '/' );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $path ) ? $path : '';
	}

	/**
	 * Última release publicada (com cache).
	 *
	 * @return array|null
	 */
	public static function release() {
		$repo = self::repo();
		if ( '' === $repo ) {
			return null;
		}

		// "Verificar novamente" em Painel › Atualizações ignora o cache.
		$force  = is_admin() && current_user_can( 'update_plugins' ) && ! empty( $_GET['force-check'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$cached = get_site_transient( self::CACHE );
		if ( ! $force && is_array( $cached ) ) {
			return ! empty( $cached['ok'] ) ? $cached['data'] : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'acic-associados/' . ACICA_VERSION,
				),
			)
		);

		$json = null;
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ), true );
		}
		if ( ! is_array( $json ) || empty( $json['tag_name'] ) ) {
			set_site_transient( self::CACHE, array( 'ok' => false ), HOUR_IN_SECONDS );
			return null;
		}

		$package = '';
		foreach ( (array) ( isset( $json['assets'] ) ? $json['assets'] : array() ) as $asset ) {
			if ( isset( $asset['name'], $asset['browser_download_url'] ) && self::ASSET === $asset['name'] ) {
				$package = $asset['browser_download_url'];
				break;
			}
		}
		if ( '' === $package && ! empty( $json['zipball_url'] ) ) {
			$package = $json['zipball_url'];
		}

		$data = array(
			'version'   => ltrim( (string) $json['tag_name'], 'vV' ),
			'package'   => esc_url_raw( $package ),
			'url'       => ! empty( $json['html_url'] ) ? esc_url_raw( $json['html_url'] ) : 'https://github.com/' . $repo,
			'notes'     => isset( $json['body'] ) ? (string) $json['body'] : '',
			'published' => isset( $json['published_at'] ) ? (string) $json['published_at'] : '',
		);

		set_site_transient(
			self::CACHE,
			array(
				'ok'   => true,
				'data' => $data,
			),
			6 * HOUR_IN_SECONDS
		);

		return $data;
	}

	/**
	 * Informa ao WordPress se há versão nova.
	 *
	 * @param array|false $update      Dados de atualização.
	 * @param array       $plugin_data Cabeçalhos do plugin.
	 * @param string      $plugin_file Arquivo do plugin.
	 * @param array       $locales     Idiomas.
	 * @return array|false
	 */
	public static function check( $update, $plugin_data, $plugin_file, $locales ) {
		if ( plugin_basename( ACICA_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = self::release();
		if ( ! $release || empty( $release['package'] ) ) {
			return $update;
		}

		return array(
			'id'           => isset( $plugin_data['UpdateURI'] ) ? $plugin_data['UpdateURI'] : '',
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires'     => '5.9',
			'requires_php' => '7.4',
		);
	}

	/**
	 * Janela "Ver detalhes" com as notas da versão.
	 *
	 * @param false|object|array $result Resultado.
	 * @param string             $action Ação.
	 * @param object             $args   Argumentos.
	 * @return false|object|array
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}

		$release = self::release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'          => 'ACIC Associados',
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => 'Jotapix Agência Criativa',
			'homepage'      => $release['url'],
			'requires'      => '5.9',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'download_link' => $release['package'],
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Cadastro dos associados da ACIC com importação da planilha e listagem com busca e filtro A–Z.', 'acic-associados' ) . '</p>',
				'changelog'   => '' !== $release['notes'] ? self::markdown( $release['notes'] ) : '<p><a href="' . esc_url( $release['url'] ) . '">' . esc_html__( 'Ver notas da versão no GitHub', 'acic-associados' ) . '</a></p>',
			),
		);
	}

	/**
	 * Markdown simples das notas da release (títulos, listas e parágrafos).
	 *
	 * @param string $md Texto.
	 * @return string
	 */
	private static function markdown( $md ) {
		$html    = '';
		$in_list = false;
		foreach ( preg_split( '/\r\n|\r|\n/', $md ) as $line ) {
			$line = trim( $line );
			if ( preg_match( '/^[-*]\s+(.+)$/', $line, $m ) ) {
				if ( ! $in_list ) {
					$html   .= '<ul>';
					$in_list = true;
				}
				$html .= '<li>' . esc_html( $m[1] ) . '</li>';
				continue;
			}
			if ( $in_list ) {
				$html   .= '</ul>';
				$in_list = false;
			}
			if ( '' === $line ) {
				continue;
			}
			if ( preg_match( '/^#{1,6}\s+(.+)$/', $line, $m ) ) {
				$html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
			} else {
				$html .= '<p>' . esc_html( $line ) . '</p>';
			}
		}
		return $in_list ? $html . '</ul>' : $html;
	}

	/**
	 * Garante que a pasta extraída se chame acic-associados (o zipball do GitHub vem como usuario-repo-hash).
	 *
	 * @param string      $source        Pasta extraída.
	 * @param string      $remote_source Pasta temporária.
	 * @param WP_Upgrader $upgrader      Upgrader.
	 * @param array       $hook_extra    Extras.
	 * @return string|WP_Error
	 */
	public static function fix_folder( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		global $wp_filesystem;

		if ( empty( $hook_extra['plugin'] ) || plugin_basename( ACICA_FILE ) !== $hook_extra['plugin'] ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( trailingslashit( $source ) === $desired ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $desired, true ) ) {
			return $desired;
		}
		return new WP_Error( 'acica_folder', __( 'Não foi possível preparar a pasta da atualização do ACIC Associados.', 'acic-associados' ) );
	}

	/**
	 * Limpa o cache depois de atualizar.
	 */
	public static function purge() {
		delete_site_transient( self::CACHE );
	}
}
