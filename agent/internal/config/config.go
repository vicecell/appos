// Package config loads and validates agent settings.
package config

import (
	"errors"
	"fmt"
	"net/url"
	"os"
	"strings"
)

// Config holds agent settings. The enrollment token is deliberately not part
// of this struct: it is read from a 0600 file at enroll time (SW-003) so it
// never lands in env dumps, shell history, or logs.
type Config struct {
	ServerURL string // base URL of the ingestion API; HTTPS only
}

// Load reads settings from the environment.
func Load(getenv func(string) string) (Config, error) {
	c := Config{ServerURL: strings.TrimSpace(getenv("SERVERWATCH_SERVER_URL"))}
	return c, c.Validate()
}

// LoadFromOS is Load bound to os.Getenv.
func LoadFromOS() (Config, error) { return Load(os.Getenv) }

// Validate enforces the "outbound HTTPS only" rule from the specification.
func (c Config) Validate() error {
	if c.ServerURL == "" {
		return errors.New("SERVERWATCH_SERVER_URL is required")
	}
	u, err := url.Parse(c.ServerURL)
	if err != nil {
		return fmt.Errorf("SERVERWATCH_SERVER_URL is not a valid URL: %w", err)
	}
	if u.Scheme != "https" {
		return errors.New("SERVERWATCH_SERVER_URL must use https")
	}
	if u.Hostname() == "" {
		return errors.New("SERVERWATCH_SERVER_URL must include a host")
	}
	if u.User != nil {
		return errors.New("SERVERWATCH_SERVER_URL must not embed credentials")
	}
	return nil
}
