  .slip {
    width: 72mm;
    box-sizing: border-box;
    padding: 3mm 2mm 4mm;
    background: #fff;
    color: #000;
    font-family: 'Courier New', ui-monospace, 'Cascadia Mono', monospace;
    font-size: 12px;
    line-height: 1.45;
    -webkit-font-smoothing: none;
  }

  /* Rules ------------------------------------------------------------------ */
  .slip-rule    { border-top: 1px dashed #000; margin: 2mm 0; }
  .slip-rule-s  { border-top: 1px solid  #000; margin: 2mm 0; }
  .slip-rule-d  { border-top: 3px double #000; margin: 2mm 0; }

  /* Header ----------------------------------------------------------------- */
  .slip-hd     { text-align: center; }
  .slip-logo   { max-width: 34mm; max-height: 16mm; margin: 0 auto 1.5mm; display: block; }
  .slip-shop   { font-size: 17px; font-weight: 700; letter-spacing: .06em; line-height: 1.2; }
  .slip-tag    { font-size: 10px; font-style: italic; margin-top: .5mm; }
  .slip-meta   { font-size: 10px; line-height: 1.35; margin-top: 1mm; }

  /* The copy marker. Inverted so the tailor can tell the two slips apart at a
     glance from across the workshop, without reading anything. */
  .slip-kind {
    background: #000; color: #fff;
    text-align: center; font-weight: 700;
    font-size: 11px; letter-spacing: .22em;
    padding: 1mm 0; margin: 2mm 0;
  }
  .slip-kind.ghost {
    background: #fff; color: #000;
    border: 1px solid #000; letter-spacing: .18em;
  }

  /* Rows ------------------------------------------------------------------- */
  .slip-row {
    display: flex; justify-content: space-between;
    align-items: baseline; gap: 3mm;
  }
  .slip-row .k { flex: 1 1 auto; min-width: 0; word-break: break-word; }
  .slip-row .v {
    flex: 0 0 auto; text-align: right; white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }
  .slip-sec {
    font-size: 10px; font-weight: 700; letter-spacing: .14em;
    margin: 2mm 0 1mm;
  }
  .slip-sub  { font-size: 10px; padding-left: 3mm; }
  .slip-bold { font-weight: 700; }

  /* The one number the customer looks for. */
  .slip-total {
    display: flex; justify-content: space-between; align-items: baseline;
    font-size: 15px; font-weight: 700; padding: 1.5mm 0;
  }

  /* Deadline block on the job card — deliberately the loudest thing on it. */
  .slip-due {
    text-align: center; border: 2px solid #000;
    padding: 1.5mm 1mm; margin: 2mm 0;
  }
  .slip-due .lbl { font-size: 9px; letter-spacing: .2em; }
  .slip-due .val { font-size: 14px; font-weight: 700; line-height: 1.25; }

  /* Measurements ----------------------------------------------------------- */
  .slip-mgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 3mm; }
  .slip-mcell {
    display: flex; justify-content: space-between; gap: 1mm;
    border-bottom: 1px dotted #666;
    padding: .6mm 0; font-size: 11px;
  }
  .slip-mcell .l {
    flex: 1 1 auto; min-width: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .slip-mcell .n {
    flex: 0 0 auto; padding-left: 2mm;
    font-weight: 700; font-variant-numeric: tabular-nums;
  }

  /* Hanging indent, so a wrapped instruction lines up under its own text
     rather than under the bullet. */
  .slip-note { font-size: 11px; padding-left: 3mm; text-indent: -3mm; }

  /* Sign-off boxes the workshop ticks as the garment moves along. */
  .slip-sign { display: flex; gap: 2mm; margin-top: 3mm; text-align: center; font-size: 9px; }
  .slip-sign > div { flex: 1; }
  .slip-sign .line { border-bottom: 1px solid #000; height: 7mm; }

  /* pre-line so a footer the shop typed across several lines actually prints
     across several lines, instead of collapsing into one run-on paragraph. */
  .slip-foot {
    text-align: center; font-size: 10px; line-height: 1.4;
    white-space: pre-line;
  }

  /* Software credit. Deliberately the smallest thing on the slip: it belongs
     to us, not to the customer's transaction. Four fixed lines — the developer
     name appears here and nowhere else on the slip. */
  .slip-credit {
    text-align: center; font-size: 9px; line-height: 1.45;
    margin-top: 2.5mm; color: #444;
  }
  /* The two facts a customer might actually need again — who built it and the
     number to call — carry the weight. The connective wording around them
     stays light so the block reads in one glance. */
  .slip-credit .name  { font-weight: 700; color: #000; }
  .slip-credit .sys   { font-weight: 600; font-size: 8.5px; letter-spacing: .03em; color: #222; }
  .slip-credit .tel   { font-weight: 700; color: #000; letter-spacing: .04em; }
  .slip-credit .ty    { font-size: 9.5px; color: #444; margin-top: 1mm; }
  .slip-code {
    text-align: center; font-size: 13px; font-weight: 700;
    letter-spacing: .18em; margin-top: 2mm;
  }

  /* On-screen preview only — never printed. */
  .slip-preview {
    box-shadow: 0 1px 3px rgba(15, 23, 42, .12), 0 8px 24px rgba(15, 23, 42, .08);
    border-radius: 2px;
  }
  .slip-label {
    font-family: Inter, system-ui, sans-serif;
    font-size: 11px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: #64748b;
    text-align: center; margin-bottom: 8px;
  }

