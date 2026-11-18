<?php
/**
 * Ajuda dentro do painel: guia com telas (manual), primeiros passos e dicas "?" nos campos.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

/**
 * Guia, checklist e dicas.
 */
class ACICA_Ajuda {

	const PAGINA = 'acica-ajuda';

	/**
	 * Ganchos.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'primeiros_passos' ), 5 );
		add_action( 'admin_post_acica_pp', array( __CLASS__, 'ocultar_pp' ) );
	}

	/**
	 * Submenu "Ajuda".
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . ACICA_CPT,
			__( 'Guia do ACIC Associados', 'acic-associados' ),
			__( 'Ajuda', 'acic-associados' ),
			'edit_posts',
			self::PAGINA,
			array( __CLASS__, 'pagina' )
		);
	}

	/**
	 * URL do guia, opcionalmente numa seção.
	 *
	 * @param string $secao Âncora.
	 * @return string
	 */
	public static function url( $secao = '' ) {
		$u = admin_url( 'edit.php?post_type=' . ACICA_CPT . '&page=' . self::PAGINA );
		return $secao ? add_query_arg( 'secao', sanitize_key( $secao ), $u ) : $u;
	}

	/**
	 * Botão "?" com explicação curta e link para o guia.
	 *
	 * @param string $texto Explicação.
	 * @param string $secao Seção do guia.
	 * @return string
	 */
	public static function dica( $texto, $secao = '' ) {
		$id = wp_unique_id( 'acica-dica-' );
		$h  = '<span class="acica-dica"><button type="button" class="acica-dica__btn" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr__( 'Ajuda sobre este campo', 'acic-associados' ) . '">?</button>';
		$h .= '<span class="acica-dica__balao" id="' . esc_attr( $id ) . '" role="tooltip" hidden>' . esc_html( $texto );
		if ( $secao ) {
			$h .= ' <a href="' . esc_url( self::url( $secao ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ver no guia', 'acic-associados' ) . ' →</a>';
		}
		return $h . '</span></span>';
	}

	/**
	 * Está numa tela do plugin?
	 *
	 * @return bool
	 */
	private static function na_tela_do_plugin() {
		$s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $s && ACICA_CPT === $s->post_type;
	}

	/**
	 * CSS/JS das dicas e do guia.
	 */
	public static function assets() {
		if ( ! self::na_tela_do_plugin() ) {
			return;
		}
		wp_enqueue_style( 'acica-admin', ACICA_URL . 'assets/css/admin.css', array(), ACICA_VERSION );
		wp_enqueue_script( 'acica-dicas', ACICA_URL . 'assets/js/admin-dicas.js', array(), ACICA_VERSION, true );
	}

	/**
	 * Página do guia (o manual em HTML que acompanha o plugin).
	 */
	public static function pagina() {
		update_user_meta( get_current_user_id(), 'acica_guia_visto', 1 );
		$secao = isset( $_GET['secao'] ) ? sanitize_key( wp_unslash( $_GET['secao'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$src   = ACICA_URL . 'manual/index.html?v=' . rawurlencode( ACICA_VERSION ) . ( $secao ? '#' . $secao : '' );
		?>
		<div class="wrap acica-ajuda">
			<div class="acica-ajuda__topo">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Guia do ACIC Associados', 'acic-associados' ); ?></h1>
				<a class="page-title-action" href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Abrir em nova aba', 'acic-associados' ); ?></a>
				<a class="page-title-action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ACICA_CPT . '&page=' . ACICA_Import::PAGE ) ); ?>"><?php esc_html_e( 'Importar planilha agora', 'acic-associados' ); ?></a>
			</div>
			<iframe class="acica-ajuda__guia" src="<?php echo esc_url( $src ); ?>" title="<?php esc_attr_e( 'Guia do ACIC Associados', 'acic-associados' ); ?>"></iframe>
		</div>
		<?php
	}

	/**
	 * A lista já está em alguma página publicada?
	 *
	 * @return bool
	 */
	private static function lista_no_site() {
		global $wpdb;
		return (bool) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ('page','post') AND post_content LIKE '%[acic_associados%' LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Passos e situação de cada um.
	 *
	 * @return array[]
	 */
	public static function passos() {
		$c = wp_count_posts( ACICA_CPT );
		return array(
			array(
				'feito'  => ( (int) $c->publish + (int) ( isset( $c->{ACICA_INATIVO} ) ? $c->{ACICA_INATIVO} : 0 ) ) > 0,
				'titulo' => __( 'Importe a planilha de associados', 'acic-associados' ),
				'texto'  => __( 'Envie o arquivo do sistema, confira e aplique.', 'acic-associados' ),
				'url'    => admin_url( 'edit.php?post_type=' . ACICA_CPT . '&page=' . ACICA_Import::PAGE ),
				'acao'   => __( 'Importar planilha', 'acic-associados' ),
			),
			array(
				'feito'  => self::lista_no_site(),
				'titulo' => __( 'Coloque a lista no site', 'acic-associados' ),
				'texto'  => __( 'Cole [acic_associados] na página Associados.', 'acic-associados' ),
				'url'    => self::url( 'no-site' ),
				'acao'   => __( 'Como fazer', 'acic-associados' ),
			),
			array(
				'feito'  => (bool) get_user_meta( get_current_user_id(), 'acica_guia_visto', true ),
				'titulo' => __( 'Conheça o guia', 'acic-associados' ),
				'texto'  => __( 'Telas e passo a passo de tudo, em Associados › Ajuda.', 'acic-associados' ),
				'url'    => self::url(),
				'acao'   => __( 'Abrir o guia', 'acic-associados' ),
			),
		);
	}

	/**
	 * Checklist "Primeiros passos" na lista de associados e na importação.
	 */
	public static function primeiros_passos() {
		if ( ! self::na_tela_do_plugin() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$s = get_current_screen();
		if ( $s && ( 'post' === $s->base || false !== strpos( $s->id, self::PAGINA ) ) ) {
			return; // Não atrapalha o cadastro nem o guia.
		}
		$passos = self::passos();
		$feitos = count( array_filter( wp_list_pluck( $passos, 'feito' ) ) );
		if ( get_user_meta( get_current_user_id(), 'acica_pp_oculto', true ) || count( $passos ) === $feitos ) {
			return;
		}
		$pct = round( 100 * $feitos / count( $passos ) );
		?>
		<div class="acica-pp" role="region" aria-label="<?php esc_attr_e( 'Primeiros passos', 'acic-associados' ); ?>">
			<div class="acica-pp__topo">
				<div>
					<h2><?php esc_html_e( 'Primeiros passos com os associados', 'acic-associados' ); ?></h2>
					<p>
						<?php
						/* translators: 1: feitos, 2: total */
						echo esc_html( sprintf( __( '%1$d de %2$d concluídos', 'acic-associados' ), $feitos, count( $passos ) ) );
						?>
					</p>
				</div>
				<div class="acica-pp__acoes">
					<a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Ver o guia', 'acic-associados' ); ?></a>
					<a class="acica-pp__ocultar" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=acica_pp' ), 'acica_pp' ) ); ?>"><?php esc_html_e( 'Ocultar', 'acic-associados' ); ?></a>
				</div>
			</div>
			<div class="acica-pp__barra" role="progressbar" aria-valuenow="<?php echo (int) $pct; ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?php esc_attr_e( 'Progresso', 'acic-associados' ); ?>"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
			<ol class="acica-pp__lista">
				<?php foreach ( $passos as $i => $p ) : ?>
					<li class="<?php echo $p['feito'] ? 'is-feito' : ''; ?>">
						<span class="acica-pp__n" aria-hidden="true"><?php echo $p['feito'] ? '✓' : (int) ( $i + 1 ); ?></span>
						<span class="acica-pp__txt"><strong><?php echo esc_html( $p['titulo'] ); ?></strong><small><?php echo esc_html( $p['texto'] ); ?></small></span>
						<?php if ( ! $p['feito'] ) : ?>
							<a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['acao'] ); ?></a>
						<?php else : ?>
							<span class="screen-reader-text"><?php esc_html_e( 'Concluído', 'acic-associados' ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
		<?php
	}

	/**
	 * Ocultar o checklist.
	 */
	public static function ocultar_pp() {
		check_admin_referer( 'acica_pp' );
		update_user_meta( get_current_user_id(), 'acica_pp_oculto', 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . ACICA_CPT ) );
		exit;
	}
}
