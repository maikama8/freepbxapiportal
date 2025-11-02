<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Payment\PaymentService;
use App\Services\Payment\NowPaymentsGateway;
use App\Services\Payment\PayPalGateway;
use App\Services\Email\PaymentNotificationService;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Exception;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;
    protected $nowPaymentsGateway;
    protected $paypalGateway;
    protected $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->nowPaymentsGateway = Mockery::mock(NowPaymentsGateway::class);
        $this->paypalGateway = Mockery::mock(PayPalGateway::class);
        $this->notificationService = Mockery::mock(PaymentNotificationService::class);
        
        $this->paymentService = new PaymentService(
            $this->nowPaymentsGateway,
            $this->paypalGateway,
            $this->notificationService
        );
    }

    public function test_create_payment_with_nowpayments_gateway()
    {
        $user = User::factory()->create();
        $amount = 50.00;
        $currency = 'USD';
        $gateway = 'nowpayments';
        $paymentMethod = 'btc';

        $expectedTransaction = PaymentTransaction::factory()->make([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $gateway,
            'payment_method' => $paymentMethod
        ]);

        $this->nowPaymentsGateway
            ->shouldReceive('createPayment')
            ->once()
            ->with($user, $amount, $currency, $paymentMethod)
            ->andReturn($expectedTransaction);

        $result = $this->paymentService->createPayment($user, $amount, $currency, $gateway, $paymentMethod);

        $this->assertEquals($expectedTransaction, $result);
    }

    public function test_create_payment_with_paypal_gateway()
    {
        $user = User::factory()->create();
        $amount = 25.00;
        $currency = 'USD';
        $gateway = 'paypal';
        $paymentMethod = 'paypal';

        $expectedTransaction = PaymentTransaction::factory()->make([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $currency,
            'gateway' => $gateway,
            'payment_method' => $paymentMethod
        ]);

        $this->paypalGateway
            ->shouldReceive('createPayment')
            ->once()
            ->with($user, $amount, $currency)
            ->andReturn($expectedTransaction);

        $result = $this->paymentService->createPayment($user, $amount, $currency, $gateway, $paymentMethod);

        $this->assertEquals($expectedTransaction, $result);
    }

    public function test_create_payment_with_unsupported_gateway()
    {
        $user = User::factory()->create();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway: unsupported');

        $this->paymentService->createPayment($user, 50.00, 'USD', 'unsupported', 'test');
    }

    public function test_get_available_payment_methods_includes_paypal()
    {
        $this->nowPaymentsGateway
            ->shouldReceive('getAvailableCurrencies')
            ->once()
            ->andReturn(['btc', 'eth', 'usdt']);

        $methods = $this->paymentService->getAvailablePaymentMethods();

        $this->assertArrayHasKey('paypal', $methods);
        $this->assertEquals('PayPal', $methods['paypal']['name']);
        $this->assertEquals('paypal', $methods['paypal']['type']);
        $this->assertEquals('paypal', $methods['paypal']['gateway']);
        $this->assertContains('USD', $methods['paypal']['currencies']);
    }

    public function test_get_available_payment_methods_includes_crypto()
    {
        $cryptoCurrencies = ['btc', 'eth', 'usdt'];
        
        $this->nowPaymentsGateway
            ->shouldReceive('getAvailableCurrencies')
            ->once()
            ->andReturn($cryptoCurrencies);

        $methods = $this->paymentService->getAvailablePaymentMethods();

        foreach ($cryptoCurrencies as $crypto) {
            $key = "crypto_{$crypto}";
            $this->assertArrayHasKey($key, $methods);
            $this->assertEquals($crypto, $methods[$key]['name']);
            $this->assertEquals('cryptocurrency', $methods[$key]['type']);
            $this->assertEquals('nowpayments', $methods[$key]['gateway']);
            $this->assertEquals($crypto, $methods[$key]['crypto_currency']);
        }
    }

    public function test_get_available_payment_methods_handles_crypto_error()
    {
        $this->nowPaymentsGateway
            ->shouldReceive('getAvailableCurrencies')
            ->once()
            ->andThrow(new Exception('API Error'));

        $methods = $this->paymentService->getAvailablePaymentMethods();

        // Should still have PayPal even if crypto fails
        $this->assertArrayHasKey('paypal', $methods);
        
        // Should not have any crypto methods
        $cryptoMethods = array_filter($methods, function($method) {
            return $method['type'] === 'cryptocurrency';
        });
        $this->assertEmpty($cryptoMethods);
    }

    public function test_get_payment_transaction_by_id()
    {
        $user = User::factory()->create();
        $transaction = PaymentTransaction::factory()->create(['user_id' => $user->id]);

        $result = $this->paymentService->getPaymentTransaction($transaction->id);

        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_get_payment_transaction_by_id_with_user_filter()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $transaction = PaymentTransaction::factory()->create(['user_id' => $user1->id]);

        $result = $this->paymentService->getPaymentTransaction($transaction->id, $user1->id);
        $this->assertEquals($transaction->id, $result->id);

        $result = $this->paymentService->getPaymentTransaction($transaction->id, $user2->id);
        $this->assertNull($result);
    }

    public function test_get_user_payment_history()
    {
        $user = User::factory()->create();
        PaymentTransaction::factory()->count(5)->create(['user_id' => $user->id]);
        PaymentTransaction::factory()->count(3)->create(); // Other users

        $result = $this->paymentService->getUserPaymentHistory($user);

        $this->assertEquals(5, $result->total());
        foreach ($result->items() as $transaction) {
            $this->assertEquals($user->id, $transaction->user_id);
        }
    }

    public function test_get_user_payment_history_with_filters()
    {
        $user = User::factory()->create();
        
        PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'gateway' => 'paypal'
        ]);
        
        PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'gateway' => 'nowpayments'
        ]);

        $filters = ['status' => 'completed', 'gateway' => 'paypal'];
        $result = $this->paymentService->getUserPaymentHistory($user, $filters);

        $this->assertEquals(1, $result->total());
        $transaction = $result->items()[0];
        $this->assertEquals('completed', $transaction->status);
        $this->assertEquals('paypal', $transaction->gateway);
    }

    public function test_get_user_payment_stats()
    {
        $user = User::factory()->create();
        
        PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'status' => 'completed'
        ]);
        
        PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 25.00,
            'status' => 'pending'
        ]);
        
        PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 10.00,
            'status' => 'failed'
        ]);

        $stats = $this->paymentService->getUserPaymentStats($user);

        $this->assertEquals(3, $stats['total_payments']);
        $this->assertEquals(1, $stats['completed_payments']);
        $this->assertEquals(1, $stats['pending_payments']);
        $this->assertEquals(1, $stats['failed_payments']);
        $this->assertEquals(85.00, $stats['total_amount']);
        $this->assertEquals(50.00, $stats['completed_amount']);
    }

    public function test_complete_payment_success()
    {
        $user = User::factory()->create(['balance' => 10.00]);
        $transaction = PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'status' => 'pending'
        ]);

        $this->notificationService
            ->shouldReceive('queuePaymentConfirmation')
            ->once()
            ->with($transaction);

        $result = $this->paymentService->completePayment($transaction);

        $this->assertTrue($result);
        
        $transaction->refresh();
        $this->assertEquals('completed', $transaction->status);
        
        $user->refresh();
        $this->assertEquals(60.00, $user->balance);
    }

    public function test_complete_payment_already_completed()
    {
        $transaction = PaymentTransaction::factory()->create(['status' => 'completed']);

        $result = $this->paymentService->completePayment($transaction);

        $this->assertTrue($result);
    }

    public function test_fail_payment_success()
    {
        $transaction = PaymentTransaction::factory()->create(['status' => 'pending']);
        $reason = 'Payment declined by bank';

        $this->notificationService
            ->shouldReceive('queuePaymentFailed')
            ->once()
            ->with($transaction, $reason);

        $result = $this->paymentService->failPayment($transaction, $reason);

        $this->assertTrue($result);
        
        $transaction->refresh();
        $this->assertEquals('failed', $transaction->status);
    }

    public function test_fail_payment_already_failed()
    {
        $transaction = PaymentTransaction::factory()->create(['status' => 'failed']);

        $result = $this->paymentService->failPayment($transaction);

        $this->assertTrue($result);
    }

    public function test_cancel_payment_success()
    {
        $transaction = PaymentTransaction::factory()->create(['status' => 'pending']);

        $result = $this->paymentService->cancelPayment($transaction);

        $this->assertTrue($result);
        
        $transaction->refresh();
        $this->assertEquals('cancelled', $transaction->status);
    }

    public function test_cancel_payment_not_pending()
    {
        $transaction = PaymentTransaction::factory()->create(['status' => 'completed']);

        $result = $this->paymentService->cancelPayment($transaction);

        $this->assertFalse($result);
    }

    public function test_retry_payment_success()
    {
        $user = User::factory()->create();
        $originalTransaction = PaymentTransaction::factory()->create([
            'user_id' => $user->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'status' => 'failed'
        ]);

        $newTransaction = PaymentTransaction::factory()->make([
            'user_id' => $user->id,
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'payment_method' => 'paypal'
        ]);

        $this->paypalGateway
            ->shouldReceive('createPayment')
            ->once()
            ->with($user, 50.00, 'USD')
            ->andReturn($newTransaction);

        $result = $this->paymentService->retryPayment($originalTransaction);

        $this->assertEquals($newTransaction, $result);
    }

    public function test_retry_payment_not_failed()
    {
        $transaction = PaymentTransaction::factory()->create(['status' => 'pending']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Can only retry failed payments');

        $this->paymentService->retryPayment($transaction);
    }

    public function test_get_minimum_amount_nowpayments()
    {
        $this->nowPaymentsGateway
            ->shouldReceive('getMinimumAmount')
            ->once()
            ->with('btc')
            ->andReturn(0.001);

        $result = $this->paymentService->getMinimumAmount('nowpayments', 'btc');

        $this->assertEquals(0.001, $result);
    }

    public function test_get_minimum_amount_paypal()
    {
        $result = $this->paymentService->getMinimumAmount('paypal', 'paypal');

        $this->assertEquals(1.00, $result);
    }

    public function test_get_minimum_amount_unsupported_gateway()
    {
        $result = $this->paymentService->getMinimumAmount('unsupported', 'test');

        $this->assertNull($result);
    }

    public function test_validate_payment_parameters_success()
    {
        $this->nowPaymentsGateway
            ->shouldReceive('getAvailableCurrencies')
            ->once()
            ->andReturn(['btc', 'eth']);
            
        $this->nowPaymentsGateway
            ->shouldReceive('getMinimumAmount')
            ->once()
            ->with('btc')
            ->andReturn(0.001);

        $errors = $this->paymentService->validatePaymentParameters(50.00, 'USD', 'nowpayments', 'btc');

        $this->assertEmpty($errors);
    }

    public function test_validate_payment_parameters_invalid_amount()
    {
        $errors = $this->paymentService->validatePaymentParameters(0, 'USD', 'paypal', 'paypal');

        $this->assertContains('Amount must be greater than 0', $errors);
    }

    public function test_validate_payment_parameters_amount_too_high()
    {
        $errors = $this->paymentService->validatePaymentParameters(15000, 'USD', 'paypal', 'paypal');

        $this->assertContains('Amount cannot exceed $10,000', $errors);
    }

    public function test_validate_payment_parameters_unsupported_currency()
    {
        $errors = $this->paymentService->validatePaymentParameters(50.00, 'JPY', 'paypal', 'paypal');

        $this->assertContains('Unsupported currency', $errors);
    }

    public function test_validate_payment_parameters_unsupported_gateway()
    {
        $errors = $this->paymentService->validatePaymentParameters(50.00, 'USD', 'unsupported', 'test');

        $this->assertContains('Unsupported payment gateway', $errors);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}