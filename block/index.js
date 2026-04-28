import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, Placeholder, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit: function Edit( { attributes, setAttributes } ) {
		const { surveyId } = attributes;
		const blockProps   = useBlockProps();
		const [ surveys, setSurveys ] = useState( null );

		useEffect( () => {
			fetch( wpApiSettings.root + 'fsm/v1/surveys?status=open', {
				headers: { 'X-WP-Nonce': wpApiSettings.nonce },
			} )
				.then( r => r.json() )
				.then( data => setSurveys( Array.isArray( data ) ? data : [] ) )
				.catch( () => setSurveys( [] ) );
		}, [] );

		const surveyOptions = surveys
			? [ { label: __( '— Select a survey —', 'fire-survey-maker' ), value: 0 },
			    ...surveys.map( s => ( { label: s.title, value: s.id } ) ) ]
			: [];

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Survey Settings', 'fire-survey-maker' ) }>
						{ surveys === null ? (
							<Spinner />
						) : (
							<SelectControl
								label={ __( 'Select Survey', 'fire-survey-maker' ) }
								value={ surveyId }
								options={ surveyOptions }
								onChange={ val => setAttributes( { surveyId: parseInt( val, 10 ) } ) }
							/>
						) }
					</PanelBody>
				</InspectorControls>

				{ ! surveyId ? (
					<Placeholder
						icon="feedback"
						label={ __( 'FireSurveyMaker Survey', 'fire-survey-maker' ) }
						instructions={ __( 'Select a survey from the block settings panel on the right.', 'fire-survey-maker' ) }
					/>
				) : (
					<div className="fsm-block-preview">
						<p>
							<strong>{ __( 'Survey:', 'fire-survey-maker' ) }</strong>{ ' ' }
							{ surveys ? ( surveys.find( s => s.id === surveyId )?.title || `#${surveyId}` ) : `#${surveyId}` }
						</p>
						<em style={ { fontSize: '12px', color: '#666' } }>
							{ __( 'Survey form renders on the frontend.', 'fire-survey-maker' ) }
						</em>
					</div>
				) }
			</div>
		);
	},
} );
