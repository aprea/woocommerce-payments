<?php
/**
 * PHPUnit bootstrap file
 *
 * @package WooCommerce\Payments
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

if ( PHP_VERSION_ID >= 80000 && file_exists( $_tests_dir . '/includes/phpunit7/MockObject' ) ) {
	// WP Core test library includes patches for PHPUnit 7 to make it compatible with PHP8.
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/NamespaceMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/Builder/ParametersMatch.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/InvocationMocker.php';
	require_once $_tests_dir . '/includes/phpunit7/MockObject/MockMethod.php';
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';


/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// NOTE: this will skip the dependency check so the plugin can load. The test environment
	// needs to still make sure that all dependencies exist for it to successfully run.
	define( 'WCPAY_TEST_ENV', true );

	// Load the WooCommerce plugin so we can use its classes in our WooCommerce Payments plugin.
	require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

	// Enable and set a default currency to be used for the multi-currency tests because the default
	// is not loaded even though it's set during the tests setup.
	update_option( '_wcpay_feature_customer_multi_currency', '1' );
	update_option( 'woocommerce_currency', 'USD' );

	// Set the 'wcpaydev_dev_mode' option to enable more currencies for now.
	// TODO: Remove dev mode option here.
	update_option( 'wcpaydev_dev_mode', true );

	$_plugin_dir = dirname( __FILE__ ) . '/../../';

	require $_plugin_dir . 'woocommerce-payments.php';

	require_once $_plugin_dir . 'includes/class-wc-payments-db.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/models/class-wc-payments-api-charge.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/models/class-wc-payments-api-intention.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/class-wc-payments-api-client.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/class-wc-payments-http-interface.php';
	require_once $_plugin_dir . 'includes/wc-payment-api/class-wc-payments-http.php';

	// Load the gateway files, so subscriptions can be tested.
	require_once $_plugin_dir . 'includes/class-wc-payment-gateway-wcpay.php';
	require_once $_plugin_dir . 'includes/compat/subscriptions/class-wc-payment-gateway-wcpay-subscriptions-compat.php';

	require_once $_plugin_dir . 'includes/exceptions/class-rest-request-exception.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-admin.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-admin-sections-overwrite.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-payments-rest-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-orders-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-webhook-controller.php';
	require_once $_plugin_dir . 'includes/admin/class-wc-rest-payments-tos-controller.php';
	require_once $_plugin_dir . 'includes/admin/tracks/class-tracker.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// We use outdated PHPUnit version, which emits deprecation errors in PHP 7.4 (deprecated reflection APIs).
if ( defined( 'PHP_VERSION_ID' ) && PHP_VERSION_ID >= 70400 ) {
	error_reporting( error_reporting() ^ E_DEPRECATED ); // phpcs:ignore
}
