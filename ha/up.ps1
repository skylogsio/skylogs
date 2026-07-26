#Requires -Version 5.1
<#
.SYNOPSIS
  Start the local 3-node full-stack HA cluster (skylogs1/2/3).

.DESCRIPTION
  Creates skylogs_ha (172.28.7.0/24), builds images, brings up node1 (bootstrap),
  waits for Raft leadership, then joins node2 and node3.

  Run from the repo root:
    .\ha\up.ps1
    .\ha\up.ps1 -SkipBuild
#>
param(
    [switch]$SkipBuild,
    [int]$LeaderTimeoutSec = 180
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $Root "docker-compose.yml"))) {
    $Root = $PSScriptRoot
    if (-not (Test-Path (Join-Path $Root "docker-compose.yml"))) {
        throw "Run from repo root (docker-compose.yml not found)."
    }
}
Set-Location $Root

$NetworkName = "skylogs_ha"
$Subnet = "172.28.7.0/24"
$Gateway = "172.28.7.1"

function Invoke-Compose {
    param(
        [Parameter(Mandatory = $true)][string]$Project,
        [Parameter(Mandatory = $true)][string]$NodeFile,
        [Parameter(Mandatory = $true)][string[]]$ComposeArgs
    )
    $all = @("-p", $Project, "-f", "docker-compose.yml", "-f", $NodeFile) + $ComposeArgs
    & docker compose @all
    if ($LASTEXITCODE -ne 0) {
        throw "docker compose $($ComposeArgs -join ' ') failed for $Project (exit $LASTEXITCODE)"
    }
}

Write-Host "==> Ensuring Docker network $NetworkName ($Subnet)..."
$existing = docker network ls --format "{{.Name}}" | Where-Object { $_ -eq $NetworkName }
if (-not $existing) {
    docker network create --driver bridge --subnet $Subnet --gateway $Gateway $NetworkName | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Failed to create network $NetworkName. If the subnet conflicts, change it in up.ps1, node env, and compose overrides together."
    }
} else {
    Write-Host "    Network already exists."
}

if (-not $SkipBuild) {
    Write-Host "==> Building images (node1 compose context)..."
    Invoke-Compose -Project "skylogs1" -NodeFile "ha/docker-compose.node1.yml" -ComposeArgs @("build")
}

Write-Host "==> Starting skylogs1 (Raft bootstrap)..."
Invoke-Compose -Project "skylogs1" -NodeFile "ha/docker-compose.node1.yml" -ComposeArgs @("up", "-d")

Write-Host "==> Waiting for Raft leader on http://127.0.0.1:8801/status (timeout ${LeaderTimeoutSec}s)..."
$deadline = (Get-Date).AddSeconds($LeaderTimeoutSec)
$leaderReady = $false
while ((Get-Date) -lt $deadline) {
    try {
        $status = curl.exe -s --max-time 2 http://127.0.0.1:8801/status 2>$null
        if ($status -match '"is_leader"\s*:\s*true') {
            Write-Host "    Leader ready: $status"
            $leaderReady = $true
            break
        }
        if ($status) {
            Write-Host "    Waiting... $status"
        }
    } catch {
        # raft HTTP not up yet
    }
    Start-Sleep -Seconds 3
}
if (-not $leaderReady) {
    throw "Timed out waiting for Raft leader on node1. Check: docker logs skylogs_raft-1"
}

Write-Host "==> Starting skylogs2 (join)..."
Invoke-Compose -Project "skylogs2" -NodeFile "ha/docker-compose.node2.yml" -ComposeArgs @("up", "-d")

Write-Host "==> Starting skylogs3 (join)..."
Invoke-Compose -Project "skylogs3" -NodeFile "ha/docker-compose.node3.yml" -ComposeArgs @("up", "-d")

Write-Host "==> Waiting for all three Raft HTTP endpoints..."
$ports = @(8801, 8802, 8803)
$clusterDeadline = (Get-Date).AddSeconds(120)
while ((Get-Date) -lt $clusterDeadline) {
    $ok = 0
    foreach ($port in $ports) {
        $s = curl.exe -s --max-time 2 "http://127.0.0.1:$port/status" 2>$null
        if ($s -match '"node_id"') { $ok++ }
    }
    if ($ok -eq 3) { break }
    Start-Sleep -Seconds 3
}

Write-Host ""
Write-Host "Cluster status:"
foreach ($port in $ports) {
    $s = curl.exe -s --max-time 2 "http://127.0.0.1:$port/status" 2>$null
    Write-Host "  :$port  $s"
}

Write-Host ""
Write-Host "HA cluster is up. Host ports: nginx 8083/8183/8283, raft 8801/8802/8803."
Write-Host "Tear down: .\ha\down.ps1   (add -Volumes -RemoveNetwork for a clean reset)"
