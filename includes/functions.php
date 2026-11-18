<?php
/**
 * Funções auxiliares: normalização de nomes, CNPJ e telefones.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

const ACICA_CPT     = 'acic_associado';
const ACICA_ATIVO   = 'publish';
const ACICA_INATIVO = 'acica_inativo';

/**
 * Remove acentos e passa para maiúsculas.
 *
 * @param string $text Texto.
 * @return string
 */
function acica_ascii_upper( $text ) {
	$text = remove_accents( (string) $text );
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
}

/**
 * Limpa espaços duplicados e quebras de linha.
 *
 * @param mixed $text Texto.
 * @return string
 */
function acica_clean( $text ) {
	$text = (string) $text;
	$text = str_replace( "\xC2\xA0", ' ', $text );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Chave usada para comparar razões sociais: sem acento, sem pontuação e sem
 * sufixos societários. "Farmácia Sagrado Coração Ltda." e "FARMACIA SAGRADO CORACAO"
 * viram a mesma chave.
 *
 * @param string $razao Razão social.
 * @return string
 */
function acica_chave_nome( $razao ) {
	$k = acica_ascii_upper( $razao );
	$k = preg_replace( '/[^A-Z0-9 ]+/', ' ', $k );
	$k = ' ' . preg_replace( '/\s+/', ' ', $k ) . ' ';
	$k = preg_replace( '/ (LTDA|ME|EPP|EIRELI|SA|S A|SS|S S|MEI|CIA) /', ' ', $k );
	$k = preg_replace( '/ (LTDA|ME|EPP|EIRELI|SA|S A|SS|S S|MEI|CIA) /', ' ', $k ); // Sufixos seguidos ("LTDA ME").
	return trim( preg_replace( '/\s+/', ' ', $k ) );
}

/**
 * Texto de busca (sem acento, maiúsculo) usado no filtro da página.
 *
 * @param string $text Texto.
 * @return string
 */
function acica_busca( $text ) {
	return acica_clean( preg_replace( '/[^A-Z0-9 ]+/', ' ', acica_ascii_upper( $text ) ) );
}

/**
 * Só os dígitos.
 *
 * @param mixed $value Valor.
 * @return string
 */
function acica_digits( $value ) {
	$value = (string) $value;
	// Números vindos do Excel em notação científica (1.2345678000199E+13).
	if ( preg_match( '/^\d+(\.\d+)?E\+?\d+$/i', trim( $value ) ) ) {
		$value = sprintf( '%.0f', (float) $value );
	} elseif ( preg_match( '/^\d+\.0+$/', trim( $value ) ) ) {
		$value = substr( trim( $value ), 0, strpos( trim( $value ), '.' ) );
	}
	return preg_replace( '/\D+/', '', $value );
}

/**
 * CNPJ só com dígitos (14), completando zeros à esquerda que o Excel costuma comer.
 *
 * @param mixed $value Valor.
 * @return string Vazio quando não parece um CNPJ.
 */
function acica_cnpj_digits( $value ) {
	$d = acica_digits( $value );
	if ( strlen( $d ) >= 12 && strlen( $d ) < 14 ) {
		$d = str_pad( $d, 14, '0', STR_PAD_LEFT );
	}
	return 14 === strlen( $d ) ? $d : '';
}

/**
 * CNPJ formatado 00.000.000/0000-00.
 *
 * @param string $value CNPJ.
 * @return string
 */
function acica_cnpj_format( $value ) {
	$d = acica_cnpj_digits( $value );
	if ( '' === $d ) {
		return '';
	}
	return substr( $d, 0, 2 ) . '.' . substr( $d, 2, 3 ) . '.' . substr( $d, 5, 3 ) . '/' . substr( $d, 8, 4 ) . '-' . substr( $d, 12, 2 );
}

/**
 * Telefone só com dígitos, sem o 55 e sem o zero do DDD.
 *
 * @param mixed $value Valor.
 * @return string
 */
function acica_fone_digits( $value ) {
	$d = acica_digits( $value );
	if ( strlen( $d ) >= 12 && 0 === strpos( $d, '55' ) ) {
		$d = substr( $d, 2 );
	}
	if ( strlen( $d ) >= 11 && '0' === $d[0] ) {
		$d = substr( $d, 1 );
	}
	return ( strlen( $d ) >= 8 && strlen( $d ) <= 11 ) ? $d : '';
}

/**
 * Telefone formatado: (49) 3563-1116 ou (49) 99154-7802.
 *
 * @param string $value Telefone.
 * @return string
 */
function acica_fone_format( $value ) {
	$d = acica_fone_digits( $value );
	switch ( strlen( $d ) ) {
		case 11:
			return '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 5 ) . '-' . substr( $d, 7 );
		case 10:
			return '(' . substr( $d, 0, 2 ) . ') ' . substr( $d, 2, 4 ) . '-' . substr( $d, 6 );
		case 9:
			return substr( $d, 0, 5 ) . '-' . substr( $d, 5 );
		case 8:
			return substr( $d, 0, 4 ) . '-' . substr( $d, 4 );
	}
	return '';
}

/**
 * Link tel: com DDI.
 *
 * @param string $value Telefone.
 * @return string
 */
function acica_fone_link( $value ) {
	$d = acica_fone_digits( $value );
	if ( strlen( $d ) < 10 ) {
		return '' === $d ? '' : 'tel:' . $d;
	}
	return 'tel:+55' . $d;
}

/**
 * Link do WhatsApp (wa.me). Só funciona com DDD.
 *
 * @param string $value Número.
 * @return string
 */
function acica_whatsapp_link( $value ) {
	$d = acica_fone_digits( $value );
	return strlen( $d ) >= 10 ? 'https://wa.me/55' . $d : '';
}

/**
 * Lista dos campos do associado (meta => rótulo).
 *
 * @return array
 */
function acica_campos() {
	return array(
		'razao'    => __( 'Razão social', 'acic-associados' ),
		'fantasia' => __( 'Nome fantasia', 'acic-associados' ),
		'cnpj'     => __( 'CNPJ', 'acic-associados' ),
		'telefone' => __( 'Telefone', 'acic-associados' ),
		'whatsapp' => __( 'WhatsApp', 'acic-associados' ),
	);
}

/**
 * Lê os dados de um associado.
 *
 * @param int|WP_Post $post Post.
 * @return array
 */
function acica_get( $post ) {
	$post = get_post( $post );
	$data = array(
		'id'     => $post ? (int) $post->ID : 0,
		'status' => $post ? $post->post_status : '',
	);
	foreach ( array_keys( acica_campos() ) as $campo ) {
		$data[ $campo ] = $post ? (string) get_post_meta( $post->ID, '_acica_' . $campo, true ) : '';
	}
	$data['fixo'] = $post ? (bool) get_post_meta( $post->ID, '_acica_fixo', true ) : false;
	return $data;
}

/**
 * Nome exibido: fantasia, ou a razão social quando não há fantasia.
 *
 * @param array $data Dados.
 * @return string
 */
function acica_nome_exibicao( $data ) {
	return '' !== $data['fantasia'] ? $data['fantasia'] : $data['razao'];
}

/**
 * Grava os dados de um associado (meta, título, texto de busca do painel).
 * Não muda o status.
 *
 * @param int   $post_id ID.
 * @param array $data    Campos (razao, fantasia, cnpj, telefone, whatsapp).
 */
function acica_save_meta( $post_id, $data ) {
	$data['cnpj']     = acica_cnpj_format( isset( $data['cnpj'] ) ? $data['cnpj'] : '' );
	$data['telefone'] = acica_fone_format( isset( $data['telefone'] ) ? $data['telefone'] : '' );
	$data['whatsapp'] = acica_fone_format( isset( $data['whatsapp'] ) ? $data['whatsapp'] : '' );

	foreach ( array_keys( acica_campos() ) as $campo ) {
		$value = isset( $data[ $campo ] ) ? acica_clean( $data[ $campo ] ) : '';
		if ( '' === $value ) {
			delete_post_meta( $post_id, '_acica_' . $campo );
		} else {
			update_post_meta( $post_id, '_acica_' . $campo, $value );
		}
	}
	update_post_meta( $post_id, '_acica_chave', acica_chave_nome( isset( $data['razao'] ) ? $data['razao'] : '' ) );
}

/**
 * Texto guardado no conteúdo do post (não aparece no site) para a busca nativa
 * do painel achar também pela razão social e pelo CNPJ.
 *
 * @param array $data Dados.
 * @return string
 */
function acica_texto_busca( $data ) {
	$cnpj = acica_cnpj_digits( isset( $data['cnpj'] ) ? $data['cnpj'] : '' );
	return trim( ( isset( $data['razao'] ) ? acica_clean( $data['razao'] ) : '' ) . ' ' . acica_cnpj_format( $cnpj ) . ' ' . $cnpj );
}

/**
 * Cria ou atualiza um associado.
 *
 * @param int    $post_id ID (0 para criar).
 * @param array  $data    Campos.
 * @param string $status  ACICA_ATIVO ou ACICA_INATIVO.
 * @return int ID, ou 0 em caso de erro.
 */
function acica_upsert( $post_id, $data, $status ) {
	$data = wp_parse_args( $data, array_fill_keys( array_keys( acica_campos() ), '' ) );
	$id   = wp_insert_post(
		array(
			'ID'           => (int) $post_id,
			'post_type'    => ACICA_CPT,
			'post_title'   => acica_nome_exibicao( $data ),
			'post_content' => acica_texto_busca( $data ),
			'post_status'  => $status,
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	acica_save_meta( $id, $data );
	return (int) $id;
}

/**
 * Limpa o cache da listagem do site.
 */
function acica_limpar_cache() {
	delete_transient( 'acica_lista_html' );
	delete_transient( 'acica_lista_dados' );
}
