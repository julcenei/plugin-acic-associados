<?php
/**
 * Prepara um WordPress de TESTES para as capturas do manual (nunca rode no site da ACIC).
 *
 * Uso: wp eval-file ferramentas/dados-demo.php /caminho/planilha.xls
 *
 * Deixa o cadastro num estado em que a próxima importação da planilha mostra um pouco
 * de tudo na conferência: novos, atualizados, que voltam e que saem do site.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

$planilha = isset( $args[0] ) ? $args[0] : getenv( 'ACICA_PLANILHA' );
if ( ! $planilha || ! file_exists( $planilha ) ) {
	exit( "Informe o caminho da planilha.\n" );
}
wp_set_current_user( 1 );

// 1) Zera associados, histórico e marcações de ajuda.
foreach ( get_posts( array( 'post_type' => ACICA_CPT, 'post_status' => 'any,' . ACICA_INATIVO, 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
delete_option( ACICA_Import::OPT_HIST );
delete_option( ACICA_Import::OPT_LOTE );
delete_user_meta( 1, 'acica_pp_oculto' );
delete_user_meta( 1, 'acica_guia_visto' );

// 2) Primeira importação (fica no histórico como de alguns dias atrás).
$d = ACICA_Planilha::ler( $planilha, basename( $planilha ) );
$p = ACICA_Import::montar_previa( $d['rows'], $d['colunas'], 'completa' );
$p['arquivo'] = 'Empresas_associadas_outubro.xls';
ACICA_Import::aplicar( $p );
$hist = get_option( ACICA_Import::OPT_HIST );
$hist[0]['quando'] = strtotime( '-26 days 10:12' );
update_option( ACICA_Import::OPT_HIST, $hist, false );
delete_option( ACICA_Import::OPT_LOTE );

$achar = static function ( $titulo ) {
	$q = get_posts( array( 'post_type' => ACICA_CPT, 'post_status' => 'any,' . ACICA_INATIVO, 'title' => $titulo, 'posts_per_page' => 1 ) );
	return $q ? $q[0]->ID : 0;
};

// 3) Diferenças para a próxima importação mostrar.
foreach ( array( 'O MONGE CHURRASCARIA E GRILL', 'TAMA SUPERMERCADO', 'SWOT SEGURANÇA' ) as $t ) { // Viram "novos".
	$id = $achar( $t );
	if ( $id ) {
		wp_delete_post( $id, true );
	}
}
foreach ( array( 'REVISTA FEELING' => 'REVISTA FEELING CAÇADOR', 'LAMINADOS AB' => 'AB LAMINADOS' ) as $t => $antigo ) { // "Atualizados".
	$id = $achar( $t );
	if ( $id ) {
		$dados             = acica_get( $id );
		$dados['fantasia'] = $antigo;
		acica_upsert( $id, $dados, ACICA_ATIVO );
	}
}
$id = $achar( 'POSTO TRIANGULO' ); // "Volta ao site".
if ( $id ) {
	wp_update_post( array( 'ID' => $id, 'post_status' => ACICA_INATIVO ) );
}
foreach ( array( 'MERCADO EXEMPLO LTDA' => 'MERCADO EXEMPLO', 'OFICINA MODELO LTDA' => 'OFICINA MODELO' ) as $r => $f ) { // "Saem do site".
	acica_upsert( 0, array( 'razao' => $r, 'fantasia' => $f ), ACICA_ATIVO );
}

// 4) Contatos ilustrativos (números fictícios) para mostrar os botões do site.
foreach ( array( '10 PASTEIS', 'A CABANHA', 'A FERMENTARIA PIZZA ARTESANAL', 'A JARDINEIRA' ) as $i => $t ) {
	$id = $achar( $t );
	if ( $id ) {
		$dados             = acica_get( $id );
		$dados['telefone'] = $i % 2 ? '' : '4930000000';
		$dados['whatsapp'] = 1 === $i ? '' : '49900000000';
		acica_upsert( $id, $dados, ACICA_ATIVO );
	}
}

// 5) Página com a lista.
$pag = get_page_by_path( 'associados' );
$conteudo = '[acic_associados]';
if ( $pag ) {
	wp_update_post( array( 'ID' => $pag->ID, 'post_title' => 'Associados ACIC', 'post_content' => $conteudo, 'post_status' => 'publish' ) );
} else {
	wp_insert_post( array( 'post_type' => 'page', 'post_name' => 'associados', 'post_title' => 'Associados ACIC', 'post_content' => $conteudo, 'post_status' => 'publish' ) );
}
acica_limpar_cache();
echo "Dados de demonstração prontos.\n";
