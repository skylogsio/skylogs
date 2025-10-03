<?php

	namespace App\Helpers;

	use common\models\User;
	use yii\helpers\Json;
	use yii\helpers\StringHelper;
	use yii\httpclient\Client;
	use backend\models\GrafanaDashboard;

	class Sentry {

		public static function SENTRY_URL() {
			$url = "https://";
			$url .= env("SENTRY_HOST");
			$url .= "/";
			return $url;
		}


	}
