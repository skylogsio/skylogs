# 👤 Users & Teams

Skylogs uses a simple, role-based system to manage user permissions and access to alert rules, endpoints, and teams.

This document explains how users are created, how roles work, and how teams are managed.

---

# 👥 User Roles

Skylogs includes three user roles:

1. **Owner**
2. **Manager**
3. **Member**

Role permissions determine what each user can see, create, and modify.

---

## 🛡 Role Permissions Overview

| Action                                | Owner                  | Manager          | Member |
| ------------------------------------- | ---------------------- | ---------------- | ------ |
| Create users                          | ✔️ (Manager or Member) | ✔️ (Member only) | ❌      |
| View Users page                       | ✔️                     | ✔️               | ❌      |
| Create teams                          | ✔️                     | ✔️               | ❌      |
| Edit/Delete teams                     | ✔️                     | ✔️               | ❌      |
| View teams                            | ✔️                     | ✔️               | ✔️     |
| Create endpoints                      | ✔️                     | ✔️               | ✔️     |
| Edit/Delete **all** endpoints         | ✔️                     | ✔️               | ❌      |
| Edit/Delete **own** endpoints         | ✔️                     | ✔️               | ✔️     |
| Set access to endpoints & alert rules | ✔️                     | ✔️               | ✔️     |
| Create alert rules                    | ✔️                     | ✔️               | ✔️     |

---

# 👤 Users

### System Owner (Admin)

* The system automatically creates a single **admin user** with the **Owner** role.
* This user is the only one who starts with Owner permissions.

### Creating Users

* **Owner** can create new users with either:

    * **Manager**
    * **Member**
* **Manager** can create only **Member** users.
* **Member** users **cannot** create users and **cannot** see the Users section.

### User Visibility

* Owners and managers have access to the **Users** menu.
* Members do **not** have access to this section.

---

# 👨‍👩‍👧 Teams

Teams allow grouping multiple users to simplify access control for alert rules and endpoints.

Users with access to a team automatically gain access to resources shared with that team.

---

## Team Structure

Each team has:

* **Team Owner**
  (a user with Owner or Manager permissions)
* **Team Members**
  (users of any role)

---

## Team Permissions

| Action                  | Owner | Manager | Member |
| ----------------------- | ----- | ------- | ------ |
| Create team             | ✔️    | ✔️      | ❌      |
| Edit team               | ✔️    | ✔️      | ❌      |
| Delete team             | ✔️    | ✔️      | ❌      |
| View team details       | ✔️    | ✔️      | ✔️     |
| Add/remove team members | ✔️    | ✔️      | ❌      |

Members can see team owners and members but **cannot modify** teams.

---

# 🔐 Access Control (Users & Teams)

Skylogs provides a flexible sharing model for both:

* **Alert Rules**
* **Endpoints**

Users can grant access to either:

* Specific **users**
* Entire **teams**

### Sharing Behavior

* Shared users and teams receive **read-only** access.
* Only users with appropriate roles (Owner/Manager/Owner-of-resource) can edit or delete.
* Access can be shared with multiple users or teams at once.

### Examples

* Share an alert rule with the “Ops Team” to notify everyone in operations.
* Share a critical SMS endpoint with a specific user so the user can set the endpoint to his/her alert rules that has access.

---

# 📍 Navigation in the UI

### **If you are an Owner or Manager**

You can see **Users** and **Teams** menu items

### **If you are a Member**

You see only **Teams** 


Members do not see the **Users** section and cannot create/edit/delete teams.

---

# ✨ Summary

Skylogs uses a simple and powerful structure:

### Users

* Owner → full control
* Manager → manages members and teams
* Member → limited user without administrative permissions

### Teams

* Created by owners/managers
* Each team has an owner and members
* Members can only view team info

### Access Control

* Endpoints and alert rules can be shared with users or teams
* Shared access is always read-only
* Ownership determines who can edit/delete
