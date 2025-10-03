# Notification Alert Rule

## Overview

The **Notification Alert** is a type of alert rule in Skylogs that sends a notification without maintaining a state. It is designed to immediately notify users or systems about specific events or conditions. This alert type is stateless, meaning it does not track whether the alert is active or resolved; it simply triggers a notification based on the provided configuration.

## Request Body

The Notification Alert requires a JSON payload in the request body with the following fields:

| Field         | Type   | Required | Description                                      |
|---------------|--------|----------|--------------------------------------------------|
| `instance`    | String | Yes      | The identifier for the instance triggering the alert. |
| `description` | String | No       | An optional description providing additional context for the notification. |

### Example Request Body

```json
{
  "instance": "server-001",
  "description": "High CPU usage detected on primary database server."
}
```

## Behavior

- **Stateless**: The Notification Alert does not maintain a state (e.g., active or resolved). Each time the alert is triggered, it sends a notification based on the configured channels (e.g., Phone calls, SMS, Slack, Telegram, or Email).
- **Immediate Notification**: Upon receiving a valid request, Skylogs processes the alert and sends notifications to the configured recipients or systems.
- **Integration**: Works seamlessly with Skylogs' notification systems, including on-call rotations and automatic escalations if configured.

## Use Case

The Notification Alert is ideal for scenarios where you need to be informed of an event without tracking its state, such as:
- One-time alerts for system events (e.g., a user action or a log entry).
- Informational notifications that do not require follow-up or resolution tracking.

## Configuration

To configure a Notification Alert:
1. Ensure your Skylogs instance is set up to handle notifications via your preferred channels (e.g., Slack, Email, etc.).
2. Send a POST request to the Skylogs API with the required `instance` field and optional `description` field in the JSON payload.
3. Verify that the notification is received through the configured channels.

## Example API Request

```bash
curl -X POST https://api.skylogs.io/alerts/notification \
  -H "Content-Type: application/json" \
  -d '{
        "instance": "server-001",
        "description": "High CPU usage detected on primary database server."
      }'
```

## Notes

- Ensure the `instance` field is unique and descriptive to avoid confusion in notification logs.
- The `description` field, while optional, is recommended to provide context for the notification.
- Notification Alerts integrate with Skylogs' on-call rotation and escalation policies if configured.