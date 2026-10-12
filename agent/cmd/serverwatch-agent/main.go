package main

import (
	"flag"
	"fmt"
	"os"

	"github.com/vicecell/serverwatch/agent/internal/config"
)

// version is overridden at build time: -ldflags "-X main.version=1.2.3".
var version = "0.0.0-dev"

func main() {
	showVersion := flag.Bool("version", false, "print version and exit")
	flag.Parse()

	if *showVersion {
		fmt.Println(version)
		return
	}

	cfg, err := config.LoadFromOS()
	if err != nil {
		fmt.Fprintln(os.Stderr, "config error:", err)
		os.Exit(2)
	}

	// Collectors, transport and enrollment arrive in Sprint 2.
	fmt.Fprintf(os.Stderr, "serverwatch-agent %s: config ok (%s); collectors not implemented in Sprint 0\n", version, cfg.ServerURL)
}
