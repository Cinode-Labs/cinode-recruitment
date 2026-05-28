(function( $ ) {
	'use strict';

	var SESSION_KEY = 'cinode_active_tab';

	/**
	 * Show the requested tab and hide all others.
	 *
	 * @param {string} tabId  Element ID of the tab to activate (without #).
	 */
	function activateTab( tabId ) {
		var $tabs    = $( '.cinode-tab-content' );
		var $navLink = $( '.cinode-nav-tabs .nav-tab[data-tab="' + tabId + '"]' );

		// Guard: do nothing if the tab does not exist.
		if ( ! $navLink.length ) {
			return;
		}

		$tabs.hide();
		$( '#' + tabId ).show();

		$( '.cinode-nav-tabs .nav-tab' ).removeClass( 'nav-tab-active' ).removeAttr( 'aria-current' );
		$navLink.addClass( 'nav-tab-active' ).attr( 'aria-current', 'page' );

		try {
			sessionStorage.setItem( SESSION_KEY, tabId );
		} catch ( e ) { /* private browsing — ignore */ }
	}

	/**
	 * Determine which tab to show on initial page load.
	 * Priority: URL hash → sessionStorage → default.
	 *
	 * @returns {string} Tab ID.
	 */
	function resolveInitialTab() {
		var hash = window.location.hash.replace( '#', '' );

		if ( hash && $( '.cinode-nav-tabs .nav-tab[data-tab="' + hash + '"]' ).length ) {
			return hash;
		}

		try {
			var stored = sessionStorage.getItem( SESSION_KEY );
			if ( stored && $( '.cinode-nav-tabs .nav-tab[data-tab="' + stored + '"]' ).length ) {
				return stored;
			}
		} catch ( e ) { /* ignore */ }

		return 'tab-api';
	}

	$( function() {

		// ---- Tab switching ----------------------------------------
		$( '.cinode-nav-tabs .nav-tab' ).on( 'click', function( e ) {
			e.preventDefault();
			var tab = $( this ).data( 'tab' );
			activateTab( tab );
			if ( history.replaceState ) {
				history.replaceState( null, '', '#' + tab );
			}
		} );

		// Restore active tab on load.
		activateTab( resolveInitialTab() );

		function syncCandidateDefaultStages() {
			var $pipeline = $( '#cinode_default_candidate_pipeline_id' );
			var $stage    = $( '#cinode_default_candidate_pipeline_stage_id' );

			if ( ! $pipeline.length || ! $stage.length || ! $stage.is( 'select' ) ) {
				return;
			}

			var selectedPipeline = parseInt( $pipeline.val() || 0, 10 );
			var selectedStageIsVisible = false;

			$stage.find( 'option' ).each( function() {
				var $option = $( this );
				var optionPipeline = parseInt( $option.data( 'pipeline-id' ) || 0, 10 );
				var shouldShow = optionPipeline === 0 || ( selectedPipeline > 0 && optionPipeline === selectedPipeline );

				$option.prop( 'hidden', ! shouldShow ).prop( 'disabled', ! shouldShow );

				if ( shouldShow && $option.is( ':selected' ) ) {
					selectedStageIsVisible = true;
				}
			} );

			var i18n = ( window.cinodeRecruitmentAdmin && window.cinodeRecruitmentAdmin.i18n ) || {};
			$stage.find( 'option[value="0"]' ).text( selectedPipeline > 0 ? ( i18n.selectAStage || 'Select a stage' ) : ( i18n.selectAPipelineFirst || 'Select a pipeline first' ) );

			if ( ! selectedStageIsVisible ) {
				$stage.val( '0' );
			}
		}

		$( '#cinode_default_candidate_pipeline_id' ).on( 'change', syncCandidateDefaultStages );
		syncCandidateDefaultStages();


		// ---- Copy-to-clipboard ------------------------------------
		$( document ).on( 'click', '.cinode-copy-btn', function() {
			var $btn    = $( this );
			var target  = $btn.data( 'clipboard-target' );
			var text    = $( target ).text().trim();
			var original = $btn.html();

			function onSuccess() {
				var i18n = ( window.cinodeRecruitmentAdmin && window.cinodeRecruitmentAdmin.i18n ) || {};
				$btn.html( '<span class="dashicons dashicons-yes" aria-hidden="true"></span> ' + ( i18n.copied || 'Copied!' ) );
				setTimeout( function() {
					$btn.html( original );
				}, 2000 );
			}

			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( onSuccess ).catch( function() {
					legacyCopy( text, onSuccess );
				} );
			} else {
				legacyCopy( text, onSuccess );
			}
		} );

		/**
		 * Fallback copy for non-secure contexts or older browsers.
		 *
		 * @param {string}   text
		 * @param {Function} callback  Called when copy succeeds.
		 */
		function legacyCopy( text, callback ) {
			var $tmp = $( '<textarea>' )
				.css( { position: 'fixed', top: 0, left: 0, opacity: 0 } )
				.val( text )
				.appendTo( 'body' )
				.trigger( 'select' );

			try {
				document.execCommand( 'copy' );
				if ( typeof callback === 'function' ) { callback(); }
			} catch ( e ) { /* copy not supported */ }

			$tmp.remove();
		}

	} );

})( jQuery );

