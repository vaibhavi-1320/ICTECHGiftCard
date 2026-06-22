<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $shopDomain = $request->string('shop')->toString();
        $shop = Shop::where('shopify_domain', $shopDomain)->firstOrFail();

        $defaults = [
            'storefrontText' => '<p>Delight your loved ones or a friend for their Birthday, Valentine\'s Day, wedding, Christmas... Send a personalised gift card by email to the address of your choice. The amount will then be available as a voucher valid across our entire site.</p>',
            'storefrontCardWidth' => 528,
            'storefrontCardHeight' => 318,
            'storefrontCardLargeWidth' => 800,
            'storefrontCardLargeHeight' => 518,
            'storefrontTemplateLabel' => 'Template',
            'storefrontPictureLabel' => 'Picture',
            'storefrontSenderNameLabel' => 'Sender name',
            'storefrontRecipientNameLabel' => 'Recipient name',
            'storefrontMailRecipientLabel' => 'Email recipient',
            'storefrontMessageLabel' => 'Message',
            'storefrontDateSendLabel' => 'Date send',
            'emailSubjectPurchaser' => 'Your gift card',
            'emailSubjectRecipient' => 'Gift card offer from %s',
            'emailCardWidth' => 300,
            'emailCardHeight' => 194,
            'pdfPrefix' => 'GIFTCARD-',
            'pdfContent' => '<table cellpadding="10" style="width:100%;text-align:center;color:#333;background:#ffffff;font-size:14px;"><tbody><tr><td style="width:25%;">&nbsp;</td><td style="width:50%;font-size:30px;border:1px solid #333;"><strong>Gift Card</strong></td><td style="width:25%;">&nbsp;</td></tr><tr><td colspan="3"><p>Hi {{card_lastname}},</p><p>You have received a <strong>{{card_price}}</strong> gift card from {{card_from}}!</p><p style="font-size:18px;margin:0;"><em>Good shopping on {{shop_name}}!</em></p></td></tr><tr><td colspan="3">{{card_image}}</td></tr><tr><td style="width:25%;">&nbsp;</td><td style="width:50%;font-size:16px;background-color:#333;color:#fff;">Your code:<br><strong>{{card_code}}</strong></td><td style="width:25%;">&nbsp;</td></tr><tr><td colspan="3"><p><strong>Message from {{card_from}}</strong></p><div>{{card_message}}</div></td></tr><tr><td colspan="3" style="font-size:1px;"></td></tr><tr><td style="width:33%;font-size:1px;">&nbsp;</td><td style="width:34%;font-size:1px;border-top:1px solid #777;">&nbsp;</td><td style="width:33%;font-size:1px;">&nbsp;</td></tr><tr><td colspan="3"><p style="font-size:16px;"><strong>To take advantage of the gift card</strong></p><p>Copy/paste your code <strong>{{card_code}}</strong> into the shopping cart before checking out.</p></td></tr></tbody></table>',
            'pdfCardWidth' => 300,
            'pdfCardHeight' => 192,
            'pdfImageSourceMode' => 'http',
        ];

        $currentSettings = array_merge($defaults, $shop->metadata['settings'] ?? []);

        return view('shopify.settings', [
            'shop' => $shop,
            'shopDomain' => $shopDomain,
            'settings' => $currentSettings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $shopDomain = $request->string('shop')->toString();
        $shop = Shop::where('shopify_domain', $shopDomain)->firstOrFail();

        $data = $request->validate([
            'storefrontText' => ['required', 'string'],
            'storefrontCardWidth' => ['required', 'integer', 'min:1'],
            'storefrontCardHeight' => ['required', 'integer', 'min:1'],
            'storefrontCardLargeWidth' => ['required', 'integer', 'min:1'],
            'storefrontCardLargeHeight' => ['required', 'integer', 'min:1'],
            'storefrontTemplateLabel' => ['required', 'string', 'max:100'],
            'storefrontPictureLabel' => ['required', 'string', 'max:100'],
            'storefrontSenderNameLabel' => ['required', 'string', 'max:100'],
            'storefrontRecipientNameLabel' => ['required', 'string', 'max:100'],
            'storefrontMailRecipientLabel' => ['required', 'string', 'max:100'],
            'storefrontMessageLabel' => ['required', 'string', 'max:100'],
            'storefrontDateSendLabel' => ['required', 'string', 'max:100'],
            'emailSubjectPurchaser' => ['required', 'string', 'max:255'],
            'emailSubjectRecipient' => ['required', 'string', 'max:255'],
            'emailCardWidth' => ['required', 'integer', 'min:1'],
            'emailCardHeight' => ['required', 'integer', 'min:1'],
            'pdfPrefix' => ['required', 'string', 'max:50'],
            'pdfContent' => ['required', 'string'],
            'pdfCardWidth' => ['required', 'integer', 'min:1'],
            'pdfCardHeight' => ['required', 'integer', 'min:1'],
            'pdfImageSourceMode' => ['required', 'string', 'in:http,local'],
        ]);

        $metadata = $shop->metadata ?? [];
        $metadata['settings'] = $data;
        $shop->metadata = $metadata;
        $shop->save();

        return redirect()->route('shopify.settings.edit', $request->query())
            ->with('status', 'Settings updated.');
    }
}
