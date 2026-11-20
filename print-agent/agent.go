package main

import (
	"bufio"
	"encoding/base64"
	"errors"
	"fmt"
	"net"
	"os"
	"strings"
	"time"
)

// setup asks for the site address and connection code, saves the
// connection, and makes the agent start with Windows.
func setup() error {
	in := bufio.NewReader(os.Stdin)
	fmt.Println("Express POS Print Agent", version, "- set up")
	fmt.Println()
	fmt.Println("1. On the Express POS Printers page, click 'Get a connection code'.")
	fmt.Println("2. Type the site's address and the code below.")
	fmt.Println()

	fmt.Print("Site address (e.g. yourbusiness.pos.solusysdigital.com): ")
	server, _ := in.ReadString('\n')
	server = normalizeServer(server)
	if server == "" {
		return errors.New("no site address typed")
	}

	fmt.Print("Connection code: ")
	code, _ := in.ReadString('\n')
	code = strings.TrimSpace(code)

	config, err := connect(server, code)
	if err != nil {
		return err
	}
	if err := saveConfig(config); err != nil {
		return fmt.Errorf("couldn't save the settings: %v", err)
	}
	fmt.Println()
	fmt.Printf("Connected as %q for %s, %s.\n", config.Agent, config.Business, config.Branch)
	logf("connected to %s as %q", config.Server, config.Agent)

	if err := installStartup(); err != nil {
		fmt.Println()
		fmt.Println("It couldn't set itself to start with Windows:", err)
		fmt.Println("Right-click ExpressPOSPrintAgent.exe and choose 'Run as administrator', then set it up again.")
		return nil
	}
	fmt.Println("It now runs in the background and starts with Windows. Printing is ready.")
	return nil
}

// run collects and prints until stopped. It never gives up: a dropped
// internet connection or a server restart just means trying again shortly.
func run() error {
	config, err := loadConfig()
	if err != nil {
		return err
	}
	logf("started %s, printing for %s (%s)", version, config.Branch, config.Server)

	wait := 2 * time.Second
	for {
		jobs, err := fetchJobs(config)
		if err != nil {
			var problem *serverError
			if errors.As(err, &problem) && problem.status == 401 {
				logf("disconnected on the Printers page: %v", err)
				wait = time.Minute
			} else {
				logf("can't collect jobs: %v", err)
				wait = min(wait*2, 30*time.Second)
			}
			time.Sleep(wait)
			continue
		}
		wait = 2 * time.Second

		for _, job := range jobs {
			printErr := printJob(job)
			if printErr != nil {
				logf("job %d on %q failed: %v", job.ID, job.Printer.Name, printErr)
			} else {
				logf("job %d printed on %q", job.ID, job.Printer.Name)
			}
			if err := report(config, job, printErr); err != nil {
				logf("couldn't report job %d: %v", job.ID, err)
			}
		}

		time.Sleep(wait)
	}
}

func status() error {
	config, err := loadConfig()
	if err != nil {
		return err
	}
	fmt.Println("Express POS Print Agent", version)
	fmt.Printf("Connected as %q for %s, %s\n", config.Agent, config.Business, config.Branch)
	fmt.Println("Site:", config.Server)
	if _, err := fetchJobs(config); err != nil {
		return fmt.Errorf("the site didn't answer: %v", err)
	}
	fmt.Println("The site answers. Log:", logPath())
	return nil
}

func printJob(job Job) error {
	data, err := base64.StdEncoding.DecodeString(job.Data)
	if err != nil {
		return fmt.Errorf("damaged print job")
	}
	address := strings.TrimSpace(job.Printer.Address)
	if address == "" {
		return fmt.Errorf("no address is set for %q on the Printers page", job.Printer.Name)
	}

	switch job.Printer.Connection {
	case "network":
		return printNetwork(address, data)
	case "usb":
		return printWindows(address, data)
	default:
		return fmt.Errorf("unknown connection %q", job.Printer.Connection)
	}
}

// printNetwork sends the bytes straight to the printer's raw port (9100
// unless the address names another).
func printNetwork(address string, data []byte) error {
	if _, _, err := net.SplitHostPort(address); err != nil {
		address = net.JoinHostPort(address, "9100")
	}
	conn, err := net.DialTimeout("tcp", address, 5*time.Second)
	if err != nil {
		return fmt.Errorf("can't reach the printer at %s: is it on and on the network?", address)
	}
	defer conn.Close()
	_ = conn.SetWriteDeadline(time.Now().Add(15 * time.Second))
	if _, err := conn.Write(data); err != nil {
		return fmt.Errorf("the printer at %s stopped answering", address)
	}
	return nil
}
