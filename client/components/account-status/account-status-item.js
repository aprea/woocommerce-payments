/** @format */

/**
 * External dependencies
 */
import { Flex, FlexBlock, FlexItem } from '@wordpress/components';

/**
 * Internal dependencies
 */

const AccountStatusItem = ( { label, value, children } ) => {
	return (
		<Flex
			direction={ 'row' }
			align={ 'center' }
			justify={ 'left' }
			gap={ 3 }
			className={ 'woocommerce-account-status-item' }
		>
			<FlexItem>{ label }</FlexItem>
			<FlexBlock>{ children || value || null }</FlexBlock>
		</Flex>
	);
};

export default AccountStatusItem;
