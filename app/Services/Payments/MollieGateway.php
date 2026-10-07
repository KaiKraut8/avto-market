<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// Mollie's payments API (https://docs.mollie.com/reference/payments-api)
class MollieGateway implements PaymentGateway
{
    public function __construct(private string $key, private string $api) {}

    public function name(): string
    {
        return 'mollie';
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->key)->acceptJson()->asJson()->timeout(20)->throw();
    }

    public function createCustomer(User $user): string
    {
        return $this->http()->post($this->api.'/customers', ['name' => $user->name, 'email' => $user->email])->json('id');
    }

    public function create(Payment $payment, string $sequence, array $options): array
    {
        $body = [
            'amount' => ['currency' => $payment->currency ?? 'EUR', 'value' => number_format((float) $payment->amount, 2, '.', '')],
            'description' => $payment->description,
            'sequenceType' => $sequence,
            'metadata' => ['payment_id' => $payment->id],
        ];
        if ($sequence === 'recurring') {
            $body += ['customerId' => $options['customer_id'], 'mandateId' => $options['mandate_id']];
        } else {
            $body += ['method' => $payment->method, 'redirectUrl' => $options['redirect_url']];
            if ($sequence === 'first') {
                $body['customerId'] = $options['customer_id'];
            }
        }
        // Mollie refuses webhook addresses it can't reach (like localhost); the return page checks the payment too
        if (! empty($options['webhook_url'])) {
            $body['webhookUrl'] = $options['webhook_url'];
        }
        $response = $this->http()->post($this->api.'/payments', $body)->json();

        return [
            'id' => $response['id'],
            'checkout_url' => $response['_links']['checkout']['href'] ?? null,
            'status' => $response['status'],
        ];
    }

    public function fetch(Payment $payment): array
    {
        $response = $this->http()->get($this->api.'/payments/'.$payment->provider_id)->json();

        return [
            'status' => $response['status'],
            'customer_id' => $response['customerId'] ?? null,
            'mandate_id' => $response['mandateId'] ?? null,
        ];
    }
}
