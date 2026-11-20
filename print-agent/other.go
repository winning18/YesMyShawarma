//go:build !windows

package main

import "errors"

// The agent is for Windows; these let it build and be tested elsewhere.

func printWindows(name string, data []byte) error {
	return errors.New("printing to a Windows printer only works on Windows")
}

func installStartup() error {
	return errors.New("starting with the computer is only set up on Windows")
}

func uninstall() error { return nil }
