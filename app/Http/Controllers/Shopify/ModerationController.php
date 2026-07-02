<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\GiftCardVoucher;
use App\Models\GiftCardAuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class ModerationController extends Controller
{
    public function index(Request $request): View
    {
        $shopDomain = $request->string('shop')->toString();
        $shop = Shop::where('shopify_domain', $shopDomain)->firstOrFail();

        return view('shopify.moderation', [
            'shop' => $shop,
            'shopDomain' => $shopDomain,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        // 1. Handle Autocomplete query
        if ($request->has('q')) {
            $q = strtoupper(trim($request->input('q', '')));
            $codes = GiftCardVoucher::where('code', 'like', "%{$q}%")
                ->limit(10)
                ->pluck('code');
            return response()->json($codes);
        }

        // 2. Handle Exact match details fetch
        $code = strtoupper(trim($request->input('code', '')));
        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'Voucher code is required'], 400);
        }

        $voucher = GiftCardVoucher::where('code', $code)->first();
        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Voucher not found'], 404);
        }

        // Fetch transactions
        $transactions = $voucher->transactions()->orderBy('created_at', 'desc')->get()->map(function($t) {
            return [
                'id' => $t->id,
                'amount_used' => $t->amount_used,
                'balance_before' => $t->balance_before,
                'balance_after' => $t->balance_after,
                'order_number' => $t->order_number,
                'customer_name' => $t->customer_name,
                'customer_email' => $t->customer_email,
                'created_at' => $t->created_at->format('Y-m-d H:i'),
            ];
        });

        // Fetch audit logs
        $auditLogs = GiftCardAuditLog::where('voucher_id', $voucher->id)->orderBy('created_at', 'desc')->get()->map(function($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'reason' => $log->reason,
                'created_at' => $log->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'voucher' => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'sender_name' => $voucher->sender_name ?: 'N/A',
                'recipient_name' => $voucher->recipient_name ?: 'N/A',
                'recipient_email' => $voucher->recipient_email ?: 'N/A',
                'original_amount' => (float) $voucher->original_amount,
                'remaining_balance' => (float) $voucher->remaining_balance,
                'status' => $voucher->status,
                'expires_at' => $voucher->expires_at ? $voucher->expires_at->format('Y-m-d H:i') : 'N/A',
                'sent_at' => $voucher->sent_at ? $voucher->sent_at->format('Y-m-d H:i') : 'Not sent yet',
                'personal_message' => $voucher->personal_message,
            ],
            'transactions' => $transactions,
            'auditLogs' => $auditLogs,
        ]);
    }

    public function resendEmail(Request $request): JsonResponse
    {
        $voucherId = $request->input('voucher_id');
        $email = trim($request->input('recipient_email'));

        $voucher = GiftCardVoucher::findOrFail($voucherId);
        $oldEmail = $voucher->recipient_email;

        if (!empty($email) && $email !== $oldEmail) {
            $voucher->recipient_email = $email;
            $voucher->save();
        }

        // Dispatch the SendGiftCardEmailJob synchronously
        try {
            dispatch_sync(new \App\Jobs\SendGiftCardEmailJob($voucher->id));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Failed to resend email: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to send email: ' . $e->getMessage()], 500);
        }

        // Log to audit logs
        GiftCardAuditLog::create([
            'voucher_id' => $voucher->id,
            'action' => 'resend_email',
            'old_value' => ['recipient_email' => $oldEmail],
            'new_value' => ['recipient_email' => $voucher->recipient_email],
            'reason' => 'Manually resent gift card email by admin' . ($oldEmail !== $voucher->recipient_email ? " (email updated from {$oldEmail} to {$voucher->recipient_email})" : ""),
        ]);

        return response()->json(['success' => true, 'message' => 'Email resent successfully']);
    }

    public function adjustBalance(Request $request): JsonResponse
    {
        $voucherId = $request->input('voucher_id');
        $newBalance = (float) $request->input('remaining_balance');
        $reason = trim($request->input('reason', ''));

        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Reason is required for auditing'], 400);
        }

        $voucher = GiftCardVoucher::with(['giftCard.shop'])->findOrFail($voucherId);
        $oldBalance = (float) $voucher->remaining_balance;
        $oldStatus = $voucher->status;
        $originalAmount = (float) $voucher->original_amount;

        if ($newBalance < 0 || $newBalance > $originalAmount) {
            return response()->json([
                'success' => false,
                'message' => "The balance must be between 0 and the original amount ($originalAmount)."
            ], 400);
        }

        // Determine new status
        if ($newBalance === 0.0) {
            $newStatus = 'used';
        } elseif ($newBalance === $originalAmount) {
            $newStatus = ($oldStatus === 'unused') ? 'unused' : 'delivered';
        } else {
            $newStatus = 'partially_used';
        }

        $voucher->remaining_balance = $newBalance;
        $voucher->status = $newStatus;
        $voucher->save();

        // Sync to Shopify if Shopify Price Rule exists
        $metadata = $voucher->metadata ?? [];
        if (!empty($metadata['shopify_price_rule_id'])) {
            $shopifyService = app(\App\Services\Shopify\ShopifyService::class);
            $shop = $voucher->giftCard?->shop;
            if ($shop) {
                $priceRuleId = $metadata['shopify_price_rule_id'];
                try {
                    $shopifyService->api($shop, 'PUT', "price_rules/{$priceRuleId}.json", [
                        'price_rule' => [
                            'id' => $priceRuleId,
                            'value' => '-' . number_format($newBalance, 2, '.', '')
                        ]
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("ModerationController: Failed to update Shopify Price Rule {$priceRuleId}: " . $e->getMessage());
                }
            }
        }

        // Log to audit logs
        GiftCardAuditLog::create([
            'voucher_id' => $voucher->id,
            'action' => 'adjust_balance',
            'old_value' => ['remaining_balance' => $oldBalance, 'status' => $oldStatus],
            'new_value' => ['remaining_balance' => $newBalance, 'status' => $newStatus],
            'reason' => $reason,
        ]);

        return response()->json(['success' => true, 'message' => 'Balance adjusted successfully']);
    }

    public function revoke(Request $request): JsonResponse
    {
        $voucherId = $request->input('voucher_id');
        $reason = trim($request->input('reason', ''));

        if (empty($reason)) {
            return response()->json(['success' => false, 'message' => 'Reason is required for revocation'], 400);
        }

        $voucher = GiftCardVoucher::with(['giftCard.shop'])->findOrFail($voucherId);
        $oldStatus = $voucher->status;

        if ($oldStatus === 'revoked') {
            return response()->json(['success' => false, 'message' => 'Voucher is already revoked'], 400);
        }

        $voucher->status = 'revoked';
        $voucher->save();

        // Delete from Shopify if Shopify Price Rule exists
        $metadata = $voucher->metadata ?? [];
        if (!empty($metadata['shopify_price_rule_id'])) {
            $shopifyService = app(\App\Services\Shopify\ShopifyService::class);
            $shop = $voucher->giftCard?->shop;
            if ($shop) {
                $priceRuleId = $metadata['shopify_price_rule_id'];
                try {
                    $shopifyService->api($shop, 'DELETE', "price_rules/{$priceRuleId}.json");
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("ModerationController: Failed to delete Shopify Price Rule {$priceRuleId}: " . $e->getMessage());
                }
            }
        }

        // Log to audit logs
        GiftCardAuditLog::create([
            'voucher_id' => $voucher->id,
            'action' => 'revoke',
            'old_value' => ['status' => $oldStatus],
            'new_value' => ['status' => 'revoked'],
            'reason' => $reason,
        ]);

        return response()->json(['success' => true, 'message' => 'Voucher revoked successfully']);
    }
}
