# Endpoint Overview
Great — I have everything I need.
Below is a **clean, professional, README-style Markdown documentation** for the **Endpoints** feature in Skylogs.

You can paste this directly into your repo under `/docs/endpoints.md` or anywhere you prefer.

---

# 📡 Skylogs Endpoints

Endpoints are notification destinations that Skylogs uses to deliver alerts.
Users can create endpoints, organize them into flows, and connect them to alert rules.

---

## 🚀 What Are Endpoints?

An **Endpoint** is a notification target such as:

* **Email**
* **Call**
* **SMS**
* **Microsoft Teams**
* **Telegram**
* **Discord**
* **MatterMost**
* **Flow** (a multi-step sequence of actions)

Endpoints determine *where and how* Skylogs delivers alert notifications.

---

# 🧩 Endpoint Types

Each endpoint type requires specific configuration fields:

| Type                | Required Fields                             | Description                                                                                                                                             |
| ------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Email**           | `email`                                     | Sends alert message to an email address.                                                                                                                |
| **Call**            | `phoneNumber`                               | Places a phone call to notify the user.                                                                                                                 |
| **SMS**             | `phoneNumber`                               | Sends a text message.                                                                                                                                   |
| **Microsoft Teams** | `webhookUrl`                                | Sends a message via Teams webhook.                                                                                                                      |
| **Discord**         | `webhookUrl`                                | Sends a message to a Discord channel.                                                                                                                   |
| **MatterMost**      | `webhookUrl`                                | Sends a message to a MatterMost channel.                                                                                                                |
| **Telegram**        | `chatId`, `threadId`, `botToken` (optional) | Sends a message to a Telegram chat/thread. If `botToken` is empty, Skylogs uses the system bot token configured in environment variables, if available. |
| **Flow**            | —                                           | A sequence of steps containing waits or other endpoints.                                                                                                |

---

# 👥 Roles & Permissions

Skylogs has three roles:

1. **Owner**
2. **Manager**
3. **Member**

### **Permission Summary**

#### Owner and Manager role:

has all access to all endpoints

#### Member:
Edit/Delete **their own** endpoints
View shared endpoints   


### Sharing Access

* Endpoints can be shared with **users** or **teams**.
* Shared access is **read-only**.

---

# 🔗 Endpoint Flows

An **Endpoint Flow** is a sequence of steps executed when an alert triggers the flow.

Flows enable advanced notifications such as:

* Wait 1 minute → Send SMS
* Wait 5 minutes → Send Email
* Wait 10 minutes → Notify Discord + Teams

### Types of Flow Steps

1. **Wait Step**

    * Requires a duration (time interval).
    * Pauses flow execution before moving to the next step.

2. **Endpoint Step**

    * Executes one or multiple endpoints at once.
    * Useful for sending notifications to multiple channels simultaneously.

### Execution Rules

* Steps run **sequentially**.
* If a step **fails**, the flow **continues**.
* If an alert rule becomes:

    * **Resolved**, or
    * **Acknowledged** by a user
      → the flow **stops immediately**.
* If an alert rule is **silenced**, **no endpoints are executed**.

---

# 🛎 Connecting Endpoints to Alert Rules

Alerts are delivered through endpoints via **Alert Rules**.

An Alert Rule defines:

* the conditions for triggering,
* the notification message,
* and the endpoint(s) or endpoint flow to execute.

When an alert becomes active:

1. Skylogs runs the assigned endpoint or flow.
2. The flow continues until:

    * all steps complete, or
    * the alert is resolved, acknowledged, or silenced.

---

# 📍 Navigation in the Skylogs UI



### Inside the **Endpoints** page:

The page has **two tabs**:

1. **Endpoints**

    * Contains all standard endpoint types
      (Email, SMS, Call, Teams, Discord, Telegram, MatterMost)

2. **Endpoint Flows**

    * Contains only flow-type endpoints
    * Used to build multi-step notification sequences

---

# ✨ Summary

Skylogs Endpoints make it easy to route notifications across multiple channels and complex escalation flows.

* Users can create endpoints and flows.
* Owners and managers has access all endpoints.
* Members can manage only the endpoints they created.
* Shared access allows visibility without modification.
* Flows allow sequential, multi-channel escalation steps.
* Alert rules trigger endpoints or flows and manage their lifecycle.

