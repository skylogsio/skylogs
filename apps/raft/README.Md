run first node
```go
./raft-node \
  --node-id node1 \
  --bind-address 0.0.0.0 \
  --advertise-address 192.168.56.11 \
  --raft-port 7000 \
  --http-port 8000 \
  --data-dir ./data/node1 \
  --bootstrap

```


run second node
```go
./raft-node \
  --node-id node2 \
  --bind-address 0.0.0.0 \
  --advertise-address 192.168.56.12 \
  --raft-port 7000 \
  --http-port 8000 \
  --data-dir ./data/node2 \
  --join http://192.168.56.11:8000

```

run third node
```go
./raft-node \
  --node-id node3 \
  --bind-address 0.0.0.0 \
  --advertise-address 192.168.56.13 \
  --raft-port 7000 \
  --http-port 8000 \
  --data-dir ./data/node3 \
  --join http://192.168.56.11:8000

```

---
# Docker run
### Use this line for data folder
```bash
sudo chown -R 65532:65532 ./data
```

### Node1 Start
```go
docker run -d \
  --name raft-node1 \
  -p 8000:8000 \
  -p 7000:7000 \
  -v $(pwd)/data/node1:/data/node1 \
  skylogs-raft \
  --node-id=node1 \
  --bind-address=0.0.0.0 \
  --advertise-address 172.28.7.11 \
  --raft-port=7000 \
  --http-port=8000 \
  --data-dir=/data/node1 \
  --bootstrap
```

### Node2 Start
```go
docker run -d \
  --name raft-node2 \
  -p 8000:8000 \
  -p 7000:7000 \
  -v $(pwd)/data/node2:/data/node2 \
  skylogs-raft \
  --node-id=node2 \
  --bind-address=0.0.0.0 \
  --advertise-address 172.28.7.12 \
  --raft-port=7000 \
  --http-port=8000 \
  --data-dir=/data/node2 \
  --join http://172.28.7.11:8000
```
### Node3 Start
```go
docker run -d \
  --name raft-node3 \
  -p 8000:8000 \
  -p 7000:7000 \
  -v $(pwd)/data/node3:/data/node3 \
  skylogs-raft \
  --node-id=node3 \
  --bind-address=0.0.0.0 \
  --advertise-address 172.28.7.13 \
  --raft-port=7000 \
  --http-port=8000 \
  --data-dir=/data/node3 \
  --join http://172.28.7.13:8000
```


### All nodes back
just use the up ones without --join & --bootstrap



### Run/Stop with timelines
```bash
echo "Start: $(date '+%H:%M:%S.%3N')"
sudo docker stop raft-node
echo "End:   $(date '+%H:%M:%S.%3N')"
```

### Restart the docker container     
```bash
#sudo docker stop raft-node 
sudo docker rm -f raft-node 
sudo docker volume rm raft_raft-data
sudo docker compose up -d 
```