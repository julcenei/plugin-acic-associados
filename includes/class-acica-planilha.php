<?php
/**
 * Leitura da planilha de associados (.xlsx ou .csv) sem bibliotecas externas.
 *
 * @package ACIC_Associados
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converte a planilha em linhas com os campos do associado.
 */
class ACICA_Planilha {

	/**
	 * Nomes de coluna aceitos para cada campo (comparados sem acento, espaço ou pontuação).
	 *
	 * @var array
	 */
	private static $aliases = array(
		'razao'    => array( 'NOME', 'RAZAO', 'RAZAOSOCIAL', 'NOMERAZAO', 'EMPRESA', 'NOMEEMPRESARIAL' ),
		'fantasia' => array( 'NOMEFANTASIA', 'FANTASIA' ),
		'cnpj'     => array( 'CNPJ', 'CNPJCPF', 'CPFCNPJ', 'DOCUMENTO' ),
		'telefone' => array( 'TELEFONE', 'FONE', 'TEL', 'TELEFONE1', 'FONE1', 'TELEFONECOMERCIAL' ),
		'whatsapp' => array( 'WHATSAPP', 'WHATS', 'ZAP', 'CELULAR', 'CEL', 'TELEFONECELULAR' ),
	);

	/**
	 * Lê o arquivo enviado.
	 *
	 * @param string $path Caminho temporário.
	 * @param string $name Nome original.
	 * @return array|WP_Error { rows: array, colunas: array, total_linhas: int }
	 */
	public static function ler( $path, $name ) {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'xlsx' === $ext ) {
			$grid = self::ler_xlsx( $path );
		} elseif ( 'csv' === $ext || 'txt' === $ext ) {
			$grid = self::ler_csv( $path );
		} elseif ( 'xls' === $ext ) {
			return new WP_Error( 'acica_xls', __( 'Este arquivo está no formato antigo do Excel (.xls). Abra no Excel e use "Salvar como" › "Pasta de Trabalho do Excel (.xlsx)", ou salve como .csv, e envie de novo.', 'acic-associados' ) );
		} else {
			return new WP_Error( 'acica_ext', __( 'Envie a planilha em .xlsx ou .csv.', 'acic-associados' ) );
		}
		if ( is_wp_error( $grid ) ) {
			return $grid;
		}
		return self::mapear( $grid );
	}

	/**
	 * Identifica as colunas pelo cabeçalho e monta as linhas.
	 *
	 * @param array $grid Linhas e células.
	 * @return array|WP_Error
	 */
	private static function mapear( $grid ) {
		$header_index = -1;
		$map          = array();
		// O cabeçalho costuma ser a 1ª linha, mas alguns relatórios têm título antes.
		foreach ( array_slice( $grid, 0, 10, true ) as $i => $row ) {
			$found = array();
			foreach ( $row as $col => $cell ) {
				$label = preg_replace( '/[^A-Z0-9]/', '', acica_ascii_upper( $cell ) );
				foreach ( self::$aliases as $campo => $names ) {
					if ( ! isset( $found[ $campo ] ) && in_array( $label, $names, true ) ) {
						$found[ $campo ] = $col;
						break;
					}
				}
			}
			if ( isset( $found['razao'] ) ) {
				$header_index = $i;
				$map          = $found;
				break;
			}
		}
		if ( $header_index < 0 ) {
			return new WP_Error( 'acica_header', __( 'Não encontrei a coluna com a razão social. A planilha precisa ter um cabeçalho como "NOME" ou "Razão social" (e, se tiver, "Nome fantasia", "CNPJ", "Telefone", "WhatsApp").', 'acic-associados' ) );
		}

		$rows  = array();
		$total = 0;
		foreach ( $grid as $i => $row ) {
			if ( $i <= $header_index ) {
				continue;
			}
			$item = array();
			foreach ( array_keys( self::$aliases ) as $campo ) {
				$item[ $campo ] = isset( $map[ $campo ], $row[ $map[ $campo ] ] ) ? acica_clean( $row[ $map[ $campo ] ] ) : '';
			}
			if ( '' === $item['razao'] ) {
				if ( '' === $item['fantasia'] ) {
					continue;
				}
				$item['razao'] = $item['fantasia'];
			}
			++$total;
			$rows[] = $item;
		}

		return array(
			'rows'         => $rows,
			'colunas'      => array_keys( $map ),
			'total_linhas' => $total,
		);
	}

	/**
	 * Lê a primeira aba de um .xlsx.
	 *
	 * @param string $path Arquivo.
	 * @return array|WP_Error
	 */
	private static function ler_xlsx( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'acica_zip', __( 'O servidor não tem a extensão ZipArchive para ler .xlsx. Salve a planilha como .csv e envie de novo.', 'acic-associados' ) );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return new WP_Error( 'acica_xlsx', __( 'Não consegui abrir o arquivo .xlsx. Ele pode estar corrompido ou ser de outro formato.', 'acic-associados' ) );
		}

		$prev = libxml_use_internal_errors( true );

		// Textos compartilhados.
		$strings = array();
		$xml     = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false !== $xml ) {
			$sx = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
			if ( $sx ) {
				foreach ( $sx->si as $si ) {
					$strings[] = self::texto_si( $si );
				}
			}
		}

		// Primeira aba, pelo workbook + rels.
		$sheet_path = 'xl/worksheets/sheet1.xml';
		$wb         = $zip->getFromName( 'xl/workbook.xml' );
		$rels       = $zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		if ( false !== $wb && false !== $rels ) {
			$wbx = simplexml_load_string( $wb, 'SimpleXMLElement', LIBXML_NONET );
			$rx  = simplexml_load_string( $rels, 'SimpleXMLElement', LIBXML_NONET );
			if ( $wbx && $rx && isset( $wbx->sheets->sheet[0] ) ) {
				$attrs = $wbx->sheets->sheet[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
				$rid   = (string) $attrs['id'];
				foreach ( $rx->Relationship as $rel ) {
					if ( (string) $rel['Id'] === $rid ) {
						$target     = ltrim( (string) $rel['Target'], '/' );
						$sheet_path = 0 === strpos( $target, 'xl/' ) ? $target : 'xl/' . $target;
						break;
					}
				}
			}
		}

		$xml = $zip->getFromName( $sheet_path );
		$zip->close();
		if ( false === $xml ) {
			libxml_use_internal_errors( $prev );
			return new WP_Error( 'acica_sheet', __( 'Não encontrei a aba de dados dentro do .xlsx.', 'acic-associados' ) );
		}
		$sx = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
		libxml_use_internal_errors( $prev );
		if ( ! $sx || ! isset( $sx->sheetData ) ) {
			return new WP_Error( 'acica_sheet', __( 'Não consegui ler os dados da planilha.', 'acic-associados' ) );
		}

		$grid = array();
		foreach ( $sx->sheetData->row as $row ) {
			$cells = array();
			foreach ( $row->c as $c ) {
				$ref = (string) $c['r'];
				$col = self::coluna( $ref );
				$t   = (string) $c['t'];
				if ( 's' === $t ) {
					$idx   = (int) $c->v;
					$value = isset( $strings[ $idx ] ) ? $strings[ $idx ] : '';
				} elseif ( 'inlineStr' === $t ) {
					$value = isset( $c->is ) ? self::texto_si( $c->is ) : '';
				} else {
					$value = isset( $c->v ) ? (string) $c->v : '';
				}
				$cells[ $col ] = $value;
			}
			if ( $cells ) {
				$grid[] = $cells;
			}
		}
		return $grid;
	}

	/**
	 * Texto de um <si> (pode ter vários <r> com formatação).
	 *
	 * @param SimpleXMLElement $si Nó.
	 * @return string
	 */
	private static function texto_si( $si ) {
		if ( isset( $si->t ) ) {
			return (string) $si->t;
		}
		$text = '';
		foreach ( $si->r as $r ) {
			$text .= (string) $r->t;
		}
		return $text;
	}

	/**
	 * Índice da coluna (A=0) a partir da referência (ex.: "C12").
	 *
	 * @param string $ref Referência.
	 * @return int
	 */
	private static function coluna( $ref ) {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$n       = 0;
		$len     = strlen( $letters );
		for ( $i = 0; $i < $len; $i++ ) {
			$n = $n * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $n - 1 );
	}

	/**
	 * Lê um .csv (separador ; , ou tab, em UTF-8 ou Windows-1252).
	 *
	 * @param string $path Arquivo.
	 * @return array|WP_Error
	 */
	private static function ler_csv( $path ) {
		$content = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content || '' === $content ) {
			return new WP_Error( 'acica_csv', __( 'O arquivo .csv está vazio.', 'acic-associados' ) );
		}
		$content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );
		$utf8 = function_exists( 'wp_is_valid_utf8' ) ? wp_is_valid_utf8( $content ) : seems_utf8( $content );
		if ( ! $utf8 ) {
			$content = function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $content, 'UTF-8', 'Windows-1252' ) : utf8_encode( $content ); // phpcs:ignore PHPCompatibility.FunctionUse.RemovedFunctions.utf8_encodeDeprecated
		}
		$first = strtok( $content, "\n" );
		$delim = ';';
		$best  = 0;
		foreach ( array( ';', ',', "\t" ) as $d ) {
			$c = substr_count( (string) $first, $d );
			if ( $c > $best ) {
				$best  = $c;
				$delim = $d;
			}
		}
		$grid = array();
		$fh   = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $fh, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		rewind( $fh );
		while ( ( $row = fgetcsv( $fh, 0, $delim, '"', '\\' ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			if ( array( null ) !== $row ) {
				$grid[] = $row;
			}
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $grid;
	}
}
