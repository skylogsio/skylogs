package main

import (
	"log"

	"RAFT_Service/internal/app"
)

func main() {

	application, err := app.New()
	if err != nil {
		log.Fatal(err)
	}

	if err := application.Run(); err != nil {
		log.Fatal(err)
	}
}
