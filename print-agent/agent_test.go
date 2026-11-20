package main

import (
	"encoding/base64"
	"encoding/json"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"strings"
	"sync"
	"testing"
)

// A fake receipt printer on the network: records what it's sent.
func fakePrinter(t *testing.T) (address string, received func() []byte) {
	t.Helper()
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { listener.Close() })

	var mu sync.Mutex
	var got []byte
	done := make(chan struct{})
	go func() {
		conn, err := listener.Accept()
		if err != nil {
			return
		}
		data, _ := io.ReadAll(conn)
		mu.Lock()
		got = data
		mu.Unlock()
		close(done)
	}()

	return listener.Addr().String(), func() []byte {
		<-done
		mu.Lock()
		defer mu.Unlock()
		return got
	}
}

func TestANetworkJobIsPrintedAndReported(t *testing.T) {
	printerAddress, received := fakePrinter(t)
	escPos := "\x1B@Mama Put Kitchen\n\x1DV\x42\x03"

	var reports []map[string]any
	var mu sync.Mutex
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer secret" {
			w.WriteHeader(http.StatusUnauthorized)
			return
		}
		switch {
		case r.Method == "GET" && r.URL.Path == "/api/print-agent/jobs":
			job := map[string]any{"id": 7, "data": base64.StdEncoding.EncodeToString([]byte(escPos)),
				"printer": map[string]string{"name": "Front", "connection": "network", "address": printerAddress}}
			json.NewEncoder(w).Encode(map[string]any{"jobs": []any{job}})
		case r.Method == "POST" && r.URL.Path == "/api/print-agent/jobs/7":
			var body map[string]any
			json.NewDecoder(r.Body).Decode(&body)
			mu.Lock()
			reports = append(reports, body)
			mu.Unlock()
			w.Write([]byte(`{"ok":true}`))
		default:
			w.WriteHeader(http.StatusNotFound)
		}
	}))
	defer server.Close()

	config := Config{Server: server.URL, Token: "secret"}
	jobs, err := fetchJobs(config)
	if err != nil || len(jobs) != 1 {
		t.Fatalf("fetchJobs: %v %v", jobs, err)
	}
	printErr := printJob(jobs[0])
	if err := report(config, jobs[0], printErr); err != nil {
		t.Fatal(err)
	}

	if got := string(received()); got != escPos {
		t.Fatalf("printer got %q", got)
	}
	if len(reports) != 1 || reports[0]["printed"] != true {
		t.Fatalf("report: %v", reports)
	}
}

func TestAnUnreachablePrinterIsReportedAsFailed(t *testing.T) {
	err := printNetwork("127.0.0.1:1", []byte("x"))
	if err == nil || !strings.Contains(err.Error(), "can't reach the printer") {
		t.Fatalf("got %v", err)
	}
}

func TestANetworkAddressWithoutAPortUses9100(t *testing.T) {
	// Nothing listens there in the test; only the port choice matters.
	err := printNetwork("127.0.0.1", []byte("x"))
	if err == nil || !strings.Contains(err.Error(), "127.0.0.1:9100") {
		t.Fatalf("got %v", err)
	}
}

func TestTheSiteAddressIsTidied(t *testing.T) {
	for typed, want := range map[string]string{
		" mama.pos.solusysdigital.com/ ": "https://mama.pos.solusysdigital.com",
		"http://localhost:8000":                 "http://localhost:8000",
	} {
		if got := normalizeServer(typed); got != want {
			t.Errorf("%q → %q, want %q", typed, got, want)
		}
	}
}

func TestAWrongSecretIsTheServersMessage(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusUnauthorized)
		w.Write([]byte(`{"message":"This print agent is not connected."}`))
	}))
	defer server.Close()

	_, err := fetchJobs(Config{Server: server.URL, Token: "old"})
	if err == nil || err.Error() != "This print agent is not connected." {
		t.Fatalf("got %v", err)
	}
}
