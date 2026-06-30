/**
 * Admin preview behavior for Greek URL Guard.
 *
 * @package Greek_URL_Guard
 */

( function () {
	'use strict';

	function initPreview() {
		var form   = document.querySelector( '[data-greek-url-guard-preview-form]' );
		var input  = document.getElementById( 'greek-url-guard-preview-text' );
		var result = document.querySelector( '[data-greek-url-guard-preview-result]' );
		var output = document.querySelector( '[data-greek-url-guard-preview-output]' );
		var button = form ? form.querySelector( 'input[type="submit"], button[type="submit"]' ) : null;
		var config = window.GreekURLGuardAdmin || {};

		if ( ! form || ! input || ! result || ! output ) {
			return;
		}

		form.addEventListener(
			'submit',
			function ( event ) {
				var params;

				if ( ! window.fetch || ! window.URLSearchParams || ! config.ajaxUrl ) {
					return;
				}

				event.preventDefault();

				params = new window.URLSearchParams(
					{
						action: 'greek_url_guard_preview_slug',
						nonce: config.previewNonce || '',
						text: input.value || ''
					}
				);

				if ( button ) {
					button.disabled = true;
				}

				window.fetch(
					config.ajaxUrl,
					{
						body: params,
						credentials: 'same-origin',
						method: 'POST'
					}
				)
					.then(
						function ( response ) {
							return response.json();
						}
					)
					.then(
						function ( payload ) {
							if ( payload && payload.success && payload.data && typeof payload.data.slug === 'string' ) {
								result.textContent = payload.data.slug;
							} else if ( payload && payload.data && payload.data.message ) {
								result.textContent = payload.data.message;
							} else {
								result.textContent = config.previewError || '';
							}

							output.hidden = false;
						}
					)
					.catch(
						function () {
							result.textContent = config.previewError || '';
							output.hidden      = false;
						}
					)
					.then(
						function () {
							if ( button ) {
								button.disabled = false;
							}
						}
					);
			}
		);
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initPreview );
	} else {
		initPreview();
	}
}() );
