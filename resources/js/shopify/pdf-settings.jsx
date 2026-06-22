import React from 'react';
import { createRoot } from 'react-dom/client';
import { AppProvider, Badge, Card, Button, ButtonGroup, Select, TextField, BlockStack, InlineStack, Box, Text, Tabs, Layout, Divider } from '@shopify/polaris';

function parseConfig(raw, fallback = {}) {
    if (!raw) return fallback;

    try {
        return JSON.parse(raw);
    } catch (error) {
        try {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = raw;
            return JSON.parse(textarea.value);
        } catch (innerError) {
            console.error('Failed to parse React mount config', raw, innerError);
            return fallback;
        }
    }
}

function PdfSettingsIsland({ settings, shopDomain }) {
    const [mode, setMode] = React.useState('preview');
    const [html, setHtml] = React.useState(settings.pdfContent || '');
    const [pdfImageSourceMode, setPdfImageSourceMode] = React.useState(settings.pdfImageSourceMode || 'http');
    const token = (name) => `{{${name}}}`;

    const previewHtml = React.useMemo(() => {
        let content = html;
        const replacements = {
            [token('card_lastname')]: 'John Doe',
            [token('card_firstname')]: 'John',
            [token('card_price')]: '$100.00',
            [token('card_from')]: 'Jane Smith',
            [token('card_code')]: 'GC-A1B2C3D4',
            [token('card_message')]: 'Happy Birthday! Enjoy your gift.',
            [token('card_image')]: '<div style="width:300px;height:192px;border:1px solid #ccc;background:#eee;text-align:center;line-height:192px;margin:0 auto;">[Gift Card Image]</div>',
            [token('shop_name')]: shopDomain || 'My Store',
            [token('validity_date')]: new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toLocaleDateString(),
            [token('custom_text_1')]: 'PrestaShop',
            [token('custom_text_2')]: 'HAPPY',
            [token('custom_text_3')]: 'BIRTHDAY',
        };

        for (const [key, value] of Object.entries(replacements)) {
            content = content.split(key).join(value);
        }

        return content;
    }, [html, shopDomain]);

    return (
        <AppProvider i18n={{}}>
            <Card>
                <BlockStack gap="400">
                    <InlineStack align="space-between" blockAlign="center">
                        <Text as="h3" variant="headingMd">Default PDF HTML Content</Text>
                        <ButtonGroup>
                            <Button pressed={mode === 'code'} onClick={() => setMode('code')}>Code</Button>
                            <Button pressed={mode === 'preview'} onClick={() => setMode('preview')}>Preview</Button>
                        </ButtonGroup>
                    </InlineStack>

                    <input type="hidden" name="pdfImageSourceMode" value={pdfImageSourceMode} />

                    <Select
                        label="Image Source Mode"
                        options={[
                            { label: 'HTTP URL (default)', value: 'http' },
                            { label: 'Local file', value: 'local' },
                        ]}
                        value={pdfImageSourceMode}
                        onChange={setPdfImageSourceMode}
                    />

                    {mode === 'code' ? (
                        <TextField
                            label="HTML code"
                            value={html}
                            onChange={setHtml}
                            multiline={14}
                            autoComplete="off"
                            name="pdfContent"
                        />
                    ) : (
                        <Box background="bg-surface-secondary" padding="400" borderRadius="200">
                            <div dangerouslySetInnerHTML={{ __html: previewHtml }} />
                        </Box>
                    )}
                </BlockStack>
            </Card>
        </AppProvider>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('pdf-settings-react');
    if (!mount) return;

    const settings = parseConfig(mount.dataset.settings, {});
    const shopDomain = mount.dataset.shopDomain || '';
    createRoot(mount).render(<PdfSettingsIsland settings={settings} shopDomain={shopDomain} />);
});

function DashboardOverview({ stats }) {
    const cards = [
        { label: 'Total Vouchers', value: stats.totalVouchers, tone: 'base' },
        { label: 'Pending Issuance', value: stats.pendingVouchers, tone: 'attention' },
        { label: 'Expired Vouchers', value: stats.expiredVouchers, tone: 'critical' },
        { label: 'Total Sold', value: `$${Number(stats.totalSold || 0).toFixed(2)}`, tone: 'success' },
        { label: 'Total Redeemed', value: `$${Number(stats.redeemedAmount || 0).toFixed(2)}`, tone: 'info' },
        { label: 'App Status', value: 'Connected & Active', tone: 'success' },
    ];

    return (
        <AppProvider i18n={{}}>
            <BlockStack gap="400">
                <Text as="h2" variant="headingMd">Overview Statistics</Text>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px' }}>
                    {cards.map((card) => (
                        <Card key={card.label}>
                            <Box padding="400">
                                <BlockStack gap="100">
                                    <Text as="p" variant="bodySm" tone="subdued">{card.label}</Text>
                                    <Text as="p" variant="headingLg" tone={card.tone === 'success' ? 'success' : 'base'}>{card.value}</Text>
                                </BlockStack>
                            </Box>
                        </Card>
                    ))}
                </div>
            </BlockStack>
        </AppProvider>
    );
}

function DashboardControls({ config }) {
    const [values, setValues] = React.useState(config.filters || {});

    const submit = (event) => {
        event.preventDefault();
        const url = new URL(window.location.href);
        const params = new URLSearchParams(url.search);

        Object.entries(config.preserved || {}).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                params.delete(key);
            } else {
                params.set(key, value);
            }
        });

        Object.entries(values).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '') {
                params.delete(key === 'status' ? 'p_status' : `u_${key}`);
            } else {
                params.set(key === 'status' ? 'p_status' : `u_${key}`, value);
            }
        });

        params.set('shop', config.shop || '');
        if (config.host) {
            params.set('host', config.host);
        }

        window.location.href = `${url.pathname}?${params.toString()}`;
    };

    const reset = () => {
        window.location.href = config.resetUrl;
    };

    return (
        <Card>
            <Box padding="400">
                <InlineStack align="space-between" blockAlign="center">
                    <Text as="h2" variant="headingMd">{config.title}</Text>
                    <Button variant="primary" url={config.exportUrl}>Export CSV</Button>
                </InlineStack>
            </Box>
            <Box padding="400" borderBlockStartWidth="025" borderColor="border-subdued">
                <form onSubmit={submit}>
                    <InlineStack gap="300" blockAlign="end" wrap>
                        {config.title === 'Gift Cards Purchased' ? (
                            <>
                                <Select
                                    label="Filter Status"
                                    options={[
                                        { label: 'All statuses', value: '' },
                                        { label: 'Pending Issuance', value: 'pending_issuance' },
                                        { label: 'Unused (Active)', value: 'unused' },
                                        { label: 'Partially Used', value: 'partially_used' },
                                        { label: 'Used', value: 'used' },
                                        { label: 'Expired', value: 'expired' },
                                        { label: 'Revoked', value: 'revoked' },
                                    ]}
                                    value={values.status || ''}
                                    onChange={(value) => setValues((prev) => ({ ...prev, status: value }))}
                                />
                                <TextField
                                    label="From"
                                    type="date"
                                    value={values.from || ''}
                                    onChange={(value) => setValues((prev) => ({ ...prev, from: value }))}
                                    autoComplete="off"
                                />
                                <TextField
                                    label="To"
                                    type="date"
                                    value={values.to || ''}
                                    onChange={(value) => setValues((prev) => ({ ...prev, to: value }))}
                                    autoComplete="off"
                                />
                            </>
                        ) : (
                            <>
                                <TextField
                                    label="From"
                                    type="date"
                                    value={values.from || ''}
                                    onChange={(value) => setValues((prev) => ({ ...prev, from: value }))}
                                    autoComplete="off"
                                />
                                <TextField
                                    label="To"
                                    type="date"
                                    value={values.to || ''}
                                    onChange={(value) => setValues((prev) => ({ ...prev, to: value }))}
                                    autoComplete="off"
                                />
                            </>
                        )}
                        <Button submit variant="primary">Filter</Button>
                        <Button onClick={reset}>Reset</Button>
                    </InlineStack>
                </form>
            </Box>
        </Card>
    );
}

function DashboardTable({ config }) {
    const rows = config.rows || [];
    const isPurchased = config.title === 'Gift Cards Purchased';

    return (
        <Card>
            <Box padding="400">
                <InlineStack align="space-between" blockAlign="center">
                    <Text as="h2" variant="headingMd">{config.title}</Text>
                    <Button variant="primary" url={config.exportUrl}>Export CSV</Button>
                </InlineStack>
            </Box>
            <Box padding="400" borderBlockStartWidth="025" borderColor="border-subdued">
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ textAlign: 'left', background: 'var(--p-color-bg-surface-secondary)' }}>
                                {isPurchased ? (
                                    <>
                                        <th style={{ padding: '14px 16px' }}>Code</th>
                                        <th style={{ padding: '14px 16px' }}>Amount</th>
                                        <th style={{ padding: '14px 16px' }}>Balance</th>
                                        <th style={{ padding: '14px 16px' }}>Status</th>
                                        <th style={{ padding: '14px 16px' }}>Recipient</th>
                                        <th style={{ padding: '14px 16px' }}>Order</th>
                                        <th style={{ padding: '14px 16px' }}>Date</th>
                                    </>
                                ) : (
                                    <>
                                        <th style={{ padding: '14px 16px' }}>Code</th>
                                        <th style={{ padding: '14px 16px' }}>Amount Used</th>
                                        <th style={{ padding: '14px 16px' }}>Balance Before</th>
                                        <th style={{ padding: '14px 16px' }}>Balance After</th>
                                        <th style={{ padding: '14px 16px' }}>Order</th>
                                        <th style={{ padding: '14px 16px' }}>Date</th>
                                    </>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={isPurchased ? 7 : 6} style={{ padding: '32px 16px', textAlign: 'center', color: 'var(--p-color-text-disabled)' }}>
                                        {isPurchased ? 'No purchased gift cards found.' : 'No redemptions found.'}
                                    </td>
                                </tr>
                            ) : rows.map((row) => (
                                <tr key={row.id} style={{ borderTop: '1px solid var(--p-color-border-subdued)' }}>
                                    {isPurchased ? (
                                        <>
                                            <td style={{ padding: '14px 16px', fontFamily: 'monospace', fontWeight: 600 }}>{row.code}</td>
                                            <td style={{ padding: '14px 16px' }}>${Number(row.originalAmount || 0).toFixed(2)}</td>
                                            <td style={{ padding: '14px 16px' }}>${Number(row.remainingBalance || 0).toFixed(2)}</td>
                                            <td style={{ padding: '14px 16px' }}>
                                                <Badge tone={row.status === 'used' ? 'critical' : row.status === 'unused' ? 'success' : 'attention'}>
                                                    {String(row.status || '').replaceAll('_', ' ')}
                                                </Badge>
                                            </td>
                                            <td style={{ padding: '14px 16px' }}>{row.recipientName || '-'}</td>
                                            <td style={{ padding: '14px 16px' }}>{row.orderId || '-'}</td>
                                            <td style={{ padding: '14px 16px' }}>{row.createdAt || '-'}</td>
                                        </>
                                    ) : (
                                        <>
                                            <td style={{ padding: '14px 16px', fontFamily: 'monospace', fontWeight: 600 }}>{row.code || 'Deleted'}</td>
                                            <td style={{ padding: '14px 16px', color: 'var(--p-color-text-critical)', fontWeight: 600 }}>-${Number(row.amountUsed || 0).toFixed(2)}</td>
                                            <td style={{ padding: '14px 16px' }}>${Number(row.balanceBefore || 0).toFixed(2)}</td>
                                            <td style={{ padding: '14px 16px' }}>${Number(row.balanceAfter || 0).toFixed(2)}</td>
                                            <td style={{ padding: '14px 16px' }}>{row.orderId || '-'}</td>
                                            <td style={{ padding: '14px 16px' }}>{row.createdAt || '-'}</td>
                                        </>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
            </Box>
        </Card>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('dashboard-overview-react');
    if (!mount) return;

    const stats = parseConfig(mount.dataset.stats, {});
    createRoot(mount).render(<DashboardOverview stats={stats} />);
});

document.addEventListener('DOMContentLoaded', () => {
    const mounts = [
        document.getElementById('dashboard-purchased-react'),
        document.getElementById('dashboard-used-react'),
    ];

    mounts.forEach((mount) => {
        if (!mount) return;
        const config = parseConfig(mount.dataset.config, {});
        const isPurchased = config.title === 'Gift Cards Purchased';
        createRoot(mount).render(
            <AppProvider i18n={{}}>
                <BlockStack gap="400">
                    <DashboardOverview stats={config.stats || {}} />
                    <DashboardControls config={config} />
                    <DashboardTable config={config} />
                </BlockStack>
            </AppProvider>
        );
    });
});

function GiftCardTemplateSelect({ templateId, templates }) {
    const [value, setValue] = React.useState(String(templateId || ''));
    return (
        <Box>
            <input type="hidden" name="template_id" value={value} />
            <Select
                label="Template"
                options={[
                    { label: 'None', value: '' },
                    ...templates.map((template) => ({ label: template.name, value: String(template.id) })),
                ]}
                value={value}
                onChange={setValue}
            />
        </Box>
    );
}

function GiftCardActiveToggle({ checked }) {
    const [value, setValue] = React.useState(checked);
    return (
        <Card>
            <Box padding="400">
                <input type="hidden" name="active" value={value ? '1' : '0'} />
                <label style={{ display: 'flex', alignItems: 'center', gap: '10px', cursor: 'pointer' }}>
                    <input type="checkbox" checked={value} onChange={(e) => setValue(e.target.checked)} />
                    <Text as="span" variant="bodyMd">Active</Text>
                </label>
            </Box>
        </Card>
    );
}

function TemplatesList({ config }) {
    const rows = config.rows || [];

    return (
        <AppProvider i18n={{}}>
            <Card>
                <Box padding="400">
                    <InlineStack align="space-between" blockAlign="center">
                        <div>
                            <Text as="h2" variant="headingMd">Gift Card Templates</Text>
                            <Text as="p" tone="subdued">Manage the content used for gift card emails and PDF output.</Text>
                        </div>
                        <Button variant="primary" url={config.createUrl}>Add template</Button>
                    </InlineStack>
                </Box>
                <div style={{ borderTop: '1px solid var(--p-color-border-subdued)' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ textAlign: 'left', background: 'var(--p-color-bg-surface-secondary)' }}>
                                <th style={{ padding: '14px 16px', width: '90px' }}>Image</th>
                                <th style={{ padding: '14px 16px' }}>Name</th>
                                <th style={{ padding: '14px 16px' }}>Tag</th>
                                <th style={{ padding: '14px 16px', width: '120px' }}>Status</th>
                                <th style={{ padding: '14px 16px', width: '110px', textAlign: 'right' }}>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan="5" style={{ padding: '32px 16px', textAlign: 'center', color: 'var(--p-color-text-disabled)' }}>No templates yet.</td>
                                </tr>
                            ) : rows.map((row) => (
                                <tr key={row.id} style={{ borderTop: '1px solid var(--p-color-border-subdued)' }}>
                                    <td style={{ padding: '14px 16px' }}>
                                        {row.imageUrl ? (
                                            <img src={row.imageUrl} alt={row.name} style={{ width: '52px', height: '52px', objectFit: 'cover', borderRadius: '6px', border: '1px solid var(--p-color-border-subdued)' }} />
                                        ) : (
                                            <div style={{ width: '52px', height: '52px', borderRadius: '6px', border: '1px dashed var(--p-color-border-subdued)', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--p-color-text-subdued)', fontSize: '12px' }}>IMG</div>
                                        )}
                                    </td>
                                    <td style={{ padding: '14px 16px', fontWeight: 600 }}>{row.name}</td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <Badge>{row.tag || 'None'}</Badge>
                                    </td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <Badge tone={row.active ? 'success' : 'critical'}>{row.active ? 'Active' : 'Inactive'}</Badge>
                                    </td>
                                    <td style={{ padding: '14px 16px', textAlign: 'right' }}>
                                        <Button url={row.editUrl} size="slim">Edit</Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AppProvider>
    );
}

function TemplateFormIsland({ config }) {
    const [selectedTab, setSelectedTab] = React.useState(0);
    const [name, setName] = React.useState(config.name || '');
    const [tag, setTag] = React.useState(config.tag || '');
    const [title, setTitle] = React.useState(config.previewTitle || '');
    const [price, setPrice] = React.useState(config.previewPrice || '');
    const [code, setCode] = React.useState(config.previewCode || '');
    const [text1, setText1] = React.useState(config.customText1 || '');
    const [text2, setText2] = React.useState(config.customText2 || '');
    const [text3, setText3] = React.useState(config.customText3 || '');
    const [color1, setColor1] = React.useState(config.customColor1 || '#ff6a3d');
    const [color2, setColor2] = React.useState(config.customColor2 || '#ff6a3d');
    const [color3, setColor3] = React.useState(config.customColor3 || '#ff6a3d');
    const [pdfOnlyImage, setPdfOnlyImage] = React.useState(Boolean(config.pdfOnlyImage));
    const [active, setActive] = React.useState(Boolean(config.active));
    const [templateImage, setTemplateImage] = React.useState(config.templateMediaUrl || '');
    const fileInputId = React.useId();

    const previewMarkup = React.useMemo(() => {
        let html = config.previewHtml || '';
        const replacements = {
            '{{card_lastname}}': title || 'HAPPY BIRTHDAY',
            '{{card_firstname}}': title || 'HAPPY',
            '{{card_price}}': price || '100',
            '{{card_from}}': text1 || 'PrestaShop',
            '{{card_code}}': code || 'XXXXXXXXXX',
            '{{card_message}}': config.previewMessage || 'Happy Birthday! Enjoy your gift.',
            '{{card_image}}': templateImage
                ? `<img src="${templateImage}" alt="Template media" style="max-width:120px; max-height:120px; object-fit:contain;" />`
                : '<div style="width:72px;height:72px;border:3px dashed #c9cccf;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#6b7177;font-weight:700;margin:0 auto;">IMG</div>',
            '{{shop_name}}': config.shopDomain || 'My Store',
            '{{validity_date}}': config.validityDate || '',
            '{{custom_text_1}}': text1,
            '{{custom_text_2}}': text2,
            '{{custom_text_3}}': text3,
        };

        Object.entries(replacements).forEach(([key, value]) => {
            html = html.split(key).join(String(value ?? ''));
        });
        return html;
    }, [config.previewHtml, config.previewMessage, config.shopDomain, config.validityDate, title, price, code, text1, text2, text3, templateImage]);

    const tabs = [
        { id: 'information', content: 'Information' },
        { id: 'customize', content: 'Customize' },
    ];

    return (
        <AppProvider i18n={{}}>
            <Card>
                <Box padding="400">
                    <Tabs tabs={tabs} selected={selectedTab} onSelect={setSelectedTab}>
                        <div />
                    </Tabs>
                </Box>
                <Divider />
                <form id="template-form" method="POST" encType="multipart/form-data" action={config.formAction}>
                    {config.methodField ? <input type="hidden" name="_method" value={config.methodField} /> : null}
                    <input type="hidden" name="_token" value={config.csrfToken} />
                    <input type="hidden" name="name" value={name} />
                    <input type="hidden" name="tag" value={tag} />
                    <input type="hidden" name="preview_price" value={price} />
                    <input type="hidden" name="preview_code" value={code} />
                    <input type="hidden" name="custom_text_1" value={text1} />
                    <input type="hidden" name="custom_text_2" value={text2} />
                    <input type="hidden" name="custom_text_3" value={text3} />
                    <input type="hidden" name="custom_color_1" value={color1} />
                    <input type="hidden" name="custom_color_2" value={color2} />
                    <input type="hidden" name="custom_color_3" value={color3} />
                    <input type="hidden" name="preview_title" value={title} />
                    <input type="hidden" name="active" value={active ? '1' : '0'} />
                    <input type="hidden" name="pdf_only_image" value={pdfOnlyImage ? '1' : '0'} />

                    {selectedTab === 0 ? (
                        <Box padding="400">
                            <BlockStack gap="400">
                                <Text as="h2" variant="headingMd">General Template Information</Text>
                                <Layout>
                                    <Layout.Section variant="oneHalf">
                                        <TextField label="Name" value={name} onChange={setName} autoComplete="off" />
                                    </Layout.Section>
                                    <Layout.Section variant="oneHalf">
                                        <TextField label="Tag" value={tag} onChange={setTag} autoComplete="off" />
                                    </Layout.Section>
                                </Layout>
                                <BlockStack gap="200">
                                    <Text as="p" variant="bodyMd">Media Upload</Text>
                                    <input type="file" name="media_upload" accept="image/*" onChange={(event) => {
                                        const file = event.target.files && event.target.files[0];
                                        if (!file) return;
                                        const reader = new FileReader();
                                        reader.onload = (e) => setTemplateImage(String(e.target?.result || ''));
                                        reader.readAsDataURL(file);
                                    }} />
                                    {templateImage ? <img src={templateImage} alt="Template media" style={{ maxHeight: '120px', width: 'auto', border: '1px solid var(--p-color-border-subdued)', borderRadius: '6px' }} /> : null}
                                </BlockStack>
                                <label style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                    <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} />
                                    <Text as="span">Active</Text>
                                </label>
                            </BlockStack>
                        </Box>
                    ) : (
                        <Layout>
                            <Layout.Section variant="oneHalf">
                                <Box padding="400">
                                    <Card>
                                        <Box padding="400">
                                            <BlockStack gap="300">
                                                <Text as="h2" variant="headingMd">Live Preview</Text>
                                                <div style={{ background: '#fff', border: '1px solid var(--p-color-border)', borderRadius: '8px', minHeight: '260px', padding: '20px' }}>
                                                    <div dangerouslySetInnerHTML={{ __html: previewMarkup }} />
                                                </div>
                                            </BlockStack>
                                        </Box>
                                    </Card>
                                </Box>
                            </Layout.Section>
                            <Layout.Section variant="oneHalf">
                                <Box padding="400">
                                    <BlockStack gap="400">
                                        <Card><Box padding="400"><BlockStack gap="300">
                                            <Text as="h2" variant="headingMd">Data variables</Text>
                                            <TextField label="Price" value={price} onChange={setPrice} autoComplete="off" />
                                            <TextField label="Discount code" value={code} onChange={setCode} autoComplete="off" />
                                        </BlockStack></Box></Card>
                                        <Card><Box padding="400"><BlockStack gap="300">
                                            <Text as="h2" variant="headingMd">Customizable text</Text>
                                            <TextField label="var_text1" value={text1} onChange={setText1} autoComplete="off" />
                                            <TextField label="var_text2" value={text2} onChange={setText2} autoComplete="off" />
                                            <TextField label="var_text3" value={text3} onChange={setText3} autoComplete="off" />
                                        </BlockStack></Box></Card>
                                        <Card><Box padding="400"><BlockStack gap="300">
                                            <Text as="h2" variant="headingMd">Customizable color</Text>
                                            <InlineStack gap="400" blockAlign="center">
                                                <input type="color" value={color1} onChange={(e) => setColor1(e.target.value)} />
                                                <TextField label="Color 1" value={color1} onChange={setColor1} autoComplete="off" />
                                            </InlineStack>
                                            <InlineStack gap="400" blockAlign="center">
                                                <input type="color" value={color2} onChange={(e) => setColor2(e.target.value)} />
                                                <TextField label="Color 2" value={color2} onChange={setColor2} autoComplete="off" />
                                            </InlineStack>
                                            <InlineStack gap="400" blockAlign="center">
                                                <input type="color" value={color3} onChange={(e) => setColor3(e.target.value)} />
                                                <TextField label="Color 3" value={color3} onChange={setColor3} />
                                            </InlineStack>
                                            <label style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                                                <input type="checkbox" checked={pdfOnlyImage} onChange={(event) => setPdfOnlyImage(event.target.checked)} />
                                                <Text as="span">PDF only image</Text>
                                            </label>
                                        </BlockStack></Box></Card>
                                        {/* Body HTML removed intentionally; template content is managed via the preview editor above. */}
                                    </BlockStack>
                                </Box>
                            </Layout.Section>
                        </Layout>
                    )}
                </form>
            </Card>
        </AppProvider>
    );
}

function GiftCardsList({ config }) {
    const rows = config.rows || [];

    return (
        <AppProvider i18n={{}}>
            <Card>
                <Box padding="400">
                    <InlineStack align="space-between" blockAlign="center">
                        <div>
                            <Text as="h2" variant="headingMd">Gift Cards</Text>
                            <Text as="p" tone="subdued">Create gift cards, assign templates, and manage Shopify product sync.</Text>
                        </div>
                        <Button variant="primary" url={config.createUrl}>Create Gift Card</Button>
                    </InlineStack>
                </Box>
                <div style={{ borderTop: '1px solid var(--p-color-border-subdued)' }}>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ textAlign: 'left', background: 'var(--p-color-bg-surface-secondary)' }}>
                                <th style={{ padding: '14px 16px' }}>Name</th>
                                <th style={{ padding: '14px 16px' }}>Amount</th>
                                <th style={{ padding: '14px 16px' }}>Prefix</th>
                                <th style={{ padding: '14px 16px' }}>Validity</th>
                                <th style={{ padding: '14px 16px' }}>Active</th>
                                <th style={{ padding: '14px 16px', textAlign: 'right' }}>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan="6" style={{ padding: '32px 16px', textAlign: 'center', color: 'var(--p-color-text-disabled)' }}>No gift cards yet.</td>
                                </tr>
                            ) : rows.map((row) => (
                                <tr key={row.id} style={{ borderTop: '1px solid var(--p-color-border-subdued)' }}>
                                    <td style={{ padding: '14px 16px', fontWeight: 600 }}>{row.name}</td>
                                    <td style={{ padding: '14px 16px' }}>${Number(row.amount || 0).toFixed(2)}</td>
                                    <td style={{ padding: '14px 16px' }}>{row.codePrefix || '-'}</td>
                                    <td style={{ padding: '14px 16px' }}>{row.validityDays || 365} days</td>
                                    <td style={{ padding: '14px 16px' }}>
                                        <Badge tone={row.active ? 'success' : 'attention'}>{row.active ? 'Yes' : 'No'}</Badge>
                                    </td>
                                    <td style={{ padding: '14px 16px', textAlign: 'right' }}>
                                        <InlineStack gap="200" align="end">
                                            <Button url={row.editUrl} size="slim">Edit</Button>
                                            <Button url={row.deleteUrl} variant="tertiary" size="slim">Delete</Button>
                                        </InlineStack>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Card>
        </AppProvider>
    );
}

function GiftCardFormIsland({ config }) {
    const [name, setName] = React.useState(config.name || '');
    const [amount, setAmount] = React.useState(config.amount || '');
    const [codePrefix, setCodePrefix] = React.useState(config.codePrefix || '');
    const [validityDays, setValidityDays] = React.useState(config.validityDays || 365);
    const [templateId, setTemplateId] = React.useState(String(config.templateId || ''));
    const [active, setActive] = React.useState(Boolean(config.active));

    return (
        <AppProvider i18n={{}}>
            <Card>
                <Box padding="400">
                    <BlockStack gap="400">
                        <Text as="h2" variant="headingMd">{config.title}</Text>
                        <Layout>
                            <Layout.Section variant="oneHalf">
                                <TextField label="Name" value={name} onChange={setName} autoComplete="off" />
                            </Layout.Section>
                            <Layout.Section variant="oneHalf">
                                <TextField label="Amount" value={amount} onChange={setAmount} autoComplete="off" />
                            </Layout.Section>
                            <Layout.Section variant="oneHalf">
                                <TextField label="Code Prefix" value={codePrefix} onChange={setCodePrefix} autoComplete="off" />
                            </Layout.Section>
                            <Layout.Section variant="oneHalf">
                                <TextField label="Validity Days" value={String(validityDays)} onChange={setValidityDays} type="number" autoComplete="off" />
                            </Layout.Section>
                            <Layout.Section variant="oneHalf">
                                <Select
                                    label="Template"
                                    options={[
                                        { label: 'None', value: '' },
                                        ...(config.templates || []).map((template) => ({ label: template.name, value: String(template.id) })),
                                    ]}
                                    value={templateId}
                                    onChange={setTemplateId}
                                />
                            </Layout.Section>
                        </Layout>
                        <input type="hidden" name="name" value={name} />
                        <input type="hidden" name="amount" value={amount} />
                        <input type="hidden" name="code_prefix" value={codePrefix} />
                        <input type="hidden" name="validity_days" value={validityDays} />
                        <input type="hidden" name="template_id" value={templateId} />
                        <input type="hidden" name="active" value={active ? '1' : '0'} />
                        <input type="hidden" name="_token" value={config.csrfToken} />
                        <label style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                            <input type="checkbox" checked={active} onChange={(event) => setActive(event.target.checked)} />
                            <Text as="span">Active</Text>
                        </label>
                    </BlockStack>
                </Box>
            </Card>
        </AppProvider>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const templateMount = document.getElementById('giftcard-template-react');
    if (templateMount) {
        const templateId = templateMount.dataset.templateId || '';
        const templates = parseConfig(templateMount.dataset.templates, []);
        createRoot(templateMount).render(<GiftCardTemplateSelect templateId={templateId} templates={templates} />);
    }

    const activeMount = document.getElementById('giftcard-active-react');
    if (activeMount) {
        const checked = activeMount.dataset.checked === '1';
        createRoot(activeMount).render(<GiftCardActiveToggle checked={checked} />);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('templates-list-react');
    if (!mount) return;

    const config = parseConfig(mount.dataset.config, {});
    createRoot(mount).render(<TemplatesList config={config} />);
});

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('template-form-react');
    if (!mount) return;

    const config = parseConfig(mount.dataset.config, {});
    createRoot(mount).render(<TemplateFormIsland config={config} />);
});

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('giftcards-list-react');
    if (!mount) return;

    const config = parseConfig(mount.dataset.config, {});
    createRoot(mount).render(<GiftCardsList config={config} />);
});

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('giftcard-form-react');
    if (!mount) return;

    const config = parseConfig(mount.dataset.config, {});
    createRoot(mount).render(<GiftCardFormIsland config={config} />);
});
