<div class="page-width" style="margin-top: 40px; margin-bottom: 60px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <div style="margin-bottom: 28px;">
        <h2 style="font-size: 30px; font-weight: 800; color: #111827; margin: 0 0 8px;">Gift Cards</h2>
        <p style="color: #6b7280; margin: 0; font-size: 16px;">Delight your loved ones in just a few clicks. Send a personalized gift card by email to the address of your choice.</p>
    </div>

    <!-- TAB 1: Buy a Gift Card -->
    <div id="tab-buy-content" style="display: block;">
        <div style="margin-bottom: 30px;">
            <h2 style="font-size: 28px; font-weight: 800; color: #111827; margin: 0 0 8px;">Select a Gift Card</h2>
            <p style="color: #6b7280; margin: 0; font-size: 16px;">Choose a gift card design and add custom recipient details to send it as a gift.</p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px;">
            @forelse ($templates as $template)
                <div style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer;" 
                     onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" 
                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';"
                     onclick="openPersonalization('{{ $template['id'] }}')">
                    
                    <div style="position: relative; height: 180px; background: #f3f4f6; overflow: hidden;">
                        @if ($template['media_url'])
                            <img src="{{ url('/storage/' . $template['media_url']) }}" style="width: 100%; height: 100%; object-fit: cover;" alt="{{ $template['name'] }}">
                        @else
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #9ca3af;">
                                <img src="{{ url('/images/default-gift-card.png') }}" style="width: 100%; height: 100%; object-fit: cover;" alt="Default Gift Card">
                            </div>
                        @endif
                    </div>

                    <div style="padding: 20px;">
                        <h3 style="font-size: 18px; font-weight: 700; color: #1f2937; margin: 0 0 5px;">{{ $template['name'] }}</h3>
                        <p style="font-size: 14px; color: #6b7280; margin: 0 0 15px;">
                            Available: 
                            @foreach ($template['amounts'] as $index => $amt)
                                {{ $index > 0 ? ', ' : '' }}<span class="currency-symbol">$</span>{{ number_format($amt['amount'], 2) }}
                            @endforeach
                        </p>
                        <button style="width: 100%; padding: 10px; background: #4f46e5; color: #fff; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#4338ca'" onmouseout="this.style.background='#4f46e5'">
                            Customize & Purchase
                        </button>
                    </div>
                </div>

                <!-- Personalization Form Modal for each template -->
                <div id="modal-{{ $template['id'] }}" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
                    <div style="background: #fff; border-radius: 16px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; padding: 30px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); position: relative;" onclick="event.stopPropagation()">
                        <button onclick="closePersonalization('{{ $template['id'] }}')" style="position: absolute; top: 15px; right: 15px; border: none; background: none; font-size: 24px; cursor: pointer; color: #9ca3af;">&times;</button>
                        
                        <h3 style="font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 10px;">Personalize Your Gift Card</h3>
                        <p style="color: #6b7280; font-size: 14px; margin: 0 0 20px;">Design: <strong>{{ $template['name'] }}</strong></p>

                        <form action="/cart/add" method="post" enctype="multipart/form-data">
                            <input type="hidden" id="variant-id-{{ $template['id'] }}" name="id" value="{{ $template['amounts'][0]['variant_id'] }}">
                            <input type="hidden" name="quantity" value="1">

                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 8px;">Select Amount *</label>
                                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                                    @foreach ($template['amounts'] as $index => $amt)
                                        <button type="button" 
                                                class="amount-btn-{{ $template['id'] }}" 
                                                onclick="selectAmount('{{ $template['id'] }}', '{{ $amt['variant_id'] }}', '{{ $amt['amount'] }}', this)"
                                                style="padding: 8px 16px; font-size: 14px; font-weight: 600; border: 2px solid {{ $index === 0 ? '#4f46e5' : '#d1d5db' }}; border-radius: 20px; cursor: pointer; transition: all 0.2s; background: {{ $index === 0 ? '#4f46e5' : '#f9fafb' }}; color: {{ $index === 0 ? '#fff' : '#374151' }};">
                                            <span class="currency-symbol">$</span>{{ number_format($amt['amount'], 2) }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>

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

    <div style="margin-bottom: 24px;">
        <h3 style="font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 8px;">Select a template</h3>
        <p style="color: #6b7280; margin: 0;">Choose a template design and then customize the gift card details.</p>
    </div>

    <div id="giftcard-template-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px;">
        @forelse ($templates as $template)
            <div class="giftcard-template-card" data-template-id="{{ $template['id'] }}" data-template-name="{{ $template['name'] }}" data-template-tag="{{ $template['tag'] ?: 'all' }}" data-template-image="{{ $template['imageUrl'] ?: '' }}" data-template-amount="{{ $template['giftCardAmount'] ? number_format((float) $template['giftCardAmount'], 2, '.', '') : '' }}" data-template-variant="{{ $template['variantId'] ?: '' }}" style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; background: #fff; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: transform .2s ease, box-shadow .2s ease;" onclick="selectTemplate('{{ $template['id'] }}')">
                <div style="position: relative; height: 260px; background: #f3f4f6;">
                    @if ($template['imageUrl'])
                        <img src="{{ $template['imageUrl'] }}" alt="{{ $template['name'] }}" style="width: 100%; height: 100%; object-fit: cover;">
                    @else
                        <div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9ca3af;font-weight:700;">No image</div>
                    @endif
                    @if ($template['giftCardAmount'])
                        <div style="position:absolute; top:10px; right:10px; background:#111827; color:#fff; font-weight:700; padding:6px 10px; border-radius:999px;">
                            {{ number_format((float) $template['giftCardAmount'], 0) }}
                        </div>
                    @endif
                </div>
                <div style="padding: 16px;">
                    <div style="font-size: 18px; font-weight: 700; color: #1f2937;">{{ $template['name'] }}</div>
                    @if ($template['tag'])
                        <div style="margin-top: 6px; font-size: 13px; color: #6b7280;">Tag: {{ $template['tag'] }}</div>
                    @endif
                    <div style="margin-top: 12px;">
                        <button type="button" style="width:100%; padding: 10px 12px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;">Customize & Purchase</button>
                    </div>
                </div>
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
                                    <span class="currency-symbol">$</span>{{ number_format($v->original_amount, 2) }}
                                </td>
                                <td style="padding: 16px; font-weight: 600; color: {{ $v->remaining_balance > 0 ? '#10b981' : '#9ca3af' }};">
                                    <span class="currency-symbol">$</span>{{ number_format($v->remaining_balance, 2) }}
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
        @endforelse
    </div>

    <div style="margin-top: 34px;">
        <h3 style="font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 8px;">Gift Card Information</h3>
        <p style="color: #6b7280; margin: 0 0 18px;">Select a template above to fill the form and continue to checkout.</p>
        <div id="selected-template-summary" style="display:none; margin-bottom: 16px; padding: 14px 16px; border: 1px solid #dbe4f0; border-radius: 10px; background: #f8fafc;">
            <strong id="selected-template-name" style="display:block; margin-bottom:6px;"></strong>
            <span id="selected-template-tag" style="color:#64748b;"></span>
        </div>
        <div style="display:grid; grid-template-columns: 280px 1fr; gap: 20px; align-items:start;">
            <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; min-height: 360px; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                <img id="selected-template-image" src="" alt="Selected template" style="width:100%; height:100%; object-fit:cover; display:none;">
                <div id="selected-template-placeholder" style="color:#9ca3af; font-weight:700;">Select a template</div>
            </div>
            <div style="border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding: 18px;">
                @if ($templates->firstWhere('variantId'))
                    <form id="giftcard-purchase-form" action="/cart/add" method="post">
                        <input type="hidden" id="selected-template-variant" name="id" value="{{ $templates->firstWhere('variantId')['variantId'] ?? '' }}">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" id="selected-template-hidden-name" name="properties[Template]" value="">
                        <input type="hidden" id="selected-template-hidden-tag" name="properties[Template Tag]" value="">

                        <div style="margin-bottom: 14px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Amount</label>
                            <input type="text" id="selected-template-amount" value="" readonly style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px; background:#f9fafb;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Recipient Name *</label>
                            <input type="text" name="properties[Recipient Name]" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Recipient Email *</label>
                            <input type="email" name="properties[Recipient Email]" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Sender Name *</label>
                            <input type="text" name="properties[Sender Name]" required style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px;">
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Personal Message</label>
                            <textarea name="properties[Personal Message]" rows="3" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px; resize:vertical;"></textarea>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display:block; font-size:14px; font-weight:600; color:#374151; margin-bottom:6px;">Scheduled Send Date</label>
                            <input type="date" name="properties[Scheduled Send Date]" min="{{ date('Y-m-d') }}" value="{{ date('Y-m-d') }}" style="width:100%; padding:10px; border:1px solid #d1d5db; border-radius:8px;">
                        </div>

                        <button type="submit" style="width:100%; padding:12px; background:#111827; color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:700; cursor:pointer;">Add to Cart</button>
                    </form>
                @else
                    <div style="padding:16px; background:#fef3c7; border-radius:10px; color:#92400e;">
                        No linked gift card is available yet. Please assign a template to an active gift card in the app first.
                    </div>
                @endif
            </div>
        </div>
    </div>

</div>

<script>
function selectTemplate(id) {
    const card = document.querySelector(`.giftcard-template-card[data-template-id="${id}"]`);
    if (!card) return;

    const name = card.dataset.templateName || '';
    const tag = card.dataset.templateTag || '';
    const image = card.dataset.templateImage || '';
    const amount = card.dataset.templateAmount || '';
    const variant = card.dataset.templateVariant || '';

function openPersonalization(templateId) {
    const modal = document.getElementById('modal-' + templateId);
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closePersonalization(templateId) {
    const modal = document.getElementById('modal-' + templateId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function selectAmount(templateId, variantId, amount, btnElement) {
    const hiddenInput = document.getElementById('variant-id-' + templateId);
    if (hiddenInput) {
        hiddenInput.value = variantId;
    }

    const buttons = document.querySelectorAll('.amount-btn-' + templateId);
    buttons.forEach(btn => {
        btn.style.background = '#f9fafb';
        btn.style.color = '#374151';
        btn.style.borderColor = '#d1d5db';
    });

    btnElement.style.background = '#4f46e5';
    btnElement.style.color = '#fff';
    btnElement.style.borderColor = '#4f46e5';
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const modals = document.querySelectorAll('[id^="modal-"]');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }

    document.querySelectorAll('.giftcard-template-card').forEach((current) => {
        current.style.outline = current.dataset.templateId === id ? '2px solid #111827' : 'none';
    });
}

document.querySelectorAll('[data-template-filter]').forEach((button) => {
    button.addEventListener('click', function() {
        const filter = this.dataset.templateFilter;

        document.querySelectorAll('[data-template-filter]').forEach((btn) => {
            const active = btn.dataset.templateFilter === filter;
            btn.classList.toggle('is-active', active);
            btn.style.background = active ? '#111827' : '#fff';
            btn.style.color = active ? '#fff' : '#111827';
            btn.style.borderColor = active ? '#111827' : '#d1d5db';
        });

        document.querySelectorAll('.giftcard-template-card').forEach((card) => {
            card.style.display = filter === 'all' || card.dataset.templateTag === filter ? '' : 'none';
        });
    });
});

// Update currency symbols dynamically from Shopify context if available
(function() {
    function applyCurrency() {
        const symbol = (window.Shopify && window.Shopify.currency && window.Shopify.currency.symbol) || '$';
        document.querySelectorAll('.currency-symbol').forEach(el => {
            el.textContent = symbol;
        });
    }
    applyCurrency();
    document.addEventListener('DOMContentLoaded', applyCurrency);
    setTimeout(applyCurrency, 500);
    setTimeout(applyCurrency, 1500);
})();
</script>
