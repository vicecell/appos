// Package http exposes the ingestion API handlers.
package http

import (
	"encoding/json"
	nethttp "net/http"
)

// NewHandler builds the ingestion router. Sprint 0 only serves a health probe;
// agent endpoints (/api/v1/agent/*) arrive in Sprint 2/3.
func NewHandler() nethttp.Handler {
	mux := nethttp.NewServeMux()
	mux.HandleFunc("GET /healthz", healthz)
	return mux
}

func healthz(w nethttp.ResponseWriter, _ *nethttp.Request) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Cache-Control", "no-store")
	_ = json.NewEncoder(w).Encode(map[string]string{"status": "ok", "service": "serverwatch-ingest"})
}
