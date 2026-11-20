package main

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"time"
)

// Config is what setup saves: where the server is and the agent's secret.
type Config struct {
	Server   string `json:"server"`
	Token    string `json:"token"`
	Agent    string `json:"agent"`
	Branch   string `json:"branch"`
	Business string `json:"business"`
}

// dataDir is where the agent keeps its config, log and installed copy:
// C:\ProgramData\ExpressPOS on Windows.
func dataDir() string {
	if runtime.GOOS == "windows" {
		if base := os.Getenv("ProgramData"); base != "" {
			return filepath.Join(base, "ExpressPOS")
		}
		return `C:\ProgramData\ExpressPOS`
	}
	home, _ := os.UserHomeDir()
	return filepath.Join(home, ".expresspos")
}

func configPath() string { return filepath.Join(dataDir(), "print-agent.json") }

func logPath() string { return filepath.Join(dataDir(), "print-agent.log") }

func loadConfig() (Config, error) {
	var config Config
	raw, err := os.ReadFile(configPath())
	if err != nil {
		return config, fmt.Errorf("not set up yet: run ExpressPOSPrintAgent.exe and follow the steps")
	}
	if err := json.Unmarshal(raw, &config); err != nil {
		return config, fmt.Errorf("the settings file is damaged; set the agent up again")
	}
	if config.Server == "" || config.Token == "" {
		return config, fmt.Errorf("not connected yet: run ExpressPOSPrintAgent.exe and follow the steps")
	}
	return config, nil
}

func saveConfig(config Config) error {
	if err := os.MkdirAll(dataDir(), 0o755); err != nil {
		return err
	}
	raw, _ := json.MarshalIndent(config, "", "  ")
	return os.WriteFile(configPath(), raw, 0o600)
}

// logf appends a line to the log, starting a fresh log once it passes 1 MB.
func logf(format string, args ...any) {
	_ = os.MkdirAll(dataDir(), 0o755)
	if info, err := os.Stat(logPath()); err == nil && info.Size() > 1<<20 {
		_ = os.Rename(logPath(), logPath()+".old")
	}
	file, err := os.OpenFile(logPath(), os.O_CREATE|os.O_APPEND|os.O_WRONLY, 0o644)
	if err != nil {
		return
	}
	defer file.Close()
	fmt.Fprintf(file, "%s %s\n", time.Now().Format("2006-01-02 15:04:05"), fmt.Sprintf(format, args...))
}
