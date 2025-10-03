<?php

namespace App\Helpers;

use App\Models\Site;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class Constants {
    public const SERVICE = "service";
    public const API = "api";
    public const SPLUNK= "splunk";
    public const SENTRY= "sentry";
    public const METABASE= "metabase";
    public const PROMETHEUS = "prometheus";
    public const HEALTH = "health";
    public const GRAFANA = "grafana";
    public const NOTIFICATION = "notification";
    public const ELASTIC = "elastic";
    public const ZABBIX = "zabbix";
    public const COLOR_YELLOW = "#D6E321";
    public const COLOR_BLUE = "#14DCE3";
    public const COLOR_GREEN = "#00ef3d";
    public const COLOR_RED = "#f80000";

    public const  AND = "AND";
    public const  OR = "OR";
    public const  LITERAL = "LITERAL";
    public const  XOR = "XOR";
    public const  NOT = "NOT";

    public const  LAST_LEADER_PING = "LAST_LEADER_PING";
    public const  LEADER_PRIORITY = "LEADER_PRIORITY";

    public const UPDATE = "بروزرسانی";
    public const VIEW = "مشاهده";
    public const CREATE = "ایجاد";
    public const SAVE = "ذخیره";


    public const UPLOAD = "بارگذاری";
    public const UPLOADED_FILES = "فایل‌های بارگذاری شده";
    public const SEARCH = "جستجو";
    public const INDEX = "مشاهده";
//    public  const CREATE = "ایجاد";
    public const IMPORT = "بارگذاری";


    public static function RouteLable($route) {
        if (Str::contains($route, "site/search")) {
            return "جستجوی کلی";
        }
        foreach (Site::$types as $type => $label) {
            if (Str::contains($route, $type)) {
                if (Str::contains($route, "view/")) {
                    return $label . " " . "مشاهده";
                } elseif (Str::contains($route, "delete/")) {
                    return $label . " " . "حذف";
                } elseif (Str::contains($route, "update/")) {
                    return $label . " " . "بروزرسانی";
                } elseif (Str::contains($route, "search")) {
                    return $label . " " . "جستجو";
                } elseif (Str::contains($route, "store")) {
                    return $label . " " . "ذخیره";
                } elseif (Str::contains($route, "create")) {
                    return $label . " " . "ذخیره";
                } elseif (Str::contains($route, "export")) {
                    return $label . " " . "خروجی";
                } elseif (!Str::contains($route, "check")) {
                    return $label;
                }
            }
        }

        return "";


    }
}
