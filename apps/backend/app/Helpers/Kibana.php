<?php

namespace App\Helpers;

use backend\models\ElasticIndex;
use common\models\User;
use yii\helpers\Json;
use yii\helpers\StringHelper;
use yii\httpclient\Client;
use backend\models\ElasticIndexAccess;

class Kibana
{
    public static function KIBANA_URL()
    {
        $url = "https://";
        $url .= env("KIBANA_HOST");
        $url .= "/";
        //			$url = "http://elastic:rBIqmQLDqD5V73rZRB7r@localhost:5602/";
        return $url;
    }

    public static function KIBANA_API()
    {
        $url = "https://";
        $url .= env("ELASTIC_USER");
        $url .= ":";
        $url .= env("ELASTIC_PASS");
        $url .= "@";
        $url .= env("KIBANA_HOST");
        $url .= "/";
        //			$url = "http://elastic:rBIqmQLDqD5V73rZRB7r@localhost:5602/";
        return $url;
    }

    public static function ELASTIC_API()
    {
        $url = "http://";
        $url .= env("ELASTIC_USER");
        $url .= ":";
        $url .= env("ELASTIC_PASS");
        $url .= "@";
        $url .= env("ELASTIC_HOST");
        $url .= "/";
        //			$url = "http://elastic:rBIqmQLDqD5V73rZRB7r@localhost:9200/";

        return $url;
    }

    //		private static $ELASTIC_USER = "elastic";
    //		private static $ELASTIC_PASS = "rBIqmQLDqD5V73rZRB7r";
    //		private static $ELASTIC_HOST = "localhost:9200";
    //		private static $KIBANA_HOST = "localhost:5601";
    public static function onCreateUser(User $user,$pass){
        Kibana::makeElasticsearchUser($user,$pass);
        Kibana::createSpace($user);
        Kibana::createRole($user);
        Kibana::assignRole($user);
    }
    public static function createSpace(User $user)
    {
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('POST');

        $name = StringHelper::explode($user->username,
            "@"
        );
        $name = $name[0];
        $username = $user->getKibanaSpaceUsername();

        $response = $request->setUrl(self::KIBANA_API() . 'api/spaces/space')
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setData([
                'id' => $username,
                'name' => $name,

            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        return $response->content;
    }
    public static function deleteSpace(User $user)
    {
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('DELETE');

        $name = StringHelper::explode($user->username,
            "@"
        );
        $name = $name[0];
        $username = $user->getKibanaSpaceUsername();

        $response = $request->setUrl(self::KIBANA_API() . 'api/spaces/space/' . $username)
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
    }

    public static function createRole(User $user)
    {
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('PUT');

        $username = $user->getKibanaSpaceUsername();

        $response = $request->setUrl(self::KIBANA_API() . 'api/security/role/' . $user->username)
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setData([
                'metadata' => ['version' => 1],
                'elasticsearch' => [
                    'cluster' => [
                        'read_ilm',
                        'monitor',
                        'read_pipeline'
                    ],
                    'indices' => [
                        [
                            "names" => ['demo',],
                            "privileges" => ['all',]
                        ],
                    ]
                ],
                'kibana' => [
                    [
                        'base' => [],
                        'feature' => [
                            "discover" => ["all"],
                            'dashboard' => ['all'],
                            'visualize' => ['all'],
                        ],
                        "spaces" => [
                            $username,
                        ]
                    ],
                ],

            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
        return $response->content;
    }
    public static function deleteRole(User $user)
    {
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('DELETE');

        $response = $request->setUrl(self::KIBANA_API() . 'api/security/role/' . $user->username)
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
    }
    public static function assignRole(User $user)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('PUT')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::ELASTIC_API() . '_security/user/' . $user->username)
            ->setData([
                'roles' => [$user->username,],
            ])
            ->send();
        return $response->content;
    }
    public static function userCreateIndex(ElasticIndex $site){
        self::createKibanaIndex($site);
        self::createPipeline($site);
    }
    public static function createKibanaIndex(ElasticIndex $site)
    {
        $client = new Client();
        $maps = [];
        if (!empty($site->mappingObj))
            foreach ($site->mappingObj as $map) {
                $maps[$map->name] = [
                    "type" => $map->type
                ];
            }

        $data = [];
        $data['mappings']['properties'] = $maps;

        $response = $client->createRequest()
            ->setMethod('PUT')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::ELASTIC_API() . $site->name)
            ->setData($data)
            ->send();
        return $response->content;
    }
    public static function deleteIndex(ElasticIndex $site)
    {
        $client = new Client();

        $response = $client->createRequest()
            ->setMethod('DELETE')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::ELASTIC_API() . $site->name)
            ->send();

        return $response->content;
    }

    private static function deleteKibanaIndexPattern(ElasticIndex $site, $userId)
    {
        $username = User::find()
            ->where(['id' => $userId])
            ->one()
            ->getKibanaSpaceUsername();
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('DELETE');

        $response = $request->setUrl(self::KIBANA_API() . 's/' . $username . '/api/index_patterns/index_pattern/' . $site->name
        )
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
    }
    private static function createKibanaIndexPattern(ElasticIndex $site, $userId)
    {
        $username = User::find()
            ->where(['id' => $userId])
            ->one()
            ->getKibanaSpaceUsername();
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('POST');
        if(empty($site->default_time_field)){
            $timeField = "@timestamp";
        }else{
            $timeField = "@".$site->default_time_field;
        }
        $response = $request->setUrl(self::KIBANA_API() . 's/' . $username . '/api/index_patterns/index_pattern')
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setData([
                'override' => false,
                'index_pattern' => [
                    'id' => $site->name,
                    'title' => $site->name,
                    "timeFieldName" => $timeField,

                ],
            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
    }

    public static function setIndexPatternAccessUser(ElasticIndex $elasticIndex, $userId)
    {
        self::createKibanaIndexPattern($elasticIndex,
            $userId
        );
        self::refreshIndexPatternAccessToRole($userId);
    }
    public static function deleteIndexPatternAccessUser(ElasticIndex $elasticIndex, $userId)
    {
        self::deleteKibanaIndexPattern($elasticIndex,
            $userId
        );
        self::refreshIndexPatternAccessToRole($userId);
    }


    public static function deleteElasticsearchUser(User $user)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('DELETE')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::ELASTIC_API() . '_security/user/' . $user->username)
            ->send();
    }
    public static function makeElasticsearchUser($user, $password)
    {
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('POST')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::ELASTIC_API() . '_security/user/' . $user->username)
            ->setData([
                'password' => $password,
                'roles' => ['viewer'],
                'full_name' => $user->username,
                'email' => $user->username,
            ])
            ->send();
        return $response->content;
    }

    public static function refreshIndexPatternAccessToRole($userId)
    {
        $user = User::find()
            ->where(['id' => $userId])
            ->one();

        $client = new Client();

        $request = $client->createRequest()
            ->setMethod('PUT');

        $username = $user->getKibanaSpaceUsername();
        $indexPatternArray = ['demo',];
        $elasticIndexesId = ElasticIndexAccess::find()
            ->select('elastic_index_id')
            ->where(['user_id' => \Yii::$app->user->id])
            ->column();

        $webs = ElasticIndex::find()
            ->where([
                'or',
                [
                    'amak_elastic_index.user_id' => \Yii::$app->user->id
                ],
                [
                    'amak_elastic_index.id' => $elasticIndexesId
                ]
            ])
            ->all();

        foreach ($webs as $web) {
            $indexPatternArray[] = $web->name;
        }

        $response = $request->setUrl(self::KIBANA_API() . 'api/security/role/' . $user->username)
            ->addHeaders(['kbn-xsrf' => 'true'])
            ->setData([
                'metadata' => ['version' => 1],
                'elasticsearch' => [
                    'cluster' => [
                        'read_ilm',
                        'monitor',
                        'read_pipeline'
                    ],
                    'indices' => [
                        [
                            "names" => $indexPatternArray,
                            "privileges" => ['all']
                        ]
                    ]
                ],
                'kibana' => [
                    [
                        'base' => [],
                        'feature' => [
                            "discover" => ["all"],
                            'dashboard' => ['all'],
                            'visualize' => ['all'],
                        ],
                        "spaces" => [
                            $username,
                        ]
                    ],
                ],

            ])
            ->setFormat(Client::FORMAT_JSON)
            ->send();
    }

    public static function createPipeline(ElasticIndex $index){
        $client = new Client();
        $request = $client->createRequest()
            ->setMethod('PUT');

        // ctx?.token == 'myToken' && ctx?.index == 'myIndex'
        $condString = "ctx?.service_token == '$index->token' && ctx?.index == '$index->name'";

        $ifStatement = 'if(' . $condString . '){return false;}return true;';
        if(empty($index->default_time_field)){
            $data = [
                'processors' => [
                    [
                        "drop" => [
                            "if" => $ifStatement
                        ]
                    ],
                    [
                        "remove" => [
                            "field" => [
                                "service_token",
                                "org_token"
                            ]
                        ]
                    ],
                    [
                        "remove" => [
                            "field" => [
                                "message",
                                "data"
                            ],
                            "ignore_failure"=>true
                        ]
                    ],
                ],

            ];
        }else{
            $data = [
                'processors' => [
                    [
                        "drop" => [
                            "if" => $ifStatement
                        ]
                    ],
                [
                    "set"=>[
                        "field"=>"@".$index->default_time_field,
                        "override"=>false,
                        "ignore_failure"=>true,
                        "copy_from"=>$index->default_time_field
                    ]
                ],
                    [
                        "remove" => [
                            "field" => [
                                "service_token",
                                "org_token"
                            ]
                        ]
                    ],
                    [
                        "remove" => [
                            "field" => [
                                "message",
                                "data"
                            ],
                            "ignore_failure"=>true
                        ]
                    ],
                ],

            ];
        }

        $response = $request->setUrl(self::ELASTIC_API() . '_ingest/pipeline/'.$index->name)
            ->setData($data)
            ->setFormat(Client::FORMAT_JSON)
            ->send();

        return $response->content;
    }
    public static function getSizeOfIndexesInMb(array $indexes){
        if(empty($indexes)) return 0;
        $stringIndexes = implode(",",$indexes);
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setFormat(Client::FORMAT_JSON)
            ->setUrl(self::ELASTIC_API() . "$stringIndexes/_stats/store")
            ->send();
        $result = Json::decode($response->content);
        $size =(float) $result['_all']['primaries']['store']['total_data_set_size_in_bytes'];
        $size = $size / 1024.0;//Kb
        $size = $size / 1024.0;//Mb
        return (float) number_format($size,2);
    }
    public static function stopIndexesPipeline(array $indexes)
    {

        foreach ($indexes as $index){
            $client = new Client();
            $request = $client->createRequest()
                ->setMethod('PUT');
            $ifStatement = 'return true;';
            $data = [
                'processors' => [
                    [
                        "drop" => [
                            "if" => $ifStatement
                        ]
                    ],
                    [
                        "remove" => [
                            "field" => [
                                "service_token",
                                "org_token"
                            ]
                        ]
                    ],
                    [
                        "remove" => [
                            "field" => [
                                "message",
                                "data"
                            ],
                            "ignore_failure"=>true
                        ]
                    ],
                ],

            ];


            $response = $request->setUrl(self::ELASTIC_API() . '_ingest/pipeline/'.$index)
                ->setData($data)
                ->setFormat(Client::FORMAT_JSON)
                ->send();

        }

    }
    /************************************************************/
    public static function getUserTodayLogSize($userId)
    {
        $userElasticIndexes = ElasticIndex::find()
            ->select('name')
            ->where(['user_id' => $userId])
            ->column();
        $todayElasticIndexesArr = [];

        $time = time();

        $today = \Yii::$app->formatter->asDate($time,
            'yyyy.MM.dd'
        );
        foreach ($userElasticIndexes as $userElasticIndex) {
            $todayElasticIndexesArr[] = $userElasticIndex . '-' . $today;
        }

        $todayIndexes = implode(',',
            $todayElasticIndexesArr
        );
        $client = new Client();

        $responseTodayDocs = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(self::ELASTIC_API() . $todayIndexes . "/_stats/store,search?ignore_unavailable=true"
            )
            ->send();

        $contentTodayDocs = Json::decode($responseTodayDocs->content);

        if ($contentTodayDocs['_shards']['successful'] == 0) {
            $sizeByte = 0;
        } else
            $sizeByte = $contentTodayDocs['_all']['total']['store']['size_in_bytes'];
        $sizeMG = $sizeByte / (1024 * 1024);

        return $sizeMG;
    }
    public static function getUserWeekLogCount($userId)
    {
        $userElasticIndexes = ElasticIndex::find()
            ->select('name')
            ->where(['user_id' => $userId])
            ->column();
        $allElasticIndexesWeekArr = [];
        $dateArr = [];
        $time = time();
        for ($i = 0; $i < 7; $i++) {
            $day = \Yii::$app->formatter->asDate($time,
                'yyyy.MM.dd'
            );
            $dateArr[] = $day;
            $time = $time - (3600 * 24);
        }
        foreach ($userElasticIndexes as $userElasticIndex) {
            foreach ($dateArr as $date) {
                $allElasticIndexesWeekArr[] = $userElasticIndex . '-' . $date;
            }
        }

        $lastWeekIndexes = implode(',',
            $allElasticIndexesWeekArr
        );
        $client = new Client();
        $responseLastWeekDocs = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(self::ELASTIC_API() . $lastWeekIndexes . "/_stats/docs,search?ignore_unavailable=true"
            )
            ->send();

        $contentLastWeekDocs = Json::decode($responseLastWeekDocs->content);

        $countDayIndex = [];
        foreach ($dateArr as $date) {
            foreach ($contentLastWeekDocs['indices'] as $index => $indexValue) {
                if (str_contains($index,
                    $date
                )) {
                    if (empty($countDayIndex[$date])) {
                        $countDayIndex[$date] = $indexValue['total']['docs']['count'];
                    } else {
                        $countDayIndex[$date] += $indexValue['total']['docs']['count'];
                    }
                }
            }
        }
        return $countDayIndex;
    }
    public static function delete8DayIndex()
    {
        $time = time() - (3600 * 24 * 8);
        $today = \Yii::$app->formatter->asDate($time,
            'yyyy.MM.dd'
        );

        $indexes = '*-' . $today;
        $client = new Client();
        $responseLastWeekDocs = $client->createRequest()
            ->setMethod('DELETE')
            ->setUrl(self::ELASTIC_API() . $indexes)
            ->send();
    }
    public static function resetConfigFile()
    {
        $filebeatFilePath = "/etc/logstash/conf.d/filebeat.conf";

        $confFile = fopen($filebeatFilePath,
            'r'
        );
        $file = fread($confFile,
            filesize($filebeatFilePath)
        );

        $users = User::find()
            ->where("exists(select * from amak_auth_assignment where amak_auth_assignment.user_id = amak_user.id AND amak_auth_assignment.item_name = 'user')"
            )
            ->all();
        $result = [];
        foreach ($users as $user) {
            $userElasticIndexes = ElasticIndex::find()
                ->select('name')
                ->where(['user_id' => $user->id])
                ->column();
            if ($userElasticIndexes) {
                $size = self::getSize($userElasticIndexes);
            } else $size = 0;

            if ($size < 800) {
                $result[] = $user->access_token;
            }
        }

        $result = self::replacesToken($file,
            $result
        );
        fclose($confFile);

        $confFile = fopen($filebeatFilePath,
            'w+'
        );
        fwrite($confFile,
            $result
        );
        fclose($confFile);
    }
    private static function getSize(array $elasticIndexes)
    {
        $today = \Yii::$app->formatter->asDate('now',
            'yyyy.MM.dd'
        );
        foreach ($elasticIndexes as &$s) {
            $s = $s . '-' . $today;
        }
        $tokens = implode(',',
            $elasticIndexes
        );
        $client = new Client();
        $response = $client->createRequest()
            ->setMethod('GET')
            ->setUrl(self::ELASTIC_API() . $tokens . "/_stats/store,search?ignore_unavailable=true")
            ->send();

        $content = json_decode($response->content);
        if ($content->_shards->successful == 0) {
            $sizeByte = 0;
        } else
            $sizeByte = $content->_all->total->store->size_in_bytes;
        $sizeMG = $sizeByte / 1024;
        //		$sizeMG = $sizeByte / (1024 * 1024 );

        return $sizeMG;
    }
    private static function replacesToken($file, array $tokens)
    {
        $ifString = "if [token] not in ";
        $ifPos = strpos($file,
            $ifString
        );
        $startArrayPos = $ifPos + +strlen($ifString) + 1;
        $endArrayPos = strpos($file,
            "]",
            $startArrayPos
        );
        $tokensInFile = substr($file,
            $startArrayPos,
            $endArrayPos - $startArrayPos
        );
        foreach ($tokens as &$token) {
            $token = "\"" . $token . "\"";
        }
        $newTokensString = implode(",",
            $tokens
        );

        $newFile = str_replace($tokensInFile,
            $newTokensString,
            $file
        );

        return $newFile;
    }
    public static function testCronjob()
    {
        $filebeatFilePath = "/var/www/website-yii/test.json";

        $confFile = fopen($filebeatFilePath,
            'a+'
        );
        fwrite($confFile,
            time()
        );
        fwrite($confFile,
            "\n"
        );
        fclose($confFile);
    }
    /*	public static function updateFileBeat(User $user) {
            $filebeatFilePath = "/etc/logstash/conf.d/filebeat.conf";
            $confFile = fopen($filebeatFilePath,
                'r'
            );
            $textFileBeat = fread($confFile,
                filesize($filebeatFilePath)
            );
            $findIndex = "if [token] not in [";
            $result = str_replace($findIndex,
                $findIndex . "\"" . $user->access_token . "\",",
                $textFileBeat
            );
            fclose($confFile);

            $confFile = fopen($filebeatFilePath,
                'w+'
            );
            fwrite($confFile,
                $result
            );
            fclose($confFile);
        }
        */

}
