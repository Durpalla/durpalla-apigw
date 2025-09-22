<?php


namespace Modules\Media\Repository;

use App\Repositories\BaseRepository;
use Modules\Media\Entities\Media;

class MediaRepository extends BaseRepository implements MediaRepositoryInterface
{
    public function __construct(Media $model)
    {
        parent::__construct($model);
    }
}
