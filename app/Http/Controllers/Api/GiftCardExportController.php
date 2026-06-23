<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCardTransaction;
use App\Models\GiftCardVoucher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GiftCardExportController extends Controller
{
    public function purchased(Request $request): StreamedResponse
    {
        $rows = GiftCardVoucher::query()->latest()->get([
            'code',
            'original_amount',
            'remaining_balance',
            'status',
            'recipient_name',
            'recipient_email',
            'sender_name',
            'expires_at',
            'created_at',
            'shopify_order_id',
        ]);

        return $this->streamCsv('gift-cards-purchased.csv', $rows->toArray(), [
            'Code', 'Original Amount', 'Remaining Balance', 'Status', 'Recipient Name', 'Recipient Email', 'Sender Name', 'Expires At', 'Created At', 'Order',
        ]);
    }

    public function used(Request $request): StreamedResponse
    {
        $rows = GiftCardTransaction::query()->latest()->get([
            'amount_used',
            'balance_before',
            'balance_after',
            'created_at',
            'shopify_order_id',
            'shopify_customer_id',
        ]);

        return $this->streamCsv('gift-cards-used.csv', $rows->toArray(), [
            'Amount Used', 'Balance Before', 'Balance After', 'Used At', 'Order', 'Customer',
        ]);
    }

    private function streamCsv(string $filename, array $rows, array $headers): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, $filename);
    }
}
