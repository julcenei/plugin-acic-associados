/* ACIC Associados — busca e filtro A–Z da listagem [acic_associados] */
( function () {
	'use strict';

	function normalizar( texto ) {
		return ( texto || '' )
			.normalize( 'NFD' )
			.replace( /[̀-ͯ]/g, '' )
			.toUpperCase()
			.replace( /[^A-Z0-9]+/g, ' ' )
			.trim();
	}

	function fmt( modelo, valor ) {
		return modelo.replace( '%s', valor );
	}

	function iniciar( raiz ) {
		var txt = {};
		try {
			txt = JSON.parse( raiz.getAttribute( 'data-txt' ) || '{}' );
		} catch ( e ) {}

		var campo = raiz.querySelector( '.acica-busca__campo' );
		var botoes = Array.prototype.slice.call( raiz.querySelectorAll( '.acica-letra' ) );
		var grupos = Array.prototype.slice.call( raiz.querySelectorAll( '.acica-grupo' ) );
		var resultado = raiz.querySelector( '.acica-resultado' );
		var vazio = raiz.querySelector( '.acica-vazio' );
		var limpar = raiz.querySelector( '.acica-limpar' );
		var letraAtual = '';
		var timer = null;

		// Guarda itens e textos de busca uma vez só.
		var dados = grupos.map( function ( g ) {
			return {
				el: g,
				letra: g.getAttribute( 'data-letra' ),
				qtd: g.querySelector( '.acica-grupo__qtd' ),
				itens: Array.prototype.slice.call( g.querySelectorAll( '.acica-item' ) ).map( function ( li ) {
					return { el: li, k: li.getAttribute( 'data-k' ) || '' };
				} ),
			};
		} );

		function aplicar() {
			var bruto = campo ? campo.value.trim() : '';
			var q = normalizar( bruto );
			// Busca por CNPJ com ou sem pontuação.
			if ( /^[\d.\/\-\s]+$/.test( bruto ) ) {
				q = bruto.replace( /\D+/g, '' );
			}
			var porLetra = {};
			var total = 0;

			dados.forEach( function ( g ) {
				var visiveisBusca = 0;
				var visiveis = 0;
				g.itens.forEach( function ( it ) {
					var bate = ! q || it.k.indexOf( q ) !== -1;
					if ( bate ) {
						visiveisBusca++;
					}
					var mostra = bate && ( ! letraAtual || letraAtual === g.letra );
					it.el.hidden = ! mostra;
					if ( mostra ) {
						visiveis++;
					}
				} );
				porLetra[ g.letra ] = visiveisBusca;
				g.el.hidden = visiveis === 0;
				if ( g.qtd ) {
					g.qtd.textContent = fmt( visiveis === 1 ? txt.um : txt.n, visiveis.toLocaleString( 'pt-BR' ) );
				}
				total += visiveis;
			} );

			botoes.forEach( function ( b ) {
				var l = b.getAttribute( 'data-letra' );
				var ativa = l === letraAtual;
				b.classList.toggle( 'is-ativa', ativa );
				b.setAttribute( 'aria-pressed', ativa ? 'true' : 'false' );
				if ( l ) {
					b.disabled = ! porLetra[ l ] && ! ativa;
				}
			} );

			if ( resultado ) {
				if ( ! q && ! letraAtual ) {
					resultado.textContent = resultado.getAttribute( 'data-todos' );
				} else {
					var t = fmt( total === 1 ? txt.r1 : txt.rn, total.toLocaleString( 'pt-BR' ) );
					if ( bruto ) {
						t += fmt( txt.para, bruto );
					}
					if ( letraAtual ) {
						t += fmt( txt.letra, letraAtual === '#' ? '0–9' : letraAtual );
					}
					resultado.textContent = t + '.';
				}
			}
			if ( vazio ) {
				vazio.hidden = total !== 0;
			}
		}

		if ( campo ) {
			campo.addEventListener( 'input', function () {
				clearTimeout( timer );
				timer = setTimeout( aplicar, 120 );
			} );
		}

		botoes.forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var l = b.getAttribute( 'data-letra' );
				letraAtual = l && l === letraAtual ? '' : l;
				aplicar();
			} );
		} );

		if ( limpar ) {
			limpar.addEventListener( 'click', function () {
				letraAtual = '';
				if ( campo ) {
					campo.value = '';
					campo.focus();
				}
				aplicar();
			} );
		}
	}

	function boot() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-acica]' ), iniciar );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
