//go:build windows

package main

import (
	"fmt"
	"io"
	"os"
	"os/exec"
	"path/filepath"
	"syscall"
	"unsafe"
)

var (
	winspool         = syscall.NewLazyDLL("winspool.drv")
	openPrinter      = winspool.NewProc("OpenPrinterW")
	closePrinter     = winspool.NewProc("ClosePrinter")
	startDocPrinter  = winspool.NewProc("StartDocPrinterW")
	endDocPrinter    = winspool.NewProc("EndDocPrinter")
	startPagePrinter = winspool.NewProc("StartPagePrinter")
	endPagePrinter   = winspool.NewProc("EndPagePrinter")
	writePrinterProc = winspool.NewProc("WritePrinter")
)

type docInfo1 struct {
	docName    *uint16
	outputFile *uint16
	dataType   *uint16
}

// printWindows sends raw ESC/POS to a printer installed in Windows (USB,
// or any printer Windows can see), by its name in Windows.
func printWindows(name string, data []byte) error {
	printerName, _ := syscall.UTF16PtrFromString(name)
	var handle syscall.Handle
	if ok, _, err := openPrinter.Call(uintptr(unsafe.Pointer(printerName)), uintptr(unsafe.Pointer(&handle)), 0); ok == 0 {
		return fmt.Errorf("Windows has no printer called %q (%v): check the name on the Printers page matches Windows' Printers list", name, err)
	}
	defer closePrinter.Call(uintptr(handle))

	docName, _ := syscall.UTF16PtrFromString("Express POS")
	dataType, _ := syscall.UTF16PtrFromString("RAW")
	info := docInfo1{docName: docName, dataType: dataType}
	if job, _, err := startDocPrinter.Call(uintptr(handle), 1, uintptr(unsafe.Pointer(&info))); job == 0 {
		return fmt.Errorf("Windows wouldn't start printing on %q (%v)", name, err)
	}
	defer endDocPrinter.Call(uintptr(handle))
	startPagePrinter.Call(uintptr(handle))
	defer endPagePrinter.Call(uintptr(handle))

	var written uint32
	if ok, _, err := writePrinterProc.Call(uintptr(handle), uintptr(unsafe.Pointer(&data[0])), uintptr(len(data)), uintptr(unsafe.Pointer(&written))); ok == 0 || int(written) != len(data) {
		return fmt.Errorf("Windows couldn't send the print to %q (%v)", name, err)
	}
	return nil
}

const taskName = "Express POS Print Agent"

// installStartup copies the agent to C:\ProgramData\ExpressPOS and has
// Windows start it at boot, in the background, whoever is signed in.
func installStartup() error {
	self, err := os.Executable()
	if err != nil {
		return err
	}
	target := filepath.Join(dataDir(), "ExpressPOSPrintAgent.exe")
	if !samePath(self, target) {
		if err := copyFile(self, target); err != nil {
			return fmt.Errorf("couldn't copy the agent to %s: %v", target, err)
		}
	}

	_ = exec.Command("schtasks", "/End", "/TN", taskName).Run()
	create := exec.Command("schtasks", "/Create", "/F", "/TN", taskName,
		"/TR", fmt.Sprintf(`"%s" run`, target),
		"/SC", "ONSTART", "/RU", "SYSTEM", "/RL", "HIGHEST")
	if out, err := create.CombinedOutput(); err != nil {
		return fmt.Errorf("%v: %s", err, out)
	}
	if out, err := exec.Command("schtasks", "/Run", "/TN", taskName).CombinedOutput(); err != nil {
		return fmt.Errorf("%v: %s", err, out)
	}
	return nil
}

func uninstall() error {
	_ = exec.Command("schtasks", "/End", "/TN", taskName).Run()
	if out, err := exec.Command("schtasks", "/Delete", "/F", "/TN", taskName).CombinedOutput(); err != nil {
		return fmt.Errorf("%v: %s", err, out)
	}
	fmt.Println("The Express POS Print Agent no longer starts with Windows.")
	return nil
}

func samePath(a, b string) bool {
	absA, _ := filepath.Abs(a)
	absB, _ := filepath.Abs(b)
	return filepath.Clean(absA) == filepath.Clean(absB)
}

func copyFile(from, to string) error {
	if err := os.MkdirAll(filepath.Dir(to), 0o755); err != nil {
		return err
	}
	src, err := os.Open(from)
	if err != nil {
		return err
	}
	defer src.Close()
	dst, err := os.Create(to)
	if err != nil {
		return err
	}
	defer dst.Close()
	_, err = io.Copy(dst, src)
	return err
}
