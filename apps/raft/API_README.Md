# REST API

This service exposes a simple HTTP API for interacting with the Raft cluster.

Base URL:

```
http://<host>:8000
```

---

# Set Value

Stores a key-value pair in the cluster.

This operation **must be committed through the Raft leader**. If the request is sent to a follower, it may be redirected or rejected depending on the implementation.

## Endpoint

```
POST /set
```

## Request

```json
{
    "key": "username",
    "value": "mobin"
}
```

## Example

```bash
curl -X -i POST http://localhost:8000/set \
    -H "Content-Type: application/json" \
    -d '{
        "key":"username",
        "value":"mobin"
    }'
```

## Success Response

```json
HTTP/1.1 200 OK
Content-Type: application/json
Date: Sun, 26 Jul 2026 13:22:33 GMT
Content-Length: 16

{
    "status": "ok"
}
```

## Failed Response
### if we call set from follower node : 
```json
HTTP/1.1 500 Internal Server Error
Content-Type: text/plain; charset=utf-8
X-Content-Type-Options: nosniff
Date: Sun, 26 Jul 2026 13:26:31 GMT
Content-Length: 15

not the leader

```

---

# Get Value

Retrieves the value associated with a key.

## Endpoint

```
GET /get?key=<key>
```
## Example

```bash
curl '"http://localhost:8000/get?key="username"'
```

## Success Response

```json
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
Date: Sun, 26 Jul 2026 13:34:26 GMT
Content-Length: 33


{
    "key": "username",
    "value": "mobin"
}
```

## If Key Does Not Exist

```json
HTTP/1.1 404 Not Found
Content-Type: application/json; charset=utf-8
Date: Sun, 26 Jul 2026 13:35:57 GMT
Content-Length: 25

{"error":"key not found"}
```

---

#  Status

Returns information about the current node and the Raft cluster.

## Endpoint

```
GET /status
```

## Example

```bash
curl http://localhost:8000/status
```

## Example Response (LEADER)

```json
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
Date: Sun, 26 Jul 2026 13:37:29 GMT
Content-Length: 83

{
  "is_leader":true,
  "leader":"192.168.56.11:7000",
  "node_id":"node1",
  "state":"Leader"
}
```
## Example Response (FOLLOWER)

```json
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
Date: Sun, 26 Jul 2026 13:39:55 GMT
Content-Length: 86

{
    "is_leader": false,
    "leader": "192.168.56.11:7000",
    "node_id": "node2",
    "state": "Follower"
}
```

---

# Get Current Leader

Returns the current Raft leader.

## Endpoint

```
GET /leader
```

## Example

```bash
curl http://localhost:8000/leader
```

## Example Response (If the node was Leader)

```json
HTTP/1.1 200 OK
Content-Type: application/json; charset=utf-8
Date: Sun, 26 Jul 2026 13:45:41 GMT
Content-Length: 46

{"address":"192.168.56.11:7000","leader":true}
```

## Example Response (If the node was Follower)

```json
HTTP/1.1 503 Service Unavailable
Content-Type: application/json; charset=utf-8
Date: Sun, 26 Jul 2026 13:41:39 GMT
Content-Length: 47

{"address":"192.168.56.11:7000","leader":false}
```


---

# Typical Workflow

1. Start the first node and bootstrap the cluster.
2. Start additional nodes.
3. Send `POST /join` requests to the leader for each new node.
4. Verify the cluster using:

```
GET /status
```

5. Find the current leader:

```
GET /leader
```

6. Write data:

```
POST /set
```

7. Read data:

```
GET /get
```

---

# Example

Store a value:

```bash
curl -X POST http://localhost:8000/set \
    -H "Content-Type: application/json" \
    -d '{"key":"language","value":"Go"}'
```

Read it:

```bash
curl "http://localhost:8000/get?key=language"
```

Result:

```json
{
    "key":"language",
    "value":"Go"
}
```

