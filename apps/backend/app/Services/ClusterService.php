<?php

namespace App\Services;

use App\Enums\AlertRuleType;
use App\Enums\ClusterType;
use App\Enums\EndpointType;
use App\Enums\HealthAlertType;
use App\Models\AlertRule;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Config\ConfigSkylogs;
use App\Models\Endpoint;
use App\Models\SkylogsInstance;
use App\Models\Team;
use App\Models\User;
use MongoDB\BSON\ObjectId;

class ClusterService
{
    private ClusterType $clusterType;

    public function __construct()
    {
        $this->clusterType = app(ConfigSkylogsService::class)->getClusterType();
    }

    public function type(): ClusterType
    {
        return $this->clusterType;
    }

    public function clusterByToken($token): ?SkylogsInstance
    {
        return SkylogsInstance::where('token', $token)->first();
    }

    public function clusterById($id): ?SkylogsInstance
    {
        return SkylogsInstance::where('id', $id)->first();
    }

    public function refreshHealthMain(ConfigSkylogs $model)
    {
        $alert = AlertRule::where('type', AlertRuleType::HEALTH)
            ->where('checkType', HealthAlertType::SOURCE_CLUSTER)
            ->first();
        if ($model->type == ClusterType::MAIN) {
            if ($alert) {
                $alert->delete();
            }
        } else {
            if ($alert) {
                $alert->url = $model->sourceUrl;
                $alert->sourceToken = $model->sourceToken;
                $alert->save();
            } else {
                app(AlertRuleService::class)->createHealthCluster($model);
            }

        }
    }

    public function refreshHealthAgent(SkylogsInstance $model)
    {
        $alert = AlertRule::where('type', AlertRuleType::HEALTH)
            ->where('checkType', HealthAlertType::AGENT_CLUSTER)
            ->where('skylogsInstanceId', $model->id)
            ->first();

        if ($alert) {
            $alert->url = $model->url;
            $alert->agentToken = $model->token;
            $alert->save();
        } else {
            app(AlertRuleService::class)->createHealthCluster($model);
        }

    }

    public function getSyncData()
    {
        return [
            'users' => User::get()->makeVisible('password'),
            'endpoints' => Endpoint::get(),
            'roles' => Role::all(),
            'permissions' => Permission::all(),
            'teams' => Team::all(),
        ];
    }

    public function syncData()
    {
        if ($this->clusterType == ClusterType::AGENT) {
            $config = app(ConfigSkylogsService::class)->cluster();
            $sourceUrl = $config->sourceUrl;
            $sourceToken = $config->sourceToken;

            $response = \Http::withToken($sourceToken)->get($sourceUrl.'/api/cluster/sync-data');
            $data = $response->json();
            foreach ($data['users'] as $user) {
                $dbUser = User::where('username', $user['username'])->first();

                if (! $dbUser) {
                    $userModel = new User;
                    $userModel->_id = new ObjectId($user['id']);
                    $exists = false;
                } else {
                    if ($dbUser->id != $user['id']) {
                        $dbUser->delete();
                        $userModel = new User;
                        $userModel->_id = new ObjectId($user['id']);
                        $exists = false;
                    } else {
                        $userModel = $dbUser;
                        $exists = true;
                    }
                }

                $userModel->username = $user['username'];
                $userModel->name = $user['name'];
                $userModel->mainClusterId = $user['id'];
                $userModel->password = $user['password'];
                $userModel->role_id = $user['role_id'] ?? [];

                if ($exists && ! $userModel->isDirty()) {
                    continue;
                }
                $userModel->save();
            }

            foreach ($data['endpoints'] as $endpoint) {
                $dbEndpoint = Endpoint::where('id', $endpoint['id'])->first();
                if ($dbEndpoint) {
                    $exists = false;
                    $endpointModel = $dbEndpoint;
                } else {
                    $exists = true;
                    $endpointModel = new Endpoint;
                }

                $endpointModel->_id = new ObjectId($endpoint['id']);
                $endpointModel->userId = $endpoint['userId'];
                $endpointModel->name = $endpoint['name'];
                $endpointModel->type = $endpoint['type'];
                $endpointModel->accessUserIds = $endpoint['accessUserIds'] ?? [];
                $endpointModel->accessTeamIds = $endpoint['accessTeamIds'] ?? [];

                if ($endpoint['type'] == EndpointType::FLOW->value) {
                    $endpointModel->steps = $endpoint['steps'];
                } elseif ($endpoint['type'] == EndpointType::TELEGRAM->value) {
                    $endpointModel->chatId = $endpoint['chatId'] ?? '';
                    $endpointModel->threadId = $endpoint['threadId'] ?? '';
                    $endpointModel->botToken = $endpoint['botToken'] ?? '';
                } else {
                    $endpointModel->value = $endpoint['value'];
                }
                if ($exists && ! $endpointModel->isDirty()) {
                    continue;
                }
                $endpointModel->save();
            }

            foreach ($data['roles'] as $role) {
                $dbRole = Role::where('name', $role['name'])->first();

                if (! $dbRole) {
                    $roleModel = new Role;
                    $roleModel->_id = new ObjectId($role['id']);
                    $exists = false;
                } else {
                    if ($dbRole->id != $role['id']) {
                        $dbRole->delete();
                        $roleModel = new Role;
                        $roleModel->_id = new ObjectId($role['id']);
                        $exists = false;
                    } else {
                        $roleModel = $dbRole;
                        $exists = true;
                    }
                }

                $roleModel->name = $role['name'];
                $roleModel->guard_name = $role['guard_name'];
                $roleModel->permission_id = $role['permission_id'] ?? [];
                $roleModel->model_has_roles = $role['model_has_roles'] ?? [];

                if ($exists && ! $roleModel->isDirty()) {
                    continue;
                }
                $roleModel->save();
            }

            foreach ($data['permissions'] as $permission) {
                $dbPermission = Permission::where('name', $permission['name'])->first();

                if (! $dbPermission) {
                    $permissionModel = new Permission;
                    $permissionModel->_id = new ObjectId($permission['id']);
                    $exists = false;
                } else {
                    if ($dbPermission->id != $permission['id']) {
                        $dbPermission->delete();
                        $permissionModel = new Permission;
                        $permissionModel->_id = new ObjectId($permission['id']);
                        $exists = false;
                    } else {
                        $permissionModel = $dbRole;
                        $exists = true;
                    }
                }

                $permissionModel->name = $permission['name'];
                $permissionModel->guard_name = $permission['guard_name'];
                $permissionModel->role_id = $permission['role_id'] ?? [];

                if ($exists && ! $permissionModel->isDirty()) {
                    continue;
                }
                $permissionModel->save();
            }

            foreach ($data['teams'] as $team) {
                $dbTeam = Team::where('id', $team['id'])->first();
                if ($dbTeam) {
                    $exists = false;
                    $teamModel = $dbTeam;
                } else {
                    $exists = true;
                    $teamModel = new Team;
                }
                $teamModel->_id = new ObjectId($team['id']);
                $teamModel->ownerId = $team['ownerId'];
                $teamModel->userIds = $team['userIds'];
                $teamModel->name = $team['name'];
                $teamModel->description = $team['description'];

                if ($exists && ! $teamModel->isDirty()) {
                    continue;
                }
                $teamModel->save();
            }

        }
    }
}
