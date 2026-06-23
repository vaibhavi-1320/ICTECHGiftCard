<div class="page-width" style="margin-top: 40px; margin-bottom: 60px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <div style="margin-bottom: 28px;">
        <h2 style="font-size: 30px; font-weight: 800; color: #111827; margin: 0 0 8px;">Gift Cards</h2>
        <p style="color: #6b7280; margin: 0; font-size: 16px;">Delight your loved ones in just a few clicks. Send a personalized gift card by email to the address of your choice.</p>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 28px;">
        <button type="button" data-template-filter="all" class="template-filter-btn is-active" style="padding: 10px 16px; border: 1px solid #111827; background: #111827; color: #fff; cursor: pointer;">All ({{ count($templates) }})</button>
        @foreach ($tags as $tag)
            <button type="button" data-template-filter="{{ $tag }}" class="template-filter-btn" style="padding: 10px 16px; border: 1px solid #d1d5db; background: #fff; color: #111827; cursor: pointer;">{{ $tag }} ({{ $templates->where('tag', $tag)->count() }})</button>
        @endforeach
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
        @empty
            <div style="grid-column:1/-1; padding:40px; text-align:center; color:#6b7280; border:2px dashed #e5e7eb; border-radius:12px;">
                No templates are currently available for purchase.
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

    const summary = document.getElementById('selected-template-summary');
    const summaryName = document.getElementById('selected-template-name');
    const summaryTag = document.getElementById('selected-template-tag');
    const imageEl = document.getElementById('selected-template-image');
    const placeholder = document.getElementById('selected-template-placeholder');
    const amountEl = document.getElementById('selected-template-amount');
    const variantEl = document.getElementById('selected-template-variant');
    const hiddenName = document.getElementById('selected-template-hidden-name');
    const hiddenTag = document.getElementById('selected-template-hidden-tag');

    if (summary) summary.style.display = 'block';
    if (summaryName) summaryName.textContent = name;
    if (summaryTag) summaryTag.textContent = tag ? `Tag: ${tag}` : '';
    if (amountEl) amountEl.value = amount ? Number(amount).toFixed(2) : '';
    if (variantEl) variantEl.value = variant;
    if (hiddenName) hiddenName.value = name;
    if (hiddenTag) hiddenTag.value = tag;

    if (imageEl && placeholder) {
        if (image) {
            imageEl.src = image;
            imageEl.style.display = 'block';
            placeholder.style.display = 'none';
        } else {
            imageEl.style.display = 'none';
            placeholder.style.display = 'block';
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

document.addEventListener('DOMContentLoaded', () => {
    const firstCard = document.querySelector('.giftcard-template-card');
    if (firstCard) {
        selectTemplate(firstCard.dataset.templateId);
    }
});
</script>
