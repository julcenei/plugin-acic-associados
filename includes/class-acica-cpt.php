<?php
/**
 * Tipo de post "Associado": cadastro, colunas, situação e filtros no painel.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cadastro manual dos associados.
 */
class ACICA_CPT {

	/**
	 * Ganchos.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'add_meta_boxes_' . ACICA_CPT, array( __CLASS__, 'meta_boxes' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'before_save' ), 10, 2 );
		add_action( 'save_post_' . ACICA_CPT, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'save_post_' . ACICA_CPT, 'acica_limpar_cache', 99 );
		add_action( 'deleted_post', array( __CLASS__, 'maybe_flush' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'maybe_flush' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'maybe_flush' ) );

		add_filter( 'manage_' . ACICA_CPT . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . ACICA_CPT . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . ACICA_CPT . '_sortable_columns', array( __CLASS__, 'sortable' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'admin_order' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'display_post_states', array( __CLASS__, 'post_states' ), 10, 2 );
		add_filter( 'views_edit-' . ACICA_CPT, array( __CLASS__, 'views' ) );
		add_action( 'admin_action_acica_situacao', array( __CLASS__, 'toggle' ) );
		add_filter( 'bulk_actions-edit-' . ACICA_CPT, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . ACICA_CPT, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
	}

	/**
	 * Registra o tipo de post e o status "Inativo".
	 */
	public static function register() {
		register_post_type(
			ACICA_CPT,
			array(
				'labels'              => array(
					'name'               => __( 'Associados', 'acic-associados' ),
					'singular_name'      => __( 'Associado', 'acic-associados' ),
					'menu_name'          => __( 'Associados', 'acic-associados' ),
					'all_items'          => __( 'Todos os associados', 'acic-associados' ),
					'add_new'            => __( 'Novo associado', 'acic-associados' ),
					'add_new_item'       => __( 'Novo associado', 'acic-associados' ),
					'edit_item'          => __( 'Editar associado', 'acic-associados' ),
					'new_item'           => __( 'Novo associado', 'acic-associados' ),
					'search_items'       => __( 'Buscar associados', 'acic-associados' ),
					'not_found'          => __( 'Nenhum associado encontrado.', 'acic-associados' ),
					'not_found_in_trash' => __( 'Nenhum associado na lixeira.', 'acic-associados' ),
					'item_updated'       => __( 'Associado atualizado.', 'acic-associados' ),
					'item_published'     => __( 'Associado salvo.', 'acic-associados' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'menu_icon'           => 'dashicons-groups',
				'menu_position'       => 25,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'rewrite'             => false,
				'query_var'           => false,
			)
		);

		register_post_status(
			ACICA_INATIVO,
			array(
				'label'                     => _x( 'Inativo', 'situação do associado', 'acic-associados' ),
				'public'                    => false,
				'internal'                  => false,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: quantidade */
				'label_count'               => _n_noop( 'Inativos <span class="count">(%s)</span>', 'Inativos <span class="count">(%s)</span>', 'acic-associados' ),
			)
		);
	}

	/**
	 * Placeholder do título.
	 *
	 * @param string  $text Texto.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		return ACICA_CPT === $post->post_type ? __( 'Nome fantasia (como aparece no site)', 'acic-associados' ) : $text;
	}

	/**
	 * Caixas da tela de edição.
	 */
	public static function meta_boxes() {
		add_meta_box( 'acica_dados', __( 'Dados do associado', 'acic-associados' ), array( __CLASS__, 'box_dados' ), ACICA_CPT, 'normal', 'high' );
		add_meta_box( 'acica_situacao', __( 'Situação', 'acic-associados' ), array( __CLASS__, 'box_situacao' ), ACICA_CPT, 'side', 'high' );
	}

	/**
	 * Campos do associado.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box_dados( $post ) {
		$d = acica_get( $post );
		wp_nonce_field( 'acica_save', 'acica_nonce' );
		$fields = array(
			'razao'    => array( __( 'Razão social', 'acic-associados' ), 'text', '' ),
			'cnpj'     => array( __( 'CNPJ', 'acic-associados' ), 'text', '00.000.000/0000-00' ),
			'telefone' => array( __( 'Telefone', 'acic-associados' ), 'tel', '(49) 0000-0000' ),
			'whatsapp' => array( __( 'WhatsApp', 'acic-associados' ), 'tel', '(49) 90000-0000' ),
		);
		$dicas = array(
			'razao'    => ACICA_Ajuda::dica( __( 'O nome registrado da empresa. Aparece em letra menor, abaixo do nome fantasia, quando os dois forem diferentes.', 'acic-associados' ), 'cadastro' ),
			'cnpj'     => ACICA_Ajuda::dica( __( 'Pode digitar só os números: o plugin coloca os pontos. Se for pessoa física, deixe em branco (CPF não aparece no site).', 'acic-associados' ), 'cadastro' ),
			'telefone' => ACICA_Ajuda::dica( __( 'Com DDD. No celular, o visitante toca no número e já liga.', 'acic-associados' ), 'cadastro' ),
			'whatsapp' => ACICA_Ajuda::dica( __( 'Número com DDD. O site mostra um botão verde que abre a conversa no WhatsApp.', 'acic-associados' ), 'cadastro' ),
		);
		echo '<div class="acica-campos">';
		foreach ( $fields as $key => $f ) {
			printf(
				'<p><span class="acica-rot"><label for="acica_%1$s">%2$s</label>%7$s</span><input type="%3$s" id="acica_%1$s" name="acica[%1$s]" value="%4$s" placeholder="%5$s" class="widefat"%6$s></p>',
				esc_attr( $key ),
				esc_html( $f[0] ),
				esc_attr( $f[1] ),
				esc_attr( $d[ $key ] ),
				esc_attr( $f[2] ),
				'cnpj' === $key || 'tel' === $f[1] ? ' inputmode="numeric"' : '',
				$dicas[ $key ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML montado e escapado em ACICA_Ajuda::dica().
			);
		}
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'O nome fantasia é o título acima. Sem nome fantasia, o site mostra a razão social. CNPJ, telefone e WhatsApp são opcionais: só aparecem no site quando preenchidos.', 'acic-associados' ) . '</p>';
	}

	/**
	 * Ativo/inativo e "manter sempre ativo".
	 *
	 * @param WP_Post $post Post.
	 */
	public static function box_situacao( $post ) {
		$inativo = ACICA_INATIVO === $post->post_status;
		$fixo    = (bool) get_post_meta( $post->ID, '_acica_fixo', true );
		?>
		<fieldset class="acica-situacao">
			<legend class="screen-reader-text"><?php esc_html_e( 'Situação', 'acic-associados' ); ?></legend>
			<label><input type="radio" name="acica_situacao" value="ativo" <?php checked( ! $inativo ); ?>> <?php esc_html_e( 'Ativo — aparece no site', 'acic-associados' ); ?></label><br>
			<label><input type="radio" name="acica_situacao" value="inativo" <?php checked( $inativo ); ?>> <?php esc_html_e( 'Inativo — fica só no painel', 'acic-associados' ); ?></label>
		</fieldset>
		<p><label><input type="checkbox" name="acica_fixo" value="1" <?php checked( $fixo ); ?>> <?php esc_html_e( 'Manter sempre ativo', 'acic-associados' ); ?></label><?php echo ACICA_Ajuda::dica( __( 'Use para quem não vem na planilha do sistema mas deve continuar no site, como um associado honorário.', 'acic-associados' ), 'cadastro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<p class="description"><?php esc_html_e( 'Marque para que a importação da planilha nunca inative este associado (ex.: associado honorário que não está na planilha).', 'acic-associados' ); ?></p>
		<?php
	}

	/**
	 * Define status e texto de busca antes de salvar pela tela de edição.
	 *
	 * @param array $data    Dados do post.
	 * @param array $postarr Dados enviados.
	 * @return array
	 */
	public static function before_save( $data, $postarr ) {
		if ( ACICA_CPT !== $data['post_type'] || ! self::is_form_save() ) {
			return $data;
		}
		if ( in_array( $data['post_status'], array( 'publish', 'draft', 'pending', 'private', 'future', ACICA_INATIVO ), true ) ) {
			$situacao            = isset( $_POST['acica_situacao'] ) ? sanitize_key( wp_unslash( $_POST['acica_situacao'] ) ) : 'ativo'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$data['post_status'] = 'inativo' === $situacao ? ACICA_INATIVO : ACICA_ATIVO;
		}
		$campos               = self::posted_fields();
		$campos['fantasia']   = $data['post_title'];
		$data['post_content'] = acica_texto_busca( $campos );
		if ( '' === trim( $data['post_title'] ) && '' !== $campos['razao'] ) {
			$data['post_title'] = $campos['razao'];
		}
		return $data;
	}

	/**
	 * Salva os campos da tela de edição.
	 *
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! self::is_form_save() || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$campos             = self::posted_fields();
		$campos['fantasia'] = acica_clean( $post->post_title );
		acica_save_meta( $post_id, $campos );
		if ( ! empty( $_POST['acica_fixo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_acica_fixo', 1 );
		} else {
			delete_post_meta( $post_id, '_acica_fixo' );
		}
	}

	/**
	 * O envio veio da tela de edição (com nonce válido)?
	 *
	 * @return bool
	 */
	private static function is_form_save() {
		return isset( $_POST['acica_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acica_nonce'] ) ), 'acica_save' );
	}

	/**
	 * Campos enviados pelo formulário.
	 *
	 * @return array
	 */
	private static function posted_fields() {
		$raw = isset( $_POST['acica'] ) && is_array( $_POST['acica'] ) ? wp_unslash( $_POST['acica'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$out = array();
		foreach ( array( 'razao', 'cnpj', 'telefone', 'whatsapp' ) as $k ) {
			$out[ $k ] = isset( $raw[ $k ] ) ? acica_clean( sanitize_text_field( $raw[ $k ] ) ) : '';
		}
		return $out;
	}

	/**
	 * Limpa o cache quando um associado é excluído ou vai para a lixeira.
	 *
	 * @param int          $post_id ID.
	 * @param WP_Post|null $post    Post.
	 */
	public static function maybe_flush( $post_id, $post = null ) {
		$post = $post ? $post : get_post( $post_id );
		if ( $post && ACICA_CPT === $post->post_type ) {
			acica_limpar_cache();
		}
	}

	/**
	 * Colunas da listagem.
	 *
	 * @param array $cols Colunas.
	 * @return array
	 */
	public static function columns( $cols ) {
		return array(
			'cb'             => $cols['cb'],
			'title'          => __( 'Nome fantasia', 'acic-associados' ),
			'acica_razao'    => __( 'Razão social', 'acic-associados' ),
			'acica_cnpj'     => __( 'CNPJ', 'acic-associados' ),
			'acica_contato'  => __( 'Contato', 'acic-associados' ),
			'acica_situacao' => __( 'Situação', 'acic-associados' ),
			'date'           => __( 'Cadastro', 'acic-associados' ),
		);
	}

	/**
	 * Conteúdo das colunas.
	 *
	 * @param string $col     Coluna.
	 * @param int    $post_id ID.
	 */
	public static function column( $col, $post_id ) {
		$d = acica_get( $post_id );
		switch ( $col ) {
			case 'acica_razao':
				echo esc_html( $d['razao'] );
				break;
			case 'acica_cnpj':
				echo '' !== $d['cnpj'] ? esc_html( $d['cnpj'] ) : '<span aria-hidden="true">—</span>';
				break;
			case 'acica_contato':
				$parts = array();
				if ( '' !== $d['telefone'] ) {
					$parts[] = '<span class="dashicons dashicons-phone" aria-hidden="true"></span> ' . esc_html( $d['telefone'] );
				}
				if ( '' !== $d['whatsapp'] ) {
					$parts[] = '<span class="dashicons dashicons-whatsapp" aria-hidden="true"></span> ' . esc_html( $d['whatsapp'] );
				}
				echo $parts ? implode( '<br>', $parts ) : '<span aria-hidden="true">—</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'acica_situacao':
				if ( ACICA_INATIVO === $d['status'] ) {
					echo '<span class="acica-tag acica-tag--inativo">' . esc_html__( 'Inativo', 'acic-associados' ) . '</span>';
				} elseif ( ACICA_ATIVO === $d['status'] ) {
					echo '<span class="acica-tag acica-tag--ativo">' . esc_html__( 'Ativo', 'acic-associados' ) . '</span>';
				} else {
					echo esc_html( get_post_status_object( $d['status'] ) ? get_post_status_object( $d['status'] )->label : $d['status'] );
				}
				if ( $d['fixo'] ) {
					echo ' <span class="dashicons dashicons-lock" title="' . esc_attr__( 'Manter sempre ativo', 'acic-associados' ) . '"></span>';
				}
				break;
		}
	}

	/**
	 * Colunas ordenáveis.
	 *
	 * @param array $cols Colunas.
	 * @return array
	 */
	public static function sortable( $cols ) {
		$cols['acica_razao'] = 'acica_razao';
		return $cols;
	}

	/**
	 * Ordem padrão alfabética e ordenação por razão social.
	 *
	 * @param WP_Query $q Consulta.
	 */
	public static function admin_order( $q ) {
		if ( ! is_admin() || ! $q->is_main_query() || ACICA_CPT !== $q->get( 'post_type' ) ) {
			return;
		}
		if ( 'acica_razao' === $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_acica_razao' );
			$q->set( 'orderby', 'meta_value' );
		} elseif ( ! $q->get( 'orderby' ) ) {
			$q->set( 'orderby', 'title' );
			$q->set( 'order', 'ASC' );
		}
	}

	/**
	 * Mostra "Inativo" ao lado do nome na lista "Todos".
	 *
	 * @param array   $states Estados.
	 * @param WP_Post $post   Post.
	 * @return array
	 */
	public static function post_states( $states, $post ) {
		if ( ACICA_CPT === $post->post_type ) {
			unset( $states[ ACICA_INATIVO ] );
		}
		return $states;
	}

	/**
	 * Troca "Publicados" por "Ativos" nos filtros da listagem.
	 *
	 * @param array $views Filtros.
	 * @return array
	 */
	public static function views( $views ) {
		if ( isset( $views['publish'] ) ) {
			$n                = (int) wp_count_posts( ACICA_CPT )->publish;
			$views['publish'] = preg_replace(
				'#(<a[^>]*>).*?</a>#s',
				'$1' . sprintf(
					/* translators: %s: quantidade */
					__( 'Ativos <span class="count">(%s)</span>', 'acic-associados' ),
					number_format_i18n( $n )
				) . '</a>',
				$views['publish']
			);
		}
		return $views;
	}

	/**
	 * Ação rápida "Inativar"/"Reativar" na linha.
	 *
	 * @param array   $actions Ações.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( ACICA_CPT !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'], $actions['view'] );
		$inativo = ACICA_INATIVO === $post->post_status;
		$url     = wp_nonce_url(
			admin_url( 'admin.php?action=acica_situacao&post=' . $post->ID . '&para=' . ( $inativo ? 'ativo' : 'inativo' ) ),
			'acica_situacao_' . $post->ID
		);
		$label   = $inativo ? __( 'Reativar', 'acic-associados' ) : __( 'Inativar', 'acic-associados' );
		$new     = array();
		foreach ( $actions as $key => $html ) {
			$new[ $key ] = $html;
			if ( 'edit' === $key ) {
				$new['acica_situacao'] = '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
			}
		}
		return $new;
	}

	/**
	 * Troca a situação pela ação rápida.
	 */
	public static function toggle() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'acica_situacao_' . $post_id );
		if ( ! $post_id || ACICA_CPT !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'acic-associados' ) );
		}
		$para = isset( $_GET['para'] ) && 'ativo' === $_GET['para'] ? ACICA_ATIVO : ACICA_INATIVO;
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $para,
			)
		);
		acica_limpar_cache();
		wp_safe_redirect( add_query_arg( 'acica_msg', ACICA_ATIVO === $para ? 'reativado' : 'inativado', wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=' . ACICA_CPT ) ) );
		exit;
	}

	/**
	 * Ações em massa.
	 *
	 * @param array $actions Ações.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		$actions['acica_inativar'] = __( 'Inativar', 'acic-associados' );
		$actions['acica_reativar'] = __( 'Reativar', 'acic-associados' );
		return $actions;
	}

	/**
	 * Executa as ações em massa.
	 *
	 * @param string $redirect URL.
	 * @param string $action   Ação.
	 * @param array  $ids      IDs.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( ! in_array( $action, array( 'acica_inativar', 'acica_reativar' ), true ) ) {
			return $redirect;
		}
		$status = 'acica_reativar' === $action ? ACICA_ATIVO : ACICA_INATIVO;
		$count  = 0;
		foreach ( $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				wp_update_post(
					array(
						'ID'          => (int) $id,
						'post_status' => $status,
					)
				);
				++$count;
			}
		}
		acica_limpar_cache();
		return add_query_arg(
			array(
				'acica_msg' => ACICA_ATIVO === $status ? 'reativados' : 'inativados',
				'acica_n'   => $count,
			),
			$redirect
		);
	}

	/**
	 * Avisos das ações rápidas.
	 */
	public static function notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . ACICA_CPT !== $screen->id || empty( $_GET['acica_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$n    = isset( $_GET['acica_n'] ) ? absint( $_GET['acica_n'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msgs = array(
			'reativado'  => __( 'Associado reativado: voltou a aparecer no site.', 'acic-associados' ),
			'inativado'  => __( 'Associado inativado: não aparece mais no site.', 'acic-associados' ),
			/* translators: %d: quantidade */
			'reativados' => sprintf( _n( '%d associado reativado.', '%d associados reativados.', $n, 'acic-associados' ), $n ),
			/* translators: %d: quantidade */
			'inativados' => sprintf( _n( '%d associado inativado.', '%d associados inativados.', $n, 'acic-associados' ), $n ),
		);
		$key = sanitize_key( wp_unslash( $_GET['acica_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $msgs[ $key ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msgs[ $key ] ) . '</p></div>';
		}
	}

	/**
	 * CSS do painel.
	 *
	 * @param string $hook Página.
	 */
	public static function admin_assets( $hook ) {
		$screen = get_current_screen();
		if ( $screen && ACICA_CPT === $screen->post_type ) {
			wp_enqueue_style( 'acica-admin', ACICA_URL . 'assets/css/admin.css', array(), ACICA_VERSION );
		}
	}
}
