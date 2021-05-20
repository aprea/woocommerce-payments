/* eslint-disable camelcase */
/** @format */

/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import OverviewPage from '../';
import { getTasks } from '../task-list/tasks';

jest.mock( '../task-list/tasks', () => ( { getTasks: jest.fn() } ) );

describe( 'Overview page', () => {
	beforeEach( () => {
		global.wcpaySettings = {
			zeroDecimalCurrencies: [],
			accountStatus: {
				status: 'complete',
				paymentsEnabled: 1,
				depositsStatus: 'weekly',
			},
			accountFees: {
				base: {
					currency: 'EUR',
					percentage_rate: 0.029,
					fixed_rate: 0.3,
				},
				discount: [],
			},
		};
	} );

	it( 'Skips rendering task list when there are no tasks', () => {
		getTasks.mockReturnValue( [] );
		const { container } = render( <OverviewPage /> );

		expect(
			container.querySelector( '.woocommerce-experimental-list' )
		).toBeNull();
	} );
} );
