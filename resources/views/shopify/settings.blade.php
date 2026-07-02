@include('shopify.layout-start', ['title' => 'Settings', 'subtitle' => 'Global configurations for Storefront, Email, and PDF', 'shopDomain' => $shopDomain])

    <ui-title-bar title="Settings">
        <button variant="primary" onclick="document.getElementById('settings-form').submit()">Save Settings</button>
    </ui-title-bar>

    <form id="settings-form" method="POST" action="{{ route('shopify.settings.update', request()->query(), false) }}">
        @csrf
        @method('PUT')

        <!-- Storefront Configuration Card -->
        <div class="Polaris-Card" style="margin-bottom: 20px;">
            <div class="Polaris-Card__Header" style="padding: 16px;">
                <h2 class="Polaris-Text--headingMd">Storefront Configuration</h2>
            </div>
            <div class="Polaris-Card__Section" style="border-top: 1px solid var(--p-color-border-subdued); padding: 16px;">
                <div class="Polaris-FormLayout">
                    <!-- Text Area (Full Width) -->
                    <div class="Polaris-FormLayout__Item" style="margin-bottom: 16px;">
                        <div class="Polaris-Labelled__LabelWrapper">
                            <div class="Polaris-Label">
                                <label class="Polaris-Label__Text">Storefront Info Text (HTML allowed)</label>
                            </div>
                        </div>
                        <div class="Polaris-Connected">
                            <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                <div class="Polaris-TextField">
                                    <textarea name="storefrontText" rows="4" class="Polaris-TextField__Input" style="resize: vertical; font-family: inherit;">{{ old('storefrontText', $settings['storefrontText']) }}</textarea>
                                    <div class="Polaris-TextField__Backdrop"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="polaris-form-grid">
                        <!-- Template Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Template Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontTemplateLabel" value="{{ old('storefrontTemplateLabel', $settings['storefrontTemplateLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Picture Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Picture Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontPictureLabel" value="{{ old('storefrontPictureLabel', $settings['storefrontPictureLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sender Name Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Sender Name Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontSenderNameLabel" value="{{ old('storefrontSenderNameLabel', $settings['storefrontSenderNameLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recipient Name Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Recipient Name Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontRecipientNameLabel" value="{{ old('storefrontRecipientNameLabel', $settings['storefrontRecipientNameLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Email Recipient Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Email Recipient Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontMailRecipientLabel" value="{{ old('storefrontMailRecipientLabel', $settings['storefrontMailRecipientLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Message Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Message Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontMessageLabel" value="{{ old('storefrontMessageLabel', $settings['storefrontMessageLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date Send Label -->
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Date Send Label</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="storefrontDateSendLabel" value="{{ old('storefrontDateSendLabel', $settings['storefrontDateSendLabel']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Image Dimensions Section -->
                    <h3 class="Polaris-Text--headingSm" style="margin-top: 24px; margin-bottom: 12px;">Card Image Dimensions</h3>
                    <div class="polaris-form-grid">
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Card Image Width (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="storefrontCardWidth" value="{{ old('storefrontCardWidth', $settings['storefrontCardWidth']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Card Image Height (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="storefrontCardHeight" value="{{ old('storefrontCardHeight', $settings['storefrontCardHeight']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Large Card Image Width (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="storefrontCardLargeWidth" value="{{ old('storefrontCardLargeWidth', $settings['storefrontCardLargeWidth']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Large Card Image Height (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="storefrontCardLargeHeight" value="{{ old('storefrontCardLargeHeight', $settings['storefrontCardLargeHeight']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Configuration Card -->
        <div class="Polaris-Card" style="margin-bottom: 20px;">
            <div class="Polaris-Card__Header" style="padding: 16px;">
                <h2 class="Polaris-Text--headingMd">Email Configuration</h2>
            </div>
            <div class="Polaris-Card__Section" style="border-top: 1px solid var(--p-color-border-subdued); padding: 16px;">
                <div class="Polaris-FormLayout">
                    <div class="polaris-form-grid">
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Email Subject (to purchaser)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="emailSubjectPurchaser" value="{{ old('emailSubjectPurchaser', $settings['emailSubjectPurchaser']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Email Subject (to recipient, use %s for sender)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="emailSubjectRecipient" value="{{ old('emailSubjectRecipient', $settings['emailSubjectRecipient']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Email Card Image Width (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="emailCardWidth" value="{{ old('emailCardWidth', $settings['emailCardWidth']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">Email Card Image Height (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="emailCardHeight" value="{{ old('emailCardHeight', $settings['emailCardHeight']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PDF Configuration Card -->
        <div class="Polaris-Card" style="margin-bottom: 20px;">
            <div class="Polaris-Card__Header" style="padding: 16px;">
                <h2 class="Polaris-Text--headingMd">PDF Configuration</h2>
            </div>
            <div class="Polaris-Card__Section" style="border-top: 1px solid var(--p-color-border-subdued); padding: 16px;">
                <div class="Polaris-FormLayout">
                    <div class="polaris-form-grid">
                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">PDF Filename Prefix</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input name="pdfPrefix" value="{{ old('pdfPrefix', $settings['pdfPrefix']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">PDF Card Image Width (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="pdfCardWidth" value="{{ old('pdfCardWidth', $settings['pdfCardWidth']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="Polaris-FormLayout__Item">
                            <div class="Polaris-Labelled__LabelWrapper">
                                <div class="Polaris-Label">
                                    <label class="Polaris-Label__Text">PDF Card Image Height (px)</label>
                                </div>
                            </div>
                            <div class="Polaris-Connected">
                                <div class="Polaris-Connected__Item Polaris-Connected__Item--primary">
                                    <div class="Polaris-TextField">
                                        <input type="number" name="pdfCardHeight" value="{{ old('pdfCardHeight', $settings['pdfCardHeight']) }}" class="Polaris-TextField__Input">
                                        <div class="Polaris-TextField__Backdrop"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="pdf-settings-react" data-settings='@json($settings)' data-shop-domain="{{ $shopDomain }}" style="margin-top: 16px;"></div>
                </div>
            </div>
        </div>
    </form>

    <form id="cleanup-form" method="POST" action="{{ route('shopify.settings.cleanup', request()->query(), false) }}" style="display: none;">
        @csrf
    </form>

    <!-- App Uninstall Clean-up Card -->
    <div class="Polaris-Card" style="margin-top: 20px; margin-bottom: 20px; border: 1px solid var(--p-color-border-critical);">
        <div class="Polaris-Card__Header" style="padding: 16px;">
            <h2 class="Polaris-Text--headingMd" style="color: var(--p-color-text-critical);">App Uninstall Storefront Clean-up</h2>
        </div>
        <div class="Polaris-Card__Section" style="border-top: 1px solid var(--p-color-border-subdued); padding: 16px;">
            <p style="margin-bottom: 16px; color: var(--p-color-text-secondary);">
                Shopify immediately revokes API permissions when you delete an app, which prevents us from cleaning up the generated pages or navigation links after uninstallation.
                <strong>Before you uninstall this app</strong>, click the button below to automatically remove the "Gift Card" page and the navigation menu link from your storefront.
            </p>
            <button type="button" class="Polaris-Button" style="background: #d82c0d; border-color: #d82c0d; color: white; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600;" onclick="if(confirm('Are you sure you want to remove the storefront Gift Card page and navigation menu link? This will disable the customer-facing purchase form. This action will also automatically uninstall the app.')) { document.getElementById('cleanup-form').submit(); }">
                Clean Storefront & Uninstall App
            </button>
        </div>
    </div>

@include('shopify.layout-end')
