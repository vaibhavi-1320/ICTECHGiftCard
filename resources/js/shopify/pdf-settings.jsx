import React from 'react';
import { createRoot } from 'react-dom/client';
import { AppProvider, Badge, Card, Button, ButtonGroup, Select, TextField, BlockStack, InlineStack, Box, Text, Tabs, Layout, Divider, Pagination } from '@shopify/polaris';

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

function DashboardOverview({ stats: initialStats }) {
    const [stats, setStats] = React.useState(initialStats);
    const [hoveredCardIndex, setHoveredCardIndex] = React.useState(null);

    React.useEffect(() => {
        const handleUpdate = (event) => {
            if (event.detail) {
                setStats(event.detail);
            }
        };
        window.addEventListener('update-dashboard-stats', handleUpdate);
        return () => window.removeEventListener('update-dashboard-stats', handleUpdate);
    }, []);

    const cards = [
        { label: 'Total Vouchers', value: stats.totalVouchers, tone: 'base' },
        { label: 'Pending Issuance', value: stats.pendingVouchers, tone: 'attention' },
        { label: 'Expired Vouchers', value: stats.expiredVouchers, tone: 'critical' },
        { label: 'Total Sold', value: `$${Number(stats.totalSold || 0).toFixed(2)}`, tone: 'success' },
        { label: 'Total Redeemed', value: `$${Number(stats.redeemedAmount || 0).toFixed(2)}`, tone: 'info' },
        { label: 'App Status', value: 'Active', tone: 'success' },
    ];

    return (
        <AppProvider i18n={{}}>
            <BlockStack gap="400">
                <Text as="h2" variant="headingMd">Overview Statistics</Text>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: '12px' }}>
                    {cards.map((card, index) => (
                        <div
                            key={card.label}
                            onMouseEnter={() => setHoveredCardIndex(index)}
                            onMouseLeave={() => setHoveredCardIndex(null)}
                            style={{
                                transform: hoveredCardIndex === index ? 'translateY(-5px)' : 'translateY(0)',
                                boxShadow: hoveredCardIndex === index 
                                    ? '0 10px 20px rgba(0, 0, 0, 0.08), 0 2px 6px rgba(0, 0, 0, 0.04)' 
                                    : '0 1px 3px rgba(0, 0, 0, 0.05)',
                                transition: 'transform 0.25s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.25s cubic-bezier(0.25, 0.8, 0.25, 1)',
                                borderRadius: 'var(--p-border-radius-200)',
                                display: 'flex',
                                flexDirection: 'column'
                            }}
                        >
                            <Card>
                                <Box padding="400">
                                    <BlockStack gap="100">
                                        <Text as="p" variant="bodySm" tone="subdued">{card.label}</Text>
                                        <Text as="p" variant="headingLg" tone={card.tone === 'success' ? 'success' : 'base'}>{card.value}</Text>
                                    </BlockStack>
                                </Box>
                            </Card>
                        </div>
                    ))}
                </div>
            </BlockStack>
        </AppProvider>
    );
}

function DashboardControls({ config, onFilterChange }) {
    const [values, setValues] = React.useState(config.filters || {});

    // Debounce search input
    React.useEffect(() => {
        const handler = setTimeout(() => {
            const isOrders = config.title === 'Gift Card Orders';
            const searchVal = values.search || '';
            const configSearchVal = config.filters?.search || '';
            if (isOrders && searchVal !== configSearchVal) {
                onFilterChange(values);
            }
        }, 500);
        return () => clearTimeout(handler);
    }, [values.search]);

    // Handle dropdown/date changes immediately
    React.useEffect(() => {
        const isOrders = config.title === 'Gift Card Orders';
        const statusChanged = isOrders && (values.status || '') !== (config.filters?.status || '');
        const fromChanged = (values.from || '') !== (config.filters?.from || '');
        const toChanged = (values.to || '') !== (config.filters?.to || '');

        if (statusChanged || fromChanged || toChanged) {
            onFilterChange(values);
        }
    }, [values.status, values.from, values.to]);

    const reset = () => {
        const defaultFilters = config.title === 'Gift Card Orders'
            ? { search: '', status: '', from: '', to: '' }
            : { from: '', to: '' };
        setValues(defaultFilters);
        onFilterChange(defaultFilters);
    };

    const submit = (event) => {
        event.preventDefault();
        onFilterChange(values);
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
                        {config.title === 'Gift Card Orders' ? (
                            <>
                                <TextField
                                    label="Search"
                                    placeholder="Search order, customer, email..."
                                    value={values.search || ''}
                                    onChange={(value) => setValues((prev) => ({ ...prev, search: value }))}
                                    autoComplete="off"
                                />
                                <Select
                                    label="Filter Status"
                                    options={[
                                        { label: 'All statuses', value: '' },
                                        { label: 'Completed', value: 'completed' },
                                        { label: 'Pending', value: 'pending' },
                                        { label: 'Unused', value: 'unused' },
                                        { label: 'Used', value: 'used' },
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
                        <Button submit variant="primary">Filter / Search</Button>
                        <Button onClick={reset}>Reset</Button>
                    </InlineStack>
                </form>
            </Box>
        </Card>
    );
}

function DashboardTable({ config, onPageChange }) {
    const rows = config.rows || [];
    const isOrders = config.title === 'Gift Card Orders';
    const [hoveredRowId, setHoveredRowId] = React.useState(null);

    return (
        <Card>
            <Box padding="400">
                <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                    <thead>
                        <tr style={{ textAlign: 'left', background: 'var(--p-color-bg-surface-secondary)' }}>
                            {isOrders ? (
                                <>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Shopify Order</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Voucher Code</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Customer Name</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Recipient</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Recipient Email</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Amount</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Template</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Delivery Date</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Status</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Created At</th>
                                </>
                            ) : (
                                <>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Code</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Amount Used</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Balance Before</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Balance After</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Order</th>
                                    <th style={{ padding: '14px 16px', color: 'var(--p-color-text-secondary)', fontWeight: 600, borderBottom: '2px solid var(--p-color-border-subdued)' }}>Date</th>
                                </>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={isOrders ? 10 : 6} style={{ padding: '32px 16px', textAlign: 'center', color: 'var(--p-color-text-disabled)' }}>
                                    {isOrders ? 'No gift card orders found.' : 'No redemptions found.'}
                                </td>
                            </tr>
                        ) : rows.map((row) => (
                            <tr
                                key={row.id}
                                onMouseEnter={() => setHoveredRowId(row.id)}
                                onMouseLeave={() => setHoveredRowId(null)}
                                style={{
                                    borderTop: '1px solid var(--p-color-border-subdued)',
                                    background: hoveredRowId === row.id ? 'var(--p-color-bg-surface-hover)' : 'transparent',
                                    transition: 'background-color 0.2s ease',
                                    cursor: 'pointer'
                                }}
                            >
                                {isOrders ? (
                                    <>
                                        <td style={{ padding: '14px 16px', fontWeight: 600, color: 'var(--p-color-text)' }}>{row.shopifyOrderNumber}</td>
                                        <td style={{ padding: '14px 16px', fontFamily: 'monospace', fontWeight: 600, color: 'var(--p-color-text)' }}>{row.voucherCode}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.customerName || '-'}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.recipientName || '-'}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.recipientEmail || '-'}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)', fontWeight: 600 }}>${Number(row.amount || 0).toFixed(2)}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.templateName}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.deliveryDate || '-'}</td>
                                        <td style={{ padding: '14px 16px' }}>
                                            <Badge tone={row.voucherStatus === 'used' ? 'critical' : row.voucherStatus === 'unused' ? 'success' : 'attention'}>
                                                {String(row.voucherStatus || '').replaceAll('_', ' ')}
                                            </Badge>
                                        </td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.createdAt || '-'}</td>
                                    </>
                                ) : (
                                    <>
                                        <td style={{ padding: '14px 16px', fontFamily: 'monospace', fontWeight: 600, color: 'var(--p-color-text)' }}>{row.code || 'Deleted'}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text-critical)', fontWeight: 600 }}>-${Number(row.amountUsed || 0).toFixed(2)}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>${Number(row.balanceBefore || 0).toFixed(2)}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>${Number(row.balanceAfter || 0).toFixed(2)}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.orderId || '-'}</td>
                                        <td style={{ padding: '14px 16px', color: 'var(--p-color-text)' }}>{row.createdAt || '-'}</td>
                                    </>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Box>
            {config.pagination && config.pagination.lastPage > 1 && (
                <Box padding="400" borderBlockStartWidth="025" borderColor="border-subdued">
                    <InlineStack align="center">
                        <Pagination
                            hasPrevious={config.pagination.hasPrevious}
                            onPrevious={() => {
                                onPageChange(config.pagination.currentPage - 1);
                            }}
                            hasNext={config.pagination.hasNext}
                            onNext={() => {
                                onPageChange(config.pagination.currentPage + 1);
                            }}
                        />
                    </InlineStack>
                </Box>
            )}
        </Card>
    );
}

function DashboardSection({ initialConfig }) {
    const [config, setConfig] = React.useState(initialConfig);
    const [loading, setLoading] = React.useState(false);

    const handleFilterChange = async (filters, page = null) => {
        setLoading(true);
        try {
            const url = new URL(window.location.href);
            const params = new URLSearchParams(url.search);

            // Preserve shop & host params from current URL or initialConfig fallback
            const shop = params.get('shop') || initialConfig.shop || '';
            const host = params.get('host') || initialConfig.host || '';
            if (shop) params.set('shop', shop);
            if (host) params.set('host', host);

            // Set filter params
            Object.entries(filters).forEach(([key, value]) => {
                const paramKey = initialConfig.title === 'Gift Card Orders'
                    ? (key === 'status' ? 'p_status' : (key === 'search' ? 'p_search' : `p_${key}`))
                    : `u_${key}`;
                if (value === null || value === undefined || value === '') {
                    params.delete(paramKey);
                } else {
                    params.set(paramKey, value);
                }
            });

            // Set page param if specified
            const pageParam = initialConfig.title === 'Gift Card Orders' ? 'p_page' : 'u_page';
            if (page) {
                params.set(pageParam, page);
            } else {
                params.delete(pageParam);
            }

            const fetchUrl = `${url.pathname}?${params.toString()}`;

            // Update browser URL state history so back/refresh works, but without reload!
            window.history.replaceState(null, '', fetchUrl);

            const response = await fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                
                setConfig((prev) => ({
                    ...prev,
                    rows: initialConfig.title === 'Gift Card Orders' ? data.purchasedRows : data.usedRows,
                    pagination: initialConfig.title === 'Gift Card Orders' ? data.purchasedPagination : data.usedPagination,
                    filters: filters
                }));

                if (data.stats) {
                    const event = new CustomEvent('update-dashboard-stats', { detail: data.stats });
                    window.dispatchEvent(event);
                }
            }
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <BlockStack gap="400">
            <DashboardControls
                config={config}
                onFilterChange={handleFilterChange}
            />
            <div style={{ position: 'relative' }}>
                {loading && (
                    <div style={{
                        position: 'absolute',
                        top: 0,
                        left: 0,
                        right: 0,
                        bottom: 0,
                        background: 'rgba(255, 255, 255, 0.4)',
                        zIndex: 10,
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        borderRadius: 'var(--p-border-radius-200)',
                        backdropFilter: 'blur(1px)'
                    }}>
                        <Text tone="subdued">Loading...</Text>
                    </div>
                )}
                <DashboardTable
                    config={config}
                    onPageChange={(page) => handleFilterChange(config.filters || {}, page)}
                />
            </div>
        </BlockStack>
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
        createRoot(mount).render(
            <AppProvider i18n={{}}>
                <DashboardSection initialConfig={config} />
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
    const [deleteId, setDeleteId] = React.useState(null);
    const selectedRow = rows.find((row) => String(row.id) === String(deleteId)) || null;

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
                                            <Button variant="tertiary" size="slim" onClick={() => setDeleteId(row.id)}>Delete</Button>
                                        </InlineStack>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div style={{ display: 'none' }}>
                    <form id="gift-card-delete-form" method="POST" action={selectedRow?.deleteUrl || '#'}>
                        <input type="hidden" name="_token" value={config.csrfToken} />
                        <input type="hidden" name="_method" value="DELETE" />
                    </form>
                </div>
            </Card>
            {selectedRow ? (
                <div style={{
                    position: 'fixed',
                    inset: 0,
                    background: 'rgba(0,0,0,0.35)',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    zIndex: 9999,
                }}>
                    <Card>
                        <Box padding="400">
                            <BlockStack gap="300">
                                <Text as="h3" variant="headingMd">Delete gift card?</Text>
                                <Text as="p">This cannot be undone. The matching Shopify product will also be deleted.</Text>
                                <InlineStack gap="200" align="end">
                                    <Button onClick={() => setDeleteId(null)}>Cancel</Button>
                                    <Button
                                        variant="primary"
                                        tone="critical"
                                        onClick={() => {
                                            const form = document.getElementById('gift-card-delete-form');
                                            if (form) form.submit();
                                        }}
                                    >
                                        Delete
                                    </Button>
                                </InlineStack>
                            </BlockStack>
                        </Box>
                    </Card>
                </div>
            ) : null}
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
