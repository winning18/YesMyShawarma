{{--
    Printing from the staff screens. With the branch's Express POS Print
    Agent on (step 8b), the server queues receipts and tickets and the
    agent prints them with no dialog; Print buttons queue a reprint. With
    no agent, the browser prints (step 8a): a hidden frame, one at a time —
    silent on Windows with Chrome's kiosk printing.
    $printBranchId: the branch whose printers decide what prints.
--}}
@php
    $printers = app(\App\Services\Printing\PrinterService::class);
    $printBranch = (int) ($printBranchId ?? app(\App\Services\Branches\BranchContext::class)->id());
    $printing = [
        'enabled' => $printBranch && $printers->hasPrinters($printBranch),
        'receipt' => $printBranch && (bool) $printers->receiptPrinterFor($printBranch, app(\App\Services\Shifts\CounterService::class)->currentAt($printBranch)),
        'kitchen' => $printBranch && $printers->kitchenPrinters($printBranch)->isNotEmpty(),
        'agent' => $printBranch && app(\App\Services\Printing\PrintAgentService::class)->isOnline($printBranch),
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

    // A Print button: the agent if it's on, otherwise the browser.
    window.expressReprint = async (orderId, kind) => {
        if (window.expressPrinting.agent) {
            try {
                const response = await fetch(window.expressPrintUrl(orderId, kind), {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                const payload = await response.json();
                if (response.ok && payload.queued > 0) return;
            } catch (e) {
                // fall through to the browser
            }
        }

        window.expressPrint(window.expressPrintUrl(orderId, kind));
    };
</script>
