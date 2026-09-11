export default {
  endpoints: {
    workspace: {
      endpoints: "Endpoints",
      flows: "Endpoint Flows"
    },
    list: {
      title: "Endpoints",
      column: {
        name: "Name",
        type: "Type",
        value: "Value"
      }
    },
    flow: {
      title: "Flows",
      column: {
        steps: "Steps",
        createdAt: "Created At"
      },
      step: {
        wait: "Wait {duration}{unit}",
        endpoint: "Endpoint"
      },
      emptySteps: "No steps"
    },
    modal: {
      createTitle: "Create New Endpoint",
      updateTitle: "Update Endpoint",
      submit: {
        create: "Create",
        update: "Update"
      },
      field: {
        name: "Name",
        type: "Type",
        value: "Value",
        chatId: "Chat ID",
        threadId: "Thread ID",
        botToken: "Bot Token",
        isPublic: "Is Public"
      },
      otp: {
        send: "Send OTP Code",
        resend: "Resend"
      },
      addWait: "ADD WAIT",
      addEndpoint: "ADD ENDPOINTS"
    },
    delete: {
      title: "Delete Endpoint",
      confirm: "Are you sure?",
      field: {
        name: "Name",
        type: "Type",
        value: "Value",
        steps: "Steps"
      }
    }
  }
} as const;
