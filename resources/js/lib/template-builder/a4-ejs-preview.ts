/** Sample print payload for previewing production EJS templates (e.g. HOAD credit note). */
function createSamplePrintData(): Record<string, unknown> {
    return {
        header: {
            customerDetails: {
                customerName: 'John Doe',
                contactNumber: '+91 9876543210',
                address1: '123 Main Street',
                city: 'Mumbai',
                pincode: '400001',
                location: 'Maharashtra',
                shippingAddress: null,
            },
            invoice: {
                invoiceHeader: {
                    localDate: '2026-07-07',
                    localTime: '11:39:29 AM',
                    invoiceNo: 'RA03592600034',
                    invoiceSequence: 92600034,
                    transactionType: 'return',
                    referenceInvoiceNumber: 'SIA035926270855',
                    referenceInvoiceDate: '2026-07-07',
                    customerGstin: '',
                },
                invoiceDetails: {
                    productList: [
                        {
                            category3: 'SAMPLE 3',
                            barcode: '8901234567890',
                            hsnCode: '6204',
                            taxper: 18,
                            attribute3: 'STY001',
                            qty: -1,
                            effectivePrice: 39996,
                            discount: 0,
                            total: -39996,
                        },
                    ],
                    taxSummary: [
                        {
                            name: 'GST 18%',
                            taxable: 33895.76,
                            CGST_amt: 3050.12,
                            SGST_amt: 3050.12,
                            IGST_amt: 0,
                            value: { CGST_amt: 9, SGST_amt: 9, IGST_amt: 0 },
                        },
                    ],
                    invoiceSummary: {
                        totalQty: -1,
                        discount: 0,
                        total: -39996,
                        totalInWords: 'Thirty Nine Thousand Nine Hundred Ninety Six Only',
                        taxableAmount: 33895.76,
                    },
                },
            },
            payments: [
                {
                    mop_name: 'credit_note_issued',
                    amount: -39996,
                    voucher_no: 'CN92600034',
                    available_value: 0,
                },
            ],
            organizationDetails: {
                brandName: 'House of Anita Dongre',
                registeredAddress: 'Regd. Office, Mumbai',
                state: 'Maharashtra',
                pincode: '400001',
                cin: 'U12345MH2010PTC000000',
            },
            storeDetails: {
                storeName: 'HOAD Store Mumbai',
                addressLine: 'Phoenix Mall, Lower Parel',
                city: 'Mumbai',
                state: 'Maharashtra',
                pincode: '400013',
                contactNumber: '022-12345678',
                taxRegistrationCode: '27AAAAA0000A1Z5',
            },
        },
    };
}

type EvalContext = Record<string, unknown>;

function createEvalContext(): EvalContext {
    return {
        printData: createSamplePrintData(),
        Math,
        Date,
        parseInt,
        parseFloat,
        JSON,
        Array,
        Object,
        String,
        Number,
        Boolean,
        isNaN,
        invoiceTitle: 'Credit Note',
        prefix: 'SR',
        abs: '-',
        custName: 'John Doe',
        custLocation: 'Maharashtra',
        custAddress: '123 Main Street Mumbai-400001',
        totalcgst: 3050.12,
        totalsgst: 3050.12,
        totaligst: 0,
    };
}

function escapeHtml(value: string): string {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function evaluateExpression(code: string, context: EvalContext): unknown {
    const trimmed = code.trim();

    if (!trimmed) {
        return '';
    }

    try {
        const fn = new Function(
            'ctx',
            `
            var printData = ctx.printData;
            with (ctx) {
                return (${trimmed});
            }
            `,
        );

        return fn(context);
    } catch {
        return '';
    }
}

function executeScript(code: string, context: EvalContext): void {
    const trimmed = code.trim();

    if (!trimmed) {
        return;
    }

    try {
        const fn = new Function(
            'ctx',
            `
            var printData = ctx.printData;
            with (ctx) {
                ${trimmed}
            }
            `,
        );

        fn(context);
    } catch {
        // Ignore script errors in preview — partial render is still useful.
    }
}

function formatOutput(value: unknown): string {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value);
}

function expandForEachLoops(source: string, context: EvalContext): string {
    let result = source;
    let changed = true;

    while (changed) {
        changed = false;

        result = result.replace(
            /<%\s*\(([\s\S]+?)\)\.forEach\s*\(\s*(\w+)\s*=>\s*\{\s*%>([\s\S]*?)<%\s*\}\)\s*;?\s*%>/g,
            (match, arrayExpr: string, itemVar: string, template: string) => {
                changed = true;
                const array = evaluateExpression(arrayExpr, context);

                if (!Array.isArray(array)) {
                    return '';
                }

                return array
                    .map((item, index) => {
                        const loopContext: EvalContext = {
                            ...context,
                            [itemVar]: item,
                            index,
                        };

                        return renderEjsTemplate(String(template), loopContext, {
                            expandLoops: false,
                            expandConditionals: true,
                        });
                    })
                    .join('');
            },
        );

        result = result.replace(
            /<%\s*\(([\s\S]+?)\)\.forEach\s*\(\s*function\s*\(\s*(\w+)\s*,\s*(\w+)\s*\)\s*\{\s*%>([\s\S]*?)<%\s*\}\)\s*;?\s*%>/g,
            (match, arrayExpr: string, itemVar: string, indexVar: string, template: string) => {
                changed = true;
                const array = evaluateExpression(arrayExpr, context);

                if (!Array.isArray(array)) {
                    return '';
                }

                return array
                    .map((item, index) => {
                        const loopContext: EvalContext = {
                            ...context,
                            [itemVar]: item,
                            [indexVar]: index,
                        };

                        return renderEjsTemplate(String(template), loopContext, {
                            expandLoops: false,
                            expandConditionals: true,
                        });
                    })
                    .join('');
            },
        );
    }

    return result;
}

function expandConditionals(source: string, context: EvalContext): string {
    const ifPattern =
        /<%\s*if\s*\(([\s\S]+?)\)\s*\{\s*%>([\s\S]*?)(?:<%\s*\}\s*else\s*\{\s*%>([\s\S]*?))?<%\s*\}\s*(?:else\s*\{\s*[\s\S]*?\})?\s*%>/g;

    let result = source;
    let changed = true;

    while (changed) {
        changed = false;

        result = result.replace(ifPattern, (_, condition: string, ifBody: string, elseBody?: string) => {
            changed = true;
            const value = evaluateExpression(condition, context);

            if (value) {
                return renderEjsTemplate(ifBody ?? '', context, { expandLoops: false, expandConditionals: true });
            }

            return elseBody
                ? renderEjsTemplate(elseBody, context, { expandLoops: false, expandConditionals: true })
                : '';
        });
    }

    return result;
}

function renderEjsTemplate(
    source: string,
    context: EvalContext,
    options: { expandLoops?: boolean; expandConditionals?: boolean } = {},
): string {
    const { expandLoops = true, expandConditionals: shouldExpandConditionals = true } = options;
    let working = source;

    if (expandLoops) {
        executeStandaloneScripts(working, context);
        working = expandForEachLoops(working, context);
    }

    if (shouldExpandConditionals) {
        working = expandConditionals(working, context);
    }

    const parts = working.split(/(<%[\s\S]*?%>)/g);
    let output = '';

    for (const part of parts) {
        if (!part.startsWith('<%')) {
            output += part;

            continue;
        }

        const match = part.match(/^<%([=#-]?)([\s\S]*?)%>$/);

        if (!match) {
            continue;
        }

        const [, modifier, code] = match;

        if (modifier === '=' || modifier === '-') {
            const value = evaluateExpression(code, context);

            output += modifier === '-' ? formatOutput(value) : escapeHtml(formatOutput(value));
        } else if (!code.includes('.forEach') && !code.trim().startsWith('if ') && !code.trim().startsWith('}')) {
            executeScript(code, context);
        }
    }

    return output;
}

/** Runs top-level let/const and calculations before loops expand. */
function executeStandaloneScripts(source: string, context: EvalContext): void {
    const parts = source.split(/(<%[\s\S]*?%>)/g);

    for (const part of parts) {
        if (!part.startsWith('<%')) {
            continue;
        }

        const match = part.match(/^<%([=#-]?)([\s\S]*?)%>$/);

        if (!match) {
            continue;
        }

        const [, modifier, code] = match;
        const trimmed = code.trim();

        if (modifier !== '') {
            continue;
        }

        if (trimmed.includes('.forEach') || trimmed.startsWith('if ') || trimmed.startsWith('}')) {
            continue;
        }

        executeScript(code, context);
    }
}

function extractFullPreviewDocument(html: string): string {
    const trimmed = html.trim();

    if (!trimmed.includes('<html')) {
        return `<!DOCTYPE html><html><head><meta charset="UTF-8" /></head><body>${trimmed}</body></html>`;
    }

    return trimmed.startsWith('<!DOCTYPE') ? trimmed : `<!DOCTYPE html>\n${trimmed}`;
}

/** Renders imported EJS/HTML with sample data — preserves CSS and layout. */
export function renderImportedEjsPreviewHtml(source: string): string {
    if (!source.trim()) {
        return '';
    }

    const withoutMeta = source.replace(/<!--\s*TB_TEMPLATE:[\s\S]*?-->\s*/g, '');
    const context = createEvalContext();
    const body = renderEjsTemplate(withoutMeta, context);
    const doc = extractFullPreviewDocument(body);

    if (typeof DOMParser !== 'undefined') {
        const parsed = new DOMParser().parseFromString(doc, 'text/html');
        const styles = parsed.querySelector('style')?.outerHTML ?? '';
        const bodyHtml = parsed.body.innerHTML;

        return `${styles}<div class="tb-ejs-preview-root">${bodyHtml}</div>`;
    }

    return doc;
}

/** Builds a printable HTML document from raw EJS source. */
export function renderImportedEjsPrintDocument(source: string): string {
    if (!source.trim()) {
        return '';
    }

    const withoutMeta = source.replace(/<!--\s*TB_TEMPLATE:[\s\S]*?-->\s*/g, '');
    const context = createEvalContext();
    const rendered = renderEjsTemplate(withoutMeta, context);

    return extractFullPreviewDocument(rendered);
}

/** Detect production EJS templates that should stay in source editor (not block parser). */
export function shouldUseEjsSourceMode(source: string): boolean {
    if (source.includes('TB_TEMPLATE:') || source.includes('tb-page-content')) {
        return false;
    }

    const hasTbBlocks = source.includes('tb-header-band') || source.includes('tb-table');
    const hasComplexScripts = /<%\s*(let|const|var|if)\s/.test(source);
    const hasArrowLoops = /\.forEach\s*\(\s*\w+\s*=>/.test(source);
    const hasOptionalChaining = /\?\./.test(source);
    const hasSubstantialCss = source.includes('<style') && source.length > 2000;

    if (hasTbBlocks) {
        return false;
    }

    return hasComplexScripts || hasArrowLoops || hasOptionalChaining || hasSubstantialCss;
}
