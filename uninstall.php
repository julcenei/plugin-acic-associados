<?php
/**
 * Ao excluir o plugin, remove só configurações e cache.
 * Os associados cadastrados são mantidos no banco de propósito: excluir o plugin
 * por engano não apaga a lista.
 *
 * @package ACIC_Associados
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acica_ultimo_lote' );
delete_option( 'acica_historico' );
delete_transient( 'acica_lista_html' );
delete_transient( 'acica_lista_dados' );
delete_site_transient( 'acica_github_release' );
