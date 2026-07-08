import {
    createA4Block,
    createA4Column,
    createDefaultPageBackground,
    createDefaultPageSettings,
    createHeaderSlot,
    createSummaryLine,
    type A4Block,
    type A4PageSettings,
    type A4TextToken,
} from '@/lib/template-builder/a4';
import type { A4TemplateCategory, A4TemplateDocument } from '@/lib/template-builder/a4-template-storage';

function token(path: string): A4TextToken {
    return { kind: 'variable', path };
}

function literal(value: string): A4TextToken {
    return { kind: 'literal', value };
}

const P = {
    org: 'printData.header.organizationDetails',
    store: 'printData.header.storeDetails',
    cluster: 'printData.header.clusterDetails',
    customer: 'printData.header.customerDetails',
    invoice: 'printData.header.invoice.invoiceHeader',
    summary: 'printData.header.invoice.invoiceDetails.invoiceSummary',
    products: 'printData.header.invoice.invoiceDetails.productList',
    tax: 'printData.header.invoice.invoiceDetails.taxSummary',
    payments: 'printData.header.payments',
} as const;

type BuildOptions = {
    id: string;
    name: string;
    description: string;
    category: A4TemplateCategory;
    accent: string;
    pageSettings?: Partial<A4PageSettings>;
    watermark?: boolean;
    watermarkOpacity?: number;
    blocks: A4Block[];
};

function doc(options: BuildOptions): A4TemplateDocument {
    return {
        id: options.id,
        name: options.name,
        description: options.description,
        category: options.category,
        accent: options.accent,
        isCustom: false,
        pageSettings: { ...createDefaultPageSettings(), ...options.pageSettings },
        pageBackground: options.watermark
            ? {
                  enabled: true,
                  sourceType: 'variable',
                  path: `${P.org}.orgLogo`,
                  url: '',
                  opacity: options.watermarkOpacity ?? 10,
                  size: 'contain',
                  position: 'center',
              }
            : createDefaultPageBackground(),
        blocks: options.blocks,
    };
}

function companyHeader(id: string, title: string, extraRight: A4TextToken[] = [], includeCustomer = true) {
    const leftTokens: A4TextToken[] = [
        token(`${P.org}.legalName`),
        literal('\n'),
        token(`${P.store}.addressLine`),
        literal(', '),
        token(`${P.store}.city`),
        literal(' - '),
        token(`${P.store}.pincode`),
        literal('\nGSTIN: '),
        token(`${P.org}.taxRegistrationCode`),
        literal('\nPh: '),
        token(`${P.store}.contactNumber`),
    ];

    if (includeCustomer) {
        leftTokens.push(
            literal('\n\nCustomer: '),
            token(`${P.customer}.customerName`),
            literal('\nPh: '),
            token(`${P.customer}.contactNumber`),
        );
    }

    return {
        id,
        type: 'headerBand' as const,
        showBorder: true,
        left: { ...createHeaderSlot('text'), align: 'left' as const, tokens: leftTokens },
        center: {
            ...createHeaderSlot('image'),
            align: 'center' as const,
            sourceType: 'variable' as const,
            path: `${P.org}.orgLogo`,
            width: '90px',
            maxHeight: '70px',
        },
        right: {
            ...createHeaderSlot('text'),
            align: 'right' as const,
            tokens: [
                literal(`${title}\n`),
                token(`${P.invoice}.localDate`),
                literal(' '),
                token(`${P.invoice}.localTime`),
                literal('\nNo: '),
                token(`${P.invoice}.invoiceNo`),
                ...extraRight,
            ],
        },
    };
}

function fullProductTable(id: string) {
    return {
        id,
        type: 'table' as const,
        arrayPath: P.products,
        variant: 'invoice' as const,
        compact: true,
        showHeader: true,
        columns: [
            { ...createA4Column('index'), header: '#', width: '4%', align: 'center' as const },
            { ...createA4Column('variable'), header: 'HSN', path: 'hsnCode', width: '8%', align: 'center' as const },
            {
                ...createA4Column('variable'),
                header: 'Product Details',
                path: 'name',
                width: '30%',
                tokens: [token('name'), literal('\n['), token('category'), literal(' | '), token('brand'), literal(']')],
            },
            { ...createA4Column('variable'), header: 'GST%', path: 'taxper', width: '7%', align: 'center' as const, format: 'number' as const },
            { ...createA4Column('variable'), header: 'Qty', path: 'qty', width: '6%', align: 'center' as const },
            { ...createA4Column('variable'), header: 'MRP', path: 'unitMrp', width: '10%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'Rate', path: 'effectivePrice', width: '10%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'Amount', path: 'total', width: '12%', align: 'right' as const, format: 'currency' as const },
        ],
    };
}

function simpleProductTable(id: string) {
    return {
        id,
        type: 'table' as const,
        arrayPath: P.products,
        variant: 'invoice' as const,
        compact: true,
        showHeader: true,
        columns: [
            { ...createA4Column('index'), header: '#', width: '6%', align: 'center' as const },
            { ...createA4Column('variable'), header: 'Item', path: 'name', width: '44%' },
            { ...createA4Column('variable'), header: 'Qty', path: 'qty', width: '10%', align: 'center' as const },
            { ...createA4Column('variable'), header: 'Rate', path: 'effectivePrice', width: '18%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'Amount', path: 'total', width: '18%', align: 'right' as const, format: 'currency' as const },
        ],
    };
}

function taxTable(id: string) {
    return {
        id,
        type: 'table' as const,
        arrayPath: P.tax,
        variant: 'tax' as const,
        compact: true,
        showHeader: true,
        columns: [
            { ...createA4Column('variable'), header: 'Description', path: 'name', width: '22%' },
            { ...createA4Column('variable'), header: 'Taxable', path: 'taxable', width: '14%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'IGST', path: 'igst', width: '12%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'CGST', path: 'cgst', width: '12%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'SGST', path: 'sgst', width: '12%', align: 'right' as const, format: 'currency' as const },
            { ...createA4Column('variable'), header: 'CESS', path: 'cess', width: '12%', align: 'right' as const, format: 'currency' as const },
        ],
    };
}

function summaryPanel(id: string) {
    return {
        id,
        type: 'summaryPanel' as const,
        leftLabel: 'In words:',
        leftTokens: [token(`${P.summary}.totalInWords`)],
        rightLines: [
            { ...createSummaryLine('Gross Amount'), path: `${P.summary}.subtotal` },
            { ...createSummaryLine('Total GST'), path: `${P.summary}.taxableAmount` },
            { ...createSummaryLine('Net Amount', true), path: `${P.summary}.total` },
        ],
    };
}

function standardTerms(id: string) {
    return {
        id,
        type: 'terms' as const,
        title: 'Terms & Conditions',
        size: 'xs' as const,
        tokens: [
            literal(
                '1. Goods once sold will not be taken back except as per store policy.\n2. All disputes subject to local jurisdiction.\n3. Verify items and amounts before leaving the counter.',
            ),
        ],
    };
}

function buildFullTaxInvoice(id: string, name: string, description: string, accent: string): A4TemplateDocument {
    return doc({
        id,
        name,
        description,
        category: 'tax-invoice',
        accent,
        watermark: true,
        pageSettings: { marginMm: 12, fontFamily: 'Arial', baseFontSize: 11 },
        blocks: [
            companyHeader(`${id}-header`, 'Tax Invoice', [literal('\nSales: '), token(`${P.invoice}.cashierName`)]),
            fullProductTable(`${id}-products`),
            summaryPanel(`${id}-summary`),
            taxTable(`${id}-tax`),
            { id: `${id}-spacer`, type: 'spacer', height: 10 },
            { id: `${id}-notice`, type: 'text', align: 'center', size: 'sm', bold: true, uppercase: true, tokens: [literal('Thank you for shopping with us')] },
            standardTerms(`${id}-terms`),
        ],
    });
}

export const BUILTIN_A4_TEMPLATES: A4TemplateDocument[] = [
    buildFullTaxInvoice('tpl-tax-pro', 'Professional Tax Invoice', 'Full GST invoice with products, tax breakup, and terms.', 'sky'),
    buildFullTaxInvoice('tpl-tax-fashion', 'Fashion Retail Invoice', 'Premium retail layout with watermark logo and footer notice.', 'violet'),
    doc({
        id: 'tpl-tax-compact',
        name: 'Compact GST Bill',
        description: 'Space-saving invoice for high-volume billing counters.',
        category: 'tax-invoice',
        accent: 'emerald',
        pageSettings: { marginMm: 10, baseFontSize: 10 },
        watermark: true,
        watermarkOpacity: 8,
        blocks: [companyHeader('c-header', 'Tax Invoice'), simpleProductTable('c-table'), summaryPanel('c-summary')],
    }),
    doc({
        id: 'tpl-retail-simple',
        name: 'Simple Product Invoice',
        description: 'Clean header, item table, and totals — no tax annex.',
        category: 'retail',
        accent: 'amber',
        blocks: [companyHeader('s-header', 'Invoice'), simpleProductTable('s-table'), summaryPanel('s-summary')],
    }),
    doc({
        id: 'tpl-b2b',
        name: 'B2B Tax Invoice',
        description: 'Includes customer GSTIN and IRN fields for business buyers.',
        category: 'tax-invoice',
        accent: 'indigo',
        watermark: true,
        blocks: [
            companyHeader('b-header', 'Tax Invoice', [
                literal('\nCustomer GSTIN: '),
                token(`${P.invoice}.customerGstin`),
                literal('\nIRN: '),
                token(`${P.invoice}.irnNumber`),
            ]),
            fullProductTable('b-table'),
            summaryPanel('b-summary'),
            taxTable('b-tax'),
        ],
    }),
    doc({
        id: 'tpl-minimal',
        name: 'Minimal Itemized Bill',
        description: 'Lightweight four-column bill for quick checkout.',
        category: 'retail',
        accent: 'cyan',
        pageSettings: { baseFontSize: 11 },
        blocks: [
            { id: 'm-head', type: 'heading', align: 'center', size: 'md', bold: true, uppercase: false, tokens: [literal('Invoice')] },
            { id: 'm-kv1', type: 'keyValue', label: 'Invoice No', path: `${P.invoice}.invoiceNo`, boldLabel: true, boldValue: false },
            { id: 'm-kv2', type: 'keyValue', label: 'Date', path: `${P.invoice}.localDate`, boldLabel: true, boldValue: false },
            simpleProductTable('m-table'),
            summaryPanel('m-summary'),
        ],
    }),
    doc({
        id: 'tpl-tax-report',
        name: 'Tax Breakup Report',
        description: 'GST summary table with company letterhead.',
        category: 'report',
        accent: 'rose',
        blocks: [
            companyHeader('r-header', 'Tax Summary', [], false),
            taxTable('r-tax'),
            summaryPanel('r-summary'),
        ],
    }),
    doc({
        id: 'tpl-payment',
        name: 'Payment Receipt',
        description: 'Lists payment modes and amounts received.',
        category: 'receipt',
        accent: 'teal',
        blocks: [
            companyHeader('p-header', 'Payment Receipt'),
            {
                id: 'p-table',
                type: 'table',
                arrayPath: P.payments,
                variant: 'standard',
                compact: true,
                showHeader: true,
                columns: [
                    { ...createA4Column('index'), header: '#', width: '8%', align: 'center' },
                    { ...createA4Column('variable'), header: 'Mode', path: 'mop_name', width: '35%' },
                    { ...createA4Column('variable'), header: 'Ref', path: 'ref_number', width: '25%' },
                    { ...createA4Column('variable'), header: 'Amount', path: 'amount', width: '20%', align: 'right', format: 'currency' },
                ],
            },
            { id: 'p-total', type: 'keyValue', label: 'Total Paid', path: `${P.summary}.total`, boldLabel: true, boldValue: true },
        ],
    }),
    doc({
        id: 'tpl-letterhead',
        name: 'Store Letterhead',
        description: 'Branded header band with open content area below.',
        category: 'blank',
        accent: 'orange',
        watermark: true,
        blocks: [
            companyHeader('l-header', 'Official Document', [], false),
            { id: 'l-body', type: 'text', align: 'left', size: 'md', bold: false, uppercase: false, tokens: [literal('Add your document content here…')] },
        ],
    }),
    doc({
        id: 'tpl-delivery',
        name: 'Delivery Challan',
        description: 'Dispatch note with item list and receiver acknowledgement.',
        category: 'retail',
        accent: 'fuchsia',
        blocks: [
            companyHeader('d-header', 'Delivery Challan'),
            simpleProductTable('d-table'),
            { id: 'd-sign', type: 'text', align: 'left', size: 'sm', bold: false, uppercase: false, tokens: [literal('\n\nReceiver signature: ____________________\nDate: ____________________')] },
        ],
    }),
    doc({
        id: 'tpl-quote',
        name: 'Quotation / Estimate',
        description: 'Non-tax quotation with validity note for customers.',
        category: 'retail',
        accent: 'lime',
        blocks: [
            companyHeader('q-header', 'Quotation'),
            simpleProductTable('q-table'),
            summaryPanel('q-summary'),
            { id: 'q-valid', type: 'text', align: 'center', size: 'xs', bold: true, uppercase: false, tokens: [literal('Valid for 7 days from date of issue. Prices subject to change.')] },
        ],
    }),
    doc({
        id: 'tpl-credit',
        name: 'Credit Note',
        description: 'Return / credit document with item and amount summary.',
        category: 'tax-invoice',
        accent: 'pink',
        blocks: [
            companyHeader('cr-header', 'Credit Note'),
            simpleProductTable('cr-table'),
            summaryPanel('cr-summary'),
        ],
    }),
    doc({
        id: 'tpl-cluster',
        name: 'Cluster Store Invoice',
        description: 'Multi-store cluster branding with standard invoice body.',
        category: 'retail',
        accent: 'blue',
        watermark: true,
        blocks: [
            {
                id: 'cl-header',
                type: 'headerBand',
                showBorder: true,
                left: {
                    ...createHeaderSlot('text'),
                    tokens: [
                        token(`${P.cluster}.clusterName`),
                        literal('\n'),
                        token(`${P.store}.storeName`),
                        literal('\n'),
                        token(`${P.store}.addressLine`),
                    ],
                },
                center: { ...createHeaderSlot('image'), path: `${P.org}.orgLogo`, width: '80px', maxHeight: '60px' },
                right: {
                    ...createHeaderSlot('text'),
                    align: 'right',
                    tokens: [literal('Tax Invoice\n'), token(`${P.invoice}.invoiceNo`), literal('\n'), token(`${P.invoice}.localDate`)],
                },
            },
            fullProductTable('cl-table'),
            summaryPanel('cl-summary'),
        ],
    }),
    doc({
        id: 'tpl-org-brand',
        name: 'Organization Brand Invoice',
        description: 'Corporate identity focused — legal name, CIN, website in header.',
        category: 'tax-invoice',
        accent: 'slate',
        watermark: true,
        watermarkOpacity: 14,
        blocks: [
            {
                id: 'o-header',
                type: 'headerBand',
                showBorder: true,
                left: {
                    ...createHeaderSlot('text'),
                    tokens: [
                        token(`${P.org}.legalName`),
                        literal('\nCIN: '),
                        token(`${P.org}.cin`),
                        literal('\n'),
                        token(`${P.org}.website`),
                        literal('\n'),
                        token(`${P.org}.email`),
                    ],
                },
                center: { ...createHeaderSlot('image'), path: `${P.org}.orgLogo`, width: '100px', maxHeight: '72px' },
                right: {
                    ...createHeaderSlot('text'),
                    align: 'right',
                    tokens: [literal('TAX INVOICE\n'), token(`${P.invoice}.invoiceNo`)],
                },
            },
            fullProductTable('o-table'),
            summaryPanel('o-summary'),
            taxTable('o-tax'),
        ],
    }),
    doc({
        id: 'tpl-terms-only',
        name: 'Invoice + Terms Footer',
        description: 'Standard invoice blocks with extended terms section.',
        category: 'tax-invoice',
        accent: 'zinc',
        blocks: [
            companyHeader('t-header', 'Tax Invoice'),
            simpleProductTable('t-table'),
            summaryPanel('t-summary'),
            standardTerms('t-terms'),
        ],
    }),
    doc({
        id: 'tpl-kv-starter',
        name: 'Key-Value Starter',
        description: 'Heading plus label/value rows — ideal for custom fields.',
        category: 'blank',
        accent: 'neutral',
        blocks: [
            { id: 'kv-head', type: 'heading', align: 'center', size: 'lg', bold: true, uppercase: false, tokens: [literal('Document Title')] },
            { id: 'kv1', type: 'keyValue', label: 'Store', path: `${P.store}.storeName`, boldLabel: true, boldValue: false },
            { id: 'kv2', type: 'keyValue', label: 'Customer', path: `${P.customer}.customerName`, boldLabel: true, boldValue: false },
            { id: 'kv3', type: 'keyValue', label: 'Invoice No', path: `${P.invoice}.invoiceNo`, boldLabel: true, boldValue: false },
            { id: 'kv4', type: 'keyValue', label: 'Date', path: `${P.invoice}.localDate`, boldLabel: true, boldValue: false },
            { id: 'kv-div', type: 'divider', weight: 'thin' },
            simpleProductTable('kv-table'),
        ],
    }),
    doc({
        id: 'tpl-blank-pro',
        name: 'Blank Professional Canvas',
        description: 'Empty header band, table shell, and summary — configure from scratch.',
        category: 'blank',
        accent: 'stone',
        blocks: [
            { id: 'blank-header', type: 'headerBand', showBorder: true, left: createHeaderSlot('text'), center: { ...createHeaderSlot('image'), align: 'center' }, right: { ...createHeaderSlot('text'), align: 'right' } },
            { id: 'blank-table', type: 'table', arrayPath: '', variant: 'invoice', compact: true, showHeader: true, columns: [createA4Column('index'), createA4Column('variable'), createA4Column('variable')] },
            { id: 'blank-summary', type: 'summaryPanel', leftLabel: 'In words:', leftTokens: [], rightLines: [createSummaryLine('Total', true)] },
        ],
    }),
    doc({
        id: 'tpl-blank-scratch',
        name: 'Start from Scratch',
        description: 'Completely empty — add only the blocks you need.',
        category: 'blank',
        accent: 'gray',
        blocks: [createA4Block('heading')],
    }),
];

/** @deprecated Use BUILTIN_A4_TEMPLATES */
export const A4_PRESETS = BUILTIN_A4_TEMPLATES;

export function findBuiltinA4Template(id: string): A4TemplateDocument | undefined {
    return BUILTIN_A4_TEMPLATES.find((template) => template.id === id);
}
