@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap');

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

/* =========================================================
   NEO DESIGN: CLOTH SALES RECEIPT 
========================================================= */
.cloth-sales-neo {
    width: 302px;
    background: #fff;
    color: #141414;
    padding: 19px 15px 21px;
    font: 400 10px/1.5 'IBM Plex Mono', monospace;
    border: 1px solid #BFBAB0; /* screen preview only */
    margin: 0 auto;
}
.cloth-sales-neo .rc-head { text-align: center; }
.cloth-sales-neo .rc-name { font: 700 23px/1.1 'Space Grotesk', sans-serif; letter-spacing: .03em; color: #000; overflow-wrap: anywhere;}
.cloth-sales-neo .rc-tag { display: flex; align-items: center; gap: 8px; margin: 9px 0 0; }
.cloth-sales-neo .rc-tag::before, .cloth-sales-neo .rc-tag::after { content: ''; flex: 1; height: 1px; background: #000; }
.cloth-sales-neo .rc-tag span { font: 600 7.5px 'Space Grotesk', sans-serif; letter-spacing: .32em; margin-right: -.32em; text-transform: uppercase; white-space: nowrap; }
.cloth-sales-neo .rc-addr { font: 500 9.5px/1.55 'IBM Plex Mono', monospace; color: #000; margin-top: 8px; overflow-wrap: anywhere;}
.cloth-sales-neo .rc-ph { font: 600 8.5px 'IBM Plex Mono', monospace; margin-top: 2px; }
.cloth-sales-neo .rc-rule { height: 1.5px; background: #000; border: 0; margin: 12px 0 14px; }
.cloth-sales-neo .rc-doc { display: flex; justify-content: center; margin: 3px 0 13px; }
.cloth-sales-neo .rc-doc b { font: 700 8.5px 'Space Grotesk', sans-serif; letter-spacing: .3em; margin-right: -.3em; text-transform: uppercase; color: #000; }
.cloth-sales-neo .rc-meta { display: flex; flex-direction: column; gap: 6px; margin: 0 0 3px; }
.cloth-sales-neo .rc-m { display: flex; align-items: baseline; gap: 6px; }
.cloth-sales-neo .rc-m .k { font: 600 8px 'Space Grotesk', sans-serif; letter-spacing: .12em; color: #5c5c5c; white-space: nowrap; text-transform: uppercase; }
.cloth-sales-neo .rc-m .dots { flex: 1; min-width: 12px; border-bottom: 1px dotted #9a9a9a; transform: translateY(-3px); }
.cloth-sales-neo .rc-m .v { font: 500 10.5px 'IBM Plex Mono', monospace; color: #000; word-break: break-word; overflow-wrap: anywhere;}
.cloth-sales-neo .rc-m .v.b { font-weight: 700; }
.cloth-sales-neo .rc-sec { display: flex; align-items: center; gap: 8px; margin: 16px 0 8px; }
.cloth-sales-neo .rc-sec::before, .cloth-sales-neo .rc-sec::after { content: ''; flex: 1; height: 1px; background: #000; }
.cloth-sales-neo .rc-sec span { font: 700 8px 'Space Grotesk', sans-serif; letter-spacing: .3em; margin-right: -.3em; text-transform: uppercase; }
.cloth-sales-neo .rc-item { padding: 7px 0; }
.cloth-sales-neo .rc-item + .rc-item { border-top: 1px dashed #d5d5d5; }
.cloth-sales-neo .rc-i1 { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.cloth-sales-neo .rc-i1 .nm { font: 600 11px 'Space Grotesk', sans-serif; color: #000; word-break: break-word; overflow-wrap: anywhere;}
.cloth-sales-neo .rc-i1 .qt { font: 500 9px 'IBM Plex Mono', monospace; color: #555; white-space: nowrap; }
.cloth-sales-neo .rc-i2 { display: flex; align-items: baseline; gap: 6px; margin-top: 2px; }
.cloth-sales-neo .rc-i2 .rt { font: 400 8.5px 'IBM Plex Mono', monospace; color: #5a5a5a; white-space: nowrap; }
.cloth-sales-neo .rc-i2 .dots { flex: 1; min-width: 10px; border-bottom: 1px dotted #a5a5a5; transform: translateY(-3px); }
.cloth-sales-neo .rc-i2 .tt { font: 700 11px 'IBM Plex Mono', monospace; white-space: nowrap;}
.cloth-sales-neo .rc-tot { margin-top: 3px; }
.cloth-sales-neo .rc-tr { display: flex; align-items: baseline; gap: 6px; padding: 3.5px 0; }
.cloth-sales-neo .rc-tr .k { font: 600 8px 'Space Grotesk', sans-serif; letter-spacing: .14em; color: #555; white-space: nowrap; text-transform: uppercase; }
.cloth-sales-neo .rc-tr .dots { flex: 1; min-width: 10px; border-bottom: 1px dotted #a5a5a5; transform: translateY(-3px); }
.cloth-sales-neo .rc-tr .v { font: 600 10.5px 'IBM Plex Mono', monospace; white-space: nowrap;}
.cloth-sales-neo .rc-tr.due .k { color: #000; }
.cloth-sales-neo .rc-tr.due .v { font-weight: 700; font-size: 11.5px; }
.cloth-sales-neo .rc-tr.sub .k { color: #666; }
.cloth-sales-neo .rc-tr.sub .v { font-weight: 500; font-size: 9.5px; }
.cloth-sales-neo .rc-tb { border: 1.5px solid #000; border-radius: 3px; margin: 10px 0 8px; padding: 10px 12px; display: flex; justify-content: space-between; align-items: center; }
.cloth-sales-neo .rc-tb span { font: 700 9px 'Space Grotesk', sans-serif; letter-spacing: .24em; text-transform: uppercase; }
.cloth-sales-neo .rc-tb b { font: 700 15px 'IBM Plex Mono', monospace; white-space: nowrap;}
.cloth-sales-neo .rc-stat { margin-top: 13px; text-align: center; font: 700 7.5px 'Space Grotesk', sans-serif; letter-spacing: .18em; border: 1px solid #000; border-radius: 3px; padding: 6px 4px; text-transform: uppercase; }
.cloth-sales-neo .rc-note { text-align: center; font: 400 8.5px/1.65 'IBM Plex Mono', monospace; color: #333; margin-top: 13px; }
.cloth-sales-neo .rc-credit { margin-top: 15px; border: 1px solid #000; border-radius: 3px; padding: 10px 10px 12px; text-align: center; }
.cloth-sales-neo .rc-credit .c1 { font: 600 7px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .28em; margin-right: -.28em; text-transform: uppercase; }
.cloth-sales-neo .rc-credit .c2 { font: 700 7.5px/1.6 'Space Grotesk', sans-serif; letter-spacing: .04em; margin-top: 4px; color: #000; text-transform: uppercase; }
.cloth-sales-neo .rc-credit .cline { display: block; width: 24px; height: 1.5px; background: #000; margin: 7px auto; }
.cloth-sales-neo .rc-credit .c3 { font: 700 8.5px 'Space Grotesk', sans-serif; letter-spacing: .08em; color: #000; text-transform: uppercase; }
.cloth-sales-neo .rc-credit .c3 span { font: 600 7.5px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .08em; margin-right: 6px; }
.cloth-sales-neo .rc-credit .c4 { font: 600 9px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .03em; margin-top: 5px; }
.cloth-sales-neo .rc-credit .c4 span { font: 600 7.5px 'IBM Plex Mono', monospace; color: #000; letter-spacing: .08em; margin-right: 6px; text-transform: uppercase; }
.cloth-sales-neo .rc-thx { display: flex; align-items: center; gap: 8px; margin-top: 13px; }
.cloth-sales-neo .rc-thx::before, .cloth-sales-neo .rc-thx::after { content: ''; flex: 1; height: 1px; background: #000; }
.cloth-sales-neo .rc-thx span { font: 700 8.5px 'Space Grotesk', sans-serif; letter-spacing: .4em; margin-right: -.4em; text-transform: uppercase; color: #000; }

@media print {
    #thermal-print-area .cloth-sales-neo {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
        background: #fff;
        border: none;
    }
}
