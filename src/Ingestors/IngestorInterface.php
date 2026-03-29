<?php

declare(strict_types=1);

namespace HistoryIngestion\Ingestors;

use HistoryIngestion\Schema\ProjectSnapshot;

interface IngestorInterface
{
    /**
     * Ingest history for a single project configuration entry.
     *
     * @param  array{name:string,local_path:string,repo:string,description:string,url:string,stack:string[]}  $projectConfig
     */
    public function ingest(array $projectConfig): ProjectSnapshot;

    /**
     * Whether this ingestor can handle the given project config.
     */
    public function supports(array $projectConfig): bool;
}
