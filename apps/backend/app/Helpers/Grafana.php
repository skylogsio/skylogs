<?php

namespace App\Helpers;

use common\models\User;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\httpclient\Client;
use backend\models\GrafanaDashboard;

class Grafana
{

    public static function PROMETHEUS_API()
    {
        $url = "https://";
        $url .= env("PROMETHEUS_HOST");
        $url .= "/api/v1/";
        $url = "https://prometheus-develop.qcluster.org/api/v1/";
        return $url;
    }

    public static function GRAFANA_API()
    {
        $url = "https://";
        $url .= env("GRAFANA_USER");
        $url .= ":";
        $url .= env("GRAFANA_PASS");
        $url .= "@";
        $url .= env("GRAFANA_HOST");
        $url .= "/api/";
        return $url;
    }

    public static function GRAFANA_URL()
    {
        $url = "https://";
        $url .= env("GRAFANA_HOST");
        return $url;
    }

    public static function createUser(User $user, $password)
    {
        $user->user_grafana_id = self::makeGrafanaUser($user,
            $password
        );
        $user->save();
    }

    public static function makeGrafanaUser(User $user, $password)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::GRAFANA_API() . 'admin/users')
            ->setData([
                'password' => $password,
                'name' => $user->username,
                'login' => $user->username,
                'email' => $user->username,
                'orgId' => 1
            ])
            ->send();
        $id = Json::decode($response->content)['id'];

        return (int)$id;
    }

    public static function createDashboard(User $user, GrafanaDashboard $dashboard, array $panels)
    {
        self::changeCurrentOrgToMain();

        $panelsString = implode(",", $panels);
        $data = <<< DASHBOARD
{
	"dashboard":{
		"id":null,
		"uid":null,
		"title":"$dashboard->title",
		"timezone":"browser",
		"schemaVersion":16,
		"panels":[
		$panelsString
		],
		"version":0
	},
	"overwrite":true,
	"message":"message"
}
DASHBOARD;

        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::GRAFANA_API() . 'dashboards/db')
            ->setData(Json::decode($data))
            ->send();

        $dashboard->dash_id = Json::decode($response->content)['id'];
        $dashboard->dash_uid = Json::decode($response->content)['uid'];
        $dashboard->save();
    }

    public static function deleteDashboard(GrafanaDashboard $dashboard)
    {
        self::changeCurrentOrgToMain();
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('DELETE')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::GRAFANA_API() . 'dashboards/uid/' . $dashboard->dash_uid)
            ->send();
    }

    public static function deleteGrafanaUser(User $user)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('DELETE')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::GRAFANA_API() . 'admin/users/' . $user->user_grafana_id)
            ->send();
    }

    private static function changeCurrentOrgToMain()
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::GRAFANA_API() . 'users/1/using/1')
            ->send();
    }
}
