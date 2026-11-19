{{--
    Browser printing (Express POS step 8a): loads a receipt or kitchen
    ticket into a hidden frame and prints it, one at a time. On Windows with
    Chrome's kiosk printing on, it prints straight to the default printer
    with no dialog; the print agent (8b) will take over automatic printing.
    $printBranchId: the branch whose printers decide what prints.
--}}
@php
    $printers = app(\App\Services\Printing\PrinterService::class);
    $printBranch = (int) ($printBranchId ?? app(\App\Services\Branches\BranchContext::class)->id());
    $printing = [
        'enabled' => $printBranch && $printers->hasPrinters($printBranch),
        'receipt' => $printBranch && (bool) $printers->receiptPrinterFor($printBranch, app(\App\Services\Shifts\CounterService::class)->currentAt($printBranch)),
        'kitchen' => $printBranch && $printers->kitchenPrinters($printBranch)->isNotEmpty(),
    ];
@endphp
<script>
    window.expressPrinting = @js($printing);
    window.expressPrintUrl = (orderId, kind) => @js(url('/dashboard/orders')) + '/' + orderId + '/print/' + kind;
    window.expressPrint = (() => {
        let queue = Promise.resolve();

        return (url) => {
            queue = queue.then(() => new Promise((resolve) => {
                const frame = document.createElement('iframe');
                frame.setAttribute('aria-hidden', 'true');
                frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
                let finished = false;
                const finish = () => {
                    if (finished) return;
                    finished = true;
                    setTimeout(() => { frame.remove(); resolve(); }, 300);
                };
                frame.onload = () => {
                    try {
                        frame.contentWindow.onafterprint = finish;
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    } catch (e) {
                        finish();
                    }
                    setTimeout(finish, 60000);
                };
                frame.onerror = finish;
                frame.src = url;
                document.body.appendChild(frame);
            }));

            return queue;
        };
    })();
</script>
