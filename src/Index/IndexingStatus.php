<?php

declare(strict_types=1);

namespace EsLite\Index;

enum IndexingStatus: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Unchanged = 'unchanged';
    case Deleted = 'deleted';
}
