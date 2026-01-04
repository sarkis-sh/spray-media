<?php

namespace SprayMedia\Infrastructure\Persistence;

use SprayMedia\Contracts\MediaItemRepositoryInterface;
use SprayMedia\Domain\Models\MediaItem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;

/**
 * Class EloquentMediaItemRepository
 *
 * A concrete repository implementation for media item records using Laravel's Eloquent ORM.
 * This class translates the interface methods into efficient Eloquent queries.
 */
class EloquentMediaItemRepository implements MediaItemRepositoryInterface
{
    protected function getModel(): MediaItem
    {
        return App::make(Config::get('spray-media.model'));
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data): MediaItem
    {
        return $this->getModel()->newQuery()->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function find($id): ?MediaItem
    {
        return $this->getModel()->newQuery()->find($id);
    }

    /**
     * {@inheritdoc}
     *
     * This implementation uses a direct UPDATE query for better performance,
     * avoiding a separate SELECT query to fetch the model first.
     */
    public function update($id, array $data): bool
    {
        $affectedRows = $this->getModel()->newQuery()->where('id', $id)->update($data);

        return $affectedRows > 0;
    }

    /**
     * {@inheritdoc}
     *
     * This implementation uses the static `delete` method, which is highly
     * efficient for deleting one or more models by their primary key in a single query.
     */
    public function delete($id): bool
    {
        $deletedCount = $this->getModel()->newQuery()->where('id', $id)->delete();

        return $deletedCount > 0;
    }
}
