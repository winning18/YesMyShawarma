{{--
    Shared CSS for every report PDF (sales statement, expenses) so they
    share one design: Figtree from local TTF files (needs $fontDir), black
    and grey with the brand maroon #9a0200 for rules and the key total.
    DomPDF: tables and floats only, no flexbox/grid.
--}}
<style>
        @font-face { font-family: 'Figtree'; font-weight: 400; src: url('{{ $fontDir }}/figtree-400.ttf') format('truetype'); }
        @font-face { font-family: 'Figtree'; font-weight: 600; src: url('{{ $fontDir }}/figtree-600.ttf') format('truetype'); }
        @font-face { font-family: 'Figtree'; font-weight: 700; src: url('{{ $fontDir }}/figtree-700.ttf') format('truetype'); }

        @page { margin: 28px 32px 36px; }
        body { font-family: 'Figtree', sans-serif; color: #111; font-size: 9px; }
        .rule { border-top: 2px solid #9a0200; margin: 10px 0 14px; }
        .header td { vertical-align: top; }
        .logo { height: 46px; }
        h1 { font-size: 20px; font-weight: 400; margin: 14px 0 0; }
        .muted { color: #555; }
        .strong { font-weight: 700; }
        .period { font-weight: 700; font-size: 10px; margin: 0 0 8px; }
        h2 { font-size: 10px; font-weight: 700; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.04em; }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { font-size: 8px; font-weight: 700; text-align: left; padding: 4px 5px; border-bottom: 1.5px solid #111; vertical-align: bottom; }
        table.grid td { padding: 4px 5px; }
        table.grid .num { text-align: right; white-space: nowrap; }
        table.grid th.caption { font-size: 10px; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: none; padding: 0 0 6px; }
        table.grid tr.total td { background: #eeeeee; font-weight: 700; }
        table.grid tr.gap td { padding: 5px; }

        table.summary { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.summary th { background: #eeeeee; text-align: right; padding: 5px 6px; font-weight: 700; }
        table.summary td { padding: 5px 6px; }
        table.summary td.num { text-align: right; width: 120px; }
        table.summary tr.net td { background: #eeeeee; font-weight: 700; border-top: 2px solid #9a0200; color: #9a0200; font-size: 11px; }
        table.summary tr.memo td { color: #555; padding-top: 3px; padding-bottom: 3px; }

        .two-col { width: 100%; }
        .two-col td { vertical-align: top; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #888; }
</style>
