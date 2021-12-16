/** @format **/

/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __ } from '@wordpress/i18n';
import moment from 'moment';
import { TableCard } from '@woocommerce/components';
import { onQueryChange, getQuery } from '@woocommerce/navigation';
import {
	downloadCSVFile,
	generateCSVDataFromTable,
	generateCSVFileName,
} from '@woocommerce/csv-export';

/**
 * Internal dependencies.
 */
import { useDisputes } from 'wcpay/data';
import OrderLink from 'components/order-link';
import DisputeStatusChip from 'components/dispute-status-chip';
import ClickableCell from 'components/clickable-cell';
import DetailsLink, { getDetailsURL } from 'components/details-link';
import Page from 'components/page';
import { TestModeNotice, topics } from 'components/test-mode-notice';
import { reasons } from './strings';
import { formatStringValue } from 'utils';
import { formatExplicitCurrency } from 'utils/currency';
import DownloadButton from 'components/download-button';
import disputeStatusMapping from 'components/dispute-status-chip/mappings';

import './style.scss';

const headers = [
	{
		key: 'details',
		label: '',
		required: true,
		cellClassName: 'info-button',
		isLeftAligned: true,
	},
	{
		key: 'amount',
		label: __( 'Amount', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'status',
		label: __( 'Status', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'reason',
		label: __( 'Reason', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'source',
		label: __( 'Source', 'woocommerce-payments' ),
		required: true,
		cellClassName: 'is-center-aligned',
	},
	{
		key: 'order',
		label: __( 'Order #', 'woocommerce-payments' ),
		required: true,
	},
	{
		key: 'customer',
		label: __( 'Customer', 'woocommerce-payments' ),
		isLeftAligned: true,
	},
	{
		key: 'email',
		label: __( 'Email', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'country',
		label: __( 'Country', 'woocommerce-payments' ),
		visible: false,
		isLeftAligned: true,
	},
	{
		key: 'created',
		label: __( 'Disputed on', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
	{
		key: 'dueBy',
		label: __( 'Respond by', 'woocommerce-payments' ),
		required: true,
		isLeftAligned: true,
	},
];

export const DisputesList = () => {
	const { disputes, isLoading } = useDisputes( getQuery() );

	const rows = disputes.map( ( dispute ) => {
		const {
			amount = 0,
			currency = 'USD',
			customer_name: customerName = '',
			customer_email: customerEmail = '',
			customer_country: customerCountry = '',
			created,
			dispute_id: disputeId,
			due_by: dueBy,
			order: disputeOrder,
			reason,
			source,
			status,
		} = dispute;

		const order = disputeOrder
			? {
					value: disputeOrder.number,
					display: <OrderLink order={ disputeOrder } />,
			  }
			: null;

		const clickable = ( children ) => (
			<ClickableCell href={ getDetailsURL( disputeId, 'disputes' ) }>
				{ children }
			</ClickableCell>
		);

		const detailsLink = (
			<DetailsLink id={ disputeId } parentSegment="disputes" />
		);

		const reasonMapping = reasons[ reason ];
		const reasonDisplay = reasonMapping
			? reasonMapping.display
			: formatStringValue( reason );

		const data = {
			amount: {
				value: amount / 100,
				display: clickable(
					formatExplicitCurrency( amount, currency )
				),
			},
			status: {
				value: status,
				display: clickable( <DisputeStatusChip status={ status } /> ),
			},
			reason: {
				value: reason,
				display: clickable( reasonDisplay ),
			},
			source: {
				value: source,
				display: clickable(
					<span
						className={ `payment-method__brand payment-method__brand--${ source }` }
					/>
				),
			},
			created: {
				value: created * 1000,
				display: clickable(
					dateI18n( 'M j, Y', moment( created * 1000 ).toISOString() )
				),
			},
			dueBy: {
				value: dueBy * 1000,
				display: clickable(
					dateI18n(
						'M j, Y / g:iA',
						moment( dueBy * 1000 ).toISOString()
					)
				),
			},
			order,
			customer: {
				value: customerName,
				display: clickable( customerName ),
			},
			email: {
				value: customerEmail,
				display: clickable( customerEmail ),
			},
			country: {
				value: customerCountry,
				display: clickable( customerCountry ),
			},
			details: { value: disputeId, display: detailsLink },
		};
		return headers.map( ( { key } ) => data[ key ] || { display: null } );
	} );

	const downloadable = !! rows.length;

	function onDownload() {
		const title = __( 'Disputes', 'woocommerce-payments' );
		const { page, path, ...params } = getQuery();

		const csvColumns = [
			{
				...headers[ 0 ],
				label: __( 'Dispute Id', 'woocommerce-payments' ),
			},
			...headers.slice( 1 ),
		];

		const csvRows = rows.map( ( row ) => {
			return [
				...row.slice( 0, 2 ),
				{
					...row[ 2 ],
					value: disputeStatusMapping[ row[ 2 ].value ].message,
				},
				{
					...row[ 3 ],
					value: formatStringValue( row[ 3 ].value ),
				},
				...row.slice( 4, 9 ),
				{
					...row[ 9 ],
					value: dateI18n(
						'Y-m-d',
						moment( row[ 9 ].value ).toISOString()
					),
				},
				{
					...row[ 10 ],
					value: dateI18n(
						'Y-m-d / g:iA',
						moment( row[ 10 ].value ).toISOString()
					),
				},
			];
		} );

		downloadCSVFile(
			generateCSVFileName( title, params ),
			generateCSVDataFromTable( csvColumns, csvRows )
		);

		window.wcTracks.recordEvent( 'wcpay_disputes_download', {
			exported_disputes: csvRows.length,
			total_disputes: disputes.length,
		} );
	}

	return (
		<Page>
			<TestModeNotice topic={ topics.disputes } />
			<TableCard
				className="wcpay-disputes-list"
				title={ __( 'Disputes', 'woocommerce-payments' ) }
				isLoading={ isLoading }
				rowsPerPage={ 10 }
				totalRows={ 10 }
				headers={ headers }
				rows={ rows }
				query={ getQuery() }
				onQueryChange={ onQueryChange }
				actions={ [
					downloadable && (
						<DownloadButton
							key="download"
							isDisabled={ isLoading }
							onClick={ onDownload }
						/>
					),
				] }
			/>
		</Page>
	);
};

export default DisputesList;
