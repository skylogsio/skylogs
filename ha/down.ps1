#Requires -Version 5.1
<#
.SYNOPSIS
  Stop the local 3-node HA cluster.

.PARAMETER Volumes
  Pass -v to docker compose down (wipes Raft / Mongo / Redis / app volumes).

.PARAMETER RemoveNetwork
  Remove the shared skylogs_ha network after projects are down.

.EXAMPLE
  .\ha\down.ps1
  .\ha\down.ps1 -Volumes -RemoveNetwork
#>
param(
    [switch]$Volumes,
    [switch]$RemoveNetwork
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

$downArgs = @("down")
if ($Volumes) {
    $downArgs += "-v"
}

$nodes = @(
    @{ Project = "skylogs1"; File = "ha/docker-compose.node1.yml" },
    @{ Project = "skylogs2"; File = "ha/docker-compose.node2.yml" },
    @{ Project = "skylogs3"; File = "ha/docker-compose.node3.yml" }
)

foreach ($node in $nodes) {
    Write-Host "==> Down $($node.Project)..."
    & docker compose -p $node.Project -f docker-compose.yml -f $node.File @downArgs
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "down failed for $($node.Project) (exit $LASTEXITCODE) — continuing"
    }
}

if ($RemoveNetwork) {
    Write-Host "==> Removing network skylogs_ha..."
    docker network rm skylogs_ha 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Could not remove skylogs_ha (may still be in use or already gone)."
    }
}

Write-Host "Done."
