export type SchemaPlaceholder = string | number | boolean;

export type SchemaEntry = {
    path: string;
    type?: string;
    displayName?: string;
    placeholder?: SchemaPlaceholder;
    hasObject?: boolean;
    variables?: Record<string, SchemaEntry>;
};

export type SchemaMap = Record<string, SchemaEntry>;

/**
 * Data mapping schema supplied by the print service. Each top-level entry has an
 * absolute `path` (prefixed with `printData.`) while nested variables use paths
 * relative to their parent. Leaf nodes carry a `placeholder` used for previews.
 */
export const dataSchema: SchemaMap = {
    storeDetails: {
        path: 'printData.header.storeDetails',
        type: 'object',
        displayName: 'Store Info',
        variables: {
            storeName: { path: 'storeName', type: 'string', displayName: 'Store Name', placeholder: 'Green Hills Store' },
            storeId: { path: 'storeId', type: 'number', displayName: 'Store ID', placeholder: 1 },
            storeRandom: { path: 'storeRandom', type: 'string', displayName: 'Store Random', placeholder: '1234567890' },
            storeCode: { path: 'storeCode', type: 'string', displayName: 'Store Code', placeholder: '1234567890' },
            addressLine: { path: 'addressLine', type: 'string', displayName: 'Address Line 1', placeholder: 'Udyog Vihar' },
            addressLine2: { path: 'addressLine2', type: 'string', displayName: 'Address Line 2', placeholder: 'Sector 18' },
            location: { path: 'location', type: 'string', displayName: 'Location', placeholder: 'Delhi' },
            city: { path: 'city', type: 'string', displayName: 'City', placeholder: 'Delhi' },
            state: { path: 'state', type: 'string', displayName: 'State', placeholder: 'Delhi' },
            pincode: { path: 'pincode', type: 'string', displayName: 'Pincode', placeholder: '123456' },
            district: { path: 'district', type: 'string', displayName: 'District', placeholder: 'South Delhi' },
            email: { path: 'email', type: 'string', displayName: 'Email', placeholder: 'info@greenhills.com' },
            taxRegistrationCode: { path: 'taxRegistrationCode', type: 'string', displayName: 'Tax Registration Code', placeholder: '123456' },
            stateId: { path: 'stateId', type: 'number', displayName: 'State ID', placeholder: 1 },
            contactNumber: { path: 'contactNumber', type: 'string', displayName: 'Contact Number', placeholder: '1234567890' },
            storeLogo: { path: 'storeLogo', type: 'string', displayName: 'Store Logo', placeholder: 'https://example.com/logo.png' },
            storeBottomLogo: { path: 'storeBottomLogo', type: 'string', displayName: 'Store Bottom Logo', placeholder: 'https://example.com/logo.png' },
            stateCode: { path: 'stateCode', type: 'string', displayName: 'State Code', placeholder: '123456' },
            country: { path: 'country', type: 'string', displayName: 'Country', placeholder: 'India' },
            storeCategory: { path: 'storeCategory', type: 'string', displayName: 'Store Category', placeholder: 'Retail' },
        },
    },
    clusterDetails: {
        path: 'printData.header.clusterDetails',
        type: 'object',
        displayName: 'Cluster Details',
        variables: {
            clusterName: { path: 'clusterName', type: 'string', displayName: 'Cluster Name', placeholder: 'North Cluster' },
            clusterDescription: { path: 'clusterDescription', type: 'string', displayName: 'Cluster Description', placeholder: 'Primary cluster for north region' },
            clusterRefCode: { path: 'clusterRefCode', type: 'string', displayName: 'Cluster Ref Code', placeholder: 'CL-001' },
            clusterStatus: { path: 'clusterStatus', type: 'string', displayName: 'Cluster Status', placeholder: '1' },
            clusterStoreCount: { path: 'clusterStoreCount', type: 'number', displayName: 'Cluster Store Count', placeholder: 0 },
            clusterAllowCnDnFostore: { path: 'clusterAllowCnDnFostore', type: 'string', displayName: 'Cluster Allow CnDn For Store', placeholder: '0' },
            address: { path: 'address', type: 'string', displayName: 'Address', placeholder: 'Line No1' },
            city: { path: 'city', type: 'string', displayName: 'City', placeholder: 'City Name' },
            state: { path: 'state', type: 'string', displayName: 'State', placeholder: 'State Name' },
            country: { path: 'country', type: 'string', displayName: 'Country', placeholder: 'Country Name' },
            pinCode: { path: 'pinCode', type: 'string', displayName: 'Pin Code', placeholder: '123456' },
            cin: { path: 'cin', type: 'string', displayName: 'CIN', placeholder: 'AA1234567890' },
        },
    },
    customerDetails: {
        path: 'printData.header.customerDetails',
        type: 'object',
        displayName: 'Customer',
        variables: {
            customerName: { path: 'customerName', type: 'string', displayName: 'Customer Name', placeholder: 'John Doe' },
            legalName: { path: 'legalName', type: 'string', displayName: 'Legal Name', placeholder: 'John Doe' },
            addressLine: { path: 'addressLine', type: 'string', displayName: 'Address Line 1', placeholder: 'Udyog Vihar' },
            addressLine2: { path: 'addressLine2', type: 'string', displayName: 'Address Line 2', placeholder: 'Sector 18' },
            location: { path: 'location', type: 'string', displayName: 'Location', placeholder: 'Delhi' },
            city: { path: 'city', type: 'string', displayName: 'City', placeholder: 'Delhi' },
            state: { path: 'state', type: 'string', displayName: 'State', placeholder: 'Delhi' },
            pincode: { path: 'pincode', type: 'string', displayName: 'Pincode', placeholder: '123456' },
            taxRegistrationCode: { path: 'taxRegistrationCode', type: 'string', displayName: 'Tax Registration Code', placeholder: '123456' },
            countryCode: { path: 'countryCode', type: 'string', displayName: 'Country Code', placeholder: '+91' },
            contactNumber: { path: 'contactNumber', type: 'string', displayName: 'Contact Number', placeholder: '1234567890' },
            email: { path: 'email', type: 'string', displayName: 'Email', placeholder: 'info@greenhills.com' },
        },
    },
    invoiceHeader: {
        path: 'printData.header.invoice.invoiceHeader',
        type: 'object',
        displayName: 'Invoice Header',
        variables: {
            invoiceNo: { path: 'invoiceNo', type: 'string', displayName: 'Invoice No', placeholder: '1234567890' },
            invoiceSequence: { path: 'invoiceSequence', type: 'string', displayName: 'Invoice Sequence', placeholder: 'INV-0001' },
            orderId: { path: 'orderId', type: 'string', displayName: 'Order ID', placeholder: '1234567890' },
            transactionType: { path: 'transactionType', type: 'string', displayName: 'Transaction Type', placeholder: '1234567890' },
            localDate: { path: 'localDate', type: 'string', displayName: 'Local Date', placeholder: '2025-01-01' },
            localTime: { path: 'localTime', type: 'string', displayName: 'Local Time', placeholder: '12:00:00' },
            tillNumber: { path: 'tillNumber', type: 'string', displayName: 'Till Number', placeholder: '1234567890' },
            cashierId: { path: 'cashierId', type: 'string', displayName: 'Cashier ID', placeholder: '1234567890' },
            cashierName: { path: 'cashierName', type: 'string', displayName: 'Cashier Name', placeholder: 'John Doe' },
            remark: { path: 'remark', type: 'string', displayName: 'Remark', placeholder: '1234567890' },
            invoiceType: { path: 'invoiceType', type: 'string', displayName: 'Invoice Type', placeholder: '1234567890' },
            customerGstin: { path: 'customerGstin', type: 'string', displayName: 'Customer GSTIN', placeholder: '1234567890' },
            irnNumber: { path: 'irnNumber', type: 'string', displayName: 'IRN Number', placeholder: '1234567890' },
            ackNumber: { path: 'ackNumber', type: 'string', displayName: 'Ack Number', placeholder: '1234567890' },
            ackDate: { path: 'ackDate', type: 'string', displayName: 'Ack Date', placeholder: '2025-01-01' },
            orderDueDate: { path: 'orderDueDate', type: 'string', displayName: 'Order Due Date', placeholder: '17 Apr 2026' },
        },
    },
    productList: {
        path: 'printData.header.invoice.invoiceDetails.productList',
        type: 'array',
        displayName: 'Product List',
        hasObject: true,
        variables: {
            name: { path: 'name', type: 'string', displayName: 'Name', placeholder: 'Product Name' },
            barcode: { path: 'barcode', type: 'string', displayName: 'Barcode', placeholder: '1234567890' },
            skuCode: { path: 'skuCode', type: 'string', displayName: 'SKU Code', placeholder: '1234567890' },
            sku: { path: 'sku', type: 'string', displayName: 'SKU', placeholder: '1234567890' },
            qty: { path: 'qty', type: 'string', displayName: 'Quantity', placeholder: '1' },
            uom: { path: 'uom', type: 'string', displayName: 'UOM', placeholder: '1234567890' },
            category: { path: 'category', type: 'string', displayName: 'Category', placeholder: '1234567890' },
            brand: { path: 'brand', type: 'string', displayName: 'Brand', placeholder: '1234567890' },
            unitMrp: { path: 'unitMrp', type: 'number', displayName: 'Unit MRP', placeholder: 100 },
            unitRsp: { path: 'unitRsp', type: 'number', displayName: 'Unit RSP', placeholder: 100 },
            effectivePrice: { path: 'effectivePrice', type: 'number', displayName: 'Effective Price', placeholder: 100 },
            subtotal: { path: 'subtotal', type: 'string', displayName: 'Subtotal', placeholder: '3990.00' },
            discount: { path: 'discount', type: 'number', displayName: 'Discount', placeholder: 100 },
            discountperc: { path: 'discountperc', type: 'number', displayName: 'Discount Percentage', placeholder: 10 },
            tax: { path: 'tax', type: 'number', displayName: 'Tax', placeholder: 0 },
            taxper: { path: 'taxper', type: 'number', displayName: 'Tax Percentage', placeholder: 0 },
            total: { path: 'total', type: 'number', displayName: 'Total', placeholder: 3790.5 },
            hsnCode: { path: 'hsnCode', type: 'string', displayName: 'HSN Code', placeholder: '' },
            batchNo: { path: 'batchNo', type: 'string', displayName: 'Batch No', placeholder: '1234567890' },
            mfgDate: { path: 'mfgDate', type: 'string', displayName: 'MFG Date', placeholder: '2025-01-01' },
            expDate: { path: 'expDate', type: 'string', displayName: 'Exp Date', placeholder: '2025-01-01' },
            remark: { path: 'remark', type: 'string', displayName: 'Remark', placeholder: 'Product Remark' },
            salesman: { path: 'salesman', type: 'string', displayName: 'Salesman', placeholder: '1234567890' },
            salesmanCode: { path: 'salesmanCode', type: 'string', displayName: 'Salesman Code', placeholder: '1234567890' },
            freeQty: { path: 'freeQty', type: 'number', displayName: 'Free Quantity', placeholder: 0 },
            departmentName: { path: 'departmentName', type: 'string', displayName: 'Department Name', placeholder: '1234567890' },
        },
    },
    taxSummary: {
        path: 'printData.header.invoice.invoiceDetails.taxSummary',
        type: 'array',
        displayName: 'Tax Summary',
        hasObject: true,
        variables: {
            name: { path: 'name', type: 'string', displayName: 'Name', placeholder: 'GST 0%' },
            taxable: { path: 'taxable', type: 'number', displayName: 'Taxable', placeholder: 3790.5 },
            taxAmt: { path: 'tax_amt', type: 'number', displayName: 'Tax Amount', placeholder: 0 },
            hsn: { path: 'hsn', type: 'string', displayName: 'HSN', placeholder: '123456' },
            cgst: { path: 'cgst', type: 'number', displayName: 'CGST', placeholder: 0 },
            sgst: { path: 'sgst', type: 'number', displayName: 'SGST', placeholder: 0 },
            igst: { path: 'igst', type: 'number', displayName: 'IGST', placeholder: 0 },
            cess: { path: 'cess', type: 'number', displayName: 'CESS', placeholder: 0 },
        },
    },
    invoiceSummary: {
        path: 'printData.header.invoice.invoiceDetails.invoiceSummary',
        type: 'object',
        displayName: 'Invoice Summary',
        variables: {
            uniqueItems: { path: 'uniqueItems', type: 'number', displayName: 'Unique Items', placeholder: 1 },
            totalQty: { path: 'totalQty', type: 'string', displayName: 'Total Quantity', placeholder: '10' },
            taxableAmount: { path: 'taxableAmount', type: 'number', displayName: 'Taxable Amount', placeholder: 3790.5 },
            subtotal: { path: 'subtotal', type: 'number', displayName: 'Subtotal', placeholder: 3990 },
            discount: { path: 'discount', type: 'number', displayName: 'Discount', placeholder: 199.5 },
            promotion: { path: 'promotion', type: 'string', displayName: 'Promotion', placeholder: '199.50' },
            total: { path: 'total', type: 'string', displayName: 'Total', placeholder: '3791.00' },
            totalInWords: { path: 'totalInWords', type: 'string', displayName: 'Total In Words', placeholder: 'Three Thousand Seven Hundred Ninety One Only' },
            roundoff: { path: 'roundoff', type: 'string', displayName: 'Round Off', placeholder: '0.50' },
        },
    },
    invoice: {
        path: 'printData.header.invoice',
        type: 'object',
        displayName: 'Invoice',
        variables: {
            customerPaid: { path: 'customerPaid', type: 'number', displayName: 'Customer Paid', placeholder: 0 },
            balanceRefund: { path: 'balanceRefund', type: 'string', displayName: 'Balance Refund', placeholder: '0.00' },
        },
    },
    payments: {
        path: 'printData.header.payments',
        type: 'array',
        displayName: 'Payment Details',
        hasObject: true,
        variables: {
            mopName: { path: 'mop_name', type: 'string', displayName: 'MOP Name', placeholder: 'Cash' },
            amount: { path: 'amount', type: 'number', displayName: 'Amount', placeholder: 0 },
            refNumber: { path: 'ref_number', type: 'string', displayName: 'Ref Number', placeholder: '1234567890' },
            voucherNo: { path: 'voucher_no', type: 'string', displayName: 'Voucher No', placeholder: '1234567890' },
            paymentDate: { path: 'payment_date', type: 'string', displayName: 'Payment Date', placeholder: '17 Apr 2026' },
        },
    },
    organizationDetails: {
        path: 'printData.header.organizationDetails',
        type: 'object',
        displayName: 'Organization',
        variables: {
            brandName: { path: 'brandName', type: 'string', displayName: 'Brand Name', placeholder: 'Green' },
            legalName: { path: 'legalName', type: 'string', displayName: 'Legal Name', placeholder: 'Green Hills' },
            corporateAddress1: { path: 'corporateAddress1', type: 'string', displayName: 'Corporate Address 1', placeholder: 'Mumbai' },
            corporateAddress2: { path: 'corporateAddress2', type: 'string', displayName: 'Corporate Address 2', placeholder: 'Maharashtra' },
            state: { path: 'state', type: 'string', displayName: 'State', placeholder: 'Maharashtra' },
            city: { path: 'city', type: 'string', displayName: 'City', placeholder: 'Mumbai' },
            pincode: { path: 'pincode', type: 'number', displayName: 'Pincode', placeholder: 400001 },
            orgLogo: { path: 'orgLogo', type: 'string', displayName: 'Organization Logo', placeholder: 'https://www.greenhills.com/logo.png' },
            taxRegistrationCode: { path: 'taxRegistrationCode', type: 'string', displayName: 'Tax Registration Code', placeholder: '1234567890' },
            panNo: { path: 'panNo', type: 'string', displayName: 'PAN No', placeholder: '1234567890' },
            cin: { path: 'cin', type: 'string', displayName: 'CIN', placeholder: '1234567890' },
            email: { path: 'email', type: 'string', displayName: 'Email', placeholder: 'admin@greenhills.com' },
            website: { path: 'website', type: 'string', displayName: 'Website', placeholder: 'https://www.greenhills.com' },
            contact_number: { path: 'contact_number', type: 'string', displayName: 'Contact Number', placeholder: '1234567890' },
        },
    },
};

const THERMAL_PREFIX = 'printData.';

export type VariableNode = {
    /** Unique id, equals the absolute path. */
    id: string;
    label: string;
    /** Absolute path used by A4/EJS output, e.g. `printData.header.storeDetails.storeName`. */
    a4Path: string;
    /** Absolute path with the leading `printData.` stripped, used by thermal output. */
    thermalPath: string;
    /** Path relative to the nearest ancestor array item (used for loop columns / EJS `item.*`). */
    relativePath: string;
    type: string;
    placeholder?: SchemaPlaceholder;
    isArray: boolean;
    isLeaf: boolean;
    children: VariableNode[];
};

function buildNode(
    key: string,
    entry: SchemaEntry,
    parentAbsolute: string | null,
    arrayBase: string | null,
): VariableNode {
    const absolute = entry.path.startsWith(THERMAL_PREFIX)
        ? entry.path
        : parentAbsolute
          ? `${parentAbsolute}.${entry.path}`
          : entry.path;

    const isArray = entry.type === 'array';
    const hasChildren = entry.variables && Object.keys(entry.variables).length > 0;
    const isLeaf = !hasChildren;

    // For descendants of an array, the relative path is measured from the array item.
    const relativePath = arrayBase && absolute.startsWith(`${arrayBase}.`)
        ? absolute.slice(arrayBase.length + 1)
        : entry.path;

    // Children of an array node are measured relative to this array.
    const childArrayBase = isArray ? absolute : arrayBase;

    const children: VariableNode[] = hasChildren
        ? Object.entries(entry.variables ?? {}).map(([childKey, childEntry]) =>
              buildNode(childKey, childEntry, absolute, childArrayBase),
          )
        : [];

    return {
        id: absolute,
        label: entry.displayName ?? key,
        a4Path: absolute,
        thermalPath: absolute.startsWith(THERMAL_PREFIX)
            ? absolute.slice(THERMAL_PREFIX.length)
            : absolute,
        relativePath,
        type: entry.type ?? 'string',
        placeholder: entry.placeholder,
        isArray,
        isLeaf,
        children,
    };
}

/** Builds the nested variable tree used by the palette. */
export function flattenSchema(map: SchemaMap = dataSchema): VariableNode[] {
    return Object.entries(map).map(([key, entry]) => buildNode(key, entry, null, null));
}

let placeholderIndex: Map<string, SchemaPlaceholder> | null = null;

function indexPlaceholders(nodes: VariableNode[], target: Map<string, SchemaPlaceholder>): void {
    for (const node of nodes) {
        if (node.placeholder !== undefined) {
            target.set(node.thermalPath, node.placeholder);
            target.set(node.a4Path, node.placeholder);
        }

        if (node.children.length > 0) {
            indexPlaceholders(node.children, target);
        }
    }
}

function normalizeThermalPath(path: string): string {
    if (path.startsWith(THERMAL_PREFIX)) {
        return path.slice(THERMAL_PREFIX.length);
    }

    if (path.startsWith('printData.')) {
        return path.slice('printData.'.length);
    }

    return path;
}

/**
 * Resolves a placeholder value for thermal or absolute paths.
 * Pass `contextPath` (array path) when resolving loop column fields like `name`.
 */
export function resolvePlaceholder(path: string, contextPath?: string): string {
    if (!path) {
        return '';
    }

    if (placeholderIndex === null) {
        placeholderIndex = new Map();
        indexPlaceholders(flattenSchema(), placeholderIndex);
    }

    const candidates: string[] = [];

    if (contextPath && !path.includes('.')) {
        const normalizedContext = normalizeThermalPath(contextPath);
        candidates.push(`${normalizedContext}.${path}`);
        candidates.push(`printData.${normalizedContext}.${path}`);
    }

    const normalized = normalizeThermalPath(path);

    candidates.push(
        path,
        normalized,
        path.startsWith(THERMAL_PREFIX) ? path.slice(THERMAL_PREFIX.length) : `${THERMAL_PREFIX}${path}`,
        `printData.${normalized}`,
        path.split('.').pop() ?? path,
    );

    for (const candidate of candidates) {
        const thermal = normalizeThermalPath(candidate);
        const value = placeholderIndex.get(candidate) ?? placeholderIndex.get(thermal);

        if (value !== undefined) {
            return String(value);
        }
    }

    return `{${path.split('.').pop() ?? path}}`;
}
