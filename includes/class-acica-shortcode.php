<?php
/**
 * Shortcode [acic_associados]: listagem com busca e filtro A–Z.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

/**
 * Listagem pública.
 */
class ACICA_Shortcode {

	const TAG = 'acic_associados';

	/**
	 * Ganchos.
	 */
	public static function init() {
		add_shortcode( self::TAG, array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Registra CSS/JS e já carrega no <head> quando a página usa o shortcode.
	 */
	public static function assets() {
		wp_register_style( 'acic-associados', ACICA_URL . 'assets/css/acic-associados.css', array(), ACICA_VERSION );
		wp_register_script( 'acic-associados', ACICA_URL . 'assets/js/acic-associados.js', array(), ACICA_VERSION, true );
		if ( is_singular() ) {
			$post = get_post();
			if ( $post && has_shortcode( $post->post_content, self::TAG ) ) {
				wp_enqueue_style( 'acic-associados' );
			}
		}
	}

	/**
	 * Associados ativos já ordenados (com cache).
	 *
	 * @return array
	 */
	public static function dados() {
		$cached = get_transient( 'acica_lista_dados' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$posts = get_posts(
			array(
				'post_type'              => ACICA_CPT,
				'post_status'            => ACICA_ATIVO,
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);
		$items = array();
		foreach ( $posts as $p ) {
			$d    = acica_get( $p );
			$nome = acica_nome_exibicao( $d );
			if ( '' === $nome ) {
				$nome = $p->post_title;
			}
			$ordem  = acica_busca( $nome );
			$letra  = '' !== $ordem && ctype_alpha( $ordem[0] ) ? $ordem[0] : '#';
			$razao  = acica_chave_nome( $d['razao'] ) !== acica_chave_nome( $nome ) ? $d['razao'] : '';
			$cnpj_d = acica_cnpj_digits( $d['cnpj'] );

			$items[] = array(
				'nome'     => $nome,
				'razao'    => $razao,
				'cnpj'     => acica_cnpj_format( $d['cnpj'] ),
				'telefone' => acica_fone_format( $d['telefone'] ),
				'tel_link' => acica_fone_link( $d['telefone'] ),
				'wa_link'  => acica_whatsapp_link( $d['whatsapp'] ),
				'letra'    => $letra,
				'ordem'    => $ordem,
				'busca'    => trim( acica_busca( $nome . ' ' . $d['razao'] ) . ' ' . $cnpj_d ),
			);
		}
		usort(
			$items,
			static function ( $a, $b ) {
				if ( $a['letra'] !== $b['letra'] ) {
					return '#' === $a['letra'] ? -1 : ( '#' === $b['letra'] ? 1 : strcmp( $a['letra'], $b['letra'] ) );
				}
				$c = strcmp( $a['ordem'], $b['ordem'] );
				return 0 !== $c ? $c : strcmp( $a['cnpj'], $b['cnpj'] ); // Filiais de mesmo nome pela ordem do CNPJ.
			}
		);
		set_transient( 'acica_lista_dados', $items, DAY_IN_SECONDS );
		return $items;
	}

	/**
	 * Saída do shortcode.
	 *
	 * @param array $atts Atributos.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'total'  => 'sim',
				'busca'  => 'sim',
				'letras' => 'sim',
			),
			$atts,
			self::TAG
		);
		foreach ( $atts as $k => $v ) {
			$atts[ $k ] = ! in_array( strtolower( (string) $v ), array( 'nao', 'não', 'no', '0', 'false' ), true );
		}

		wp_enqueue_style( 'acic-associados' );
		wp_enqueue_script( 'acic-associados' );

		$hash   = md5( wp_json_encode( $atts ) . ACICA_VERSION );
		$cached = get_transient( 'acica_lista_html' );
		if ( is_array( $cached ) && isset( $cached[ $hash ] ) ) {
			return $cached[ $hash ];
		}

		$html            = self::montar( $atts );
		$cached          = is_array( $cached ) ? $cached : array();
		$cached[ $hash ] = $html;
		set_transient( 'acica_lista_html', $cached, DAY_IN_SECONDS );
		return $html;
	}

	/**
	 * Monta o HTML da listagem.
	 *
	 * @param array $atts Atributos (bool).
	 * @return string
	 */
	private static function montar( $atts ) {
		$items = self::dados();
		$total = count( $items );

		if ( ! $total ) {
			return '<div class="acica"><p class="acica-resultado">' . esc_html__( 'A lista de associados ainda não foi cadastrada.', 'acic-associados' ) . '</p></div>';
		}

		$grupos    = array();
		$tem_cnpj  = false;
		$tem_cont  = false;
		foreach ( $items as $it ) {
			$grupos[ $it['letra'] ][] = $it;
			$tem_cnpj                 = $tem_cnpj || '' !== $it['cnpj'];
			$tem_cont                 = $tem_cont || '' !== $it['tel_link'] || '' !== $it['wa_link'];
		}

		$classes = array( 'acica' );
		if ( $tem_cnpj ) {
			$classes[] = 'acica--cnpj';
		}
		if ( $tem_cont ) {
			$classes[] = 'acica--contato';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-acica data-txt="<?php echo esc_attr( wp_json_encode( self::textos() ) ); ?>">
			<?php if ( $atts['total'] ) : ?>
				<p class="acica-intro">
					<?php
					/* translators: %s: quantidade de associados */
					echo esc_html( sprintf( __( '%s empresas fazem parte da Associação Empresarial de Caçador.', 'acic-associados' ), number_format_i18n( $total ) ) );
					?>
				</p>
			<?php endif; ?>

			<?php if ( $atts['busca'] || $atts['letras'] ) : ?>
				<div class="acica-filtros">
					<?php if ( $atts['busca'] ) : ?>
						<div class="acica-busca">
							<label for="acica-busca" class="acica-sr"><?php esc_html_e( 'Buscar associado por nome, razão social ou CNPJ', 'acic-associados' ); ?></label>
							<svg aria-hidden="true" focusable="false" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
							<input type="search" id="acica-busca" class="acica-busca__campo" placeholder="<?php echo esc_attr( $tem_cnpj ? __( 'Buscar por nome ou CNPJ', 'acic-associados' ) : __( 'Buscar associado', 'acic-associados' ) ); ?>" autocomplete="off" enterkeyhint="search">
						</div>
					<?php endif; ?>
					<?php if ( $atts['letras'] ) : ?>
						<div class="acica-letras" role="group" aria-label="<?php esc_attr_e( 'Filtrar pela letra inicial', 'acic-associados' ); ?>">
							<button type="button" class="acica-letra acica-letra--todos is-ativa" data-letra="" aria-pressed="true"><?php esc_html_e( 'Todos', 'acic-associados' ); ?></button>
							<?php foreach ( array_merge( array( '#' ), range( 'A', 'Z' ) ) as $l ) : ?>
								<button type="button" class="acica-letra" data-letra="<?php echo esc_attr( $l ); ?>" aria-pressed="false"<?php echo isset( $grupos[ $l ] ) ? '' : ' disabled'; ?><?php echo '#' === $l ? ' aria-label="' . esc_attr__( 'Números', 'acic-associados' ) . '"' : ''; ?>><?php echo esc_html( '#' === $l ? '0–9' : $l ); ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<p class="acica-resultado" aria-live="polite" data-todos="<?php echo esc_attr( sprintf( /* translators: %s: quantidade */ __( 'Mostrando todos os %s associados, em ordem alfabética.', 'acic-associados' ), number_format_i18n( $total ) ) ); ?>">
				<?php
				/* translators: %s: quantidade */
				echo esc_html( sprintf( __( 'Mostrando todos os %s associados, em ordem alfabética.', 'acic-associados' ), number_format_i18n( $total ) ) );
				?>
			</p>

			<div class="acica-vazio" hidden>
				<p class="acica-vazio__titulo"><?php esc_html_e( 'Nenhum associado encontrado', 'acic-associados' ); ?></p>
				<p><?php esc_html_e( 'Confira a grafia ou busque por outra parte do nome.', 'acic-associados' ); ?></p>
				<button type="button" class="acica-limpar"><?php esc_html_e( 'Limpar filtros', 'acic-associados' ); ?></button>
			</div>

			<div class="acica-grupos">
				<?php foreach ( $grupos as $letra => $lista ) : ?>
					<section class="acica-grupo" data-letra="<?php echo esc_attr( $letra ); ?>">
						<div class="acica-grupo__topo">
							<h2 class="acica-grupo__letra"><?php echo esc_html( '#' === $letra ? '0–9' : $letra ); ?></h2>
							<span class="acica-grupo__qtd"><?php echo esc_html( self::qtd( count( $lista ) ) ); ?></span>
						</div>
						<ul class="acica-itens">
							<?php foreach ( $lista as $it ) : ?>
								<li class="acica-item" data-k="<?php echo esc_attr( $it['busca'] ); ?>">
									<div class="acica-item__nome">
										<span class="acica-nome"><?php echo esc_html( $it['nome'] ); ?></span>
										<?php if ( '' !== $it['razao'] ) : ?>
											<span class="acica-razao"><?php echo esc_html( $it['razao'] ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( $tem_cnpj ) : ?>
										<div class="acica-item__cnpj">
											<?php if ( '' !== $it['cnpj'] ) : ?>
												<span class="acica-rotulo">CNPJ</span>
												<span class="acica-cnpj"><?php echo esc_html( $it['cnpj'] ); ?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>
									<?php if ( $tem_cont ) : ?>
										<div class="acica-item__contato">
											<?php if ( '' !== $it['tel_link'] ) : ?>
												<a class="acica-btn acica-btn--tel" href="<?php echo esc_url( $it['tel_link'], array( 'tel' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: empresa, 2: telefone */ __( 'Ligar para %1$s: %2$s', 'acic-associados' ), $it['nome'], $it['telefone'] ) ); ?>">
													<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"></path></svg>
													<?php echo esc_html( $it['telefone'] ); ?>
												</a>
											<?php endif; ?>
											<?php if ( '' !== $it['wa_link'] ) : ?>
												<a class="acica-btn acica-btn--wa" href="<?php echo esc_url( $it['wa_link'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: empresa */ __( 'Conversar com %s no WhatsApp (abre em nova aba)', 'acic-associados' ), $it['nome'] ) ); ?>">
													<svg aria-hidden="true" focusable="false" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l1.7-4.6A8.5 8.5 0 1 1 8 19.6z"></path><path d="M9 10.5c.5 1.6 1.9 3 3.5 3.5l1.2-1.1 2 .9c-.2 1.2-1.2 2-2.5 1.8-2.8-.4-5.4-3-5.8-5.8-.2-1.3.6-2.3 1.8-2.5l.9 2z"></path></svg>
													WhatsApp
												</a>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
		return preg_replace( '/>\s+</', '><', trim( ob_get_clean() ) );
	}

	/**
	 * Textos usados pelo script ao filtrar.
	 *
	 * @return array
	 */
	private static function textos() {
		return array(
			/* translators: %s: quantidade */
			'um'    => __( '%s associado', 'acic-associados' ),
			/* translators: %s: quantidade */
			'n'     => __( '%s associados', 'acic-associados' ),
			/* translators: %s: quantidade */
			'r1'    => __( '%s associado encontrado', 'acic-associados' ),
			/* translators: %s: quantidade */
			'rn'    => __( '%s associados encontrados', 'acic-associados' ),
			/* translators: %s: termo buscado */
			'para'  => __( ' para “%s”', 'acic-associados' ),
			/* translators: %s: letra */
			'letra' => __( ' na letra %s', 'acic-associados' ),
		);
	}

	/**
	 * "1 associado" / "N associados".
	 *
	 * @param int $n Quantidade.
	 * @return string
	 */
	private static function qtd( $n ) {
		/* translators: %s: quantidade */
		return sprintf( _n( '%s associado', '%s associados', $n, 'acic-associados' ), number_format_i18n( $n ) );
	}
}
