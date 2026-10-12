package config

import "testing"

func env(v string) func(string) string {
	return func(string) string { return v }
}

func TestLoadValid(t *testing.T) {
	c, err := Load(env("https://monitor.example.com"))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if c.ServerURL != "https://monitor.example.com" {
		t.Fatalf("ServerURL = %q", c.ServerURL)
	}
}

func TestLoadRejectsInvalid(t *testing.T) {
	cases := map[string]string{
		"empty":       "",
		"http":        "http://monitor.example.com",
		"no scheme":   "monitor.example.com",
		"no host":     "https://",
		"credentials": "https://user:pass@monitor.example.com",
		"ftp":         "ftp://monitor.example.com",
	}
	for name, v := range cases {
		t.Run(name, func(t *testing.T) {
			if _, err := Load(env(v)); err == nil {
				t.Fatalf("expected error for %q", v)
			}
		})
	}
}
