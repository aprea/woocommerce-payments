/** @format */

/**
 * External dependencies
 */
import { render, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { DisputeEvidenceForm } from '../dispute-evidence-form';
import { DisputeEvidencePage } from '../dispute-evidence-page';

// Need to prefix with "mock" so it's allowed in mocked wcpay/data function.
const mockDisputeNeedsResponse = {
	id: 'dp_asdfghjkl',
	amount: 1000,
	currency: 'usd',
	created: 1572590800,
	evidence: {
		customer_purchase_ip: '127.0.0.1',
		uncategorized_text: '',
	},
	evidence_details: {
		due_by: 1573199200,
	},
	metadata: {
		__product_type: 'physical_product',
	},
	reason: 'fraudulent',
	status: 'needs_response',
};

const mockDisputeNoNeedForResponse = {
	id: 'dp_zxcvbnm',
	amount: 1050,
	currency: 'usd',
	created: 1572480800,
	evidence: {
		customer_purchase_ip: '127.0.0.1',
		uncategorized_text: 'winning_evidence',
	},
	evidence_details: {
		due_by: 1573099200,
	},
	metadata: {
		__product_type: 'physical_product',
	},
	reason: 'general',
	status: 'under_review',
};

const mockSubmitEvidence = jest.fn();

jest.mock( 'wcpay/data', () => ( {
	useDisputeEvidence: () => {
		return { isSavingEvidence: false, submitEvidence: mockSubmitEvidence };
	},
	useDispute: ( disputeId ) => {
		if ( disputeId === mockDisputeNeedsResponse.id ) {
			return { dispute: mockDisputeNeedsResponse };
		} else if ( disputeId === mockDisputeNoNeedForResponse.id ) {
			return { dispute: mockDisputeNoNeedForResponse };
		}
		return {};
	},
} ) );

describe( 'Dispute evidence form', () => {
	beforeEach( () => {
		// mock Date.now that moment library uses to get current date for testing purposes
		Date.now = jest.fn( () => new Date( '2021-06-24T12:33:37.000Z' ) );
	} );
	afterEach( () => {
		// roll it back
		Date.now = () => new Date();
	} );
	test( 'needing response, renders correctly', () => {
		const { container: form } = render(
			<DisputeEvidenceForm
				evidence={ mockDisputeNeedsResponse.evidence }
				readOnly={ false }
				disputeId={ mockDisputeNeedsResponse.id }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );

	test( 'not needing response, renders correctly', () => {
		const { container: form } = render(
			<DisputeEvidenceForm
				evidence={ mockDisputeNoNeedForResponse.evidence }
				readOnly={ true }
				disputeId={ mockDisputeNoNeedForResponse.id }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );

	test( 'confirmation requested on submit', () => {
		window.confirm = jest.fn();

		// We have to mount component to select button for click.
		const { getByRole } = render(
			<DisputeEvidenceForm
				evidence={ mockDisputeNeedsResponse.evidence }
				readOnly={ false }
				disputeId={ mockDisputeNeedsResponse.id }
			/>
		);

		const submitButton = getByRole( 'button', { name: /submit.*/i } );
		fireEvent.click( submitButton );
		expect( window.confirm ).toHaveBeenCalledTimes( 1 );
		expect( window.confirm ).toHaveBeenCalledWith(
			"Are you sure you're ready to submit this evidence? Evidence submissions are final."
		);
	} );

	test( 'onSave called after confirmation only', () => {
		// We have to mount component to select button for click.
		const { getByRole } = render(
			<DisputeEvidenceForm
				evidence={ mockDisputeNeedsResponse.evidence }
				readOnly={ false }
				disputeId={ mockDisputeNeedsResponse.id }
			/>
		);

		const submitButton = getByRole( 'button', { name: /submit.*/i } );

		window.confirm = jest.fn();
		window.confirm.mockReturnValueOnce( false ).mockReturnValueOnce( true );

		// Test not confirmed case.
		fireEvent.click( submitButton );
		expect( mockSubmitEvidence ).toHaveBeenCalledTimes( 0 );

		// Test confirmed case.
		fireEvent.click( submitButton );
		expect( mockSubmitEvidence ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'Dispute evidence page', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
		};
	} );

	test( 'renders correctly', () => {
		const { container: form } = render(
			<DisputeEvidencePage
				showPlaceholder={ false }
				disputeId={ mockDisputeNeedsResponse.id }
				evidence={ mockDisputeNeedsResponse.evidence }
			/>
		);
		expect( form ).toMatchSnapshot();
	} );
} );
