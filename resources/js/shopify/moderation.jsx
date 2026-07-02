import React from 'react';
import { createRoot } from 'react-dom/client';
import { AppProvider, Card, Button, TextField, BlockStack, InlineStack, Box, Text, Tabs, Layout, Badge } from '@shopify/polaris';

function parseConfig(raw, fallback = {}) {
    if (!raw) return fallback;
    try {
        return JSON.parse(raw);
    } catch (e) {
        return fallback;
    }
}

function formatStatus(status) {
    if (!status) return 'Unknown';
    return status.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function formatAuditValue(val) {
    if (!val) return '-';
    let obj = val;
    if (typeof val === 'string') {
        try {
            obj = JSON.parse(val);
        } catch (e) {
            return <span>{val}</span>;
        }
    }
    if (typeof obj !== 'object' || obj === null) {
        return <span>{String(obj)}</span>;
    }

    const items = [];
    if (obj.remaining_balance !== undefined) {
        items.push(
            <div key="balance">
                <span style={{ color: 'var(--p-color-text-secondary)', fontSize: '12px' }}>Balance: </span>
                <strong style={{ fontSize: '13px' }}>${Number(obj.remaining_balance).toFixed(2)}</strong>
            </div>
        );
    }
    if (obj.status !== undefined) {
        items.push(
            <div key="status">
                <span style={{ color: 'var(--p-color-text-secondary)', fontSize: '12px' }}>Status: </span>
                <span style={{ fontSize: '13px', fontWeight: 600 }}>{formatStatus(obj.status)}</span>
            </div>
        );
    }
    if (obj.recipient_email !== undefined) {
        items.push(
            <div key="email">
                <span style={{ color: 'var(--p-color-text-secondary)', fontSize: '12px' }}>Email: </span>
                <span style={{ fontSize: '13px', fontFamily: 'monospace' }}>{obj.recipient_email}</span>
            </div>
        );
    }

    if (items.length === 0) {
        return <span style={{ fontSize: '12px', fontFamily: 'monospace' }}>{JSON.stringify(obj)}</span>;
    }

    return (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '4px' }}>
            {items}
        </div>
    );
}

function getBadgeTone(status) {
    switch (status) {
        case 'unused': return 'success';
        case 'delivered': return 'info';
        case 'partially_used': return 'attention';
        case 'used': return 'critical';
        case 'revoked': return 'critical';
        default: return 'attention';
    }
}

function ModerationTool({ shopDomain, vouchersUrl, resendEmailUrl, adjustBalanceUrl, revokeUrl }) {
    const [searchCode, setSearchCode] = React.useState('');
    const [suggestions, setSuggestions] = React.useState([]);
    const [loading, setLoading] = React.useState(false);
    const [actionLoading, setActionLoading] = React.useState(false);
    const [voucher, setVoucher] = React.useState(null);
    const [transactions, setTransactions] = React.useState([]);
    const [auditLogs, setAuditLogs] = React.useState([]);
    const [errorMsg, setErrorMsg] = React.useState('');
    const [successMsg, setSuccessMsg] = React.useState('');

    // Tab selection state
    const [selectedTab, setSelectedTab] = React.useState(0);

    // Form inputs state
    const [resendEmail, setResendEmail] = React.useState('');
    const [adjustBalanceVal, setAdjustBalanceVal] = React.useState('');
    const [adjustReason, setAdjustReason] = React.useState('');
    const [revokeReason, setRevokeReason] = React.useState('');

    // Fetch autocomplete suggestions as user types
    React.useEffect(() => {
        if (searchCode.trim().length < 2) {
            setSuggestions([]);
            return;
        }

        const delayDebounce = setTimeout(async () => {
            try {
                const response = await fetch(`${vouchersUrl}?q=${encodeURIComponent(searchCode)}`);
                if (response.ok) {
                    const data = await response.json();
                    setSuggestions(data);
                }
            } catch (err) {
                console.error('Error fetching suggestions:', err);
            }
        }, 300);

        return () => clearTimeout(delayDebounce);
    }, [searchCode]);

    // Load voucher details
    const fetchVoucherDetails = async (codeToFetch) => {
        setLoading(true);
        setErrorMsg('');
        setSuccessMsg('');
        setSuggestions([]);
        try {
            const response = await fetch(`${vouchersUrl}?code=${encodeURIComponent(codeToFetch)}`);
            const data = await response.json();
            if (response.ok && data.success) {
                setVoucher(data.voucher);
                setTransactions(data.transactions || []);
                setAuditLogs(data.auditLogs || []);

                // Prefill actions state
                setResendEmail(data.voucher.recipient_email || '');
                setAdjustBalanceVal(data.voucher.remaining_balance.toString());
                setAdjustReason('');
                setRevokeReason('');
            } else {
                setErrorMsg(data.message || 'Voucher not found.');
                setVoucher(null);
            }
        } catch (err) {
            setErrorMsg('Failed to load voucher details.');
            setVoucher(null);
        } finally {
            setLoading(false);
        }
    };

    const handleSearchSubmit = (e) => {
        if (e) e.preventDefault();
        if (searchCode.trim()) {
            fetchVoucherDetails(searchCode.trim());
        }
    };

    // Manual Resend Action
    const handleResendEmail = async () => {
        if (!voucher) return;
        setActionLoading(true);
        setErrorMsg('');
        setSuccessMsg('');
        try {
            const response = await fetch(resendEmailUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    voucher_id: voucher.id,
                    recipient_email: resendEmail
                })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setSuccessMsg('Gift card email has been resent successfully.');
                fetchVoucherDetails(voucher.code); // refresh
            } else {
                setErrorMsg(data.message || 'Failed to resend email.');
            }
        } catch (err) {
            setErrorMsg('An error occurred while resending the email.');
        } finally {
            setActionLoading(false);
        }
    };

    // Adjust Balance Action
    const handleAdjustBalance = async () => {
        if (!voucher) return;
        if (!adjustReason.trim()) {
            setErrorMsg('Reason is required for auditing balance changes.');
            return;
        }
        const cleanVal = adjustBalanceVal.toString().replace(/[^\d.]/g, '');
        const val = parseFloat(cleanVal);
        const maxAmt = parseFloat(voucher.original_amount);
        if (isNaN(val) || val < 0 || val > maxAmt) {
            setErrorMsg(`Balance must be a positive number and cannot exceed original amount ($${maxAmt.toFixed(2)}).`);
            return;
        }

        setActionLoading(true);
        setErrorMsg('');
        setSuccessMsg('');
        try {
            const response = await fetch(adjustBalanceUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    voucher_id: voucher.id,
                    remaining_balance: val,
                    reason: adjustReason
                })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setSuccessMsg('Voucher balance adjusted successfully! You can now use the "Manual Resend" tab to send the updated gift card email to the customer.');
                fetchVoucherDetails(voucher.code); // refresh
            } else {
                setErrorMsg(data.message || 'Failed to adjust balance.');
            }
        } catch (err) {
            setErrorMsg('An error occurred while adjusting the balance.');
        } finally {
            setActionLoading(false);
        }
    };

    // Revoke Action
    const handleRevoke = async () => {
        if (!voucher) return;
        if (!revokeReason.trim()) {
            setErrorMsg('Reason is required to revoke the voucher.');
            return;
        }

        setActionLoading(true);
        setErrorMsg('');
        setSuccessMsg('');
        try {
            const response = await fetch(revokeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    voucher_id: voucher.id,
                    reason: revokeReason
                })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                setSuccessMsg('Voucher has been revoked and canceled successfully. You can notify the customer by resending the email using the "Manual Resend" tab if needed.');
                fetchVoucherDetails(voucher.code); // refresh
            } else {
                setErrorMsg(data.message || 'Failed to revoke voucher.');
            }
        } catch (err) {
            setErrorMsg('An error occurred while revoking the voucher.');
        } finally {
            setActionLoading(false);
        }
    };

    const tabs = [
        { id: 'resend', content: 'Manual Resend', accessibilityLabel: 'Manual Resend' },
        { id: 'adjust', content: 'Adjust Balance', accessibilityLabel: 'Adjust Balance' },
        { id: 'revoke', content: 'Revoke', accessibilityLabel: 'Revoke' },
    ];

    return (
        <AppProvider i18n={{}}>
            <BlockStack gap="500">

                {/* Search Bar */}
                <Card>
                    <Box padding="400">
                        <form onSubmit={handleSearchSubmit}>
                            <BlockStack gap="300">
                                <Text as="h3" variant="headingMd">Select Gift Card Voucher</Text>
                                <div style={{ position: 'relative' }}>
                                    <InlineStack gap="300" blockAlign="center">
                                        <div style={{ flex: 1 }}>
                                            <TextField
                                                label="Voucher Code"
                                                labelHidden
                                                value={searchCode}
                                                onChange={setSearchCode}
                                                placeholder="Enter voucher code (e.g. GF-XXXX...)"
                                                autoComplete="off"
                                            />
                                        </div>
                                        <Button submit variant="primary" loading={loading}>Search</Button>
                                    </InlineStack>

                                    {suggestions.length > 0 && (
                                        <div style={{
                                            position: 'absolute',
                                            top: '100%',
                                            left: 0,
                                            right: 0,
                                            background: '#ffffff',
                                            border: '1px solid var(--p-color-border-subdued)',
                                            borderRadius: 'var(--p-border-radius-200)',
                                            zIndex: 999,
                                            boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
                                            marginTop: '4px'
                                        }}>
                                            {suggestions.map((code) => (
                                                <div
                                                    key={code}
                                                    onClick={() => {
                                                        setSearchCode(code);
                                                        fetchVoucherDetails(code);
                                                    }}
                                                    style={{
                                                        padding: '10px 14px',
                                                        cursor: 'pointer',
                                                        borderBottom: '1px solid #f1f1f1',
                                                        fontFamily: 'monospace',
                                                        fontWeight: 600,
                                                        color: 'var(--p-color-text)'
                                                    }}
                                                    onMouseEnter={(e) => e.target.style.background = '#f4f6f8'}
                                                    onMouseLeave={(e) => e.target.style.background = 'transparent'}
                                                >
                                                    {code}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </BlockStack>
                        </form>
                    </Box>
                </Card>

                {/* Error and Success Notifications */}
                {errorMsg && (
                    <Box padding="300" background="bg-surface-critical" borderRadius="200" borderStyle="solid" borderWidth="025" borderColor="border-critical">
                        <Text tone="critical" fontWeight="semibold">{errorMsg}</Text>
                    </Box>
                )}
                {successMsg && (
                    <Box padding="300" background="bg-surface-success" borderRadius="200" borderStyle="solid" borderWidth="025" borderColor="border-success">
                        <Text tone="success" fontWeight="semibold">{successMsg}</Text>
                    </Box>
                )}

                {/* Voucher Info & Moderation Action Cards */}
                {voucher && (
                    <BlockStack gap="500">
                        <Layout>
                            {/* Details Card */}
                            <Layout.Section variant="oneThird">
                                <Card>
                                    <Box padding="400">
                                        <BlockStack gap="400">
                                            <Text as="h3" variant="headingMd">Voucher Details</Text>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Voucher Code</Text>
                                                <Text as="p" variant="bodyMd" fontWeight="bold" style={{ fontFamily: 'monospace' }}>{voucher.code}</Text>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Recipient</Text>
                                                <Text as="p" variant="bodyMd" fontWeight="semibold">{voucher.recipient_name}</Text>
                                                <Text as="p" variant="bodyXs" tone="subdued">{voucher.recipient_email}</Text>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Sender</Text>
                                                <Text as="p" variant="bodyMd" fontWeight="semibold">{voucher.sender_name}</Text>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Status</Text>
                                                <Box style={{ marginTop: '4px' }}>
                                                    <Badge tone={getBadgeTone(voucher.status)}>{formatStatus(voucher.status)}</Badge>
                                                </Box>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Original Amount</Text>
                                                <Text as="p" variant="bodyMd" fontWeight="bold" tone="success">${Number(voucher.original_amount).toFixed(2)}</Text>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Remaining Balance</Text>
                                                <Text as="p" variant="bodyMd" fontWeight="bold" tone={voucher.status === 'used' || voucher.status === 'revoked' ? 'critical' : 'attention'}>
                                                    ${Number(voucher.remaining_balance).toFixed(2)}
                                                </Text>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Expires At</Text>
                                                <Text as="p" variant="bodyMd">{voucher.expires_at}</Text>
                                            </div>

                                            <div>
                                                <Text as="p" variant="bodyXs" tone="subdued">Sent At</Text>
                                                <Text as="p" variant="bodyMd">{voucher.sent_at}</Text>
                                            </div>
                                        </BlockStack>
                                    </Box>
                                </Card>
                            </Layout.Section>

                            {/* Actions Card */}
                            <Layout.Section>
                                <Card>
                                    <Tabs tabs={tabs} selected={selectedTab} onSelect={setSelectedTab}>
                                        <Box padding="400">
                                            {selectedTab === 0 && (
                                                <BlockStack gap="300">
                                                    <Text as="p" tone="subdued">
                                                        Manually trigger a resend of the gift card email. You can update the recipient's email address below if needed.
                                                    </Text>
                                                    <TextField
                                                        label="Recipient Email"
                                                        value={resendEmail}
                                                        onChange={setResendEmail}
                                                        autoComplete="off"
                                                        placeholder="Enter email address"
                                                    />
                                                    <Box style={{ marginTop: '10px' }}>
                                                        <Button variant="primary" onClick={handleResendEmail} loading={actionLoading}>
                                                            Resend Gift Card Email
                                                        </Button>
                                                    </Box>
                                                </BlockStack>
                                            )}

                                            {selectedTab === 1 && (
                                                <BlockStack gap="300">
                                                    <Text as="p" tone="subdued">
                                                        Manually adjust the remaining balance of the gift card. The balance cannot exceed the original voucher value of <strong>${Number(voucher.original_amount).toFixed(2)}</strong>.
                                                    </Text>

                                                    <TextField
                                                        label="New Remaining Balance"
                                                        type="number"
                                                        value={adjustBalanceVal}
                                                        onChange={setAdjustBalanceVal}
                                                        prefix="$"
                                                        autoComplete="off"
                                                        placeholder="0.00"
                                                    />

                                                    <TextField
                                                        label="Reason"
                                                        value={adjustReason}
                                                        onChange={setAdjustReason}
                                                        autoComplete="off"
                                                        placeholder="Explain why you are adjusting the balance (required)..."
                                                    />

                                                    <Box style={{ marginTop: '10px' }}>
                                                        <Button variant="primary" onClick={handleAdjustBalance} loading={actionLoading}>
                                                            Adjust Voucher Balance
                                                        </Button>
                                                    </Box>
                                                </BlockStack>
                                            )}

                                            {selectedTab === 2 && (
                                                <BlockStack gap="300">
                                                    <Text as="p" tone="subdued">
                                                        Deactivate this voucher. Once revoked, it cannot be redeemed or used in checkout. A reason is required for the audit log.
                                                    </Text>

                                                    <TextField
                                                        label="Reason"
                                                        value={revokeReason}
                                                        onChange={setRevokeReason}
                                                        multiline={3}
                                                        autoComplete="off"
                                                        placeholder="Explain why this voucher is being deactivated (required)..."
                                                    />

                                                    <Box style={{ marginTop: '10px' }}>
                                                        <Button variant="primary" tone="critical" onClick={handleRevoke} loading={actionLoading} disabled={voucher.status === 'revoked'}>
                                                            {voucher.status === 'revoked' ? 'Already Revoked' : 'Revoke Voucher'}
                                                        </Button>
                                                    </Box>
                                                </BlockStack>
                                            )}
                                        </Box>
                                    </Tabs>
                                </Card>
                            </Layout.Section>
                        </Layout>

                        {/* Audit Logs Table (Full Width) */}
                        <Card>
                            <Box padding="400">
                                <BlockStack gap="300">
                                    <Text as="h3" variant="headingMd">Audit Logs</Text>
                                    <div style={{ overflowX: 'auto', border: '1px solid var(--p-color-border-subdued)', borderRadius: 'var(--p-border-radius-200)' }}>
                                        <table style={{ width: '100%', borderCollapse: 'collapse', textAlign: 'left', minWidth: '600px' }}>
                                            <thead>
                                                <tr style={{ background: 'var(--p-color-bg-surface-secondary)', borderBottom: '1px solid var(--p-color-border-subdued)' }}>
                                                    <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: 600, color: 'var(--p-color-text-secondary)' }}>Date / Time</th>
                                                    <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: 600, color: 'var(--p-color-text-secondary)' }}>Action</th>
                                                    <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: 600, color: 'var(--p-color-text-secondary)' }}>Old Value</th>
                                                    <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: 600, color: 'var(--p-color-text-secondary)' }}>New Value</th>
                                                    <th style={{ padding: '12px 16px', fontSize: '13px', fontWeight: 600, color: 'var(--p-color-text-secondary)' }}>Reason</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {auditLogs.length === 0 ? (
                                                    <tr>
                                                        <td colSpan="5" style={{ padding: '24px 16px', textAlign: 'center', color: 'var(--p-color-text-secondary)' }}>
                                                            No audit logs recorded for this voucher.
                                                        </td>
                                                    </tr>
                                                ) : (
                                                    auditLogs.map((log) => (
                                                        <tr key={log.id} style={{ borderBottom: '1px solid var(--p-color-border-subdued)' }}>
                                                            <td style={{ padding: '12px 16px', color: 'var(--p-color-text)', whiteSpace: 'nowrap' }}>{log.created_at}</td>
                                                            <td style={{ padding: '12px 16px' }}>
                                                                <Badge tone={log.action === 'revoke' ? 'critical' : log.action === 'adjust_balance' ? 'attention' : 'info'}>
                                                                    {formatStatus(log.action)}
                                                                </Badge>
                                                            </td>
                                                            <td style={{ padding: '12px 16px', color: 'var(--p-color-text)' }}>
                                                                {formatAuditValue(log.old_value)}
                                                            </td>
                                                            <td style={{ padding: '12px 16px', color: 'var(--p-color-text)' }}>
                                                                {formatAuditValue(log.new_value)}
                                                            </td>
                                                            <td style={{ padding: '12px 16px', color: 'var(--p-color-text)' }}>{log.reason || '-'}</td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </BlockStack>
                            </Box>
                        </Card>
                    </BlockStack>
                )}
            </BlockStack>
        </AppProvider>
    );
}

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('moderation-tool-react');
    if (!mount) return;

    const dataset = mount.dataset;
    createRoot(mount).render(
        <ModerationTool
            shopDomain={dataset.shopDomain}
            vouchersUrl={dataset.vouchersUrl}
            resendEmailUrl={dataset.resendEmailUrl}
            adjustBalanceUrl={dataset.adjustBalanceUrl}
            revokeUrl={dataset.revokeUrl}
        />
    );
});
