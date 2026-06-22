<div class="page-width" style="margin-top: 40px; margin-bottom: 60px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    
    <!-- Tab Navigation -->
    <div style="display: flex; border-bottom: 2px solid #f3f4f6; margin-bottom: 30px; gap: 20px;">
        <button id="tab-buy-btn" onclick="switchStorefrontTab('buy')" style="padding: 12px 20px; font-size: 16px; font-weight: 600; border: none; background: none; border-bottom: 2px solid #4f46e5; color: #4f46e5; cursor: pointer; transition: all 0.3s ease;">
            Purchase a Gift Card
        </button>
        <button id="tab-my-btn" onclick="switchStorefrontTab('my')" style="padding: 12px 20px; font-size: 16px; font-weight: 600; border: none; background: none; border-bottom: 2px solid transparent; color: #6b7280; cursor: pointer; transition: all 0.3s ease;">
            My Purchased Gift Cards
        </button>
    </div>

    <!-- TAB 1: Buy a Gift Card -->
    <div id="tab-buy-content" style="display: block;">
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 28px; font-weight: 800; color: #111827; margin: 0 0 8px;">Select a Gift Card</h2>
            <p style="color: #6b7280; margin: 0; font-size: 16px;">Choose a gift card design and add custom recipient details to send it as a gift.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px;">
            @forelse ($activeGiftCards as $card)
                <div style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer;" 
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';"
                     onclick="openPersonalization('{{ $card->id }}')">
                    
                    <div style="position: relative; height: 180px; background: #f3f4f6; overflow: hidden;">
                        @if ($card->image_url)
                            <img src="{{ url('/storage/' . $card->image_url) }}" style="width: 100%; height: 100%; object-fit: cover;" alt="{{ $card->name }}">
                        @else
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #9ca3af;">
                                <svg style="width: 48px; height: 48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5a2 2 0 10-2 2h2zm-2 4h4m8 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                        @endif
                    </div>

                    <div style="padding: 20px;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #1f2937; margin: 0 0 5px;">{{ $card->name }}</h3>
                        <p style="font-size: 20px; font-weight: 800; color: #4f46e5; margin: 0 0 15px;">${{ number_format($card->amount, 2) }}</p>
                        <button style="width: 100%; padding: 10px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                            Customize & Purchase
                        </button>
                    </div>
                </div>

                <!-- Personalization Form Modal for each card -->
                <div id="modal-{{ $card->id }}" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
                    <div style="background: #fff; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); position: relative;" onclick="event.stopPropagation()">
                        <button onclick="closePersonalization('{{ $card->id }}')" style="position: absolute; top: 15px; right: 15px; border: none; background: none; font-size: 24px; cursor: pointer; color: #9ca3af;">&times;</button>
                        
                        <h3 style="font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 10px;">Personalize Your Gift Card</h3>
                        <p style="color: #6b7280; font-size: 14px; margin: 0 0 20px;">For: <strong>{{ $card->name }} - ${{ number_format($card->amount, 2) }}</strong></p>

                        <form action="/cart/add" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="id" value="{{ $card->shopify_product_variant_id }}">
                            <input type="hidden" name="quantity" value="1">

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 5px;">Recipient Name *</label>
                                <input type="text" name="properties[Recipient Name]" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 5px;">Recipient Email *</label>
                                <input type="email" name="properties[Recipient Email]" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 5px;">Sender Name *</label>
                                <input type="text" name="properties[Sender Name]" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 5px;">Personal Message</label>
                                <textarea name="properties[Personal Message]" rows="3" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; resize: vertical;"></textarea>
                            </div>

                            <div style="margin-bottom: 25px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 5px;">Scheduled Send Date</label>
                                <input type="date" name="properties[Scheduled Send Date]" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px;">
                            </div>

                            <button type="submit" style="width: 100%; padding: 12px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                                Add to Cart
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="grid-column: 1/-1; padding: 40px; text-align: center; color: #6b7280; border: 2px dashed #e5e7eb; border-radius: 12px;">
                    No gift cards are currently available for purchase.
                </div>
            @endforelse
        </div>
    </div>

    <!-- TAB 2: My Gift Cards -->
    <div id="tab-my-content" style="display: none;">
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 28px; font-weight: 800; color: #111827; margin: 0 0 8px;">My Purchased Gift Cards</h2>
            <p style="color: #6b7280; margin: 0; font-size: 16px;">View your active gift card codes and balances below.</p>
        </div>

        @if (!$isLoggedIn)
            <div style="padding: 40px; text-align: center; border: 1px solid #e5e7eb; border-radius: 12px; background: #f9fafb;">
                <h3 style="margin: 0 0 10px; font-size: 18px; color: #374151;">View your gift cards</h3>
                <p style="color: #6b7280; margin-bottom: 20px;">Please log in to your store account to see gift cards associated with your email.</p>
                <a href="/account/login" style="display: inline-block; padding: 10px 20px; background: #4f46e5; color: #fff; text-decoration: none; font-weight: 600; border-radius: 8px;">Log In</a>
            </div>
        @else
            <div style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                            <th style="padding: 14px 16px; font-size: 13px; font-weight: 600; color: #4b5563;">Code</th>
                            <th style="padding: 14px 16px; font-size: 13px; font-weight: 600; color: #4b5563;">Original Value</th>
                            <th style="padding: 14px 16px; font-size: 13px; font-weight: 600; color: #4b5563;">Remaining Balance</th>
                            <th style="padding: 14px 16px; font-size: 13px; font-weight: 600; color: #4b5563;">Status</th>
                            <th style="padding: 14px 16px; font-size: 13px; font-weight: 600; color: #4b5563;">Expires At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vouchers as $v)
                            <tr style="border-bottom: 1px solid #e5e7eb; font-size: 14px;">
                                <td style="padding: 16px; font-family: monospace; font-weight: 700; font-size: 15px; color: #111827;">
                                    {{ $v->code }}
                                </td>
                                <td style="padding: 16px; color: #374151;">
                                    ${{ number_format($v->original_amount, 2) }}
                                </td>
                                <td style="padding: 16px; font-weight: 600; color: {{ $v->remaining_balance > 0 ? '#10b981' : '#9ca3af' }};">
                                    ${{ number_format($v->remaining_balance, 2) }}
                                </td>
                                <td style="padding: 16px;">
                                    <span style="display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; background: {{ $v->status === 'unused' ? '#d1fae5' : ($v->status === 'used' ? '#fee2e2' : '#fef3c7') }}; color: {{ $v->status === 'unused' ? '#065f46' : ($v->status === 'used' ? '#991b1b' : '#92400e') }};">
                                        {{ $v->status }}
                                    </span>
                                </td>
                                <td style="padding: 16px; color: #6b7280;">
                                    {{ $v->expires_at?->format('Y-m-d') ?: 'Never' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="padding: 40px; text-align: center; color: #6b7280;">
                                    You have no purchased gift cards.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<script>
function switchStorefrontTab(tab) {
    const buyBtn = document.getElementById('tab-buy-btn');
    const myBtn = document.getElementById('tab-my-btn');
    const buyContent = document.getElementById('tab-buy-content');
    const myContent = document.getElementById('tab-my-content');

    if (tab === 'buy') {
        buyBtn.style.color = '#4f46e5';
        buyBtn.style.borderBottomColor = '#4f46e5';
        myBtn.style.color = '#6b7280';
        myBtn.style.borderBottomColor = 'transparent';
        buyContent.style.display = 'block';
        myContent.style.display = 'none';
    } else {
        myBtn.style.color = '#4f46e5';
        myBtn.style.borderBottomColor = '#4f46e5';
        buyBtn.style.color = '#6b7280';
        buyBtn.style.borderBottomColor = 'transparent';
        buyContent.style.display = 'none';
        myContent.style.display = 'block';
    }
}

function openPersonalization(cardId) {
    const modal = document.getElementById('modal-' + cardId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closePersonalization(cardId) {
    const modal = document.getElementById('modal-' + cardId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('[id^="modal-"]');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>
