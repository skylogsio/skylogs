<?php

namespace App\Services\Health\DataSourceProbes;

use App\Models\DataSource\DataSource;
use App\Services\Health\HealthProbe;

interface DataSourceProbeBuilder
{
    public function build(DataSource $dataSource): HealthProbe;
}
