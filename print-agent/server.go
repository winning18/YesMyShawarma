package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"strings"
	"time"
)

var client = &http.Client{Timeout: 20 * time.Second}

// Job is one thing to print, exactly as the server sends it.
type Job struct {
	ID      int64  `json:"id"`
	Data    string `json:"data"` // base64 ESC/POS
	Printer struct {
		Name       string `json:"name"`
		Connection string `json:"connection"` // network | usb
		Address    string `json:"address"`
	} `json:"printer"`
}

func normalizeServer(address string) string {
	address = strings.TrimSpace(address)
	address = strings.TrimRight(address, "/")
	if address != "" && !strings.HasPrefix(address, "http://") && !strings.HasPrefix(address, "https://") {
		address = "https://" + address
	}
	return address
}

func request(method, url, token string, body any, out any) error {
	var reader io.Reader
	if body != nil {
		raw, _ := json.Marshal(body)
		reader = bytes.NewReader(raw)
	}
	req, err := http.NewRequest(method, url, reader)
	if err != nil {
		return err
	}
	req.Header.Set("Accept", "application/json")
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("User-Agent", "ExpressPOSPrintAgent/"+version)
	if token != "" {
		req.Header.Set("Authorization", "Bearer "+token)
	}

	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("can't reach the Express POS site (%v)", err)
	}
	defer resp.Body.Close()
	raw, _ := io.ReadAll(resp.Body)

	if resp.StatusCode >= 300 {
		var problem struct {
			Message string `json:"message"`
		}
		_ = json.Unmarshal(raw, &problem)
		if problem.Message == "" {
			problem.Message = resp.Status
		}
		return &serverError{status: resp.StatusCode, message: problem.Message}
	}
	if out != nil {
		return json.Unmarshal(raw, out)
	}
	return nil
}

type serverError struct {
	status  int
	message string
}

func (e *serverError) Error() string { return e.message }

func connect(server, code string) (Config, error) {
	var answer struct {
		Token    string `json:"token"`
		Agent    string `json:"agent"`
		Branch   string `json:"branch"`
		Business string `json:"business"`
	}
	err := request("POST", server+"/api/print-agent/connect", "", map[string]string{"code": code}, &answer)
	return Config{Server: server, Token: answer.Token, Agent: answer.Agent, Branch: answer.Branch, Business: answer.Business}, err
}

func fetchJobs(config Config) ([]Job, error) {
	var answer struct {
		Jobs []Job `json:"jobs"`
	}
	err := request("GET", config.Server+"/api/print-agent/jobs", config.Token, nil, &answer)
	return answer.Jobs, err
}

func report(config Config, job Job, printErr error) error {
	body := map[string]any{"printed": printErr == nil}
	if printErr != nil {
		body["error"] = printErr.Error()
	}
	return request("POST", fmt.Sprintf("%s/api/print-agent/jobs/%d", config.Server, job.ID), config.Token, body, nil)
}
