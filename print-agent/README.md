# Express POS Print Agent

A small Windows program (Express POS step 8b) that prints a branch's receipts and kitchen tickets
and opens the cash drawer — straight to the printer, no print dialog. Works with any receipt
printer that understands ESC/POS (Epson, Xprinter, Rongta, Bixolon, Star in ESC/POS mode…), over
the network or USB.

## Setting it up at a branch

1. In Express POS, **Settings → Printers**: add the branch's printers. For a network printer type
   its IP address (e.g. `192.168.1.50`); for a USB printer type its name exactly as Windows shows it
   in *Settings → Bluetooth & devices → Printers & scanners* (e.g. `EPSON TM-T82III Receipt`).
2. On the same page, click **Get a connection code**.
3. On the branch's Windows PC, download the agent from the link on that page, right-click it and
   choose **Run as administrator** (Windows may say "unknown publisher" — choose *More info → Run
   anyway*; the pilot build isn't code-signed yet).
4. Type the site's address and the connection code. It connects, copies itself to
   `C:\ProgramData\ExpressPOS`, and starts with Windows from then on — no window, nothing to keep
   open. The Printers page shows it **online**.

## Day to day

- Receipts, kitchen tickets and the cash drawer happen on their own. A print that fails shows on
  the order board with a **Reprint** button.
- If the PC is off, Express POS prints through the browser instead.
- `ExpressPOSPrintAgent.exe status` shows the connection; the log is
  `C:\ProgramData\ExpressPOS\print-agent.log`.
- `ExpressPOSPrintAgent.exe uninstall` stops it starting with Windows. Disconnecting it on the
  Printers page stops it printing at once.

## Building

`go test ./...`, then
`GOOS=windows GOARCH=amd64 CGO_ENABLED=0 go build -trimpath -ldflags "-s -w" -o ExpressPOSPrintAgent.exe .`
— standard library only. The deploy workflows test and build it and publish it at
`/downloads/ExpressPOSPrintAgent.exe` on each site.
