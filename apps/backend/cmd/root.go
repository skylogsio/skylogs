package cmd

import (
	"fmt"
	"os"

	"github.com/spf13/cobra"
)

var RootCMD = &cobra.Command{
	Use: "skylogs", // TODO
	Run: func(cmd *cobra.Command, args []string) {
		cmd.HelpFunc()(cmd, args)
	},
}

func Execute() {
	if err := RootCMD.Execute(); err != nil {
		fmt.Println(err)
		os.Exit(1)
	}
}

func getConfigFilePath(cmd *cobra.Command) string {
	configFlag := cmd.Flags().Lookup("config")
	if configFlag != nil {
		configFilePath := configFlag.Value.String()
		if configFilePath != "" {
			return configFilePath
		}
	}
	return ""
}
