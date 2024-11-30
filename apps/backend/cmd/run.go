package cmd

import (
	"fmt"
	"github.com/redis/go-redis/v9"
	"github.com/skylogsio/skylogs/configs"
	"github.com/skylogsio/skylogs/handlers/http"
	"github.com/skylogsio/skylogs/internal/services/auth"
	"github.com/skylogsio/skylogs/internal/services/endpoint"
	"github.com/skylogsio/skylogs/internal/services/user"
	"github.com/skylogsio/skylogs/storage/mongo"

	"github.com/spf13/cobra"
)

func init() {
	RootCMD.AddCommand(runCMD)
}

var runCMD = &cobra.Command{
	Use:   "serve",
	Short: "Run the application",
	Long:  "Run the application",
	PreRunE: func(cmd *cobra.Command, args []string) error {
		configs.Configs.Load()
		fmt.Println(configs.Configs)
		return nil
	},
	RunE: runCmdE,
}

func runCmdE(cmd *cobra.Command, args []string) error {

	r := redis.NewClient(&redis.Options{})
	//
	mongoClient, err := mongo.CreateClient()
	if err != nil {
		return err
	}

	userService, err := user.New(
		user.WithRedis(r),
		user.WithRepository(mongoClient),
	)

	authService, err := auth.New(
		auth.WithRedis(r),
		auth.WithRepository(mongoClient),
	)

	endpointService, err := endpoint.New(
		endpoint.WithRedis(r),
		endpoint.WithRepository(mongoClient),
	)

	httpServices := http.New(
		userService,
		authService,
		endpointService,
	)

	err = httpServices.Launch()

	if err != nil {
		return err
	}

	return nil
}
