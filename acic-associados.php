<?php
/**
 * Plugin Name:       ACIC Associados
 * Plugin URI:        https://github.com/julcenei/plugin-acic-associados
 * Description:       Cadastro dos associados da ACIC Caçador com importação da planilha (prévia, sincronização e desfazer) e listagem moderna com busca e filtro A–Z pelo shortcode [acic_associados].
 * Version:           1.1.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Jotapix Agência Criativa
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acic-associados
 * Update URI:        https://github.com/julcenei/plugin-acic-associados
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

define( 'ACICA_VERSION', '1.1.1' );
define( 'ACICA_FILE', __FILE__ );
define( 'ACICA_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACICA_URL', plugin_dir_url( __FILE__ ) );

require_once ACICA_DIR . 'includes/functions.php';
require_once ACICA_DIR . 'includes/class-acica-cpt.php';
require_once ACICA_DIR . 'includes/class-acica-planilha.php';
require_once ACICA_DIR . 'includes/class-acica-import.php';
require_once ACICA_DIR . 'includes/class-acica-shortcode.php';
require_once ACICA_DIR . 'includes/class-acica-ajuda.php';
require_once ACICA_DIR . 'includes/class-acica-updater.php';

ACICA_CPT::init();
ACICA_Import::init();
ACICA_Shortcode::init();
ACICA_Ajuda::init();
ACICA_Updater::init();

register_activation_hook(
	__FILE__,
	static function () {
		ACICA_CPT::register();
		flush_rewrite_rules();
	}
);
