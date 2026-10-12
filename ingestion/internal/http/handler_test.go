package http

import (
	"encoding/json"
	nethttp "net/http"
	"net/http/httptest"
	"testing"
)

func TestHealthz(t *testing.T) {
	rec := httptest.NewRecorder()
	NewHandler().ServeHTTP(rec, httptest.NewRequest(nethttp.MethodGet, "/healthz", nil))

	if rec.Code != nethttp.StatusOK {
		t.Fatalf("status = %d, want 200", rec.Code)
	}
	var body map[string]string
	if err := json.Unmarshal(rec.Body.Bytes(), &body); err != nil {
		t.Fatalf("invalid json: %v", err)
	}
	if body["status"] != "ok" {
		t.Fatalf("status field = %q, want ok", body["status"])
	}
}

func TestHealthzRejectsNonGet(t *testing.T) {
	rec := httptest.NewRecorder()
	NewHandler().ServeHTTP(rec, httptest.NewRequest(nethttp.MethodPost, "/healthz", nil))

	if rec.Code != nethttp.StatusMethodNotAllowed {
		t.Fatalf("status = %d, want 405", rec.Code)
	}
}

func TestUnknownRouteIs404(t *testing.T) {
	rec := httptest.NewRecorder()
	NewHandler().ServeHTTP(rec, httptest.NewRequest(nethttp.MethodGet, "/nope", nil))

	if rec.Code != nethttp.StatusNotFound {
		t.Fatalf("status = %d, want 404", rec.Code)
	}
}
