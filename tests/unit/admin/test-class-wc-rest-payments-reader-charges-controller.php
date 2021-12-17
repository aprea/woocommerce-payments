<?php
/**
 * Class WC_REST_Payments_Reader_Controller_Test
 *
 * @package WooCommerce\Payments\Tests
 */

use PHPUnit\Framework\MockObject\MockObject;
use WCPay\Exceptions\Rest_Request_Exception;
use WC_REST_Payments_Reader_Controller as Controller;
use WCPay\Exceptions\API_Exception;

/**
 * WC_REST_Payments_Reader_Controller_Test unit tests.
 */
class WC_REST_Payments_Reader_Controller_Test extends WP_UnitTestCase {
	/**
	 * Controller under test.
	 *
	 * @var WC_REST_Payments_Reader_Controller
	 */
	private $controller;

	/**
	 * @var WC_Payments_API_Client|MockObject
	 */
	private $mock_api_client;

	/**
	 * @var WC_Payment_Gateway_WCPay|MockObject
	 */
	private $mock_wcpay_gateway;

	/**
	 * @var WC_Payments_In_Person_Payments_Receipts_Service|MockObject
	 */
	private $mock_receipts_service;

	public function setUp() {
		parent::setUp();

		$this->mock_api_client       = $this->createMock( WC_Payments_API_Client::class );
		$this->mock_wcpay_gateway    = $this->createMock( WC_Payment_Gateway_WCPay::class );
		$this->mock_receipts_service = $this->createMock( WC_Payments_In_Person_Payments_Receipts_Service::class );
		$this->controller            = new WC_REST_Payments_Reader_Controller( $this->mock_api_client, $this->mock_wcpay_gateway, $this->mock_receipts_service );

		$this->reader = [
			'id'          => 'tmr_P400-123-456-789',
			'device_type' => 'verifone_P400',
			'label'       => 'Blue Rabbit',
			'livemode'    => false,
			'location'    => null,
			'metadata'    => [],
			'status'      => 'online',
			'is_active'   => true,
		];
	}

	/**
	 * Post test cleanup
	 */
	public function tearDown() {
		parent::tearDown();
		delete_transient( Controller::STORE_READERS_TRANSIENT_KEY );
	}

	public function test_get_summary_no_transaction() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->willReturn( [] );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );

		$response = $this->controller->get_summary( $request );
		$this->assertSame( [], $response->get_data() );
	}

	public function test_get_summary_no_readers_charge_summary() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->willReturn( [ 'created' => 1634291278 ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_readers_charge_summary' )
			->with( gmdate( 'Y-m-d', 1634291278 ) )
			->willReturn( [] );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );

		$response = $this->controller->get_summary( $request );
		$this->assertSame( [], $response->get_data() );

	}

	public function test_get_summary_error() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->will( $this->throwException( new \WCPay\Exceptions\API_Exception( 'test exception', 'test', 0 ) ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );
		$response = $this->controller->get_summary( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
	}

	public function test_get_summary() {
		$readers = [
			[
				'reader_id' => 1,
				'count'     => 3,
				'status'    => 'active',
				'fee'       => [
					'amount'   => 300,
					'currency' => 'usd',
				],
			],
			[
				'reader_id' => 2,
				'count'     => 1,
				'status'    => 'inactive',
				'fee'       => [
					'amount'   => 0,
					'currency' => 'usd',
				],
			],
		];

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_transaction' )
			->willReturn( [ 'created' => 1634291278 ] );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_readers_charge_summary' )
			->with( gmdate( 'Y-m-d', 1634291278 ) )
			->willReturn( $readers );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'transaction_id', 1 );
		$response = $this->controller->get_summary( $request );
		$this->assertSame( $readers, $response->get_data() );
	}

	public function test_getting_all_readers_uses_cache_for_existing_readers() {
		set_transient( Controller::STORE_READERS_TRANSIENT_KEY, [ $this->reader ] );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_terminal_readers' );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_readers_charge_summary' );

		// Setup the request.
		$request = new WP_REST_Request(
			'GET',
			'/wc/v3/payments/readers'
		);
		$request->set_header( 'Content-Type', 'application/json' );
		$result = $this->controller->get_all_readers( $request );

		$this->assertEquals( [ $this->reader ], $result->get_data() );
	}

	public function test_generate_print_receipt() {
		$order = WC_Helper_Order::create_order();

		$payment_intent = $this->mock_payment_intent();

		$charge = $this->mock_charge( $order->get_id() );

		$settings = $this->mock_settings();

		$receipt = 'receipt';

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( '42' )
			->willReturn( $payment_intent );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_charge' )
			->with( $payment_intent->get_charge_id() )
			->willReturn( $charge );

		$this->mock_wcpay_gateway
			->expects( $this->exactly( 4 ) )
			->method( 'get_option' )
			->willReturnOnConsecutiveCalls( $settings['business_name'], $settings['support_info']['address'], $settings['support_info']['phone'], $settings['support_info']['email'] );

		$this->mock_receipts_service
			->expects( $this->once() )
			->method( 'get_receipt_markup' )
			->with( $settings, $this->isInstanceOf( WC_Order::class ), $charge )
			->willReturn( $receipt );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_intent_id', 42 );

		$response = $this->controller->generate_print_receipt( $request );

		$this->assertSame( $receipt, $response->get_data() );
		$this->assertSame( 200, $response->status );
	}

	public function test_generate_print_receipt_invalid_payment_error() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( '42' )
			->willReturn( $this->mock_payment_intent( 'processing' ) );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_charge' );

		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'get_option' );

		$this->mock_receipts_service
			->expects( $this->never() )
			->method( 'get_receipt_markup' );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_intent_id', 42 );

		$response = $this->controller->generate_print_receipt( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_generate_print_receipt_handle_api_exceptions() {
		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( '42' )
			->willThrowException( new API_Exception( 'Something bad happened', 'test error', 500 ) );

		$this->mock_api_client
			->expects( $this->never() )
			->method( 'get_charge' );

		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'get_option' );

		$this->mock_receipts_service
			->expects( $this->never() )
			->method( 'get_receipt_markup' );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_intent_id', 42 );

		$response = $this->controller->generate_print_receipt( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_generate_print_receipt_order_not_found() {
		$payment_intent = $this->mock_payment_intent();

		$charge = $this->mock_charge( '42' );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( '42' )
			->willReturn( $payment_intent );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_charge' )
			->with( $payment_intent->get_charge_id() )
			->willReturn( $charge );

		$this->mock_wcpay_gateway
			->expects( $this->never() )
			->method( 'get_option' );

		$this->mock_receipts_service
			->expects( $this->never() )
			->method( 'get_receipt_markup' );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_intent_id', 42 );

		$response = $this->controller->generate_print_receipt( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_generate_print_receipt_handle_settings_exception() {
		$order = WC_Helper_Order::create_order();

		$payment_intent = $this->mock_payment_intent();

		$charge = $this->mock_charge( $order->get_id() );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( '42' )
			->willReturn( $payment_intent );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_charge' )
			->with( $payment_intent->get_charge_id() )
			->willReturn( $charge );

		$this->mock_wcpay_gateway
			->expects( $this->exactly( 1 ) )
			->method( 'get_option' )
			->willThrowException( new Exception( 'Something bad' ) );

		$this->mock_receipts_service
			->expects( $this->never() )
			->method( 'get_receipt_markup' );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_intent_id', 42 );

		$response = $this->controller->generate_print_receipt( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	public function test_generate_print_receipt_handle_receipt_service_exception() {
		$order = WC_Helper_Order::create_order();

		$payment_intent = $this->mock_payment_intent();

		$charge = $this->mock_charge( $order->get_id() );

		$settings = $this->mock_settings();

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_intent' )
			->with( '42' )
			->willReturn( $payment_intent );

		$this->mock_api_client
			->expects( $this->once() )
			->method( 'get_charge' )
			->with( $payment_intent->get_charge_id() )
			->willReturn( $charge );

		$this->mock_wcpay_gateway
			->expects( $this->exactly( 4 ) )
			->method( 'get_option' )
			->willReturnOnConsecutiveCalls( $settings['business_name'], $settings['support_info']['address'], $settings['support_info']['phone'], $settings['support_info']['email'] );

		$this->mock_receipts_service
			->expects( $this->once() )
			->method( 'get_receipt_markup' )
			->with( $settings, $this->isInstanceOf( WC_Order::class ), $charge )
			->willThrowException( new Exception( 'Something bad' ) );

		$request = new WP_REST_Request( 'GET' );
		$request->set_param( 'payment_intent_id', 42 );

		$response = $this->controller->generate_print_receipt( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$data = $response->get_error_data();
		$this->assertArrayHasKey( 'status', $data );
		$this->assertSame( 500, $data['status'] );
	}

	private function mock_payment_intent( $status = 'succeeded' ) {
		return new WC_Payments_API_Intention(
			'42',
			42,
			'USD',
			'42',
			'42',
			new DateTime(),
			$status,
			'42',
			'secret'
		);
	}

	private function mock_charge( string $order_id ) {
		return [
			'amount_captured'        => 10,
			'order'                  => [
				'number' => $order_id,
			],
			'payment_method_details' => [
				'card_present' => [
					'brand'   => 'test',
					'last4'   => 'Test',
					'receipt' => [
						'application_preferred_name' => 'Test',
						'dedicated_file_name'        => 'Test 42',
						'account_type'               => 'test',
					],
				],
			],
		];
	}

	private function mock_settings() {
		return [
			'business_name' => 'Test Business Name',
			'support_info'  => [
				'address' => [],
				'phone'   => '4242',
				'email'   => 'test@example.com',
			],
		];
	}
}
