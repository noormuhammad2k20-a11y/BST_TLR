/* =========================================================
   CLOTH STORE — SIMPLE 80MM RECEIPT
   Scoped so Tailor/Workshop receipts are untouched.
========================================================= */

.cloth-receipt {
    width: 72mm;
    max-width: 72mm;
    box-sizing: border-box;
    margin: 0 auto;
    padding: 2.5mm 2mm 5mm;
    background: #fff;
    color: #111;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 11px;
    line-height: 1.4;
    -webkit-font-smoothing: antialiased;
}

.cloth-receipt .cr-header {
    text-align: center;
}

.cloth-receipt .cr-brand {
    margin: 0;
    font-size: 19px;
    line-height: 1.15;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-tagline {
    margin-top: 3px;
    font-size: 9px;
    color: #555;
}

.cloth-receipt .cr-contact {
    margin-top: 3px;
    font-size: 9px;
    line-height: 1.4;
    color: #333;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-separator {
    border-top: 1px dashed #777;
    margin: 9px 0;
}

.cloth-receipt .cr-info {
    display: grid;
    gap: 4px;
}

.cloth-receipt .cr-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.cloth-receipt .cr-info-label {
    flex: 0 0 auto;
    color: #555;
    white-space: nowrap;
}

.cloth-receipt .cr-info-value {
    min-width: 0;
    font-weight: 600;
    text-align: right;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-section-title {
    margin-bottom: 6px;
    font-size: 10px;
    font-weight: 700;
}

.cloth-receipt .cr-item-head,
.cloth-receipt .cr-item-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 32px 58px;
    gap: 5px;
}

.cloth-receipt .cr-item-head {
    padding-bottom: 4px;
    border-bottom: 1px solid #222;
    font-size: 9px;
    font-weight: 700;
}

.cloth-receipt .cr-item-head > :nth-child(2),
.cloth-receipt .cr-item-qty {
    text-align: center;
}

.cloth-receipt .cr-item-head > :nth-child(3),
.cloth-receipt .cr-item-total {
    text-align: right;
}

.cloth-receipt .cr-item {
    padding: 6px 0;
    border-bottom: 1px dotted #aaa;
    break-inside: avoid;
    page-break-inside: avoid;
}

.cloth-receipt .cr-item:last-child {
    border-bottom: 0;
}

.cloth-receipt .cr-item-main {
    align-items: start;
}

.cloth-receipt .cr-item-name {
    min-width: 0;
    font-size: 10px;
    line-height: 1.3;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-item-meta {
    margin-top: 2px;
    font-size: 8.5px;
    color: #555;
}

.cloth-receipt .cr-item-qty {
    font-size: 9.5px;
}

.cloth-receipt .cr-item-total {
    font-size: 10px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.cloth-receipt .cr-totals {
    margin-top: 3px;
}

.cloth-receipt .cr-total-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 2px 0;
    font-size: 10px;
}

.cloth-receipt .cr-total-row > :last-child {
    text-align: right;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.cloth-receipt .cr-grand-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 15px;
    margin-top: 6px;
    padding-top: 7px;
    border-top: 1.5px solid #111;
    font-size: 14px;
    font-weight: 700;
}

.cloth-receipt .cr-grand-total > :last-child {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.cloth-receipt .cr-footer {
    margin-top: 15px;
    text-align: center;
}

.cloth-receipt .cr-thanks {
    font-size: 10.5px;
    font-weight: 700;
}

.cloth-receipt .cr-footer-note {
    margin-top: 4px;
    font-size: 8.5px;
    line-height: 1.4;
    color: #555;
}

.cloth-receipt .cr-developer {
    margin-top: 11px;
    padding-top: 8px;
    border-top: 1px dashed #999;
    text-align: center;
    font-size: 7.8px;
    line-height: 1.45;
    color: #555;
}

.cloth-receipt .cr-dev-title {
    font-size: 7.5px;
}

.cloth-receipt .cr-dev-name {
    margin-top: 1px;
    font-size: 9px;
    font-weight: 700;
    color: #111;
}

.cloth-receipt .cr-dev-phone {
    margin-top: 1px;
    font-size: 8px;
    color: #333;
}

.cloth-receipt .cr-dev-system {
    margin-top: 2px;
    font-size: 7px;
    letter-spacing: .05em;
}

@media print {
    .cloth-receipt {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        color: #000 !important;
        font-weight: 600;
        -webkit-font-smoothing: none;
    }

    .cloth-receipt,
    .cloth-receipt * {
        color: #000 !important;
        opacity: 1 !important;
        filter: none !important;
        text-shadow: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cloth-receipt .cr-item {
        border-bottom-color: #000 !important;
    }

    .cloth-receipt .cr-separator,
    .cloth-receipt .cr-developer {
        border-color: #000 !important;
    }
}

/* Scope the shared slip typography to Cloth Store receipts only. */
.cloth-receipt .slip-hd { padding: 1mm 0 2mm; }
.cloth-receipt .slip-shop { font-family: Arial, sans-serif; font-size: 21px; letter-spacing: -.02em; line-height: 1.2; overflow-wrap: anywhere; }
.cloth-receipt .slip-tag { font-size: 10px; margin-top: 1mm; }
.cloth-receipt .slip-meta { font-size: 10px; margin-top: 1.5mm; line-height: 1.5; }
.cloth-receipt .cr-receipt-title { margin: 2mm 0; padding: 1.5mm 0; border-top: 1px solid #111; border-bottom: 1px solid #111; text-align: center; font-size: 10px; font-weight: 700; letter-spacing: .18em; }
.cloth-receipt .slip-row { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 3mm; padding: .8mm 0; font-size: 11px; line-height: 1.4; }
.cloth-receipt .slip-row .k { color: #333; word-break: normal; overflow-wrap: anywhere; }
.cloth-receipt .slip-row .v { white-space: normal; overflow-wrap: anywhere; font-weight: 600; }
.cloth-receipt .cr-item-head { grid-template-columns: minmax(0,1fr) 90px; margin-bottom: 1mm; padding: 1.5mm 0; }
.cloth-receipt .cr-item-head > :last-child { text-align: right; }
.cloth-receipt .slip-sub { font-size: 9px; color: #555; padding: 0 0 2mm; margin-bottom: 1mm; border-bottom: 1px dotted #bbb; }
.cloth-receipt .slip-total { font-size: 17px; gap: 3mm; padding: 2mm 0; }
.cloth-receipt .slip-total > :last-child { text-align: right; }
.cloth-receipt .slip-rule { border-color: #888; margin: 2.5mm 0; }
.cloth-receipt .slip-foot { font-size: 10px; line-height: 1.5; white-space: normal; padding-top: 2mm; }
.cloth-receipt .slip-credit { margin-top: 2mm; font-size: 9px; line-height: 1.5; }
.cloth-receipt .slip-credit .name { display: block; font-size: 11px; }
.cloth-receipt .slip-credit .tel { font-size: 11px; }
.cloth-receipt .slip-credit .sys { font-size: 8px; margin: 1mm 0; }
#receipt-content { max-height: 70vh; overflow-y: auto; }
