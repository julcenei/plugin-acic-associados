<?php
/**
 * Importação da planilha: prévia, aplicação, histórico e desfazer.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tela "Importar planilha".
 */
class ACICA_Import {

	const PAGE      = 'acica-importar';
	const CAP       = 'edit_others_posts';
	const OPT_LOTE  = 'acica_ultimo_lote';
	const OPT_HIST  = 'acica_historico';
	const MAX_HIST  = 20;
	const MAX_BYTES = 10485760; // 10 MB.

	/**
	 * Ganchos.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_acica_previa', array( __CLASS__, 'handle_previa' ) );
		add_action( 'admin_post_acica_aplicar', array( __CLASS__, 'handle_aplicar' ) );
		add_action( 'admin_post_acica_desfazer', array( __CLASS__, 'handle_desfazer' ) );
		add_action( 'admin_post_acica_cancelar', array( __CLASS__, 'handle_cancelar' ) );
	}

	/**
	 * Submenu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . ACICA_CPT,
			__( 'Importar planilha', 'acic-associados' ),
			__( 'Importar planilha', 'acic-associados' ),
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * URL da tela.
	 *
	 * @param array $args Parâmetros.
	 * @return string
	 */
	private static function url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => ACICA_CPT,
					'page'      => self::PAGE,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Chave do transient da prévia (por usuário).
	 *
	 * @return string
	 */
	private static function previa_key() {
		return 'acica_previa_' . get_current_user_id();
	}

	/* ---------------------------------------------------------------------
	 * Etapa 1: envio da planilha.
	 * ------------------------------------------------------------------- */

	/**
	 * Recebe o arquivo, monta a prévia e volta para a tela.
	 */
	public static function handle_previa() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'acic-associados' ) );
		}
		check_admin_referer( 'acica_previa' );

		$file = isset( $_FILES['planilha'] ) ? $_FILES['planilha'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::erro( __( 'Escolha a planilha antes de clicar em "Conferir planilha".', 'acic-associados' ) );
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			self::erro( __( 'O arquivo passa de 10 MB. Confira se é a planilha certa.', 'acic-associados' ) );
		}

		$name = sanitize_file_name( wp_unslash( $file['name'] ) );
		$data = ACICA_Planilha::ler( $file['tmp_name'], $name );
		if ( is_wp_error( $data ) ) {
			self::erro( $data->get_error_message() );
		}
		if ( empty( $data['rows'] ) ) {
			self::erro( __( 'A planilha não tem nenhuma empresa abaixo do cabeçalho.', 'acic-associados' ) );
		}

		$modo   = isset( $_POST['modo'] ) && 'parcial' === $_POST['modo'] ? 'parcial' : 'completa';
		$previa = self::montar_previa( $data['rows'], $data['colunas'], $modo );

		$previa['arquivo']      = $name;
		$previa['total_linhas'] = $data['total_linhas'];
		set_transient( self::previa_key(), $previa, 2 * HOUR_IN_SECONDS );

		wp_safe_redirect( self::url( array( 'etapa' => 'previa' ) ) );
		exit;
	}

	/**
	 * Volta para a tela com uma mensagem de erro.
	 *
	 * @param string $msg Mensagem.
	 */
	private static function erro( $msg ) {
		set_transient( 'acica_erro_' . get_current_user_id(), $msg, 300 );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Compara a planilha com o cadastro.
	 *
	 * @param array  $rows    Linhas da planilha.
	 * @param array  $colunas Campos encontrados no cabeçalho.
	 * @param string $modo    completa|parcial.
	 * @return array
	 */
	public static function montar_previa( $rows, $colunas, $modo ) {
		// 1) Unifica repetidas da planilha.
		$plan     = array();
		$by_cnpj  = array();
		$by_nome  = array();
		$unificad = array();
		foreach ( $rows as $row ) {
			$row['cnpj'] = acica_cnpj_digits( $row['cnpj'] );
			$k_nome      = acica_chave_nome( $row['razao'] );
			$idx         = null;
			if ( '' !== $row['cnpj'] && isset( $by_cnpj[ $row['cnpj'] ] ) ) {
				$idx = $by_cnpj[ $row['cnpj'] ];
			} elseif ( isset( $by_nome[ $k_nome ] ) ) {
				$cand = $by_nome[ $k_nome ];
				// Mesmo nome, CNPJs diferentes = empresas diferentes (filiais).
				if ( '' === $row['cnpj'] || '' === $plan[ $cand ]['cnpj'] || $plan[ $cand ]['cnpj'] === $row['cnpj'] ) {
					$idx = $cand;
				}
			}
			if ( null !== $idx ) {
				foreach ( $row as $campo => $valor ) {
					if ( '' === $plan[ $idx ][ $campo ] && '' !== $valor ) {
						$plan[ $idx ][ $campo ] = $valor;
					}
				}
				$unificad[] = array( $plan[ $idx ]['razao'], $row['razao'] );
				if ( '' !== $plan[ $idx ]['cnpj'] ) {
					$by_cnpj[ $plan[ $idx ]['cnpj'] ] = $idx;
				}
				continue;
			}
			$idx          = count( $plan );
			$plan[ $idx ] = $row;
			if ( '' !== $row['cnpj'] ) {
				$by_cnpj[ $row['cnpj'] ] = $idx;
			}
			if ( ! isset( $by_nome[ $k_nome ] ) ) {
				$by_nome[ $k_nome ] = $idx;
			}
		}

		// 2) Cadastro atual.
		$posts     = get_posts(
			array(
				'post_type'      => ACICA_CPT,
				'post_status'    => array( ACICA_ATIVO, ACICA_INATIVO, 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$atual     = array();
		$p_by_cnpj = array();
		$p_by_nome = array();
		foreach ( $posts as $p ) {
			$d                = acica_get( $p );
			$atual[ $p->ID ]  = $d;
			$cnpj             = acica_cnpj_digits( $d['cnpj'] );
			$k                = acica_chave_nome( $d['razao'] );
			if ( '' !== $cnpj && ! isset( $p_by_cnpj[ $cnpj ] ) ) {
				$p_by_cnpj[ $cnpj ] = $p->ID;
			}
			if ( '' !== $k ) {
				$p_by_nome[ $k ][] = $p->ID;
			}
		}

		// 3) Compara.
		$novos      = array();
		$alterados  = array();
		$reativados = array();
		$iguais     = 0;
		$usados     = array();
		foreach ( $plan as $item ) {
			$id = 0;
			if ( '' !== $item['cnpj'] && isset( $p_by_cnpj[ $item['cnpj'] ] ) && ! isset( $usados[ $p_by_cnpj[ $item['cnpj'] ] ] ) ) {
				$id = $p_by_cnpj[ $item['cnpj'] ];
			} else {
				$k = acica_chave_nome( $item['razao'] );
				foreach ( isset( $p_by_nome[ $k ] ) ? $p_by_nome[ $k ] : array() as $cand ) {
					$c_cnpj = acica_cnpj_digits( $atual[ $cand ]['cnpj'] );
					if ( ! isset( $usados[ $cand ] ) && ( '' === $item['cnpj'] || '' === $c_cnpj || $c_cnpj === $item['cnpj'] ) ) {
						$id = $cand;
						break;
					}
				}
			}

			if ( ! $id ) {
				$novos[] = $item;
				continue;
			}
			$usados[ $id ] = true;
			$mudancas      = self::diferencas( $atual[ $id ], $item, $colunas );
			$entry         = array(
				'id'       => $id,
				'nome'     => acica_nome_exibicao( $atual[ $id ] ),
				'item'     => $item,
				'mudancas' => $mudancas,
			);
			if ( ACICA_ATIVO !== $atual[ $id ]['status'] ) {
				$reativados[] = $entry;
			} elseif ( $mudancas ) {
				$alterados[] = $entry;
			} else {
				++$iguais;
			}
		}

		// 4) Quem sai do site.
		$saem = array();
		$fixos = 0;
		if ( 'completa' === $modo ) {
			foreach ( $atual as $id => $d ) {
				if ( isset( $usados[ $id ] ) || ACICA_ATIVO !== $d['status'] ) {
					continue;
				}
				if ( $d['fixo'] ) {
					++$fixos;
					continue;
				}
				$saem[] = array(
					'id'    => $id,
					'nome'  => acica_nome_exibicao( $d ),
					'razao' => $d['razao'],
				);
			}
		}

		$ativos = 0;
		foreach ( $atual as $d ) {
			if ( ACICA_ATIVO === $d['status'] ) {
				++$ativos;
			}
		}

		return array(
			'modo'       => $modo,
			'colunas'    => $colunas,
			'total'      => count( $plan ),
			'unificadas' => $unificad,
			'novos'      => $novos,
			'alterados'  => $alterados,
			'reativados' => $reativados,
			'saem'       => $saem,
			'iguais'     => $iguais,
			'fixos'      => $fixos,
			'ativos'     => $ativos,
			'criado'     => time(),
		);
	}

	/**
	 * Campos que a planilha mudaria. Campo vazio na planilha nunca apaga o que já está cadastrado.
	 *
	 * @param array $atual   Dados atuais.
	 * @param array $item    Linha da planilha.
	 * @param array $colunas Campos presentes na planilha.
	 * @return array campo => [antes, depois]
	 */
	private static function diferencas( $atual, $item, $colunas ) {
		$out = array();
		foreach ( $colunas as $campo ) {
			$novo = isset( $item[ $campo ] ) ? $item[ $campo ] : '';
			if ( '' === $novo ) {
				continue;
			}
			switch ( $campo ) {
				case 'cnpj':
					$igual = acica_cnpj_digits( $atual['cnpj'] ) === acica_cnpj_digits( $novo );
					$novo  = acica_cnpj_format( $novo );
					break;
				case 'telefone':
				case 'whatsapp':
					$igual = acica_fone_digits( $atual[ $campo ] ) === acica_fone_digits( $novo );
					$novo  = acica_fone_format( $novo );
					break;
				default:
					$igual = acica_clean( $atual[ $campo ] ) === acica_clean( $novo );
			}
			if ( ! $igual && '' !== $novo ) {
				$out[ $campo ] = array( $atual[ $campo ], $novo );
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Etapa 2: aplicar.
	 * ------------------------------------------------------------------- */

	/**
	 * Aplica a prévia guardada.
	 */
	public static function handle_aplicar() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'acic-associados' ) );
		}
		check_admin_referer( 'acica_aplicar' );

		$previa = get_transient( self::previa_key() );
		if ( ! is_array( $previa ) ) {
			self::erro( __( 'A conferência expirou. Envie a planilha de novo.', 'acic-associados' ) );
		}

		$res = self::aplicar( $previa );
		delete_transient( self::previa_key() );

		set_transient( 'acica_feito_' . get_current_user_id(), $res, 600 );
		wp_safe_redirect( self::url( array( 'etapa' => 'pronto' ) ) );
		exit;
	}

	/**
	 * Grava as mudanças, guardando o estado anterior para poder desfazer.
	 *
	 * @param array $previa Prévia.
	 * @return array Resumo.
	 */
	public static function aplicar( $previa ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		wp_defer_term_counting( true );
		remove_action( 'save_post_' . ACICA_CPT, 'acica_limpar_cache', 99 );

		$lote = array(
			'quando'  => time(),
			'criados' => array(),
			'antes'   => array(),
		);

		foreach ( $previa['novos'] as $item ) {
			$id = acica_upsert( 0, $item, ACICA_ATIVO );
			if ( $id ) {
				$lote['criados'][] = $id;
			}
		}

		foreach ( array_merge( $previa['alterados'], $previa['reativados'] ) as $entry ) {
			$id = (int) $entry['id'];
			if ( ACICA_CPT !== get_post_type( $id ) ) {
				continue;
			}
			$antes                = acica_get( $id );
			$lote['antes'][ $id ] = $antes;
			$dados                = $antes;
			foreach ( $entry['mudancas'] as $campo => $par ) {
				$dados[ $campo ] = $par[1];
			}
			acica_upsert( $id, $dados, ACICA_ATIVO );
		}

		foreach ( $previa['saem'] as $entry ) {
			$id = (int) $entry['id'];
			if ( ACICA_CPT !== get_post_type( $id ) || ACICA_ATIVO !== get_post_status( $id ) ) {
				continue;
			}
			$lote['antes'][ $id ] = acica_get( $id );
			wp_update_post(
				array(
					'ID'          => $id,
					'post_status' => ACICA_INATIVO,
				)
			);
		}

		wp_defer_term_counting( false );
		add_action( 'save_post_' . ACICA_CPT, 'acica_limpar_cache', 99 );
		acica_limpar_cache();

		$resumo = array(
			'quando'     => $lote['quando'],
			'usuario'    => get_current_user_id(),
			'arquivo'    => isset( $previa['arquivo'] ) ? $previa['arquivo'] : '',
			'modo'       => $previa['modo'],
			'novos'      => count( $lote['criados'] ),
			'alterados'  => count( $previa['alterados'] ),
			'reativados' => count( $previa['reativados'] ),
			'saem'       => count( $previa['saem'] ),
			'desfeito'   => false,
		);

		$lote['resumo'] = $resumo;
		update_option( self::OPT_LOTE, $lote, false );

		$hist = get_option( self::OPT_HIST, array() );
		$hist = is_array( $hist ) ? $hist : array();
		array_unshift( $hist, $resumo );
		update_option( self::OPT_HIST, array_slice( $hist, 0, self::MAX_HIST ), false );

		return $resumo;
	}

	/* ---------------------------------------------------------------------
	 * Desfazer e cancelar.
	 * ------------------------------------------------------------------- */

	/**
	 * Desfaz a última importação.
	 */
	public static function handle_desfazer() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Sem permissão.', 'acic-associados' ) );
		}
		check_admin_referer( 'acica_desfazer' );

		$lote = get_option( self::OPT_LOTE );
		if ( ! is_array( $lote ) ) {
			self::erro( __( 'Não há importação para desfazer.', 'acic-associados' ) );
		}

		remove_action( 'save_post_' . ACICA_CPT, 'acica_limpar_cache', 99 );
		foreach ( $lote['criados'] as $id ) {
			if ( ACICA_CPT === get_post_type( $id ) ) {
				wp_delete_post( (int) $id, true );
			}
		}
		foreach ( $lote['antes'] as $id => $d ) {
			if ( ACICA_CPT === get_post_type( $id ) ) {
				$status = in_array( $d['status'], array( ACICA_ATIVO, ACICA_INATIVO ), true ) ? $d['status'] : ACICA_INATIVO;
				acica_upsert( (int) $id, $d, $status );
			}
		}
		add_action( 'save_post_' . ACICA_CPT, 'acica_limpar_cache', 99 );
		acica_limpar_cache();

		$hist = get_option( self::OPT_HIST, array() );
		if ( is_array( $hist ) ) {
			foreach ( $hist as $i => $h ) {
				if ( (int) $h['quando'] === (int) $lote['quando'] ) {
					$hist[ $i ]['desfeito'] = true;
					break;
				}
			}
			update_option( self::OPT_HIST, $hist, false );
		}
		delete_option( self::OPT_LOTE );

		set_transient( 'acica_ok_' . get_current_user_id(), __( 'Importação desfeita. Os associados voltaram a ser como estavam antes dela.', 'acic-associados' ), 300 );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Descarta a prévia.
	 */
	public static function handle_cancelar() {
		check_admin_referer( 'acica_cancelar' );
		delete_transient( self::previa_key() );
		wp_safe_redirect( self::url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Tela.
	 * ------------------------------------------------------------------- */

	/**
	 * Renderiza a tela conforme a etapa.
	 */
	public static function render() {
		$etapa = isset( $_GET['etapa'] ) ? sanitize_key( wp_unslash( $_GET['etapa'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$previa = get_transient( self::previa_key() );
		$feito  = get_transient( 'acica_feito_' . get_current_user_id() );

		echo '<div class="wrap acica-wrap">';
		echo '<h1>' . esc_html__( 'Atualizar associados pela planilha', 'acic-associados' ) . '</h1>';

		$step = 1;
		if ( 'previa' === $etapa && is_array( $previa ) ) {
			$step = 2;
		} elseif ( 'pronto' === $etapa && is_array( $feito ) ) {
			$step = 3;
		}
		self::passos( $step );

		foreach ( array( 'erro' => 'error', 'ok' => 'success' ) as $key => $class ) {
			$msg = get_transient( 'acica_' . $key . '_' . get_current_user_id() );
			if ( $msg ) {
				delete_transient( 'acica_' . $key . '_' . get_current_user_id() );
				echo '<div class="notice notice-' . esc_attr( $class ) . '"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}

		if ( 2 === $step ) {
			self::render_previa( $previa );
		} elseif ( 3 === $step ) {
			delete_transient( 'acica_feito_' . get_current_user_id() );
			self::render_pronto( $feito );
		} else {
			self::render_envio();
			self::render_historico();
		}
		echo '</div>';
	}

	/**
	 * Indicador de etapas.
	 *
	 * @param int $step Etapa atual.
	 */
	private static function passos( $step ) {
		$labels = array(
			1 => __( 'Enviar planilha', 'acic-associados' ),
			2 => __( 'Conferir', 'acic-associados' ),
			3 => __( 'Pronto', 'acic-associados' ),
		);
		echo '<ol class="acica-passos">';
		foreach ( $labels as $n => $label ) {
			$class = $n === $step ? 'is-atual' : ( $n < $step ? 'is-feito' : '' );
			printf(
				'<li class="%1$s"%4$s><span class="acica-passos__n">%2$d</span> %3$s</li>',
				esc_attr( $class ),
				(int) $n,
				esc_html( $label ),
				$n === $step ? ' aria-current="step"' : ''
			);
		}
		echo '</ol>';
	}

	/**
	 * Formulário de envio.
	 */
	private static function render_envio() {
		?>
		<div class="acica-card">
			<h2><?php esc_html_e( 'Envie a planilha exportada do sistema', 'acic-associados' ); ?></h2>
			<p><?php esc_html_e( 'Pode enviar o arquivo do jeito que sai do sistema (.xls, .xlsx ou .csv). Nada muda no site agora: primeiro você confere o que vai acontecer.', 'acic-associados' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="acica_previa">
				<?php wp_nonce_field( 'acica_previa' ); ?>
				<p>
					<label for="acica-planilha" class="acica-label"><?php esc_html_e( 'Planilha', 'acic-associados' ); ?></label>
					<input type="file" id="acica-planilha" name="planilha" accept=".xlsx,.xls,.csv" required>
				</p>
				<fieldset class="acica-modo">
					<legend class="acica-label"><?php esc_html_e( 'O que tem nesta planilha?', 'acic-associados' ); ?></legend>
					<label class="acica-opcao">
						<input type="radio" name="modo" value="completa" checked>
						<span><strong><?php esc_html_e( 'A lista completa de associados', 'acic-associados' ); ?></strong><br>
						<?php esc_html_e( 'Quem não estiver na planilha fica inativo e sai do site (continua guardado no painel).', 'acic-associados' ); ?></span>
					</label>
					<label class="acica-opcao">
						<input type="radio" name="modo" value="parcial">
						<span><strong><?php esc_html_e( 'Só alguns associados', 'acic-associados' ); ?></strong><br>
						<?php esc_html_e( 'Adiciona e atualiza os que estão na planilha, sem tirar ninguém do site.', 'acic-associados' ); ?></span>
					</label>
				</fieldset>
				<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Conferir planilha', 'acic-associados' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Prévia das mudanças.
	 *
	 * @param array $p Prévia.
	 */
	private static function render_previa( $p ) {
		$nomes_campos = acica_campos();
		$cols         = array();
		foreach ( $p['colunas'] as $c ) {
			$cols[] = $nomes_campos[ $c ];
		}
		$faltam = array_diff( array( 'cnpj', 'telefone', 'whatsapp' ), $p['colunas'] );
		$total_mudancas = count( $p['novos'] ) + count( $p['alterados'] ) + count( $p['reativados'] ) + count( $p['saem'] );
		?>
		<div class="acica-card">
			<p class="acica-arquivo">
				<span class="dashicons dashicons-media-spreadsheet" aria-hidden="true"></span>
				<strong><?php echo esc_html( $p['arquivo'] ); ?></strong> ·
				<?php
				/* translators: 1: linhas na planilha, 2: empresas após unificar */
				echo esc_html( sprintf( __( '%1$d linhas, %2$d empresas', 'acic-associados' ), $p['total_linhas'], $p['total'] ) );
				?>
				·
				<?php echo esc_html( 'completa' === $p['modo'] ? __( 'lista completa', 'acic-associados' ) : __( 'só alguns associados', 'acic-associados' ) ); ?>
			</p>
			<p class="description">
				<?php
				/* translators: %s: colunas */
				echo esc_html( sprintf( __( 'Colunas encontradas: %s.', 'acic-associados' ), implode( ', ', $cols ) ) );
				if ( $faltam ) {
					$f = array();
					foreach ( $faltam as $c ) {
						$f[] = $nomes_campos[ $c ];
					}
					echo ' ';
					/* translators: %s: campos */
					echo esc_html( sprintf( __( '%s não vieram na planilha: o que já estiver cadastrado no painel é mantido.', 'acic-associados' ), implode( ', ', $f ) ) );
				}
				?>
			</p>

			<div class="acica-numeros">
				<?php
				self::numero( count( $p['novos'] ), __( 'novos', 'acic-associados' ), 'novo' );
				self::numero( count( $p['alterados'] ), __( 'com dados atualizados', 'acic-associados' ), 'alt' );
				self::numero( count( $p['reativados'] ), __( 'voltam ao site', 'acic-associados' ), 'reat' );
				if ( 'completa' === $p['modo'] ) {
					self::numero( count( $p['saem'] ), __( 'saem do site', 'acic-associados' ), 'sai' );
				}
				self::numero( $p['iguais'], __( 'sem mudança', 'acic-associados' ), 'igual' );
				?>
			</div>

			<?php if ( 'completa' === $p['modo'] && $p['ativos'] > 20 && count( $p['saem'] ) > $p['ativos'] * 0.2 ) : ?>
				<div class="notice notice-warning inline"><p>
					<?php
					/* translators: %d: quantidade */
					echo esc_html( sprintf( __( 'Atenção: %d associados vão sair do site, mais de 20%% da lista atual. Confira se a planilha está completa antes de aplicar.', 'acic-associados' ), count( $p['saem'] ) ) );
					?>
				</p></div>
			<?php endif; ?>

			<?php
			self::lista_simples( __( 'Novos associados', 'acic-associados' ), $p['novos'], 'novo' );
			self::lista_mudancas( __( 'Com dados atualizados', 'acic-associados' ), $p['alterados'] );
			self::lista_mudancas( __( 'Voltam a aparecer no site', 'acic-associados' ), $p['reativados'], true );
			if ( 'completa' === $p['modo'] ) {
				self::lista_simples( __( 'Saem do site (ficam inativos no painel)', 'acic-associados' ), $p['saem'], 'sai' );
			}
			if ( $p['unificadas'] ) {
				echo '<details class="acica-det"><summary>';
				/* translators: %d: quantidade */
				echo esc_html( sprintf( _n( '%d linha repetida foi unificada', '%d linhas repetidas foram unificadas', count( $p['unificadas'] ), 'acic-associados' ), count( $p['unificadas'] ) ) );
				echo '</summary><p class="description">' . esc_html__( 'Empresas que apareciam mais de uma vez na planilha (mesmo nome, com variação de acento, pontuação ou LTDA/ME). Cada uma vira um associado só.', 'acic-associados' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fica', 'acic-associados' ) . '</th><th>' . esc_html__( 'Repetida', 'acic-associados' ) . '</th></tr></thead><tbody>';
				foreach ( $p['unificadas'] as $par ) {
					echo '<tr><td>' . esc_html( $par[0] ) . '</td><td>' . esc_html( $par[1] ) . '</td></tr>';
				}
				echo '</tbody></table></details>';
			}
			if ( $p['fixos'] ) {
				/* translators: %d: quantidade */
				echo '<p class="description">' . esc_html( sprintf( _n( '%d associado marcado como "Manter sempre ativo" não está na planilha e continua no site.', '%d associados marcados como "Manter sempre ativo" não estão na planilha e continuam no site.', $p['fixos'], 'acic-associados' ), $p['fixos'] ) ) . '</p>';
			}
			?>

			<div class="acica-acoes">
				<?php if ( $total_mudancas > 0 ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acica-form-aplicar">
						<input type="hidden" name="action" value="acica_aplicar">
						<?php wp_nonce_field( 'acica_aplicar' ); ?>
						<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Aplicar alterações', 'acic-associados' ); ?></button>
					</form>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'Está tudo igual: nada para mudar.', 'acic-associados' ); ?></strong></p>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="acica_cancelar">
					<?php wp_nonce_field( 'acica_cancelar' ); ?>
					<button type="submit" class="button button-hero"><?php echo esc_html( $total_mudancas > 0 ? __( 'Cancelar', 'acic-associados' ) : __( 'Voltar', 'acic-associados' ) ); ?></button>
				</form>
			</div>
			<?php if ( $total_mudancas > 0 ) : ?>
				<p class="description"><?php esc_html_e( 'Depois de aplicar, dá para desfazer esta importação se algo estiver errado.', 'acic-associados' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Quadro com número.
	 *
	 * @param int    $n     Quantidade.
	 * @param string $label Rótulo.
	 * @param string $tipo  Classe.
	 */
	private static function numero( $n, $label, $tipo ) {
		printf(
			'<div class="acica-num acica-num--%1$s%4$s"><span class="acica-num__n">%2$s</span><span class="acica-num__l">%3$s</span></div>',
			esc_attr( $tipo ),
			esc_html( number_format_i18n( $n ) ),
			esc_html( $label ),
			$n ? '' : ' is-zero'
		);
	}

	/**
	 * Lista recolhível de nomes.
	 *
	 * @param string $titulo Título.
	 * @param array  $itens  Itens.
	 * @param string $tipo   novo|sai.
	 */
	private static function lista_simples( $titulo, $itens, $tipo ) {
		if ( ! $itens ) {
			return;
		}
		echo '<details class="acica-det acica-det--' . esc_attr( $tipo ) . '"><summary>' . esc_html( $titulo ) . ' <span class="count">(' . esc_html( number_format_i18n( count( $itens ) ) ) . ')</span></summary>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Nome fantasia', 'acic-associados' ) . '</th><th>' . esc_html__( 'Razão social', 'acic-associados' ) . '</th>';
		if ( 'novo' === $tipo ) {
			echo '<th>' . esc_html__( 'CNPJ', 'acic-associados' ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $itens as $it ) {
			if ( 'novo' === $tipo ) {
				echo '<tr><td>' . esc_html( '' !== $it['fantasia'] ? $it['fantasia'] : $it['razao'] ) . '</td><td>' . esc_html( $it['razao'] ) . '</td><td>' . esc_html( acica_cnpj_format( $it['cnpj'] ) ) . '</td></tr>';
			} else {
				echo '<tr><td><a href="' . esc_url( get_edit_post_link( $it['id'] ) ) . '">' . esc_html( $it['nome'] ) . '</a></td><td>' . esc_html( $it['razao'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table></details>';
	}

	/**
	 * Lista recolhível com o "antes → depois".
	 *
	 * @param string $titulo     Título.
	 * @param array  $itens      Itens.
	 * @param bool   $reativados É a lista de reativados.
	 */
	private static function lista_mudancas( $titulo, $itens, $reativados = false ) {
		if ( ! $itens ) {
			return;
		}
		$campos = acica_campos();
		echo '<details class="acica-det"><summary>' . esc_html( $titulo ) . ' <span class="count">(' . esc_html( number_format_i18n( count( $itens ) ) ) . ')</span></summary>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Associado', 'acic-associados' ) . '</th><th>' . esc_html__( 'O que muda', 'acic-associados' ) . '</th></tr></thead><tbody>';
		foreach ( $itens as $it ) {
			$linhas = array();
			foreach ( $it['mudancas'] as $campo => $par ) {
				$linhas[] = '<strong>' . esc_html( $campos[ $campo ] ) . ':</strong> ' . ( '' !== $par[0] ? '<del>' . esc_html( $par[0] ) . '</del> → ' : '' ) . '<ins>' . esc_html( $par[1] ) . '</ins>';
			}
			if ( ! $linhas && $reativados ) {
				$linhas[] = esc_html__( 'Estava inativo e voltou à planilha.', 'acic-associados' );
			}
			echo '<tr><td><a href="' . esc_url( get_edit_post_link( $it['id'] ) ) . '">' . esc_html( $it['nome'] ) . '</a></td><td>' . implode( '<br>', $linhas ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</tbody></table></details>';
	}

	/**
	 * Resultado depois de aplicar.
	 *
	 * @param array $r Resumo.
	 */
	private static function render_pronto( $r ) {
		?>
		<div class="acica-card acica-card--ok">
			<h2><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Pronto! A lista do site já está atualizada.', 'acic-associados' ); ?></h2>
			<div class="acica-numeros">
				<?php
				self::numero( $r['novos'], __( 'novos', 'acic-associados' ), 'novo' );
				self::numero( $r['alterados'], __( 'atualizados', 'acic-associados' ), 'alt' );
				self::numero( $r['reativados'], __( 'voltaram ao site', 'acic-associados' ), 'reat' );
				if ( 'completa' === $r['modo'] ) {
					self::numero( $r['saem'], __( 'saíram do site', 'acic-associados' ), 'sai' );
				}
				?>
			</div>
			<p class="acica-acoes">
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . ACICA_CPT ) ); ?>"><?php esc_html_e( 'Ver associados', 'acic-associados' ); ?></a>
				<a class="button button-hero" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Voltar', 'acic-associados' ); ?></a>
			</p>
			<?php self::botao_desfazer(); ?>
		</div>
		<?php
	}

	/**
	 * Botão "Desfazer" da última importação.
	 */
	private static function botao_desfazer() {
		$lote = get_option( self::OPT_LOTE );
		if ( ! is_array( $lote ) ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acica-desfazer" onsubmit="return confirm('<?php echo esc_js( __( 'Desfazer a última importação? Os associados voltam a ser como estavam antes dela (inclusive edições feitas depois nesses associados).', 'acic-associados' ) ); ?>');">
			<input type="hidden" name="action" value="acica_desfazer">
			<?php wp_nonce_field( 'acica_desfazer' ); ?>
			<button type="submit" class="button-link acica-link-desfazer">
				<span class="dashicons dashicons-undo" aria-hidden="true"></span>
				<?php
				/* translators: %s: data */
				echo esc_html( sprintf( __( 'Desfazer a última importação (%s)', 'acic-associados' ), wp_date( 'd/m/Y H:i', (int) $lote['quando'] ) ) );
				?>
			</button>
		</form>
		<?php
	}

	/**
	 * Histórico das importações.
	 */
	private static function render_historico() {
		$hist = get_option( self::OPT_HIST, array() );
		if ( ! is_array( $hist ) || ! $hist ) {
			return;
		}
		?>
		<div class="acica-card">
			<h2><?php esc_html_e( 'Importações anteriores', 'acic-associados' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Data', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Arquivo', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Por', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Novos', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Atualizados', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Voltaram', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Saíram', 'acic-associados' ); ?></th>
					<th><?php esc_html_e( 'Situação', 'acic-associados' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $hist as $h ) : ?>
					<?php $user = get_userdata( (int) $h['usuario'] ); ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i', (int) $h['quando'] ) ); ?></td>
						<td><?php echo esc_html( $h['arquivo'] ); ?></td>
						<td><?php echo esc_html( $user ? $user->display_name : '—' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $h['novos'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $h['alterados'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $h['reativados'] ) ); ?></td>
						<td><?php echo esc_html( 'completa' === $h['modo'] ? number_format_i18n( $h['saem'] ) : '—' ); ?></td>
						<td><?php echo $h['desfeito'] ? esc_html__( 'Desfeita', 'acic-associados' ) : esc_html__( 'Aplicada', 'acic-associados' ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php self::botao_desfazer(); ?>
		</div>
		<?php
	}
}
