// Express POS Print Agent (Express POS step 8b, Solusys Digital).
//
// Runs on a branch's Windows PC. It collects receipts, kitchen tickets and
// cash drawer kicks from the Express POS server every couple of seconds and
// sends them, as ESC/POS bytes the server has already built, to the branch's
// printers — over the network (IP address, port 9100) or to a printer
// installed in Windows (USB). Nearly every receipt printer brand speaks
// ESC/POS, so the agent doesn't need to know which one it is.
//
//	ExpressPOSPrintAgent.exe            set up (first run): site address + connection code
//	ExpressPOSPrintAgent.exe run        collect and print (what Windows starts at boot)
//	ExpressPOSPrintAgent.exe status     show the connection and check the server
//	ExpressPOSPrintAgent.exe uninstall  stop starting with Windows
package main

import (
	"bufio"
	"fmt"
	"os"
	"strings"
)

var version = "dev"

func main() {
	command := ""
	if len(os.Args) > 1 {
		command = strings.ToLower(os.Args[1])
	}

	var err error
	switch command {
	case "", "setup":
		err = setup()
		pause()
	case "run":
		err = run()
	case "status":
		err = status()
		pause()
	case "uninstall":
		err = uninstall()
		pause()
	case "version":
		fmt.Println("Express POS Print Agent", version)
	default:
		fmt.Println("Unknown command:", command)
		fmt.Println("Use: setup, run, status, uninstall, version")
		os.Exit(2)
	}

	if err != nil {
		fmt.Println()
		fmt.Println("Error:", err)
		logf("error: %v", err)
		os.Exit(1)
	}
}

// pause keeps the console window open after a double-click so the person can
// read what happened.
func pause() {
	if len(os.Args) > 1 && os.Args[len(os.Args)-1] == "--no-pause" {
		return
	}
	fmt.Println()
	fmt.Print("Press Enter to close this window.")
	bufio.NewReader(os.Stdin).ReadString('\n')
}
